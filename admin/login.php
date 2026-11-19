<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/db.php';

send_security_headers();

// If already logged in, go straight to the dashboard
if (!empty($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error       = '';
$lockedOut   = false;
$retryAfter  = 0;

// Brute-force guard: 6 failed attempts per 15 minutes, per IP
$LOGIN_LIMIT  = 6;
$LOGIN_WINDOW = 900;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } else {
        $rl = rate_limit('admin_login', $LOGIN_LIMIT, $LOGIN_WINDOW);

        if (!$rl['allowed']) {
            $lockedOut  = true;
            $retryAfter = (int) ceil($rl['retry_after'] / 60);
            $error      = "Too many failed attempts. Try again in {$retryAfter} minute" . ($retryAfter === 1 ? '' : 's') . '.';
        } else {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';

            if ($username === '' || $password === '') {
                $error = 'Please enter both username and password.';
            } else {
                $stmt = $pdo->prepare('SELECT id, username, password_hash, full_name FROM admins WHERE username = :u');
                $stmt->execute(['u' => $username]);
                $admin = $stmt->fetch();

                if ($admin && password_verify($password, $admin['password_hash'])) {
                    // Successful login clears the failed-attempt counter
                    rate_limit_reset('admin_login');
                    admin_login_session($admin);
                    header('Location: dashboard.php');
                    exit;
                }

                // Same message for wrong username and wrong password,
                // so the form never reveals which usernames exist.
                $remaining = max(0, $rl['remaining']);
                $error = 'Invalid username or password.'
                       . ($remaining <= 3 ? " {$remaining} attempt" . ($remaining === 1 ? '' : 's') . ' left before lockout.' : '');
            }
        }
    }
}

$token = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Admin Login | Bobo Baker's</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    background: linear-gradient(160deg, #1a1a2e 0%, #C8104A 60%, #1a1a2e 100%);
    min-height: 100vh;
    display: flex; align-items: center; justify-content: center;
    padding: 20px;
  }
  .login-box {
    background: #fff; border-radius: 18px; padding: 40px 36px;
    width: 100%; max-width: 380px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.25);
  }
  .login-box h1 { font-size: 1.6rem; color: #C8104A; text-align: center; margin-bottom: 6px; }
  .login-box p.sub { text-align: center; color: #888; font-size: 0.9rem; margin-bottom: 28px; }
  label { display: block; font-size: 0.85rem; font-weight: 600; color: #333; margin-bottom: 6px; }
  input {
    width: 100%; padding: 12px 14px; margin-bottom: 18px;
    border: 1.5px solid #eee; border-radius: 10px; font-size: 1rem;
    transition: border-color 0.2s;
  }
  input:focus { outline: none; border-color: #C8104A; }
  button {
    width: 100%; padding: 13px; border: none; border-radius: 10px;
    background: #C8104A; color: #fff; font-size: 1rem; font-weight: 600;
    cursor: pointer; transition: background 0.2s;
  }
  button:hover:not(:disabled) { background: #a30d3d; }
  button:disabled { background: #ccc; cursor: not-allowed; }
  .error {
    background: #fee2e2; color: #b91c1c; padding: 10px 14px;
    border-radius: 8px; font-size: 0.9rem; margin-bottom: 18px; text-align: center;
  }
  .footer-note { text-align: center; margin-top: 22px; font-size: 0.78rem; color: #aaa; }
</style>
</head>
<body>
  <div class="login-box">
    <h1>Bobo Baker's</h1>
    <p class="sub">Admin Panel Login</p>

    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="login.php" autocomplete="on">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token) ?>">

      <label for="username">Username</label>
      <input type="text" id="username" name="username" autocomplete="username" required <?= $lockedOut ? 'disabled' : '' ?>>

      <label for="password">Password</label>
      <input type="password" id="password" name="password" autocomplete="current-password" required <?= $lockedOut ? 'disabled' : '' ?>>

      <button type="submit" <?= $lockedOut ? 'disabled' : '' ?>>Log in</button>
    </form>

    <p class="footer-note">&copy; <?= date('Y') ?> Bobo Baker's &mdash; Admin access only</p>
  </div>
</body>
</html>
