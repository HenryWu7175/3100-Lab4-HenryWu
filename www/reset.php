<?php

require 'db.php';

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email']);
    $newPass = $_POST['new_password'];

    if (!empty($email) && !empty($newPass)) {

        $stmt = $pdo->prepare(
            "SELECT userID FROM users WHERE Email = ?"
        );

        $stmt->execute([$email]);

        if ($stmt->rowCount() > 0) {

            $newHash = password_hash($newPass, PASSWORD_BCRYPT);

            $update = $pdo->prepare(
                "UPDATE users
                 SET Password = ?
                 WHERE Email = ?"
            );

            $update->execute([$newHash, $email]);

            $message = "
                <p style='color:green;'>
                    Password updated successfully!
                    <a href='login.php'>Login here</a>
                </p>
            ";

        } else {

            $message = "
                <p style='color:red;'>
                    No account found with that email address.
                </p>
            ";
        }
    }
}

?>

<?php require_once __DIR__ . '/ui.php'; page_start('Reset password', false); ?>


    <h2>Reset Account Password</h2>

    <?php if (!empty($message)): ?><div class="alert alert-info" role="status"><?php echo $message; ?></div><?php endif; ?>

    <form method="POST">

        <label class="form-label" for="email">Enter Account Email:</label><br>

        <input id="email"
            type="email"
            name="email" autocomplete="email"
            required
         class="form-control mb-3">
        <br>

        <label class="form-label" for="new_password">Enter New Password:</label><br>

        <input id="new_password"
            type="password"
            name="new_password" autocomplete="new-password"
            required
         class="form-control mb-3">
        <br><br>

        <button
            type="submit"
         class="btn btn-primary">
            Update Password Hash
        </button>

    </form>

    <p>
        <a href="login.php">Return to Login</a>
    </p>


<?php page_end(); ?>
