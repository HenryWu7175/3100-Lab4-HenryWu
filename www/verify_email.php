<?php

require 'db.php';

$message = "";

// Auto-catch parameters if passed inside an active query link string
$tokenParam = $_GET['token'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' || !empty($tokenParam)) {

    $token = !empty($tokenParam)
        ? trim($tokenParam)
        : trim($_POST['token']);

    if (!empty($token)) {

        // Search for a user with the provided verification token
        $stmt = $pdo->prepare(
            "SELECT userID
             FROM users
             WHERE EmailVerificationToken = ?"
        );

        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if ($user) {

            // Null out token value and update validation metrics flag
            $update = $pdo->prepare(
                "UPDATE users
                 SET EmailVerified = 1,
                     EmailVerificationToken = NULL
                 WHERE userID = ?"
            );

            $update->execute([$user['userID']]);

            $message = "
                <p style='color:green; font-weight:bold;'>
                    ✔ Success: Email authenticated cleanly!
                    You may now navigate to the
                    <a href='login.php'>Login Terminal</a>.
                </p>
            ";

        } else {

            $message = "
                <p style='color:red;'>
                    Error: Invalid, structural mismatch, or expired
                    verification token profile.
                </p>
            ";
        }
    }
}

?>

<?php require_once __DIR__ . '/ui.php'; page_start('Verify email', false); ?>

    <h2>Verify your email</h2>

    <?php if (!empty($message)): ?><div class="alert alert-info" role="status"><?php echo $message; ?></div><?php endif; ?>

    <form method="POST">

        <label class="form-label" for="token">Verification token</label><br>

        <input id="token"
            type="text"
            name="token"
            value="<?php echo htmlspecialchars($tokenParam, ENT_QUOTES, 'UTF-8'); ?>"
            class="form-control mb-3"
            required
        >
        <br>

        <button
            type="submit"
         class="btn btn-primary">
            Verify email
        </button>

    </form>

<?php page_end(); ?>
