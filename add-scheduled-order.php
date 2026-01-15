<?php
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

// Get POST data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Validate required fields
if (!isset($data['meal_id'], $data['schedule_date'], $data['schedule_time'], $data['quantity'], $data['delivery_address'], $data['contact_no'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

$customer_id = $_SESSION['user_id'];
$meal_id = $data['meal_id'];
$schedule_date = $data['schedule_date'];
$schedule_time = $data['schedule_time'];
$quantity = $data['quantity'];
$delivery_address = $data['delivery_address'];
$contact_no = $data['contact_no'];
$total_amount = $data['total_amount'] ?? 0;

// Combine date and time for ScheduleDate
$schedule_datetime = $schedule_date . ' ' . $schedule_time . ':00';

// Start transaction
$conn->begin_transaction();

try {
    // 1. Get meal price and seller ID
    $meal_sql = "SELECT Price, SellerID FROM Meal WHERE MealID = ?";
    $meal_stmt = $conn->prepare($meal_sql);
    $meal_stmt->bind_param("i", $meal_id);
    $meal_stmt->execute();
    $meal_result = $meal_stmt->get_result();
    $meal = $meal_result->fetch_assoc();
    $meal_stmt->close();
    
    if (!$meal) {
        throw new Exception("Meal not found");
    }
    
    $meal_price = $meal['Price'];
    $subtotal = $meal_price * $quantity;
    
    // 2. Insert into Order table
    $order_sql = "INSERT INTO `Order` 
                  (CustomerID, ScheduleDate, OrderType, Status, TotalAmount, DeliveryAddress, ContactNo) 
                  VALUES (?, ?, 'Scheduled', 'Upcoming', ?, ?, ?)";
    
    $order_stmt = $conn->prepare($order_sql);
    $order_stmt->bind_param("isdss", $customer_id, $schedule_datetime, $total_amount, $delivery_address, $contact_no);
    
    if (!$order_stmt->execute()) {
        throw new Exception("Failed to create order: " . $order_stmt->error);
    }
    
    $order_id = $conn->insert_id;
    $order_stmt->close();
    
    // 3. Insert into OrderDetails table
    $order_details_sql = "INSERT INTO OrderDetails (OrderID, MealID, Quantity, Subtotal) 
                          VALUES (?, ?, ?, ?)";
    
    $order_details_stmt = $conn->prepare($order_details_sql);
    $order_details_stmt->bind_param("iiid", $order_id, $meal_id, $quantity, $subtotal);
    
    if (!$order_details_stmt->execute()) {
        throw new Exception("Failed to create order details: " . $order_details_stmt->error);
    }
    
    $order_details_stmt->close();
    
    // Commit transaction
    $conn->commit();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'message' => 'Order scheduled successfully!',
        'order_id' => $order_id
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>