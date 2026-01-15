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

// Get order ID and seller ID from POST request
$order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
$seller_id = isset($_POST['seller_id']) ? intval($_POST['seller_id']) : 0;
$user_id = $_SESSION['user_id'];

if ($order_id <= 0 || $seller_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID or seller ID']);
    exit();
}

// First, verify the order belongs to the user
$order_sql = "SELECT o.* FROM `Order` o 
              WHERE o.OrderID = ? AND o.CustomerID = ?";

$stmt = $conn->prepare($order_sql);
if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Prepare failed']);
    exit();
}

$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$order_result = $stmt->get_result();

if ($order_result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Order not found or unauthorized']);
    $stmt->close();
    $conn->close();
    exit();
}

$order = $order_result->fetch_assoc();
$stmt->close();

// Now get order details for THIS ORDER and THIS SPECIFIC SELLER
$details_sql = "SELECT od.*, m.Title, m.Price, m.ImagePath, m.SellerID, 
                       s.FullName as SellerName, s.ContactNo as SellerContact,
                       s.ImagePath as SellerImage
                FROM OrderDetails od 
                JOIN Meal m ON od.MealID = m.MealID 
                JOIN Seller s ON m.SellerID = s.SellerID 
                WHERE od.OrderID = ? AND m.SellerID = ?";

$stmt = $conn->prepare($details_sql);
if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Prepare failed']);
    $conn->close();
    exit();
}

$stmt->bind_param("ii", $order_id, $seller_id);
$stmt->execute();
$details_result = $stmt->get_result();

$items = [];
if ($details_result && $details_result->num_rows > 0) {
    while($item = $details_result->fetch_assoc()) {
        $items[] = $item;
    }
}
$stmt->close();

// If no items found for this seller in this order, return error
if (count($items) === 0) {
    echo json_encode([
        'success' => false, 
        'message' => 'No items found for this seller in this order'
    ]);
    $conn->close();
    exit();
}

// Get the seller information
$seller_sql = "SELECT FullName, ContactNo, ImagePath FROM Seller WHERE SellerID = ?";
$stmt = $conn->prepare($seller_sql);
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$seller_result = $stmt->get_result();
$seller = $seller_result->fetch_assoc();
$stmt->close();

$conn->close();

// Return the data
echo json_encode([
    'success' => true,
    'order' => $order,
    'items' => $items,
    'seller' => $seller
]);
?>