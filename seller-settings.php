<?php
session_start();

// Check if user is logged in (seller only)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'seller') {
    header('Location: index.php');
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
    die("Connection failed: " . $conn->connect_error);
}

// Get seller details
$seller_id = $_SESSION['user_id'];
$seller_sql = "SELECT * FROM Seller WHERE SellerID = ?";
$stmt = $conn->prepare($seller_sql);
if ($stmt === false) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$seller_result = $stmt->get_result();
$seller = $seller_result->fetch_assoc();
$stmt->close();

// Check if seller exists
if (!$seller) {
    session_destroy();
    header('Location: index.php');
    exit();
}

// Initialize seller variables
$seller_name = isset($seller['FullName']) ? $seller['FullName'] : 'Seller';
$seller_email = isset($seller['Email']) ? $seller['Email'] : '';
$seller_image = isset($seller['ImagePath']) ? $seller['ImagePath'] : '';
$initial = isset($seller['FullName']) ? strtoupper(substr($seller['FullName'], 0, 1)) : 'S';

// Check if Store table exists, if not create it
$check_table_sql = "SHOW TABLES LIKE 'Store'";
$table_result = $conn->query($check_table_sql);

if ($table_result->num_rows == 0) {
    // Create Store table if it doesn't exist
    $create_table_sql = "CREATE TABLE Store (
        StoreID INT AUTO_INCREMENT PRIMARY KEY,
        SellerID INT NOT NULL,
        StoreName VARCHAR(100) NOT NULL,
        Description TEXT,
        Category VARCHAR(50),
        OpeningTime TIME DEFAULT '08:00:00',
        ClosingTime TIME DEFAULT '20:00:00',
        DeliveryFee DECIMAL(10,2) DEFAULT 50.00,
        MinimumOrder DECIMAL(10,2) DEFAULT 100.00,
        PreparationTime INT DEFAULT 30,
        IsOpen TINYINT(1) DEFAULT 1,
        StoreImage VARCHAR(255),
        CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (SellerID) REFERENCES Seller(SellerID) ON DELETE CASCADE
    )";
    
    if (!$conn->query($create_table_sql)) {
        die("Error creating Store table: " . $conn->error);
    }
}

// Get store details
$store_sql = "SELECT * FROM Store WHERE SellerID = ?";
$stmt = $conn->prepare($store_sql);
if ($stmt) {
    $stmt->bind_param("i", $seller_id);
    $stmt->execute();
    $store_result = $stmt->get_result();
    $store = $store_result->fetch_assoc();
    $stmt->close();
} else {
    $store = null;
}

// Initialize store variables
$store_name = isset($store['StoreName']) ? $store['StoreName'] : ($seller_name . "'s Kitchen");
$store_description = isset($store['Description']) ? $store['Description'] : 'Delicious home-cooked meals made with love';
$store_category = isset($store['Category']) ? $store['Category'] : 'Filipino Cuisine';
$store_opening_time = isset($store['OpeningTime']) ? $store['OpeningTime'] : '08:00:00';
$store_closing_time = isset($store['ClosingTime']) ? $store['ClosingTime'] : '20:00:00';
$store_delivery_fee = isset($store['DeliveryFee']) ? $store['DeliveryFee'] : 50.00;
$store_min_order = isset($store['MinimumOrder']) ? $store['MinimumOrder'] : 100.00;
$store_preparation_time = isset($store['PreparationTime']) ? $store['PreparationTime'] : 30;
$store_is_open = isset($store['IsOpen']) ? $store['IsOpen'] : 1;
$store_image = isset($store['StoreImage']) ? $store['StoreImage'] : '';

// Get store categories for dropdown
$categories = ['Filipino Cuisine', 'Asian Fusion', 'Western', 'Desserts', 'Vegetarian', 'Fast Food', 'Healthy Options', 'Seafood', 'Grill & BBQ', 'Rice Meals'];

// Handle store settings update
$update_success = false;
$update_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_store_settings'])) {
    $store_name = trim($_POST['store_name']);
    $store_description = trim($_POST['store_description']);
    $store_category = trim($_POST['store_category']);
    $store_opening_time = $_POST['opening_time'];
    $store_closing_time = $_POST['closing_time'];
    $store_delivery_fee = floatval($_POST['delivery_fee']);
    $store_min_order = floatval($_POST['min_order']);
    $store_preparation_time = intval($_POST['preparation_time']);
    $store_is_open = isset($_POST['store_status']) ? 1 : 0;
    
    // Validation
    if (empty($store_name)) {
        $update_error = "Store name is required.";
    } elseif ($store_delivery_fee < 0) {
        $update_error = "Delivery fee cannot be negative.";
    } elseif ($store_min_order < 0) {
        $update_error = "Minimum order cannot be negative.";
    } elseif ($store_preparation_time < 5) {
        $update_error = "Preparation time must be at least 5 minutes.";
    } else {
        if ($store) {
            // Update existing store
            $update_sql = "UPDATE Store SET 
                           StoreName = ?, 
                           Description = ?, 
                           Category = ?, 
                           OpeningTime = ?, 
                           ClosingTime = ?, 
                           DeliveryFee = ?, 
                           MinimumOrder = ?, 
                           PreparationTime = ?, 
                           IsOpen = ?,
                           UpdatedAt = NOW()
                           WHERE SellerID = ?";
            $stmt = $conn->prepare($update_sql);
            if ($stmt) {
                $stmt->bind_param("sssssddiii", 
                    $store_name, 
                    $store_description, 
                    $store_category, 
                    $store_opening_time, 
                    $store_closing_time, 
                    $store_delivery_fee, 
                    $store_min_order, 
                    $store_preparation_time, 
                    $store_is_open,
                    $seller_id
                );
                
                if ($stmt->execute()) {
                    $update_success = true;
                } else {
                    $update_error = "Failed to update store settings. Please try again.";
                }
                $stmt->close();
            } else {
                $update_error = "Database error. Please try again.";
            }
        } else {
            // Create new store entry
            $insert_sql = "INSERT INTO Store (
                           SellerID, 
                           StoreName, 
                           Description, 
                           Category, 
                           OpeningTime, 
                           ClosingTime, 
                           DeliveryFee, 
                           MinimumOrder, 
                           PreparationTime, 
                           IsOpen
                           ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insert_sql);
            if ($stmt) {
                $stmt->bind_param("isssssddii", 
                    $seller_id,
                    $store_name, 
                    $store_description, 
                    $store_category, 
                    $store_opening_time, 
                    $store_closing_time, 
                    $store_delivery_fee, 
                    $store_min_order, 
                    $store_preparation_time, 
                    $store_is_open
                );
                
                if ($stmt->execute()) {
                    $update_success = true;
                    $store = ['StoreID' => $stmt->insert_id];
                } else {
                    $update_error = "Failed to create store settings. Please try again.";
                }
                $stmt->close();
            } else {
                $update_error = "Database error. Please try again.";
            }
        }
    }
}

