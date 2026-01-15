<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "1019";
$dbname = "lutongbahay_db";
$port = 3306;

$conn = new mysqli($servername, $username, $password, $dbname, $port);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Get cart count
$cart_count_sql = "SELECT COUNT(*) as cart_count FROM Cart WHERE CustomerID = ?";
$cart_count_stmt = $conn->prepare($cart_count_sql);
$cart_count_stmt->bind_param("i", $user_id);
$cart_count_stmt->execute();
$cart_count_result = $cart_count_stmt->get_result();
$cart_count_data = $cart_count_result->fetch_assoc();
$cart_count = $cart_count_data['cart_count'] ?: 0;
$cart_count_stmt->close();

$conn->close();

echo json_encode([
    'success' => true,
    'cart_count' => $cart_count
]);
?>