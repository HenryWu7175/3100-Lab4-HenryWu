<?php

echo "<h1>Hello from your Windows 11 3-Tier Docker Lab!</h1>";
echo "<h3>PHP Status: Active</h3>";

// Database Connection Parameters
// Docker resolves "database" using the Docker network/service alias.
$host = 'database';
$db = 'lab_db';
$user = 'lab_user';
$pass = 'userpassword456';
$charset = 'utf8mb4';

// Create the PDO connection string.
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

try {
    $pdo = new PDO($dsn, $user, $pass);

    echo "<p style='color:green; font-weight:bold;'>
            ✔ Success: PHP successfully connected to the MySQL Database container!
          </p>";
} catch (\PDOException $e) {
    echo "<p style='color:red; font-weight:bold;'>
            Connection Failed: " . $e->getMessage() . "
          </p>";
}

?>