// Handle store image upload
$image_success = false;
$image_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['store_image'])) {
    $target_dir = "uploads/store_images/";
    
    // Create directory if it doesn't exist
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $file_name = time() . '_' . basename($_FILES["store_image"]["name"]);
    $target_file = $target_dir . $file_name;
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    
    // Check if image file is actual image
    $check = getimagesize($_FILES["store_image"]["tmp_name"]);
    if ($check === false) {
        $image_error = "File is not an image.";
    }
    // Check file size (5MB limit)
    elseif ($_FILES["store_image"]["size"] > 5000000) {
        $image_error = "Sorry, your file is too large. Maximum size is 5MB.";
    }
    // Allow certain file formats
    elseif (!in_array($imageFileType, ['jpg', 'jpeg', 'png', 'gif'])) {
        $image_error = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
    }
    // Try to upload file
    elseif (move_uploaded_file($_FILES["store_image"]["tmp_name"], $target_file)) {
        // First, delete old image if exists
        if ($store_image && file_exists($store_image)) {
            unlink($store_image);
        }
        
        if ($store) {
            // Update database with new image path
            $update_image_sql = "UPDATE Store SET StoreImage = ? WHERE SellerID = ?";
            $stmt = $conn->prepare($update_image_sql);
            $stmt->bind_param("si", $target_file, $seller_id);
            
            if ($stmt->execute()) {
                $image_success = true;
                $store_image = $target_file;
            } else {
                $image_error = "Failed to update store image in database.";
            }
            $stmt->close();
        } else {
            // Create store entry with image
            $insert_sql = "INSERT INTO Store (SellerID, StoreName, StoreImage) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param("iss", $seller_id, $store_name, $target_file);
            
            if ($stmt->execute()) {
                $image_success = true;
                $store_image = $target_file;
                $store = ['StoreID' => $stmt->insert_id];
            } else {
                $image_error = "Failed to create store with image.";
            }
            $stmt->close();
        }
    } else {
        $image_error = "Sorry, there was an error uploading your file.";
    }
}

// Handle store image removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_store_image'])) {
    // Delete the image file if it exists
    if ($store_image && file_exists($store_image)) {
        unlink($store_image);
    }
    
    // Update database to remove image path
    $remove_image_sql = "UPDATE Store SET StoreImage = NULL WHERE SellerID = ?";
    $stmt = $conn->prepare($remove_image_sql);
    $stmt->bind_param("i", $seller_id);
    
    if ($stmt->execute()) {
        $image_success = true;
        $store_image = '';
    } else {
        $image_error = "Failed to remove store image from database.";
    }
    $stmt->close();
}

