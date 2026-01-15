<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
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
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Get cart items grouped by seller
$cart_sql = "SELECT 
                c.MealID, 
                c.Quantity, 
                m.Price,
                m.SellerID
            FROM Cart c
            JOIN Meal m ON c.MealID = m.MealID
            WHERE c.CustomerID = ?";
$stmt = $conn->prepare($cart_sql);

if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
    $conn->close();
    exit();
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$cart_result = $stmt->get_result();

$subtotal = 0;
$total_items = 0;
$unique_sellers = [];

if ($cart_result) {
    while ($item = $cart_result->fetch_assoc()) {
        $item_subtotal = $item['Price'] * $item['Quantity'];
        $subtotal += $item_subtotal;
        $total_items += $item['Quantity'];
        
        if (!in_array($item['SellerID'], $unique_sellers)) {
            $unique_sellers[] = $item['SellerID'];
        }
    }
}

$stmt->close();

// Calculate fees
$seller_count = count($unique_sellers);
$delivery_fee_per_seller = 30.00;
$total_delivery_fee = $delivery_fee_per_seller * $seller_count;
$total_amount = $subtotal + $total_delivery_fee;

$conn->close();

echo json_encode([
    'success' => true,
    'subtotal' => $subtotal,
    'total_items' => $total_items,
    'seller_count' => $seller_count,
    'delivery_fee_per_seller' => $delivery_fee_per_seller,
    'total_delivery_fee' => $total_delivery_fee,
    'total' => $total_amount
]);
?>