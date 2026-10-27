<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = 'database';
$db = 'lab_db';
$user = 'lab_user';
$pass = 'userpassword456';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (\PDOException $e) {
    die("Database Connection Fail: " . $e->getMessage());
}

try {
    $pdo->exec("ALTER TABLE users ADD COLUMN TotpSecret VARCHAR(32) NULL;");
} catch (\PDOException $e) {
    if ($e->errorInfo[1] !== 1060)
        die("Database error: " . $e->getMessage());
}

?>