// Get pending orders count
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

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store Settings | LutongBahay</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #e63946;
            --primary-dark: #c1121f;
            --secondary: #f4a261;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --light-gray: #e9ecef;
            --warning: #e9c46a;
            --danger: #e63946;
            --success: #2a9d8f;
            --shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #fefefe;
            color: var(--dark);
            line-height: 1.6;
        }
        
        /* Header & Navigation */
        header {
            background-color: white;
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 1000;
            width: 100%;
        }
        
        .container {
            width: 90%;
            max-width: 1600px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .nav-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary);
            text-decoration: none;
        }
        
        .logo i {
            font-size: 2rem;
        }
        
        .nav-links {
            display: flex;
            gap: 25px;
        }

        .nav-links a {
            color: var(--dark);
            text-decoration: none;
            font-weight: 600;
            font-size: 1rem;
            transition: var(--transition);
            position: relative;
            padding: 3px 0;
        }

        .nav-links a::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 0;
            height: 3px;
            background-color: var(--primary);
            border-radius: 3px;
            transition: width 0.3s ease;
        }

        .nav-links a:hover {
            color: var(--primary);
        }

        .nav-links a:hover::after {
            width: 100%;
        }

        .nav-links a.active {
            color: var(--primary);
        }

        .nav-links a.active::after {
            width: 100%;
        }
        
        .user-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        /* Notification Badge */
        .notification-badge {
            position: relative;
            text-decoration: none;
            display: inline-block;
            transition: var(--transition);
            padding: 8px;
            border-radius: 50%;
            cursor: pointer;
        }
        
        .notification-badge:hover {
            background-color: rgba(230, 57, 70, 0.1);
            transform: translateY(-2px);
        }
        
        .notification-icon-bell {
            position: relative;
            font-size: 1.5rem;
            color: var(--dark);
        }
        
        .badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: var(--primary);
            color: white;
            font-size: 0.8rem;
            min-width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-weight: 600;
            padding: 0 4px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(230, 57, 70, 0.7);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(230, 57, 70, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(230, 57, 70, 0);
            }
        }
        
        /* Profile Dropdown */
        .profile-dropdown {
            position: relative;
        }

        .user-profile {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background-color: var(--primary);
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            overflow: hidden;
            color: white;
            font-weight: 700;
            font-size: 1.1rem;
            transition: var(--transition);
        }

        .user-profile:hover {
            background-color: var(--primary-dark);
            transform: scale(1.05);
        }

        .dropdown-menu {
            position: absolute;
            top: 55px;
            right: 0;
            width: 280px;
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            z-index: 1001;
            display: none;
            animation: fadeIn 0.2s ease;
            overflow: hidden;
        }

        .dropdown-menu.show {
            display: block;
        }

        .dropdown-header {
            padding: 20px;
            background-color: var(--light-gray);
            border-bottom: 1px solid #eee;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-initial {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background-color: var(--primary);
            display: flex;
            justify-content: center;
            align-items: center;
            color: white;
            font-weight: 700;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .user-details {
            overflow: hidden;
        }

        .user-name {
            font-weight: 700;
            color: var(--dark);
            font-size: 1rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-email {
            font-size: 0.9rem;
            color: var(--gray);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .dropdown-divider {
            height: 1px;
            background-color: #eee;
            margin: 8px 0;
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 20px;
            color: var(--dark);
            text-decoration: none;
            transition: var(--transition);
            font-size: 1rem;
            font-weight: 500;
        }

        .dropdown-item:hover {
            background-color: var(--light-gray);
            color: var(--primary);
        }

        .dropdown-item i {
            width: 20px;
            text-align: center;
            color: var(--gray);
            font-size: 1.1rem;
        }

        .dropdown-item:hover i {
            color: var(--primary);
        }

        .dropdown-item.logout {
            color: var(--primary);
        }

        .dropdown-item.logout i {
            color: var(--primary);
        }

        .dropdown-item.logout:hover {
            background-color: rgba(230, 57, 70, 0.1);
        }
        
        /* Notification Modal */
        .notification-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 3000;
            display: flex;
            justify-content: center;
            align-items: center;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        
        .notification-modal.show {
            opacity: 1;
            visibility: visible;
        }
        
        .notification-content {
            background-color: white;
            width: 90%;
            max-width: 800px;
            max-height: 80vh;
            border-radius: 20px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            transform: translateY(30px);
            transition: transform 0.3s ease;
        }
        
        .notification-modal.show .notification-content {
            transform: translateY(0);
        }
        
        .notification-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 25px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .notification-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .notification-header h2 i {
            font-size: 1.3rem;
        }
        
        .close-notification {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
        }
        
        .close-notification:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }
        
        .notification-body {
            padding: 0;
            max-height: 60vh;
            overflow-y: auto;
        }
        
        .notification-empty {
            padding: 60px 30px;
            text-align: center;
            color: var(--gray);
        }
        
        .notification-empty i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        
        .notification-empty h3 {
            font-size: 1.5rem;
            margin-bottom: 10px;
            color: var(--dark);
        }
        
        .notification-empty p {
            color: var(--gray);
        }
        
        /* Store Settings Container */
        .settings-container {
            padding: 40px 0;
        }
        
        .settings-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .settings-header h1 {
            font-size: 2.5rem;
            color: var(--dark);
            margin-bottom: 10px;
            font-weight: 800;
        }
        
        .settings-header p {
            color: var(--gray);
            font-size: 1.1rem;
        }
        
        .settings-content {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        /* Settings Grid */
        .settings-grid {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 40px;
        }
        
        @media (max-width: 992px) {
            .settings-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Settings Sidebar */
        .settings-sidebar {
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow);
            padding: 30px;
            height: fit-content;
            position: sticky;
            top: 100px;
        }
        
        .store-image-section {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .store-image {
            width: 200px;
            height: 200px;
            border-radius: 20px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 4rem;
            font-weight: 700;
            border: 5px solid white;
            box-shadow: 0 0 0 5px rgba(230, 57, 70, 0.1);
            overflow: hidden;
            position: relative;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .store-image:hover {
            transform: scale(1.05);
        }
        
        .store-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            position: absolute;
            top: 0;
            left: 0;
        }
        
        .store-image .initial {
            position: absolute;
            z-index: 1;
        }
        
        .image-upload {
            display: none;
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 10px;
            font-size: 0.9rem;
            z-index: 2;
        }
        
        .store-image:hover .image-upload {
            display: block;
        }
        
        .image-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .btn-image {
            padding: 12px 20px;
            border-radius: 10px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-image-primary {
            background-color: var(--primary);
            color: white;
        }
        
        .btn-image-primary:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        .btn-image-secondary {
            background-color: var(--light-gray);
            color: var(--dark);
        }
        
        .btn-image-secondary:hover {
            background-color: #ddd;
            transform: translateY(-2px);
        }
        
        .store-status {
            margin-top: 30px;
            border-top: 1px solid var(--light-gray);
            padding-top: 30px;
        }
        
        .store-status h3 {
            font-size: 1.2rem;
            margin-bottom: 20px;
            color: var(--dark);
            font-weight: 700;
        }
        
        .status-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .status-item:last-child {
            border-bottom: none;
        }
        
        .status-label {
            color: var(--gray);
            font-size: 0.95rem;
        }
        
        .status-value {
            font-weight: 600;
            color: var(--dark);
        }
        
        .status-open {
            color: var(--success);
        }
        
        .status-closed {
            color: var(--danger);
        }
        
        /* Settings Main Content */
        .settings-main {
            display: flex;
            flex-direction: column;
            gap: 40px;
        }
        
        .settings-section {
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow);
            padding: 30px;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .section-header h2 {
            font-size: 1.5rem;
            color: var(--dark);
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-header h2 i {
            color: var(--primary);
            font-size: 1.3rem;
        }
        
        /* Form Styles */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-group label {
            font-size: 0.95rem;
            color: var(--dark);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .form-group label i {
            color: var(--primary);
            font-size: 0.9rem;
        }
        
        .form-group .required {
            color: var(--primary);
            font-weight: 700;
        }
        
        .form-control {
            padding: 14px 15px;
            border: 2px solid var(--light-gray);
            border-radius: 10px;
            font-size: 1rem;
            color: var(--dark);
            transition: var(--transition);
            background-color: white;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(230, 57, 70, 0.1);
        }
        
        .form-control::placeholder {
            color: #aaa;
        }
        
        select.form-control {
            cursor: pointer;
        }
        
        .form-text {
            font-size: 0.85rem;
            color: var(--gray);
            margin-top: 5px;
        }
        
        /* Toggle Switch */
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 30px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .toggle-slider {
            background-color: var(--success);
        }
        
        input:checked + .toggle-slider:before {
            transform: translateX(30px);
        }
        
        .toggle-label {
            display: flex;
            align-items: center;
            gap: 15px;
            cursor: pointer;
        }
        
        /* Alerts */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: fadeIn 0.3s ease;
        }
        
        .alert-success {
            background-color: rgba(42, 157, 143, 0.1);
            border: 1px solid rgba(42, 157, 143, 0.2);
            color: var(--success);
        }
        
        .alert-danger {
            background-color: rgba(230, 57, 70, 0.1);
            border: 1px solid rgba(230, 57, 70, 0.2);
            color: var(--danger);
        }
        
        .alert i {
            font-size: 1.2rem;
        }
        
        /* Buttons */
        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 30px;
        }
        
        .btn {
            padding: 14px 30px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background-color: var(--light-gray);
            color: var(--dark);
        }
        
        .btn-secondary:hover {
            background-color: #ddd;
            transform: translateY(-2px);
        }
        
        /* Upload Modal */
        .upload-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 3000;
            display: flex;
            justify-content: center;
            align-items: center;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        
        .upload-modal.show {
            opacity: 1;
            visibility: visible;
        }
        
        .upload-content {
            background-color: white;
            width: 90%;
            max-width: 500px;
            border-radius: 20px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            transform: translateY(30px);
            transition: transform 0.3s ease;
        }
        
        .upload-modal.show .upload-content {
            transform: translateY(0);
        }
        
        .upload-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 25px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .upload-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .close-upload {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
        }
        
        .close-upload:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }
        
        .upload-body {
            padding: 30px;
        }
        
        .preview-container {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .image-preview {
            width: 200px;
            height: 200px;
            border-radius: 20px;
            margin: 0 auto 20px;
            overflow: hidden;
            border: 3px solid var(--light-gray);
        }
        
        .image-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .upload-actions {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .file-input {
            position: relative;
        }
        
        .file-input input[type="file"] {
            position: absolute;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }
        
        .file-label {
            display: block;
            padding: 15px;
            background-color: var(--light-gray);
            border: 2px dashed var(--gray);
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .file-label:hover {
            background-color: #ddd;
            border-color: var(--primary);
        }
        
        .file-label i {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 10px;
        }
        
        .file-label span {
            display: block;
            font-weight: 600;
            color: var(--dark);
        }
        
        .file-label small {
            display: block;
            color: var(--gray);
            font-size: 0.9rem;
        }
        
        /* Footer */
        footer {
            background-color: var(--dark);
            color: white;
            padding: 60px 0 30px;
            margin-top: 60px;
        }
        
        .footer-content {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 40px;
            margin-bottom: 40px;
        }
        
        .footer-logo {
            font-size: 1.8rem;
            font-weight: 700;
            color: white;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .footer-logo i {
            font-size: 2rem;
        }
        
        .footer-content > div:first-child p {
            font-size: 1rem;
            line-height: 1.7;
        }
        
        .footer-links h3 {
            margin-bottom: 20px;
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .footer-links ul {
            list-style: none;
        }
        
        .footer-links li {
            margin-bottom: 12px;
        }
        
        .footer-links a {
            color: #aaa;
            text-decoration: none;
            transition: var(--transition);
            font-size: 1rem;
        }
        
        .footer-links a:hover {
            color: white;
            padding-left: 5px;
        }
        
        .copyright {
            text-align: center;
            padding-top: 30px;
            border-top: 1px solid #444;
            color: #aaa;
            font-size: 0.9rem;
        }
        
        /* Animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Responsive Design */
        @media (max-width: 1200px) {
            .footer-content {
                grid-template-columns: repeat(2, 1fr);
                gap: 30px;
            }
        }
        
        @media (max-width: 992px) {
            .nav-container {
                flex-direction: column;
                gap: 15px;
            }
            
            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
                gap: 15px;
            }
            
            .user-actions {
                margin-top: 10px;
            }
            
            .settings-header h1 {
                font-size: 2rem;
            }
        }
        
        @media (max-width: 768px) {
            .settings-section {
                padding: 20px;
            }
            
            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .upload-content {
                width: 95%;
            }
        }
        
        @media (max-width: 576px) {
            .container {
                width: 95%;
                padding: 0 15px;
            }
            
            .footer-content {
                grid-template-columns: 1fr;
                gap: 30px;
            }
            
            .store-image {
                width: 150px;
                height: 150px;
                font-size: 3rem;
            }
        }
    </style>
</head>
<body>
<header>
    <div class="container">
        <div class="nav-container">
            <a href="seller-homepage.php" class="logo">
                <i class="fas fa-store"></i>
                LutongBahay Seller
            </a>
            
            <div class="nav-links">
                <a href="seller-homepage.php">Dashboard</a>
                <a href="manage-meals.php">Manage Meals</a>
                <a href="seller-orders.php">Orders</a>
                <a href="seller-sales.php">Sales Report</a>
            </div>
            
            <div class="user-actions">
                <!-- Notification badge for pending orders -->
                <div class="notification-badge" id="notificationToggle">
                    <div class="notification-icon-bell">
                        <i class="fas fa-bell"></i>
                        <?php if ($pending_count > 0): ?>
                            <span class="badge" id="pendingBadge"><?php echo $pending_count; ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Profile dropdown -->
                <div class="profile-dropdown">
                    <div class="user-profile" id="profileToggle">
                        <?php if ($seller_image): ?>
                            <img src="<?php echo htmlspecialchars($seller_image); ?>" alt="<?php echo htmlspecialchars($seller_name); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <?php echo $initial; ?>
                        <?php endif; ?>
                    </div>
                    <div class="dropdown-menu" id="dropdownMenu">
                        <div class="dropdown-header">
                            <div class="user-info">
                                <div class="user-initial">
                                    <?php if ($seller_image): ?>
                                        <img src="<?php echo htmlspecialchars($seller_image); ?>" alt="<?php echo htmlspecialchars($seller_name); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <?php echo $initial; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="user-details">
                                    <div class="user-name"><?php echo htmlspecialchars($seller_name); ?></div>
                                    <div class="user-email"><?php echo htmlspecialchars($seller_email); ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a href="seller-profile.php" class="dropdown-item">
                            <i class="fas fa-user"></i> Seller Profile
                        </a>
                        <a href="seller-settings.php" class="dropdown-item">
                            <i class="fas fa-cog"></i> Store Settings
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="#" class="dropdown-item logout" id="logoutLink">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

<!-- Notification Modal -->
<div class="notification-modal" id="notificationModal">
    <div class="notification-content">
        <div class="notification-header">
            <h2><i class="fas fa-bell"></i> Pending Orders (<?php echo $pending_count; ?>)</h2>
            <button class="close-notification" id="closeNotification">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="notification-body">
            <div class="notification-empty">
                <i class="fas fa-bell-slash"></i>
                <h3>No Pending Orders</h3>
                <p>You're all caught up! There are no pending orders at the moment.</p>
            </div>
        </div>
    </div>
</div>

<!-- Store Settings Content -->
<div class="container settings-container">
    <div class="settings-header">
        <h1>Store Settings</h1>
        <p>Configure your store details, operating hours, and delivery settings</p>
    </div>
    
    <?php if ($update_success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            Store settings have been updated successfully!
        </div>
    <?php endif; ?>
    
    <?php if ($update_error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($update_error); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($image_success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            Store image updated successfully!
        </div>
    <?php endif; ?>
    
    <?php if ($image_error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($image_error); ?>
        </div>
    <?php endif; ?>
    
    <div class="settings-content">
        <div class="settings-grid">
            <!-- Store Sidebar -->
            <div class="settings-sidebar">
                <div class="store-image-section">
                    <div class="store-image" id="storeImage">
                        <?php if ($store_image): ?>
                            <img src="<?php echo htmlspecialchars($store_image); ?>" alt="<?php echo htmlspecialchars($store_name); ?>">
                            <div class="initial" style="display: none;"><?php echo strtoupper(substr($store_name, 0, 1)); ?></div>
                        <?php else: ?>
                            <div class="initial"><?php echo strtoupper(substr($store_name, 0, 1)); ?></div>
                        <?php endif; ?>
                        <div class="image-upload">
                            <i class="fas fa-camera"></i> Click to Change
                        </div>
                    </div>
                    <h3 style="text-align: center; margin-bottom: 10px; color: var(--dark);"><?php echo htmlspecialchars($store_name); ?></h3>
                    <p style="text-align: center; color: var(--gray); font-size: 0.9rem;"><?php echo htmlspecialchars($store_category); ?></p>
                    <div class="image-actions" style="margin-top: 20px;">
                        <button class="btn-image btn-image-primary" onclick="showImageUploadModal()">
                            <i class="fas fa-camera"></i> Change Store Image
                        </button>
                        <?php if ($store_image): ?>
                        <form method="POST" style="width: 100%;" onsubmit="return confirm('Are you sure you want to remove your store image?');">
                            <button type="submit" name="remove_store_image" class="btn-image btn-image-secondary" style="width: 100%;">
                                <i class="fas fa-trash"></i> Remove Image
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="store-status">
                    <h3>Store Status</h3>
                    <div class="status-item">
                        <span class="status-label">Current Status</span>
                        <span class="status-value <?php echo $store_is_open ? 'status-open' : 'status-closed'; ?>">
                            <?php echo $store_is_open ? '<i class="fas fa-check-circle"></i> Open' : '<i class="fas fa-times-circle"></i> Closed'; ?>
                        </span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">Operating Hours</span>
                        <span class="status-value"><?php echo date('g:i A', strtotime($store_opening_time)); ?> - <?php echo date('g:i A', strtotime($store_closing_time)); ?></span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">Delivery Fee</span>
                        <span class="status-value">₱<?php echo number_format($store_delivery_fee, 2); ?></span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">Minimum Order</span>
                        <span class="status-value">₱<?php echo number_format($store_min_order, 2); ?></span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">Prep Time</span>
                        <span class="status-value"><?php echo $store_preparation_time; ?> mins</span>
                    </div>
                </div>
            </div>
            
            <!-- Store Main Content -->
            <div class="settings-main">
                <!-- Store Information -->
                <div class="settings-section">
                    <div class="section-header">
                        <h2><i class="fas fa-store"></i> Store Information</h2>
                    </div>
                    
                    <form method="POST">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="store_name"><i class="fas fa-signature"></i> Store Name <span class="required">*</span></label>
                                <input type="text" id="store_name" name="store_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($store_name); ?>" required>
                                <div class="form-text">The name customers will see</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="store_category"><i class="fas fa-tag"></i> Store Category <span class="required">*</span></label>
                                <select id="store_category" name="store_category" class="form-control" required>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo htmlspecialchars($category); ?>" <?php echo ($category == $store_category) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Select your store's primary category</div>
                            </div>
                            
                            <div class="form-group full-width">
                                <label for="store_description"><i class="fas fa-align-left"></i> Store Description</label>
                                <textarea id="store_description" name="store_description" class="form-control" 
                                          rows="4"><?php echo htmlspecialchars($store_description); ?></textarea>
                                <div class="form-text">Describe your store and what makes it special</div>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="update_store_settings" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Store Information
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Operating Hours -->
                <div class="settings-section">
                    <div class="section-header">
                        <h2><i class="fas fa-clock"></i> Operating Hours</h2>
                    </div>
                    
                    <form method="POST">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="opening_time"><i class="fas fa-door-open"></i> Opening Time <span class="required">*</span></label>
                                <input type="time" id="opening_time" name="opening_time" class="form-control" 
                                       value="<?php echo date('H:i', strtotime($store_opening_time)); ?>" required>
                                <div class="form-text">When your store opens for orders</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="closing_time"><i class="fas fa-door-closed"></i> Closing Time <span class="required">*</span></label>
                                <input type="time" id="closing_time" name="closing_time" class="form-control" 
                                       value="<?php echo date('H:i', strtotime($store_closing_time)); ?>" required>
                                <div class="form-text">When your store stops accepting orders</div>
                            </div>
                            
                            <div class="form-group full-width">
                                <label class="toggle-label">
                                    <span class="status-label">Store Status</span>
                                    <div class="toggle-switch">
                                        <input type="checkbox" id="store_status" name="store_status" <?php echo $store_is_open ? 'checked' : ''; ?>>
                                        <span class="toggle-slider"></span>
                                    </div>
                                    <span class="status-value"><?php echo $store_is_open ? 'Store is Open' : 'Store is Closed'; ?></span>
                                </label>
                                <div class="form-text">Toggle to open or close your store for orders</div>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="update_store_settings" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Operating Hours
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Delivery & Order Settings -->
                <div class="settings-section">
                    <div class="section-header">
                        <h2><i class="fas fa-shipping-fast"></i> Delivery & Order Settings</h2>
                    </div>
                    
                    <form method="POST">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="delivery_fee"><i class="fas fa-truck"></i> Delivery Fee <span class="required">*</span></label>
                                <div style="position: relative;">
                                    <span style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--gray); font-weight: 600;">₱</span>
                                    <input type="number" id="delivery_fee" name="delivery_fee" class="form-control" 
                                           value="<?php echo number_format($store_delivery_fee, 2); ?>" min="0" step="0.01" required
                                           style="padding-left: 35px;">
                                </div>
                                <div class="form-text">Fee charged for delivery (0 for free delivery)</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="min_order"><i class="fas fa-shopping-cart"></i> Minimum Order <span class="required">*</span></label>
                                <div style="position: relative;">
                                    <span style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--gray); font-weight: 600;">₱</span>
                                    <input type="number" id="min_order" name="min_order" class="form-control" 
                                           value="<?php echo number_format($store_min_order, 2); ?>" min="0" step="0.01" required
                                           style="padding-left: 35px;">
                                </div>
                                <div class="form-text">Minimum order amount for delivery</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="preparation_time"><i class="fas fa-clock"></i> Preparation Time <span class="required">*</span></label>
                                <div style="position: relative;">
                                    <input type="number" id="preparation_time" name="preparation_time" class="form-control" 
                                           value="<?php echo $store_preparation_time; ?>" min="5" max="180" required
                                           style="padding-right: 60px;">
                                    <span style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); color: var(--gray); font-weight: 600;">minutes</span>
                                </div>
                                <div class="form-text">Average time to prepare orders (5-180 minutes)</div>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="update_store_settings" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Delivery Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Store Image Upload Modal -->
<div class="upload-modal" id="imageUploadModal">
    <div class="upload-content">
        <div class="upload-header">
            <h2><i class="fas fa-camera"></i> Update Store Image</h2>
            <button class="close-upload" id="closeImageUpload">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="imageUploadForm" method="POST" enctype="multipart/form-data">
            <div class="upload-body">
                <div class="preview-container">
                    <div class="image-preview" id="storeImagePreview">
                        <?php if ($store_image): ?>
                            <img src="<?php echo htmlspecialchars($store_image); ?>" alt="Current Store Image" id="storePreviewImage">
                        <?php else: ?>
                            <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: var(--light-gray); color: var(--gray); font-size: 4rem; font-weight: 700;">
                                <?php echo strtoupper(substr($store_name, 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <p style="color: var(--gray); font-size: 0.9rem;">Preview of your new store image</p>
                </div>
                
                <div class="upload-actions">
                    <div class="file-input">
                        <input type="file" id="store_image" name="store_image" accept="image/*" onchange="previewStoreImage(event)">
                        <label for="store_image" class="file-label">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <span>Choose a new store image</span>
                            <small>JPG, PNG or GIF (Max 5MB)</small>
                        </label>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeImageUploadModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload"></i> Upload Image
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Footer -->
<footer>
    <div class="container">
        <div class="footer-content">
            <div>
                <div class="footer-logo">
                    <i class="fas fa-store"></i>
                    LutongBahay Seller
                </div>
                <p>Empowering Filipino home cooks and small food entrepreneurs to grow their businesses online since 2024.</p>
            </div>
            
            <div class="footer-links">
                <h3>Seller Dashboard</h3>
                <ul>
                    <li><a href="seller-homepage.php">Dashboard</a></li>
                    <li><a href="manage-meals.php">Manage Meals</a></li>
                    <li><a href="seller-orders.php">Orders</a></li>
                    <li><a href="seller-sales.php">Sales Report</a></li>
                </ul>
            </div>
            
            <div class="footer-links">
                <h3>Seller Resources</h3>
                <ul>
                    <li><a href="seller-guide.php">Seller Guide</a></li>
                    <li><a href="pricing.php">Pricing</a></li>
                    <li><a href="seller-support.php">Support Center</a></li>
                    <li><a href="seller-faq.php">FAQs</a></li>
                </ul>
            </div>
            
            <div class="footer-links">
                <h3>Company</h3>
                <ul>
                    <li><a href="about.php">About Us</a></li>
                    <li><a href="contact.php">Contact</a></li>
                    <li><a href="privacy.php">Privacy Policy</a></li>
                    <li><a href="terms.php">Terms of Service</a></li>
                </ul>
            </div>
        </div>
        
        <div class="copyright">
            &copy; 2026 LutongBahay Seller Portal. Polytechnic University of the Philippines - Parañaque City Campus. All rights reserved.
        </div>
    </div>
</footer>

<script>
    // DOM Elements
    const profileToggle = document.getElementById('profileToggle');
    const dropdownMenu = document.getElementById('dropdownMenu');
    const logoutLink = document.getElementById('logoutLink');
    const notificationToggle = document.getElementById('notificationToggle');
    const notificationModal = document.getElementById('notificationModal');
    const closeNotification = document.getElementById('closeNotification');
    const storeImage = document.getElementById('storeImage');
    const imageUploadModal = document.getElementById('imageUploadModal');
    const closeImageUpload = document.getElementById('closeImageUpload');
    const imageUploadForm = document.getElementById('imageUploadForm');
    const storeStatusCheckbox = document.getElementById('store_status');

    // Toggle profile dropdown
    if (profileToggle && dropdownMenu) {
        profileToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            dropdownMenu.classList.toggle('show');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!profileToggle.contains(e.target) && !dropdownMenu.contains(e.target)) {
                dropdownMenu.classList.remove('show');
            }
        });

        // Close dropdown when clicking on a dropdown item
        dropdownMenu.addEventListener('click', function(e) {
            if (e.target.closest('.dropdown-item')) {
                setTimeout(() => {
                    dropdownMenu.classList.remove('show');
                }, 200);
            }
        });
    }

    // Toggle notification modal
    if (notificationToggle && notificationModal) {
        notificationToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            notificationModal.classList.add('show');
            document.body.style.overflow = 'hidden';
        });

        // Close notification modal
        if (closeNotification) {
            closeNotification.addEventListener('click', function() {
                notificationModal.classList.remove('show');
                document.body.style.overflow = 'auto';
            });
        }

        // Close notification modal when clicking outside
        notificationModal.addEventListener('click', function(e) {
            if (e.target === notificationModal) {
                notificationModal.classList.remove('show');
                document.body.style.overflow = 'auto';
            }
        });
    }

    // Store image click to show upload modal
    if (storeImage) {
        storeImage.addEventListener('click', function(e) {
            if (!e.target.closest('.btn-image')) {
                showImageUploadModal();
            }
        });
    }

    // Image upload modal functionality
    function showImageUploadModal() {
        if (imageUploadModal) {
            imageUploadModal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }
    }

    function closeImageUploadModal() {
        if (imageUploadModal) {
            imageUploadModal.classList.remove('show');
            document.body.style.overflow = 'auto';
        }
    }

    if (closeImageUpload) {
        closeImageUpload.addEventListener('click', closeImageUploadModal);
    }

    // Close image upload modal when clicking outside
    if (imageUploadModal) {
        imageUploadModal.addEventListener('click', function(e) {
            if (e.target === imageUploadModal) {
                closeImageUploadModal();
            }
        });
    }

    // Store image preview functionality
    function previewStoreImage(event) {
        const input = event.target;
        const preview = document.getElementById('storePreviewImage');
        const previewContainer = document.getElementById('storeImagePreview');
        
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                if (!preview) {
                    // Create new image element if it doesn't exist
                    const img = document.createElement('img');
                    img.id = 'storePreviewImage';
                    img.src = e.target.result;
                    img.alt = 'Preview';
                    previewContainer.innerHTML = '';
                    previewContainer.appendChild(img);
                } else {
                    preview.src = e.target.result;
                }
            };
            
            reader.readAsDataURL(input.files[0]);
        }
    }

    // Show notification
    function showNotification(message, type = 'success') {
        const notification = document.createElement('div');
        notification.textContent = message;
        
        let bgColor, icon;
        switch(type) {
            case 'success':
                bgColor = 'var(--success)';
                icon = 'fas fa-check-circle';
                break;
            case 'error':
                bgColor = 'var(--danger)';
                icon = 'fas fa-exclamation-circle';
                break;
            case 'warning':
                bgColor = 'var(--warning)';
                icon = 'fas fa-exclamation-triangle';
                break;
            case 'info':
                bgColor = 'var(--primary)';
                icon = 'fas fa-info-circle';
                break;
            default:
                bgColor = 'var(--primary)';
                icon = 'fas fa-info-circle';
        }
        
        notification.innerHTML = `
            <i class="${icon}" style="margin-right: 10px;"></i>
            ${message}
        `;
        
        notification.style.cssText = `
            position: fixed;
            top: 30px;
            right: 30px;
            background-color: ${bgColor};
            color: white;
            padding: 15px 25px;
            border-radius: 10px;
            box-shadow: var(--shadow);
            z-index: 3000;
            animation: fadeIn 0.3s ease;
            font-size: 1rem;
            font-weight: 600;
            max-width: 300px;
            display: flex;
            align-items: center;
        `;
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.animation = 'fadeIn 0.3s ease reverse';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }, 3000);
    }

    // Logout confirmation
    if (logoutLink) {
        logoutLink.addEventListener('click', function(e) {
            e.preventDefault();
            
            const confirmModal = document.createElement('div');
            confirmModal.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0, 0, 0, 0.5);
                z-index: 3000;
                display: flex;
                justify-content: center;
                align-items: center;
                animation: fadeIn 0.3s ease;
            `;
            
            const modalContent = document.createElement('div');
            modalContent.style.cssText = `
                background-color: white;
                padding: 40px;
                border-radius: 15px;
                width: 90%;
                max-width: 400px;
                box-shadow: var(--shadow);
                text-align: center;
            `;
            
            modalContent.innerHTML = `
                <div style="margin-bottom: 25px;">
                    <div style="font-size: 3rem; color: var(--primary); margin-bottom: 15px;">
                        <i class="fas fa-sign-out-alt"></i>
                    </div>
                    <h3 style="font-size: 1.5rem; color: var(--dark); margin-bottom: 10px; font-weight: 700;">
                        Confirm Logout
                    </h3>
                    <p style="color: var(--gray); font-size: 1rem;">
                        Are you sure you want to logout from Seller Dashboard?
                    </p>
                </div>
                <div style="display: flex; gap: 15px; justify-content: center;">
                    <button id="cancelLogout" style="padding: 12px 30px; background-color: var(--light-gray); border: none; border-radius: 50px; font-weight: 600; cursor: pointer; transition: var(--transition); color: var(--dark);">
                        Cancel
                    </button>
                    <button id="confirmLogout" style="padding: 12px 30px; background-color: var(--primary); border: none; border-radius: 50px; font-weight: 600; cursor: pointer; transition: var(--transition); color: white;">
                        Yes, Logout
                    </button>
                </div>
            `;
            
            confirmModal.appendChild(modalContent);
            document.body.appendChild(confirmModal);
            
            // Close dropdown when logout is clicked
            if (dropdownMenu) {
                dropdownMenu.classList.remove('show');
            }
            
            // Cancel logout
            document.getElementById('cancelLogout').addEventListener('click', function() {
                document.body.removeChild(confirmModal);
            });
            
            // Confirm logout
            document.getElementById('confirmLogout').addEventListener('click', function() {
                window.location.href = 'logout.php';
            });
            
            // Close modal when clicking outside
            confirmModal.addEventListener('click', function(e) {
                if (e.target === confirmModal) {
                    document.body.removeChild(confirmModal);
                }
            });
        });
    }

    // Auto-refresh notification badge every 30 seconds
    function refreshNotificationBadge() {
        fetch('check-pending-orders.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const pendingCount = data.pending_count;
                    let badge = document.getElementById('pendingBadge');
                    
                    // Update or create badge
                    if (pendingCount > 0) {
                        if (badge) {
                            badge.textContent = pendingCount;
                        } else {
                            badge = document.createElement('span');
                            badge.id = 'pendingBadge';
                            badge.className = 'badge';
                            badge.textContent = pendingCount;
                            document.querySelector('.notification-icon-bell').appendChild(badge);
                        }
                        
                        // Add animation if badge wasn't there before
                        if (!badge.style.animation) {
                            badge.style.animation = 'pulse 2s infinite';
                            setTimeout(() => {
                                badge.style.animation = '';
                            }, 3000);
                        }
                        
                        // Update modal title if modal is open
                        const modalTitle = document.querySelector('.notification-header h2');
                        if (modalTitle) {
                            modalTitle.innerHTML = `<i class="fas fa-bell"></i> Pending Orders (${pendingCount})`;
                        }
                    } else {
                        // Remove badge if no pending orders
                        if (badge) {
                            badge.remove();
                        }
                        
                        // Update modal title
                        const modalTitle = document.querySelector('.notification-header h2');
                        if (modalTitle) {
                            modalTitle.innerHTML = `<i class="fas fa-bell"></i> Pending Orders (0)`;
                        }
                    }
                }
            })
            .catch(error => console.error('Error refreshing notifications:', error));
    }

    // Start auto-refresh if on profile page with notification bell
    if (document.querySelector('.notification-icon-bell')) {
        setInterval(refreshNotificationBadge, 30000); // Refresh every 30 seconds
    }

    // Animate pending badge when there are new orders
    const pendingBadge = document.getElementById('pendingBadge');
    if (pendingBadge) {
        pendingBadge.style.animation = 'pulse 2s infinite';
        setTimeout(() => {
            pendingBadge.style.animation = '';
        }, 3000);
    }

    // Auto-hide alerts after 5 seconds
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.5s ease';
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.parentNode.removeChild(alert);
                }
            }, 500);
        });
    }, 5000);

    // Form validation for image upload
    if (imageUploadForm) {
        imageUploadForm.addEventListener('submit', function(e) {
            const fileInput = document.getElementById('store_image');
            if (fileInput.files.length === 0) {
                e.preventDefault();
                showNotification('Please select an image to upload', 'error');
                return false;
            }
            
            const file = fileInput.files[0];
            const maxSize = 5 * 1024 * 1024; // 5MB
            
            if (file.size > maxSize) {
                e.preventDefault();
                showNotification('File size must be less than 5MB', 'error');
                return false;
            }
            
            const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            if (!validTypes.includes(file.type)) {
                e.preventDefault();
                showNotification('Only JPG, PNG, and GIF files are allowed', 'error');
                return false;
            }
            
            return true;
        });
    }

    // Toggle store status text
    if (storeStatusCheckbox) {
        // Update status text on page load
        updateStoreStatusText();
        
        // Update status text when checkbox changes
        storeStatusCheckbox.addEventListener('change', updateStoreStatusText);
    }

    function updateStoreStatusText() {
        const statusValue = storeStatusCheckbox.parentElement.nextElementSibling;
        if (storeStatusCheckbox.checked) {
            statusValue.textContent = 'Store is Open';
            statusValue.style.color = 'var(--success)';
        } else {
            statusValue.textContent = 'Store is Closed';
            statusValue.style.color = 'var(--danger)';
        }
    }

    // Format currency inputs
    const currencyInputs = document.querySelectorAll('input[type="number"]');
    currencyInputs.forEach(input => {
        input.addEventListener('blur', function() {
            if (this.value !== '') {
                this.value = parseFloat(this.value).toFixed(2);
            }
        });
    });
</script>

</body>
</html>