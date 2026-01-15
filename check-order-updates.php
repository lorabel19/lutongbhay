<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
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

// Get order data from POST request
$order_data_json = isset($_POST['order_data']) ? $_POST['order_data'] : '[]';
$order_data = json_decode($order_data_json, true);

if (empty($order_data)) {
    echo json_encode(['success' => false, 'message' => 'No order data provided']);
    exit();
}

$user_id = $_SESSION['user_id'];
$updates = [];

// Check each order-seller combination
foreach ($order_data as $order_info) {
    $order_id = intval($order_info['order_id']);
    $seller_id = intval($order_info['seller_id']);
    
    // Get current status for this specific order-seller combination
    $status_sql = "SELECT o.Status 
                   FROM `Order` o 
                   JOIN OrderDetails od ON o.OrderID = od.OrderID
                   JOIN Meal m ON od.MealID = m.MealID
                   WHERE o.OrderID = ? 
                   AND o.CustomerID = ?
                   AND m.SellerID = ?
                   LIMIT 1";
    
    $stmt = $conn->prepare($status_sql);
    if ($stmt) {
        $stmt->bind_param("iii", $order_id, $user_id, $seller_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $updates[] = [
                'order_id' => $order_id,
                'seller_id' => $seller_id,
                'status' => $row['Status']
            ];
        }
        $stmt->close();
    }
}

$conn->close();

echo json_encode([
    'success' => true,
    'updates' => $updates
]);
?>