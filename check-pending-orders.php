<?php
// check-pending-orders.php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'seller') {
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

$seller_id = $_SESSION['user_id'];

// Get pending orders count - COUNTING DISTINCT ORDERS
$pending_count = 0;
$pending_sql = "SELECT COUNT(DISTINCT o.OrderID) as pending_count
                FROM `Order` o
                JOIN OrderDetails od ON o.OrderID = od.OrderID
                JOIN Meal m ON od.MealID = m.MealID
                WHERE m.SellerID = ? AND o.Status = 'Pending'";
$stmt = $conn->prepare($pending_sql);
if ($stmt) {
    $stmt->bind_param("i", $seller_id);
    $stmt->execute();
    $pending_result = $stmt->get_result();
    if ($pending_result) {
        $pending_data = $pending_result->fetch_assoc();
        $pending_count = $pending_data['pending_count'] ?: 0;
    }
    $stmt->close();
}

// Also get count of items in pending orders for debugging/verification
$pending_items_sql = "SELECT COUNT(*) as pending_items
                     FROM OrderDetails od
                     JOIN Meal m ON od.MealID = m.MealID
                     JOIN `Order` o ON od.OrderID = o.OrderID
                     WHERE m.SellerID = ? AND o.Status = 'Pending'";
$stmt2 = $conn->prepare($pending_items_sql);
$pending_items = 0;
if ($stmt2) {
    $stmt2->bind_param("i", $seller_id);
    $stmt2->execute();
    $items_result = $stmt2->get_result();
    if ($items_result) {
        $items_data = $items_result->fetch_assoc();
        $pending_items = $items_data['pending_items'] ?: 0;
    }
    $stmt2->close();
}

$conn->close();

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'pending_count' => $pending_count,
    'pending_items' => $pending_items
]);
?>