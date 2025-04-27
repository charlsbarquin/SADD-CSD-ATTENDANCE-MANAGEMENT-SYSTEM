<?php
$servername = "localhost";
$username = "root";  
$password = "";  // Leave blank if no password is set
$dbname = "attendance_system";
$port = 3308;  // Set to your MySQL port

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname, $port);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ✅ Set PHP Timezone
date_default_timezone_set('Asia/Manila'); // Change if needed

// ✅ Set MySQL Timezone
$conn->query("SET time_zone = '+08:00';");

?>
