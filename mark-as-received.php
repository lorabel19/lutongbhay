<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (isset($_POST['order_id']) && isset($_POST['seller_id'])) {
    $order_id = $_POST['order_id'];
    $seller_id = $_POST['seller_id'];
    $customer_id = $_SESSION['user_id'];
    
    // Update order status to 'Completed'
    $sql = "UPDATE `Order` SET Status = 'Completed', CompletedDate = NOW() 
            WHERE OrderID = ? AND CustomerID = ? AND Status = 'Delivered'";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $order_id, $customer_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Order marked as received']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating order']);
    }
    
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}

$conn->close();
?>