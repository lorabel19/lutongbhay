<?php
// cancel-order.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
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
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Get order ID from POST request
$order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
$customer_id = $_SESSION['user_id'];

if ($order_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit();
}

// Check if the order belongs to the current customer
$check_sql = "SELECT * FROM `Order` WHERE OrderID = ? AND CustomerID = ? AND Status = 'Pending'";
$stmt = $conn->prepare($check_sql);
$stmt->bind_param("ii", $order_id, $customer_id);
$stmt->execute();
$result = $stmt->get_result();
$order = $result->fetch_assoc();
$stmt->close();

if (!$order) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Order not found or cannot be cancelled']);
    exit();
}

// Update order status to Cancelled
$update_sql = "UPDATE `Order` SET Status = 'Cancelled' WHERE OrderID = ?";
$stmt = $conn->prepare($update_sql);
$stmt->bind_param("i", $order_id);

if ($stmt->execute()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Order cancelled successfully']);
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error cancelling order']);
}

$stmt->close();
$conn->close();
?>