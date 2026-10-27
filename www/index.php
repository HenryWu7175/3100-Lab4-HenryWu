<?php
echo "<h1>Hello from PHP!</h1>";

$host = "database";
$user = "root";
$password = getenv("MYSQL_ROOT_PASSWORD");
$db = getenv("MYSQL_DATABASE");

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