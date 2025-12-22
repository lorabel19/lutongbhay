<?php
$servername = "localhost";
$username = "root";
$password = "1019"; // kung may password ang MySQL root mo, ilagay dito
$dbname = "lutongbahay_db"; // pangalan ng database mo
$port = 3306; // palitan kung nag-change ka ng MySQL port

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname, $port);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
echo "MySQL connection successful!";
?>
