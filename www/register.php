<?php

require 'db.php';
require 'mailer.php';
require_once __DIR__ . '/account_helpers.php';

$message = "";


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $first = trim($_POST['firstname']);
    $last = trim($_POST['lastname']);
    $email = trim($_POST['email']);
    $pass = $_POST['password'];

    if (!empty($first) && !empty($last) && !empty($email) && !empty($pass)) {

        try {

            // Check whether the email is already registered
            $stmt = $pdo->prepare(
                "SELECT userID
                 FROM users
                 WHERE Email = ?"
            );

            $stmt->execute([$email]);

            if ($stmt->rowCount() > 0) {

                $message = "
                    <p style='color:red;'>
                        Email already registered!
                    </p>
                ";

            } else {

                // Hash the user's password
                $hashedPass = password_hash(
                    $pass,
                    PASSWORD_BCRYPT
                );

                // Extra Credit Feature:
                // Create a unique hexadecimal email verification token
                $verificationToken = bin2hex(
                    random_bytes(16)
                );

                // Insert the new user into the database
                $insert = $pdo->prepare(
                    "INSERT INTO users (
                        FirstName,
                        LastName,
                        Email,
                        Password,
                        Status,
                        EmailVerified,
                        EmailVerificationToken,
                        RegDatetime
                    )
                    VALUES (?, ?, ?, ?, 0, 0, ?, NOW())"
                );

                $insert->execute([
                    $first,
                    $last,
                    $email,
                    $hashedPass,
                    $verificationToken
                ]);

                // Create email verification link
                $verifyLink = verification_url(
                    getenv('APP_URL') ?: 'http://localhost:8080',
                    $verificationToken
                );

                // Create verification email
                $emailSubject =
                    "Activate Your Docker Core Account Profile";

                $emailContent = "
                    <h3>
                        Welcome to the 3-Tier Laboratory Stack, {$first}!
                    </h3>

                    <p>
                        Please click the secure network connection
                        verification link below to authorize your
                        device profile:
                    </p>

                    <p>
                        <a
                            href='{$verifyLink}'
                            style='
                                background:#0076ff;
                                color:#fff;
                                padding:10px;
                                text-decoration:none;
                                border-radius:4px;
                                display:inline-block;
                            '
                        >
                            Verify My Email Address
                        </a>
                    </p>

                    <br>

                    <small>
                        Token verification hash signature:
                        <code>{$verificationToken}</code>
                    </small>
                ";

                // Send verification email
                if (
                    sendSystemMail(
                        $email,
                        "{$first} {$last}",
                        $emailSubject,
                        $emailContent
                    )
                ) {

                    $message = "
                        <p style='color:green;'>
                            Registration successful! A real verification
                            link has been dispatched. Check your Mailtrap
                            inbox to validate your access profile.
                        </p>
                    ";

                } else {

                    $message = "
                        <p style='color:orange;'>
                            Profile built, but system failed to transmit
                            verification message wrapper.
                        </p>
                    ";
                }
            }

        } catch (\PDOException $e) {

            $message = "
                <p style='color:red;'>
                    Error: " . $e->getMessage() . "
                </p>
            ";
        }

    } else {

        $message = "
            <p style='color:orange;'>
                All fields are required.
            </p>
        ";
    }
}

?>

<?php require_once __DIR__ . '/ui.php'; page_start('Create account', false); ?>


    <h2>Create your account</h2>

    <?php if (!empty($message)): ?><div class="alert alert-info" role="status"><?php echo $message; ?></div><?php endif; ?>

    <form method="POST">

        <label class="form-label" for="firstname">First Name:</label>
        <br>

        <input id="firstname"
            type="text"
            name="firstname"
            required
         class="form-control mb-3">

        <br>

        <label class="form-label" for="lastname">Last Name:</label>
        <br>

        <input id="lastname"
            type="text"
            name="lastname"
            required
         class="form-control mb-3">

        <br>

        <label class="form-label" for="email">Email Address:</label>
        <br>

        <input id="email"
            type="email"
            name="email" autocomplete="email"
            required
         class="form-control mb-3">

        <br>

        <label class="form-label" for="password">Password:</label>
        <br>

        <input id="password"
            type="password"
            name="password" autocomplete="new-password"
            required
         class="form-control mb-3">

        <br><br>

        <button
            type="submit"
         class="btn btn-primary">
            Register Account
        </button>

    </form>

    <hr>

    <p>
        Looking to verify an existing link?

        <a href="verify_email.php">
            Verify your email
        </a>
    </p>
    <p>
        Already have an account?

        <a href="login.php">
            Welcome back Here
        </a>
    </p>



<?php page_end(); ?>
