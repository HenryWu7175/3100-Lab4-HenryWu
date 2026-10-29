<?php
echo "<h1>Hello from PHP!</h1>";

$host = getenv("DB_HOST");
$user = getenv("DB_USER");
$password = getenv("DB_PASSWORD");
$db = getenv("DB_NAME");

// print("index Connecting to database with host: $host, user: $user, pass: $password, db: $db<br>");

$conn = new mysqli($host, $user, $password, $db);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

echo "<p>Successfully connected to MySQL!</p>";

// Create the users table if it does not already exist
$sql = "CREATE TABLE IF NOT EXISTS users (
    userID INT AUTO_INCREMENT PRIMARY KEY,
    FirstName VARCHAR(100) NOT NULL,
    LastName VARCHAR(100) NOT NULL,
    Email VARCHAR(255) NOT NULL UNIQUE,
    Password VARCHAR(255) NOT NULL,
    EmailVerified TINYINT(1) NOT NULL DEFAULT 0,
    EmailVerificationToken VARCHAR(32) NULL,
    TotpSecret VARCHAR(32) NULL,
    Role ENUM('user', 'admin') NOT NULL DEFAULT 'user',
    Status INT DEFAULT 0,
    RegDatetime DATETIME DEFAULT CURRENT_TIMESTAMP,
    LoginDatetime DATETIME NULL,
    LogoutDatetime DATETIME NULL
) ENGINE=InnoDB;";

if ($conn->query($sql) === TRUE) {
    echo "<p>Users table is ready!</p>";
} else {
    echo "<p>Error creating users table: " . $conn->error . "</p>";
}

$conn->close();
?>
