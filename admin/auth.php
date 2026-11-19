<?php
/**
 * admin/auth.php - Shared session, CSRF and auth helper for the admin panel.
 * Include at the top of any protected admin page or API endpoint.
 */

require_once __DIR__ . '/../config/security.php';

if (session_status() === PHP_SESSION_NONE) {
    // Harden the session cookie before starting the session.
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,   // only sent over HTTPS once SSL is on
        'httponly' => true,     // JavaScript cannot read it
        'samesite' => 'Lax',    // blocks cross-site request forgery
    ]);
    session_name('BOBOADMIN');
    session_start();
}

// ---- Idle timeout: log out after 2 hours of inactivity ----
$IDLE_TIMEOUT = 7200;
if (!empty($_SESSION['admin_id'])) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $IDLE_TIMEOUT) {
        $_SESSION = [];
        session_destroy();
    } else {
        $_SESSION['last_activity'] = time();
    }
}

/**
 * Marks an admin as logged in. Regenerates the session ID to
 * prevent session-fixation attacks.
 */
function admin_login_session(array $admin): void
{
    session_regenerate_id(true);
    $_SESSION['admin_id']      = $admin['id'];
    $_SESSION['admin_name']    = $admin['full_name'] ?: $admin['username'];
    $_SESSION['last_activity'] = time();
    $_SESSION['csrf_token']    = bin2hex(random_bytes(32));
}

/**
 * Returns the CSRF token for the current session, creating one if needed.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verifies a submitted CSRF token in constant time.
 */
function csrf_verify(?string $token): bool
{
    return !empty($_SESSION['csrf_token'])
        && is_string($token)
        && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Use on HTML admin pages - redirects to login.php if not logged in.
 */
function require_login_page(): void
{
    send_security_headers();
    if (empty($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Use on API endpoints - returns JSON 401 instead of redirecting.
 */
function require_login_api(): void
{
    if (empty($_SESSION['admin_id'])) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Unauthorized. Please log in.']);
        exit;
    }
}

/**
 * Use on state-changing admin API endpoints. Checks login AND CSRF token.
 * The token can arrive in the X-CSRF-Token header or in the JSON body.
 */
function require_login_api_csrf(?array $input = null): void
{
    require_login_api();

    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? null);
    if (!csrf_verify($token)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Security token expired. Please reload the page and try again.']);
        exit;
    }
}
