<?php

require 'db.php';

if (isset($_SESSION['userID'])) {

    // Write logout timestamp and reset Status back to 0
    $update = $pdo->prepare(
        "UPDATE users
         SET LogoutDatetime = NOW(), Status = 0
         WHERE userID = ?"
    );

    $update->execute([$_SESSION['userID']]);
}

// Destroy the tracking session entirely
$_SESSION = array();
session_destroy();

?>

<?php require_once __DIR__ . '/ui.php'; page_start('Signed out', false); ?>


    <h2>You?re signed out</h2>


    <p> You have been successfully logged out. To log back in, please click the link below. </p>

    <a href="login.php">
        Return to Login
    </a>


<?php page_end(); ?>
