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

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>System Logout Proxy</title>
</head>

<body style="
        font-family:sans-serif;
        max-width:400px;
        margin:40px auto;
        padding:20px;
    ">

    <h2>Sign-Out</h2>


    <p> You have been successfully logged out. To log back in, please click the link below. </p>

    <a href="login.php">
        Return to Login
    </a>

</body>

</html>