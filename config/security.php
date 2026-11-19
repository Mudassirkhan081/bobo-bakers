<?php
/**
 * config/security.php — Shared security helpers
 * Rate limiting, spam guards and safe JSON responses.
 *
 * File-based storage is used so this works on any shared host
 * (Hostinger / cPanel) without Redis or extra extensions.
 */

// ---------------------------------------------------------------
// Where rate-limit counters are stored. Kept outside the web root
// when possible; falls back to the system temp folder.
// ---------------------------------------------------------------
function rl_storage_dir(): string
{
    $dir = __DIR__ . '/../storage/ratelimit';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    if (is_dir($dir) && is_writable($dir)) {
        return $dir;
    }
    $fallback = sys_get_temp_dir() . '/bobo_rl';
    if (!is_dir($fallback)) {
        @mkdir($fallback, 0700, true);
    }
    return $fallback;
}

/**
 * Returns the visitor's IP, accounting for Cloudflare / reverse proxies.
 */
function client_ip(): string
{
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = trim(explode(',', $_SERVER[$key])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '0.0.0.0';
}

/**
 * Sliding-window rate limiter.
 *
 * @param string $bucket   Logical name, e.g. "place_order"
 * @param int    $limit    Max requests allowed inside the window
 * @param int    $window   Window length in seconds
 * @param string $key      Extra identity (defaults to IP)
 * @return array{allowed:bool, remaining:int, retry_after:int}
 */
function rate_limit(string $bucket, int $limit, int $window, string $key = ''): array
{
    $key  = $key !== '' ? $key : client_ip();
    $file = rl_storage_dir() . '/' . $bucket . '_' . sha1($key) . '.json';
    $now  = time();

    $hits = [];
    if (is_file($file)) {
        $raw  = @file_get_contents($file);
        $hits = json_decode((string) $raw, true);
        if (!is_array($hits)) {
            $hits = [];
        }
    }

    // Drop anything older than the window
    $hits = array_values(array_filter($hits, static fn($t) => ($now - (int) $t) < $window));

    if (count($hits) >= $limit) {
        $retry = $window - ($now - (int) $hits[0]);
        return ['allowed' => false, 'remaining' => 0, 'retry_after' => max(1, $retry)];
    }

    $hits[] = $now;
    @file_put_contents($file, json_encode($hits), LOCK_EX);

    // Opportunistic cleanup so the folder doesn't grow forever
    if (random_int(1, 50) === 1) {
        rl_cleanup();
    }

    return ['allowed' => true, 'remaining' => $limit - count($hits), 'retry_after' => 0];
}

function rl_cleanup(): void
{
    $dir = rl_storage_dir();
    foreach (glob($dir . '/*.json') ?: [] as $f) {
        if (filemtime($f) < time() - 86400) {
            @unlink($f);
        }
    }
}

/**
 * Clears a rate-limit bucket (used after a successful admin login).
 */
function rate_limit_reset(string $bucket, string $key = ''): void
{
    $key  = $key !== '' ? $key : client_ip();
    $file = rl_storage_dir() . '/' . $bucket . '_' . sha1($key) . '.json';
    @unlink($file);
}

/**
 * Sends a JSON error and stops.
 */
function json_error(string $message, int $code = 400): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Enforces a rate limit and exits with a friendly message if exceeded.
 */
function enforce_rate_limit(string $bucket, int $limit, int $window, string $friendly): void
{
    $rl = rate_limit($bucket, $limit, $window);
    if (!$rl['allowed']) {
        header('Retry-After: ' . $rl['retry_after']);
        json_error($friendly, 429);
    }
}

/**
 * Bot honeypot: a hidden field real users never fill in.
 * Returns true when the submission looks automated.
 */
function is_honeypot_tripped(array $input): bool
{
    return !empty($input['website']) || !empty($input['company_url']);
}

/**
 * Blocks obvious link-spam in free-text fields.
 */
function looks_like_spam(string $text): bool
{
    if (preg_match_all('#https?://|www\.#i', $text) >= 2) {
        return true;
    }
    if (preg_match('/\b(viagra|casino|crypto\s*airdrop|seo\s*services|buy\s*followers)\b/i', $text)) {
        return true;
    }
    return false;
}

/**
 * Adds baseline security headers to HTML responses.
 */
function send_security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
}
