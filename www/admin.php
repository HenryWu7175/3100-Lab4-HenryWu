<?php

require 'db.php';

if (!isset($_SESSION['userID'])) {
    header('Location: login.php');
    exit;
}

$roleCheck = $pdo->prepare('SELECT Role FROM users WHERE userID = ?');
$roleCheck->execute([$_SESSION['userID']]);
if ($roleCheck->fetchColumn() !== 'admin') {
    http_response_code(403);
    exit('Access denied. Administrators only.');
}

$_SESSION['admin_csrf'] ??= bin2hex(random_bytes(32));
$adminMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf'] ?? '';
    if (!is_string($csrf) || !hash_equals($_SESSION['admin_csrf'], $csrf)) {
        http_response_code(403);
        exit('Invalid request. Refresh the page and try again.');
    }
    $email = $_POST['email'] ?? '';
    if (!is_string($email) || !filter_var(trim($email), FILTER_VALIDATE_EMAIL)) {
        $adminMessage = 'Enter a valid email address.';
    } else {
        $promote = $pdo->prepare(
            "UPDATE users SET Role = 'admin' WHERE Email = ? AND EmailVerified = 1 AND Role = 'user'"
        );
        $promote->execute([trim($email)]);
        $adminMessage = $promote->rowCount() > 0
            ? 'Administrator access granted.'
            : 'No eligible account found. The user must register and verify their email first, and must not already be an admin.';
    }
}

try {
    $stmt = $pdo->query(
        "SELECT
            userID,
            FirstName,
            LastName,
            Email,
            Role,
            Status,
            EmailVerified,
            EmailVerificationToken,
            (TotpSecret IS NOT NULL AND TotpSecret <> '') AS TwoFactorEnabled,
            RegDatetime,
            LoginDatetime,
            LogoutDatetime
         FROM users
         ORDER BY RegDatetime DESC"
    );

    $users = $stmt->fetchAll();

} catch (\PDOException $e) {
    die(
        "<p style='color:red;'>
            Error fetching metrics: " . $e->getMessage() . "
        </p>"
    );
}

?>

<?php require_once __DIR__ . '/ui.php'; page_start('Admin console', true); ?>


    <div class="header">

        <div>
            <h1>Account administration</h1>

            <p>
                Account overview:
                <span>
                    registration, verification, and session activity
                </span>
            </p>
        </div>

        <a href="admin.php" class="btn btn-outline-primary">
            Refresh
        </a>

    </div>

    <h3>
        Account registry

    </h3>

    <h3>Grant administrator access</h3>
    <p>New administrators must register normally and verify their email before you promote them.</p>
    <p><?php echo htmlspecialchars($adminMessage, ENT_QUOTES, 'UTF-8'); ?></p>
    <form method="post" action="admin.php" class="bg-light border rounded-3 p-3 mb-4">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['admin_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
        <label class="form-label" for="admin-email">Registered user's email</label>
        <input id="admin-email" type="email" name="email" required class="form-control mb-3">
        <button type="submit" class="btn btn-primary">Grant admin access</button>
    </form>

    <div class="table-responsive"><table class="table table-hover align-middle">

        <thead>
            <tr>
                <th>User ID</th>
                <th>Full Name</th>
                <th>Email Address</th>
                <th>Role</th>
                <th>Verification State</th>
                <th>Email verification token</th>
                <th>2FA Setup</th>
                <th>Session</th>
                <th>Last Login</th>
                <th>Last Logout</th>
            </tr>
        </thead>

        <tbody>

            <?php if (count($users) > 0): ?>

                <?php foreach ($users as $row): ?>

                    <tr>

                        <td>
                            <strong>
                                #<?php echo $row['userID']; ?>
                            </strong>
                        </td>

                        <td>
                            <?php
                            echo htmlspecialchars(
                                $row['FirstName'] . ' ' . $row['LastName']
                            );
                            ?>
                        </td>

                        <td>
                            <code>
                                <?php echo htmlspecialchars($row['Email']); ?>
                            </code>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($row['Role'], ENT_QUOTES, 'UTF-8'); ?>
                        </td>

                        <!-- Email Verification Status -->
                        <td>

                            <?php if ($row['EmailVerified'] == 1): ?>

                                <span class="status-badge status-online">
                                    ✔ Verified (1)
                                </span>

                            <?php else: ?>

                                <span class="status-badge status-offline">
                                    Pending (0)
                                </span>

                            <?php endif; ?>

                        </td>

                        <!-- Email Verification Token -->
                        <td>

                            <?php if (!empty($row['EmailVerificationToken'])): ?>

                                <span class="token-box">
                                    <?php echo $row['EmailVerificationToken']; ?>
                                </span>

                                <a
                                    href="verify_email.php?token=<?php
                                        echo $row['EmailVerificationToken'];
                                    ?>"
                                    target="_blank"
                                >
                                    [Auto Activate]
                                </a>

                            <?php else: ?>

                                <span>
                                    None/Cleared
                                </span>

                            <?php endif; ?>

                        </td>

                        <!-- Authenticator enrollment status -->
                        <td>

                            <?php if (!empty($row['TwoFactorEnabled'])): ?>

                                <span class="pin-box">
                                    Configured
                                </span>

                            <?php else: ?>

                                <span>
                                    Inactive
                                </span>

                            <?php endif; ?>

                        </td>

                        <!-- Login Session Status -->
                        <td>

                            <?php if ($row['Status'] == 1): ?>

                                <span class="status-badge status-online">
                                    ● Online
                                </span>

                            <?php else: ?>

                                <span class="status-badge status-offline">
                                    ○ Offline
                                </span>

                            <?php endif; ?>

                        </td>

                        <!-- Last Login -->
                        <td>
                            <?php
                            echo htmlspecialchars(
                                $row['LoginDatetime'] ?? 'Never'
                            );
                            ?>
                        </td>

                        <!-- Last Logout -->
                        <td>
                            <?php
                            echo htmlspecialchars(
                                $row['LogoutDatetime'] ?? 'Never'
                            );
                            ?>
                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>
                    <td
                        colspan="10"
                    >
                        No container metrics available.
                    </td>
                </tr>

            <?php endif; ?>

        </tbody>

    </table></div>

    <p>
        <strong>Quick links</strong>

        <a href="register.php">
            Create account
        </a>

        |

        <a href="login.php">
            Sign in
        </a>

        |

        <a href="dashboard.php">
            Dashboard
        </a>
    </p>


<?php page_end(); ?>
