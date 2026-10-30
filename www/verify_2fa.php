<?php
require 'db.php';
require_once __DIR__ . '/account_helpers.php';

if (!isset($_SESSION['2fa_pending_userID'])) {
    header("Location: login.php");
    exit;
}

$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedPin = $_POST['pin'] ?? '';
    $userPin = is_string($submittedPin) ? trim($submittedPin) : '';
    $userId = $_SESSION['2fa_pending_userID'];
    $stmt = $pdo->prepare("SELECT TotpSecret, FirstName FROM users WHERE userID = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    $checkResult = $user && valid_authenticator_code($user['TotpSecret'], $userPin);

    if ($checkResult) {
        $update = $pdo->prepare("UPDATE users SET LoginDatetime = NOW(), Status = 1 WHERE userID = ?");
        $update->execute([$userId]);
        session_regenerate_id(true);
        $_SESSION['userID'] = $userId;
        $_SESSION['FirstName'] = $user['FirstName'];
        unset($_SESSION['2fa_pending_userID']);
        header("Location: dashboard.php");
        exit;
    } else {
        $message = "<p style='color:red; font-weight:bold;'>Access Denied: Invalid or expired authenticator code.</p>";
    }
}
?>
<?php require_once __DIR__ . '/ui.php'; page_start('Verify your identity', false); ?>

    <h2>Google Authenticator</h2>
    <p>Open your authenticator app and enter the current six-digit code for your account.</p>
    <?php if (!empty($message)): ?><div class="alert alert-info" role="status"><?php echo $message; ?></div><?php endif; ?>
    <form method="POST">
        <label class="form-label" for="pin">Authenticator code:</label><br>
        <input id="pin" type="text" name="pin" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" required autocomplete="off"
            placeholder="000000" class="form-control mb-3"><br>
        <button type="submit" class="btn btn-primary">Verify Code</button>
    </form>

<?php page_end(); ?>
