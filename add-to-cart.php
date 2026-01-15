<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit();
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get the JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['meal_id']) || !is_numeric($input['meal_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid meal ID']);
    exit();
}

$meal_id = intval($input['meal_id']);
$quantity = isset($input['quantity']) ? intval($input['quantity']) : 1;
$user_id = $_SESSION['user_id'];

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

// Check if meal exists and is available
$meal_check_sql = "SELECT * FROM Meal WHERE MealID = ? AND Availability = 'Available'";
$meal_stmt = $conn->prepare($meal_check_sql);
$meal_stmt->bind_param("i", $meal_id);
$meal_stmt->execute();
$meal_result = $meal_stmt->get_result();

if ($meal_result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Meal not available']);
    $meal_stmt->close();
    $conn->close();
    exit();
}
$meal_stmt->close();

// Check if item already exists in cart
$check_sql = "SELECT * FROM Cart WHERE CustomerID = ? AND MealID = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("ii", $user_id, $meal_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    // Update quantity if item exists
    $row = $check_result->fetch_assoc();
    $new_quantity = $row['Quantity'] + $quantity;
    
    $update_sql = "UPDATE Cart SET Quantity = ? WHERE CustomerID = ? AND MealID = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("iii", $new_quantity, $user_id, $meal_id);
    
    if ($update_stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Quantity updated in cart']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating cart']);
    }
    
    $update_stmt->close();
} else {
    // Add new item to cart
    $insert_sql = "INSERT INTO Cart (CustomerID, MealID, Quantity, AddedAt) VALUES (?, ?, ?, NOW())";
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param("iii", $user_id, $meal_id, $quantity);
    
    if ($insert_stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Added to cart']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error adding to cart']);
    }
    
    $insert_stmt->close();
}

$check_stmt->close();
$conn->close();
?>