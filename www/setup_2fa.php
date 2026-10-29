<?php
require 'db.php';
require 'GoogleAuthenticator.php';
// Route out to login if the session profile context isn't set yet
if (!isset($_SESSION['2fa_pending_userID'])) {
    header("Location: login.php");
    exit;
}
$userId = $_SESSION['2fa_pending_userID'];
$ga = new PHPGangsta_GoogleAuthenticator();
$message = "";
// 1. Fetch user email to populate the authenticator UI label
$stmt = $pdo->prepare("SELECT Email, TotpSecret FROM users WHERE userID = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();
// 2. Generate a permanent secret key if one does not exist yet
if (empty($user['TotpSecret'])) {
    $secretKey = $ga->createSecret();
    $update = $pdo->prepare("UPDATE users SET TotpSecret = ? WHERE userID = ?");
    $update->execute([$secretKey, $userId]);
} else {
    $secretKey = $user['TotpSecret'];
}
// 3. Construct the official RFC-6238 TOTP URI profile parameter string
$appName = "DockerLab-3Tier";
$qrCodeDataUrl =
    "otpauth://totp/{$appName}:{$user['Email']}?secret={$secretKey}&issuer={$appName}";
// 4. Validate a 6-digit test submission before activating the device layer
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codeAttempt = trim($_POST['code']);
    // Check code against the current clock cycle (includes a grace window of 1 interval)
    $checkResult = $ga->verifyCode($secretKey, $codeAttempt, 1);
    if ($checkResult) {
        // Activate full authentication metrics state variables
        $update = $pdo->prepare("UPDATE users SET LoginDatetime = NOW(), Status = 1
WHERE userID = ?");
        $update->execute([$userId]);
        $_SESSION['userID'] = $userId;
        unset($_SESSION['2fa_pending_userID']);
        header("Location: dashboard.php");
        exit;
    } else {
        $message = "<p style='color:red; font-weight:bold;'>￿ Verification Failed: PIN does not
match clock signature. Sync your device time and try again.</p>";
    }
}
?>
<?php require_once __DIR__ . '/ui.php'; page_start('Set up authenticator', false); ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

    <h2>Set up your authenticator</h2>
    <p>Scan the code wrapper below using <strong>Google Authenticator</strong> or any
        TOTP terminal application:</p>
    <div class="qr-container">
        <div id="qrcode"></div>
    </div>
    <p>Manual Secret Key Configuration: <br><code><?php echo $secretKey;
            ?></code></p>
    <?php if (!empty($message)): ?><div class="alert alert-info" role="status"><?php echo $message; ?></div><?php endif; ?>
    <form method="POST">
        <label class="form-label" for="code"><strong>Enter the 6-Digit Generated App Verification
                Code:</strong></label><br>
        <input id="code" type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="off" placeholder="000000" class="form-control mb-3"><br><br>
        <button type="submit" class="btn btn-primary">Activate authenticator</button>
    </form>
    <!-- Client-Side Javascript Injection Core Hook -->
    <script type="text/javascript">
        new QRCode(document.getElementById("qrcode"), {
            text: <?php echo json_encode($qrCodeDataUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
            width: 200,
            height: 200
        });
    </script>

<?php page_end(); ?>
