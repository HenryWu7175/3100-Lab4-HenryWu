<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


$host = getenv('DB_HOST');
$db = getenv('DB_NAME');
$user = getenv('DB_USER');
$pass = getenv('DB_PASSWORD');

// print("DB Connecting to database with host: $host, user: $user, pass: $pass, db: $db<br>");

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