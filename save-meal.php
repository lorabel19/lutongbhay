<?php
session_start();

// Check if user is logged in as seller
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'seller') {
    header('Content-Type: application/json');
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
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

$seller_id = $_SESSION['user_id'];
$response = ['success' => false, 'message' => ''];

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = isset($_POST['price']) ? floatval($_POST['price']) : 0;
    $category = trim($_POST['category'] ?? '');
    $availability = trim($_POST['availability'] ?? '');
    
    // Validate required fields
    if (empty($title) || empty($description) || empty($price) || empty($category) || empty($availability)) {
        $response['message'] = 'All fields are required';
        echo json_encode($response);
        exit();
    }
    
    if ($price <= 0) {
        $response['message'] = 'Price must be greater than 0';
        echo json_encode($response);
        exit();
    }
    
    // Validate category
    $validCategories = ['Main Dishes', 'Desserts', 'Merienda', 'Vegetarian', 'Holiday Specials'];
    if (!in_array($category, $validCategories)) {
        $response['message'] = 'Invalid category selected';
        echo json_encode($response);
        exit();
    }
    
    // Validate availability
    $validAvailability = ['Available', 'Not Available'];
    if (!in_array($availability, $validAvailability)) {
        $response['message'] = 'Invalid availability selected';
        echo json_encode($response);
        exit();
    }
    
    // Handle image upload
    $imagePath = '';
    if (isset($_FILES['meal_image']) && $_FILES['meal_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['meal_image'];
        
        // Validate file
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
        $maxSize = 2 * 1024 * 1024; // 2MB
        
        if (!in_array($file['type'], $allowedTypes)) {
            $response['message'] = 'Only JPG, PNG and GIF images are allowed';
            echo json_encode($response);
            exit();
        }
        
        if ($file['size'] > $maxSize) {
            $response['message'] = 'Image size should be less than 2MB';
            echo json_encode($response);
            exit();
        }
        
        // Generate unique filename
        $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $uniqueFilename = uniqid('meal_', true) . '.' . $fileExtension;
        $uploadDir = 'uploads/meals/';
        
        // Create directory if it doesn't exist
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $targetPath = $uploadDir . $uniqueFilename;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            $imagePath = $targetPath;
        } else {
            $response['message'] = 'Failed to upload image. Please try again.';
            echo json_encode($response);
            exit();
        }
    } else {
        $response['message'] = 'Please upload an image';
        echo json_encode($response);
        exit();
    }
    
    try {
        // Prepare SQL statement
        $sql = "INSERT INTO Meal (SellerID, Title, Description, Price, ImagePath, Availability, Category) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issdsss", $seller_id, $title, $description, $price, $imagePath, $availability, $category);
        
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Meal added successfully!';
            $response['meal_id'] = $stmt->insert_id;
        } else {
            $response['message'] = 'Failed to add meal: ' . $conn->error;
        }
        
        $stmt->close();
    } catch (Exception $e) {
        $response['message'] = 'Error: ' . $e->getMessage();
    }
} else {
    $response['message'] = 'Invalid request method';
}

$conn->close();
header('Content-Type: application/json');
echo json_encode($response);
?>