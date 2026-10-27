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

                die("<p style='color:red; font-family:sans-serif; max-width:400px; margin:40px
auto;'>￿ Login Blocked: Complete your baseline link validation first.</p>");
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

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>System Login Proxy</title>
</head>

<body style="
        font-family:sans-serif;
        max-width:400px;
        margin:40px auto;
        padding:20px;
    ">

    <h2>Sign-In</h2>

    <?php echo $message; ?>

    <form method="POST">

        <label>Email Address:</label>
        <br>

        <input type="email" name="email" style="
                width:100%;
                padding:8px;
                margin:5px 0;
            " required>

        <br>

        <label>Password:</label>
        <br>

        <input type="password" name="password" style="
                width:100%;
                padding:8px;
                margin:5px 0;
            " required>

        <br><br>

        <button type="submit" style="
                padding:10px 15px;
                background:#0076ff;
                color:#fff;
                border:none;
                border-radius:4px;
                cursor:pointer;
            ">
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
        Dont have an account? Register here!
    </a>

</body>

</html>