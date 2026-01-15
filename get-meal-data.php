<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'seller') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

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

$seller_id = $_SESSION['user_id'];
$meal_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($meal_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid meal ID']);
    exit();
}

$sql = "SELECT * FROM Meal WHERE MealID = ? AND SellerID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $meal_id, $seller_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $meal = $result->fetch_assoc();
    echo json_encode(['success' => true, 'meal' => $meal]);
} else {
    echo json_encode(['success' => false, 'message' => 'Meal not found']);
}

$stmt->close();
$conn->close();
?>