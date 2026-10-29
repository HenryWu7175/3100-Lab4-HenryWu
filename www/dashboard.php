<?php

require 'db.php';

// Route back to login if no active session is found
if (!isset($_SESSION['userID'])) {
    header("Location: login.php");
    exit;
}

// Fetch fresh profile state to see live table tracking markers
$stmt = $pdo->prepare("SELECT * FROM users WHERE userID = ?");
$stmt->execute([$_SESSION['userID']]);
$profile = $stmt->fetch();

?>

<?php require_once __DIR__ . '/ui.php'; page_start('Your dashboard', true); ?>


    <h2>
        Welcome,
        <?php
        echo htmlspecialchars($profile['FirstName']) . " " .
             htmlspecialchars($profile['LastName']);
        ?>!
    </h2>

    <p>
        Status: Session Authenticated
        (Status Code: <?php echo $profile['Status']; ?>)
    </p>

    <div>

        <h3>Account details</h3>

        <ul class="list-group list-group-flush mb-4">
            <li class="list-group-item px-0 py-3">
                <strong>User Account ID:</strong>
                <?php echo $profile['userID']; ?>
            </li>

            <li class="list-group-item px-0 py-3">
                <strong>Registered On:</strong>
                <?php echo $profile['RegDatetime']; ?>
            </li>

            <li class="list-group-item px-0 py-3">
                <strong>Last Login Captured:</strong>
                <?php echo $profile['LoginDatetime']; ?>
            </li>

            <li class="list-group-item px-0 py-3">
                <strong>Previous Logout Captured:</strong>
                <?php echo $profile['LogoutDatetime'] ?? 'Never Logged Out'; ?>
            </li>
        </ul>

    </div>

    <a
        class="btn btn-outline-danger" href="logout.php"
    >
        Secure Logout
    </a>


<?php page_end(); ?>
