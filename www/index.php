<?php
require_once __DIR__ . '/ui.php';
page_start('Welcome');
echo '<span class="badge text-bg-primary mb-3">WEB SERVICES LAB</span><h1>Your account, connected.</h1><p class="text-secondary">Create an account, verify your email, and sign in with your authenticator.</p><div class="d-grid gap-2 d-sm-flex mb-4"><a href="register.php" class="btn btn-primary">Create account</a><a href="login.php" class="btn btn-outline-primary">Sign in</a></div>';

$host = getenv("DB_HOST");
$user = getenv("DB_USER");
$password = getenv("DB_PASSWORD");
$db = getenv("DB_NAME");

// print("index Connecting to database with host: $host, user: $user, pass: $password, db: $db<br>");

$conn = new mysqli($host, $user, $password, $db);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

echo '<div class="alert alert-success">Database connected.</div>';

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
    echo '<p class="small text-secondary mb-0">Account services are ready.</p>';
} else {
    echo "<p>Error creating users table: " . $conn->error . "</p>";
}

$conn->close();
page_end();
?>
