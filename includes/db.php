<?php
// ============================================
// Database Connection
// ============================================
$host = "localhost";
$dbUsername = "root";
$dbPassword = "";
$dbName = "bus_reservation_db";

$conn = new mysqli($host, $dbUsername, $dbPassword, $dbName);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
