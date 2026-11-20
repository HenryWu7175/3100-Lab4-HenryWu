<?php
declare(strict_types=1);
require dirname(__DIR__) . '/shared/Auth.php';
require dirname(__DIR__) . '/shared/AccountTokens.php';
require dirname(__DIR__) . '/shared/AccountMail.php';
require dirname(__DIR__) . '/shared/GoogleAuthenticator.php';
$db = connect_database();
start_auth_session($db);
header('Cache-Control: no-store');
$page = $_GET['page'] ?? 'login';
if (!in_array($page, ['login', 'register', 'verify', 'mfa', 'forgot', 'reset'], true)) { http_response_code(404); exit; }
$message = '';
$token = is_string($_GET['token'] ?? null) ? $_GET['token'] : '';
if ($page === 'verify' && $token !== '') {
    $message = verify_account_email($db, $token) ? 'Email verified. You can sign in.' : 'This verification link is invalid or expired.';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    try {
        if ($page === 'register') {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255 || strlen($password) < 12 || strlen($password) > 4096) {
                throw new InvalidArgumentException('Enter a valid email and a password of at least 12 characters.');
            }
            $db->beginTransaction();
            $q = $db->prepare('INSERT INTO users (email, password_hash) VALUES (?, ?) RETURNING id');
            $q->execute([$email, password_hash($password, PASSWORD_DEFAULT)]);
            $id = $q->fetchColumn();
            $token = issue_account_token($db, $id, 'verify');
            send_account_link($email, 'Verify your Smart Assets account', rtrim((string) getenv('APP_URL'), '/') . '/login.php?page=verify&token=' . $token);
            $db->commit();
            $message = 'Check your email for a verification link.';
        } elseif ($page === 'login') {
            $q = $db->prepare('SELECT id, password_hash, email_verified_at, totp_confirmed_at FROM users WHERE lower(email) = ?');
            $q->execute([$email]);
            $user = $q->fetch();
            if (!$user || !password_verify($password, $user['password_hash']) || !$user['email_verified_at']) {
                $message = 'Invalid credentials or email not yet verified.';
            } else {
                session_regenerate_id(true);
                $_SESSION = ['csrf' => bin2hex(random_bytes(32)), 'pending_user' => $user['id'], 'pending_until' => time() + 600];
                header('Location: login.php?page=mfa'); exit;
            }
        } elseif ($page === 'mfa') {
            if (!isset($_SESSION['pending_user'], $_SESSION['pending_until']) || $_SESSION['pending_until'] < time()) { header('Location: login.php'); exit; }
            $q = $db->prepare('SELECT totp_secret, totp_confirmed_at FROM users WHERE id = ? AND email_verified_at IS NOT NULL');
            $q->execute([$_SESSION['pending_user']]);
            $user = $q->fetch();
            $code = (string) ($_POST['code'] ?? '');
            if ($user && preg_match('/\A[0-9]{6}\z/', $code) && (new PHPGangsta_GoogleAuthenticator())->verifyCode($user['totp_secret'], $code, 1)) {
                $db->prepare('UPDATE users SET totp_confirmed_at = COALESCE(totp_confirmed_at, now()), last_login_at = now() WHERE id = ?')->execute([$_SESSION['pending_user']]);
                $id = $_SESSION['pending_user'];
                session_regenerate_id(true);
                $_SESSION = ['csrf' => bin2hex(random_bytes(32)), 'user_id' => $id];
                header('Location: index.php'); exit;
            }
            $message = 'Invalid authenticator code.';
        } elseif ($page === 'forgot') {
            $q = $db->prepare('SELECT id FROM users WHERE lower(email) = ? AND email_verified_at IS NOT NULL');
            $q->execute([$email]);
            if ($id = $q->fetchColumn()) {
                $token = issue_account_token($db, $id, 'reset');
                send_account_link($email, 'Reset your Smart Assets password', rtrim((string) getenv('APP_URL'), '/') . '/login.php?page=reset&token=' . $token);
            }
            $message = 'If the account exists, a reset link has been sent.';
        } elseif ($page === 'reset') {
            $token = (string) ($_POST['token'] ?? '');
            $message = reset_account_password($db, $token, $password) ? 'Password changed. Sign in again.' : 'Invalid or expired link, or password is too short.';
        }
    } catch (Throwable $e) {
        if ($db->inTransaction()) { $db->rollBack(); }
        error_log($e->getMessage());
        $message = $e instanceof InvalidArgumentException ? $e->getMessage() : 'The request could not be completed. Please try again.';
    }
}
$secret = null;
$qrUri = null;
if ($page === 'mfa' && isset($_SESSION['pending_user'], $_SESSION['pending_until']) && $_SESSION['pending_until'] >= time()) {
    $q = $db->prepare('SELECT email, totp_secret, totp_confirmed_at FROM users WHERE id = ?');
    $q->execute([$_SESSION['pending_user']]);
    $user = $q->fetch();
    if ($user && !$user['totp_confirmed_at']) {
        $secret = $user['totp_secret'] ?: (new PHPGangsta_GoogleAuthenticator())->createSecret(32);
        $db->prepare('UPDATE users SET totp_secret = COALESCE(totp_secret, ?) WHERE id = ?')->execute([$secret, $_SESSION['pending_user']]);
        $issuer = 'Smart Assets';
        $qrUri = 'otpauth://totp/' . rawurlencode($issuer . ':' . $user['email'])
            . '?secret=' . rawurlencode($secret) . '&issuer=' . rawurlencode($issuer);
    }
} elseif ($page === 'mfa') { header('Location: login.php'); exit; }
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
$titles = ['login' => 'Sign in', 'register' => 'Create your account', 'verify' => 'Verify your email', 'mfa' => $secret ? 'Set up your authenticator' : 'Verify your identity', 'forgot' => 'Reset your password', 'reset' => 'Choose a new password'];
require __DIR__ . '/layout.php';
page_start($titles[$page]);
?>
<section class="card border-0 shadow-sm account-card mx-auto"><div class="card-body p-4 p-md-5">
<h1><?= h($titles[$page]) ?></h1>
<?php if ($message): ?><div class="alert alert-info" role="status"><?= h($message) ?></div><?php endif; ?>
<?php if ($page !== 'verify'): ?><form method="post"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
<?php if (in_array($page, ['login', 'register', 'forgot'], true)): ?><label for="email" class="form-label">Email address</label><input id="email" class="form-control mb-3" name="email" type="email" required autocomplete="email"><?php endif; ?>
<?php if (in_array($page, ['login', 'register', 'reset'], true)): ?><label for="password" class="form-label">Password</label><input id="password" class="form-control mb-3" name="password" type="password" required autocomplete="<?= $page === 'login' ? 'current-password' : 'new-password' ?>"><?php if ($page !== 'login'): ?><p class="form-text">Use at least 12 characters.</p><?php endif; ?><?php endif; ?>
<?php if ($page === 'mfa'): ?><?php if ($secret): ?><p>Scan this code with Google Authenticator or another authenticator app.</p><div class="qr-container"><div id="qrcode"></div></div><p>Or enter the setup key manually: <code class="token-box"><?= h($secret) ?></code></p><script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script><script>if (typeof QRCode !== 'undefined') { new QRCode(document.getElementById('qrcode'), {text: <?= json_encode($qrUri, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>, width: 200, height: 200}); }</script><?php else: ?><p>Enter the current six-digit code from your authenticator app.</p><?php endif; ?><label for="code" class="form-label">Authenticator code</label><input id="code" class="form-control mb-3" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required><?php endif; ?>
<?php if ($page === 'reset'): ?><input type="hidden" name="token" value="<?= h($token) ?>"><?php endif; ?><button class="btn btn-primary"><?= h(['login' => 'Sign in', 'register' => 'Create account', 'mfa' => 'Verify code', 'forgot' => 'Send reset link', 'reset' => 'Update password'][$page]) ?></button></form><?php endif; ?>
<nav class="mt-4 d-flex flex-wrap gap-3" aria-label="Account navigation"><a href="login.php">Sign in</a><a href="login.php?page=register">Register</a><a href="login.php?page=forgot">Reset password</a></nav>
</div></section><?php page_end(); ?>
