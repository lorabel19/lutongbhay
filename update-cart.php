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

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$meal_id = isset($data['meal_id']) ? intval($data['meal_id']) : 0;
$quantity = isset($data['quantity']) ? intval($data['quantity']) : 1;
$user_id = $_SESSION['user_id'];

if ($meal_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid meal ID']);
    exit();
}

if ($quantity < 1) {
    $quantity = 1;
}

if ($quantity > 10) {
    $quantity = 10;
}

// Get meal price
$price_sql = "SELECT Price FROM Meal WHERE MealID = ?";
$price_stmt = $conn->prepare($price_sql);
$price_stmt->bind_param("i", $meal_id);
$price_stmt->execute();
$price_result = $price_stmt->get_result();

if ($price_result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Meal not found']);
    exit();
}

$meal = $price_result->fetch_assoc();
$item_price = $meal['Price'];
$price_stmt->close();

// Check if item exists in cart
$check_sql = "SELECT * FROM Cart WHERE CustomerID = ? AND MealID = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("ii", $user_id, $meal_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    // Update quantity
    $update_sql = "UPDATE Cart SET Quantity = ? WHERE CustomerID = ? AND MealID = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("iii", $quantity, $user_id, $meal_id);
    
    if ($update_stmt->execute()) {
        echo json_encode(['success' => true, 'item_price' => $item_price]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update quantity']);
    }
    
    $update_stmt->close();
} else {
    // Add new item to cart (in case it was somehow removed)
    $insert_sql = "INSERT INTO Cart (CustomerID, MealID, Quantity) VALUES (?, ?, ?)";
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param("iii", $user_id, $meal_id, $quantity);
    
    if ($insert_stmt->execute()) {
        echo json_encode(['success' => true, 'item_price' => $item_price]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add item to cart']);
    }
    
    $insert_stmt->close();
}

$check_stmt->close();
$conn->close();
?>