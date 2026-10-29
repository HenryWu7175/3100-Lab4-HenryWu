<?php

require 'db.php';


$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email']);
    $pass = $_POST['password'];

    if (!empty($email) && !empty($pass)) {

        // Find the account associated with the submitted email
        $stmt = $pdo->prepare(
            "SELECT *
             FROM users
             WHERE Email = ?"
        );

        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Verify that the account exists and the password is correct
        if ($user && password_verify($pass, $user['Password'])) {

            // Extra Credit Block:
            // Stop unverified emails from completing challenge entries
            if ($user['EmailVerified'] == 0) {

                require_once __DIR__ . '/ui.php';
                page_start('Verify your email');
                echo '<h1>Check your inbox</h1><div class="alert alert-warning" role="alert">Verify your email address before signing in.</div><a class="btn btn-primary" href="verify_email.php">Verify email</a>';
                page_end();
                exit;
            }

            // Keep the user pending until authenticator verification succeeds.
            $_SESSION['2fa_pending_userID'] = $user['userID'];

            // New authenticator users enroll; existing users verify their app code.
            if (empty($user['TotpSecret'])) {
                header("Location: setup_2fa.php");
            } else {
                header("Location: verify_2fa.php");
            }
            exit;

        } else {

            $message = "
                <p style='color:red;'>
                    Invalid email or password parameters.
                </p>
            ";
        }
    }
}

?>

<?php require_once __DIR__ . '/ui.php'; page_start('Sign in', false); ?>


    <h2>Welcome back</h2>

    <?php if (!empty($message)): ?><div class="alert alert-info" role="status"><?php echo $message; ?></div><?php endif; ?>

    <form method="POST">

        <label class="form-label" for="email">Email Address:</label>
        <br>

        <input id="email" type="email" name="email" autocomplete="email" required class="form-control mb-3">

        <br>

        <label class="form-label" for="password">Password:</label>
        <br>

        <input id="password" type="password" name="password" autocomplete="current-password" required class="form-control mb-3">

        <br><br>

        <button type="submit" class="btn btn-primary">
            Login
        </button>

    </form>

    <br>

    <a href="reset.php">
        Forgot password?
    </a>

    <br>
    <br>

    <a href="register.php">
        New here? Create an account
    </a>


<?php page_end(); ?>
