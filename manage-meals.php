<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'seller') {
    header('Location: index.php');
    exit();
}

$servername = "localhost";
$username = "root";
$password = "1019";
$dbname = "lutongbahay_db";
$port = 3306;

$conn = new mysqli($servername, $username, $password, $dbname, $port);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$seller_id = $_SESSION['user_id'];
$seller_sql = "SELECT * FROM Seller WHERE SellerID = ?";
$stmt = $conn->prepare($seller_sql);
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

// Initialize seller variables with default values
$seller_name = isset($seller['FullName']) ? $seller['FullName'] : 'Seller';
$seller_email = isset($seller['Email']) ? $seller['Email'] : '';
$seller_image = isset($seller['ImagePath']) ? $seller['ImagePath'] : ''; // Add this line
$initial = isset($seller['FullName']) ? strtoupper(substr($seller['FullName'], 0, 1)) : 'S';

// Delete Meal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_meal'])) {
    $meal_id = $_POST['meal_id'];
    
    $verify_sql = "SELECT * FROM Meal WHERE MealID = ? AND SellerID = ?";
    $verify_stmt = $conn->prepare($verify_sql);
    $verify_stmt->bind_param("ii", $meal_id, $seller_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    
    if ($verify_result->num_rows > 0) {
        $delete_sql = "DELETE FROM Meal WHERE MealID = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("i", $meal_id);
        
        if ($delete_stmt->execute()) {
            $success_message = "Meal deleted successfully!";
        } else {
            $error_message = "Error deleting meal: " . $conn->error;
        }
        $delete_stmt->close();
    } else {
        $error_message = "Meal not found or unauthorized!";
    }
    $verify_stmt->close();
}

// Add Meal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_meal'])) {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $price = $_POST['price'];
    $category = $_POST['category'];
    $availability = $_POST['availability'];
    
    $image_path = '';
    $has_upload_error = false;
    if (isset($_FILES['meal_image']) && $_FILES['meal_image']['error'] == 0) {
        $target_dir = "uploads/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_name = time() . '_' . basename($_FILES['meal_image']['name']);
        $target_file = $target_dir . $file_name;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        
        $check = getimagesize($_FILES['meal_image']['tmp_name']);
        if ($check !== false) {
            if ($_FILES['meal_image']['size'] > 5000000) {
                $error_message = "Sorry, your file is too large.";
                $has_upload_error = true;
            } elseif (!in_array($imageFileType, ['jpg', 'jpeg', 'png', 'gif'])) {
                $error_message = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
                $has_upload_error = true;
            } else {
                if (move_uploaded_file($_FILES['meal_image']['tmp_name'], $target_file)) {
                    $image_path = $target_file;
                } else {
                    $error_message = "Sorry, there was an error uploading your file.";
                    $has_upload_error = true;
                }
            }
        } else {
            $error_message = "File is not an image.";
            $has_upload_error = true;
        }
    } else {
        $error_message = "Please select an image.";
        $has_upload_error = true;
    }
    
    if (!$has_upload_error && $image_path) {
        $insert_sql = "INSERT INTO Meal (SellerID, Title, Description, Price, Category, Availability, ImagePath) 
                      VALUES (?, ?, ?, ?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("issdsss", $seller_id, $title, $description, $price, $category, $availability, $image_path);
        
        if ($insert_stmt->execute()) {
            $success_message = "Meal added successfully!";
            echo "<script>window.location.href = 'manage-meals.php?success=1';</script>";
            exit();
        } else {
            $error_message = "Error adding meal: " . $conn->error;
        }
        $insert_stmt->close();
    }
}

// Edit Meal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_meal'])) {
    $meal_id = $_POST['meal_id'];
    $title = $_POST['title'];
    $description = $_POST['description'];
    $price = $_POST['price'];
    $category = $_POST['category'];
    $availability = $_POST['availability'];
    
    // Verify meal belongs to seller
    $verify_sql = "SELECT * FROM Meal WHERE MealID = ? AND SellerID = ?";
    $verify_stmt = $conn->prepare($verify_sql);
    $verify_stmt->bind_param("ii", $meal_id, $seller_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    
    if ($verify_result->num_rows > 0) {
        $meal = $verify_result->fetch_assoc();
        $image_path = $meal['ImagePath']; // Keep existing image
        
        // Check if new image is uploaded
        if (isset($_FILES['meal_image']) && $_FILES['meal_image']['error'] == 0) {
            $target_dir = "uploads/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            
            $file_name = time() . '_' . basename($_FILES['meal_image']['name']);
            $target_file = $target_dir . $file_name;
            $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
            
            $check = getimagesize($_FILES['meal_image']['tmp_name']);
            if ($check !== false) {
                if ($_FILES['meal_image']['size'] > 5000000) {
                    $error_message = "Sorry, your file is too large.";
                } elseif (!in_array($imageFileType, ['jpg', 'jpeg', 'png', 'gif'])) {
                    $error_message = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
                } else {
                    if (move_uploaded_file($_FILES['meal_image']['tmp_name'], $target_file)) {
                        $image_path = $target_file;
                    } else {
                        $error_message = "Sorry, there was an error uploading your file.";
                    }
                }
            } else {
                $error_message = "File is not an image.";
            }
        }
        
        if (!isset($error_message)) {
            $update_sql = "UPDATE Meal SET Title = ?, Description = ?, Price = ?, Category = ?, Availability = ?, ImagePath = ? WHERE MealID = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ssdsssi", $title, $description, $price, $category, $availability, $image_path, $meal_id);
            
            if ($update_stmt->execute()) {
                $success_message = "Meal updated successfully!";
                echo "<script>window.location.href = 'manage-meals.php?success=1';</script>";
                exit();
            } else {
                $error_message = "Error updating meal: " . $conn->error;
            }
            $update_stmt->close();
        }
    } else {
        $error_message = "Meal not found or unauthorized!";
    }
    $verify_stmt->close();
}

// Get meal details for editing
$edit_meal = null;
if (isset($_GET['edit_id'])) {
    $meal_id = $_GET['edit_id'];
    $edit_sql = "SELECT * FROM Meal WHERE MealID = ? AND SellerID = ?";
    $edit_stmt = $conn->prepare($edit_sql);
    $edit_stmt->bind_param("ii", $meal_id, $seller_id);
    $edit_stmt->execute();
    $edit_result = $edit_stmt->get_result();
    if ($edit_result->num_rows > 0) {
        $edit_meal = $edit_result->fetch_assoc();
    }
    $edit_stmt->close();
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? $_GET['category'] : '';

if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success_message = "Meal operation completed successfully!";
}

$query = "SELECT * FROM Meal WHERE SellerID = ?";
$params = [$seller_id];
$param_types = "i";

if (!empty($search)) {
    $query .= " AND (Title LIKE ? OR Description LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $param_types .= "ss";
}

if (!empty($category)) {
    $query .= " AND Category = ?";
    $params[] = $category;
    $param_types .= "s";
}

$query .= " ORDER BY CreatedAt DESC";

$count_query = str_replace("SELECT *", "SELECT COUNT(*) as total", $query);
$count_stmt = $conn->prepare($count_query);
$count_stmt->bind_param($param_types, ...$params);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_meals_data = $count_result->fetch_assoc();
$total_meals = $total_meals_data ? $total_meals_data['total'] : 0;
$count_stmt->close();

$per_page = 12;
$total_pages = ceil($total_meals / $per_page);
$page = isset($_GET['page']) ? max(1, min($total_pages, intval($_GET['page']))) : 1;
$offset = ($page - 1) * $per_page;

$query .= " LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$param_types .= "ii";

$stmt = $conn->prepare($query);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$meals = [];
while($row = $result->fetch_assoc()) {
    $meals[] = $row;
}
$stmt->close();

$pending_sql = "SELECT COUNT(DISTINCT o.OrderID) as pending_count
                FROM `Order` o
                JOIN OrderDetails od ON o.OrderID = od.OrderID
                JOIN Meal m ON od.MealID = m.MealID
                WHERE m.SellerID = ? AND o.Status = 'Pending'";
$pending_stmt = $conn->prepare($pending_sql);
$pending_stmt->bind_param("i", $seller_id);
$pending_stmt->execute();
$pending_result = $pending_stmt->get_result();
$pending_data = $pending_result->fetch_assoc();
$pending_count = $pending_data ? ($pending_data['pending_count'] ?? 0) : 0;
$pending_stmt->close();

// Get all pending orders for notification modal
$pending_orders = [];
if ($pending_count > 0) {
    $all_pending_sql = "SELECT DISTINCT o.OrderID, o.OrderDate, o.TotalAmount,
                           c.FullName as CustomerName, c.ContactNo,
                           GROUP_CONCAT(CONCAT(m.Title, ' (x', od.Quantity, ')') SEPARATOR ', ') as Items
                    FROM `Order` o
                    JOIN OrderDetails od ON o.OrderID = od.OrderID
                    JOIN Meal m ON od.MealID = m.MealID
                    JOIN Customer c ON o.CustomerID = c.CustomerID
                    WHERE m.SellerID = ? AND o.Status = 'Pending'
                    GROUP BY o.OrderID
                    ORDER BY o.OrderDate DESC";
    $pending_stmt = $conn->prepare($all_pending_sql);
    $pending_stmt->bind_param("i", $seller_id);
    $pending_stmt->execute();
    $all_pending_result = $pending_stmt->get_result();
    if ($all_pending_result) {
        while($row = $all_pending_result->fetch_assoc()) {
            $pending_orders[] = $row;
        }
    }
    $pending_stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Meals | LutongBahay Seller</title>
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
        
        /* Profile Dropdown - UPDATED */
        .profile-dropdown {
            position: relative;
        }

        .user-profile {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            border: 2px solid var(--primary-dark);
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

        .user-profile img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
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
            overflow: hidden;
        }

        .user-initial img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
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

        .dropdown-menu::before {
            content: '';
            position: absolute;
            top: -8px;
            right: 20px;
            width: 16px;
            height: 16px;
            background-color: white;
            transform: rotate(45deg);
            box-shadow: -2px -2px 5px rgba(0, 0, 0, 0.04);
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
        
        .notification-item {
            padding: 25px 30px;
            border-bottom: 1px solid var(--light-gray);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .notification-item:hover {
            background-color: rgba(230, 57, 70, 0.05);
        }
        
        .notification-item:last-child {
            border-bottom: none;
        }
        
        .notification-icon {
            width: 50px;
            height: 50px;
            background-color: rgba(230, 57, 70, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 1.3rem;
            flex-shrink: 0;
        }
        
        .notification-details {
            flex: 1;
            min-width: 0;
        }
        
        .notification-title {
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 5px;
            font-size: 1.1rem;
        }
        
        .notification-message {
            color: var(--gray);
            margin-bottom: 10px;
            line-height: 1.5;
        }
        
        .notification-info {
            display: flex;
            gap: 15px;
            font-size: 0.85rem;
            color: var(--gray);
        }
        
        .notification-info span {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .notification-info i {
            font-size: 0.9rem;
        }
        
        .notification-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .btn-view-order {
            padding: 10px 20px;
            background-color: var(--primary);
            color: white;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
        }
        
        .btn-view-order:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        .notification-footer {
            padding: 20px 30px;
            background-color: var(--light-gray);
            text-align: center;
            border-top: 1px solid #ddd;
        }
        
        .btn-view-all-orders {
            background-color: var(--primary);
            color: white;
            padding: 12px 25px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
        }
        
        .btn-view-all-orders:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 40px 0;
            margin-bottom: 30px;
            border-radius: 0 0 20px 20px;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .header-text h1 {
            font-size: 2.2rem;
            margin-bottom: 10px;
            font-weight: 800;
        }
        
        .header-text p {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .message-container {
            margin-bottom: 25px;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: fadeIn 0.3s ease;
        }
        
        .alert-success {
            background-color: rgba(42, 157, 143, 0.1);
            border-left: 4px solid var(--success);
            color: var(--success);
        }
        
        .alert-error {
            background-color: rgba(230, 57, 70, 0.1);
            border-left: 4px solid var(--danger);
            color: var(--danger);
        }
        
        .filters-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: 2fr 1fr auto;
            gap: 20px;
            align-items: end;
        }
        
        .form-group {
            margin-bottom: 0;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
            font-size: 0.95rem;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: var(--transition);
            background-color: white;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(230, 57, 70, 0.1);
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            align-items: end;
        }
        
        .btn {
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            font-size: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
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
        
        .btn-danger {
            background-color: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c1121f;
            transform: translateY(-2px);
        }
        
        .meals-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .meal-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: var(--transition);
            position: relative;
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        
        .meal-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.15);
        }
        
        .meal-image {
            height: 250px;
            overflow: hidden;
            position: relative;
            background-color: #f5f5f5;
        }
        
        .meal-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: var(--transition);
        }
        
        .meal-card:hover .meal-image img {
            transform: scale(1.05);
        }
        
        .availability-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            z-index: 2;
            color: white;
        }
        
        .badge-available {
            background-color: rgba(42, 157, 143, 0.9);
        }
        
        .badge-not-available {
            background-color: rgba(230, 57, 70, 0.9);
        }
        
        .meal-info {
            padding: 20px;
            display: flex;
            flex-direction: column;
            flex: 1;
        }
        
        .meal-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
            min-height: 60px;
        }
        
        .meal-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--dark);
            line-height: 1.3;
            flex: 1;
            margin-right: 10px;
        }
        
        .meal-price {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--primary);
            white-space: nowrap;
        }
        
        .meal-meta {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            font-size: 0.9rem;
            color: var(--gray);
            flex-wrap: wrap;
        }
        
        .meal-category {
            background-color: var(--light-gray);
            padding: 4px 10px;
            border-radius: 12px;
            font-weight: 600;
        }
        
        .meal-date {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .meal-description {
            color: var(--gray);
            font-size: 0.95rem;
            margin-bottom: 20px;
            line-height: 1.5;
            flex: 1;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .meal-actions {
            display: flex;
            gap: 10px;
            margin-top: auto;
        }
        
        .btn-action {
            padding: 10px 15px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 6px;
            flex: 1;
            justify-content: center;
            text-decoration: none;
        }
        
        .btn-edit {
            background-color: var(--light-gray);
            color: var(--dark);
        }
        
        .btn-edit:hover {
            background-color: #ddd;
            color: var(--dark);
        }
        
        .btn-delete {
            background-color: rgba(230, 57, 70, 0.1);
            color: var(--danger);
            border: 1px solid rgba(230, 57, 70, 0.3);
        }
        
        .btn-delete:hover {
            background-color: rgba(230, 57, 70, 0.2);
            color: var(--danger);
        }
        
        .empty-state {
            grid-column: 1/-1;
            text-align: center;
            padding: 60px 20px;
            background-color: white;
            border-radius: 15px;
            box-shadow: var(--shadow);
        }
        
        .empty-icon {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            font-size: 1.5rem;
            color: var(--dark);
            margin-bottom: 10px;
        }
        
        .empty-state p {
            color: var(--gray);
            margin-bottom: 25px;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-bottom: 50px;
        }
        
        .pagination a, .pagination span {
            padding: 10px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
        }
        
        .pagination a {
            background-color: white;
            color: var(--dark);
            box-shadow: var(--shadow);
        }
        
        .pagination a:hover {
            background-color: var(--primary);
            color: white;
            transform: translateY(-2px);
        }
        
        .pagination .active {
            background-color: var(--primary);
            color: white;
        }
        
        .pagination .disabled {
            opacity: 0.5;
            cursor: not-allowed;
            box-shadow: none;
        }
        
        footer {
            background-color: var(--dark);
            color: white;
            padding: 60px 0 30px;
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
        
        /* MODAL STYLES */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s ease;
        }
        
        .modal-content {
            background-color: white;
            border-radius: 15px;
            width: 90%;
            max-width: 600px;
            box-shadow: var(--shadow);
            animation: slideIn 0.3s ease;
            position: relative;
        }
        
        .modal-header {
            padding: 25px 30px 15px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            font-size: 1.5rem;
            color: var(--dark);
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .modal-header h3 i {
            color: var(--primary);
        }
        
        .close-modal-btn {
            background: none;
            border: none;
            font-size: 1.8rem;
            cursor: pointer;
            color: var(--gray);
            transition: var(--transition);
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        
        .close-modal-btn:hover {
            background-color: var(--light-gray);
            color: var(--dark);
        }
        
        .modal-body {
            padding: 20px 30px;
            max-height: 70vh;
            overflow-y: auto;
        }
        
        .modal-footer {
            padding: 20px 30px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: flex-end;
            gap: 15px;
        }
        
        /* IMAGE UPLOAD IN MODAL - NEW DESIGN */
        .meal-image-preview-container {
            margin-bottom: 25px;
        }
        
        .meal-image-preview-container label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: var(--dark);
            font-size: 0.95rem;
        }
        
        .image-upload-wrapper {
            position: relative;
            border: 2px dashed #ddd;
            border-radius: 12px;
            overflow: hidden;
            min-height: 200px;
            background-color: #f9f9f9;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .image-upload-wrapper:hover {
            border-color: var(--primary);
            background-color: rgba(230, 57, 70, 0.05);
        }
        
        .image-upload-wrapper.has-image {
            border-color: var(--primary);
            border-style: solid;
        }
        
        .upload-placeholder {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 30px;
            text-align: center;
            z-index: 1;
        }
        
        .upload-placeholder i {
            font-size: 3rem;
            color: var(--gray);
            margin-bottom: 15px;
        }
        
        .upload-placeholder-text {
            color: var(--dark);
            font-weight: 500;
            margin-bottom: 5px;
            font-size: 1rem;
        }
        
        .upload-placeholder-subtext {
            color: var(--gray);
            font-size: 0.9rem;
        }
        
        .image-preview {
            position: relative;
            width: 100%;
            height: 200px;
            display: none;
        }
        
        .image-preview.active {
            display: block;
        }
        
        .preview-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 10px;
        }
        
        .remove-preview-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            border: none;
            border-radius: 50%;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
            z-index: 2;
        }
        
        .remove-preview-btn:hover {
            background: var(--danger);
            transform: scale(1.1);
        }
        
        .file-input {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
            z-index: 2;
        }
        
        /* Form Grid */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .modal-alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: fadeIn 0.3s ease;
        }
        
        .modal-alert.success {
            background-color: rgba(42, 157, 143, 0.1);
            border-left: 4px solid var(--success);
            color: var(--success);
        }
        
        .modal-alert.error {
            background-color: rgba(230, 57, 70, 0.1);
            border-left: 4px solid var(--danger);
            color: var(--danger);
        }
        
        .fa-spinner {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
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
            .meals-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .header-content {
                flex-direction: column;
                text-align: center;
            }
            
            .filters-grid {
                grid-template-columns: 1fr;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            /* Notification modal adjustments */
            .notification-content {
                width: 95%;
                max-height: 90vh;
            }
            
            .notification-header {
                padding: 20px;
            }
            
            .notification-item {
                padding: 20px;
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .notification-icon {
                width: 45px;
                height: 45px;
                font-size: 1.2rem;
            }
            
            .notification-actions {
                width: 100%;
            }
            
            .btn-view-order {
                flex: 1;
                justify-content: center;
            }
        }
        
        @media (max-width: 768px) {
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
            
            .dropdown-menu {
                position: fixed;
                top: unset;
                bottom: 0;
                right: 0;
                left: 0;
                width: 100%;
                border-radius: 20px 20px 0 0;
                max-height: 80vh;
                overflow-y: auto;
            }
            
            .dropdown-menu::before {
                display: none;
            }
            
            #addMealModal .modal-content,
            #editMealModal .modal-content,
            #deleteModal .modal-content {
                width: 95%;
                max-height: 85vh;
            }
            
            .modal-footer {
                flex-direction: column;
            }
            
            .modal-footer .btn {
                width: 100%;
                justify-content: center;
            }
            
            .filters-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 576px) {
            .meals-grid {
                grid-template-columns: 1fr;
            }
            
            .filters-section {
                padding: 20px;
            }
            
            .modal-header,
            .modal-body,
            .modal-footer {
                padding: 20px 15px;
            }
            
            .footer-content {
                grid-template-columns: 1fr;
                gap: 30px;
            }
            
            .notification-header h2 {
                font-size: 1.3rem;
            }
            
            .notification-info {
                flex-direction: column;
                gap: 5px;
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
                <a href="manage-meals.php" class="active">Manage Meals</a>
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
                
                <!-- Profile dropdown - UPDATED -->
                <div class="profile-dropdown">
                    <div class="user-profile" id="profileToggle">
                        <?php if (!empty($seller_image)): ?>
                            <img src="<?php echo htmlspecialchars($seller_image); ?>" alt="<?php echo htmlspecialchars($seller_name); ?>">
                        <?php else: ?>
                            <?php echo $initial; ?>
                        <?php endif; ?>
                    </div>
                    <div class="dropdown-menu" id="dropdownMenu">
                        <div class="dropdown-header">
                            <div class="user-info">
                                <div class="user-initial">
                                    <?php if (!empty($seller_image)): ?>
                                        <img src="<?php echo htmlspecialchars($seller_image); ?>" alt="<?php echo htmlspecialchars($seller_name); ?>">
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
            <?php if (count($pending_orders) > 0): ?>
                <?php foreach ($pending_orders as $order): ?>
                    <div class="notification-item" data-order-id="<?php echo $order['OrderID']; ?>">
                        <div class="notification-icon">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="notification-details">
                            <div class="notification-title">
                                New Order #<?php echo str_pad($order['OrderID'], 6, '0', STR_PAD_LEFT); ?>
                            </div>
                            <div class="notification-message">
                                <strong><?php echo htmlspecialchars($order['CustomerName']); ?></strong> ordered: 
                                <?php echo htmlspecialchars($order['Items']); ?>
                            </div>
                            <div class="notification-info">
                                <span><i class="far fa-calendar"></i> <?php echo date('M d, Y h:i A', strtotime($order['OrderDate'])); ?></span>
                                <span><i class="fas fa-money-bill-wave"></i> ₱<?php echo number_format($order['TotalAmount'], 2); ?></span>
                                <span><i class="fas fa-phone"></i> <?php echo htmlspecialchars($order['ContactNo']); ?></span>
                            </div>
                            <div class="notification-actions">
                                <a href="seller-orders.php?view=<?php echo $order['OrderID']; ?>" class="btn-view-order">
                                    <i class="fas fa-eye"></i> View Order Details
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="notification-empty">
                    <i class="far fa-check-circle"></i>
                    <h3>No Pending Orders</h3>
                    <p>You're all caught up! There are no pending orders at the moment.</p>
                </div>
            <?php endif; ?>
        </div>
        <?php if (count($pending_orders) > 0): ?>
        <div class="notification-footer">
            <a href="seller-orders.php?status=Pending" class="btn-view-all-orders">
                <i class="fas fa-list"></i> View All Pending Orders
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<section class="page-header">
    <div class="container">
        <div class="header-content">
            <div class="header-text">
                <h1>Manage Your Meals</h1>
                <p>Add, edit, or remove your meal offerings. Currently showing <?php echo $total_meals; ?> meal(s)</p>
            </div>
            <button type="button" onclick="showAddMealModal()" class="btn btn-primary">
                <i class="fas fa-plus-circle"></i> Add New Meal
            </button>
        </div>
    </div>
</section>

<main class="container">
    <?php if (isset($success_message)): ?>
        <div class="message-container">
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $success_message; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="message-container">
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error_message; ?>
            </div>
        </div>
    <?php endif; ?>

    <section class="filters-section">
        <form method="GET" action="" id="filterForm">
            <div class="filters-grid">
                <div class="form-group">
                    <label for="search"><i class="fas fa-search"></i> Search Meals</label>
                    <input type="text" id="search" name="search" class="form-control" 
                           placeholder="Search by meal name or description..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="form-group">
                    <label for="category"><i class="fas fa-tag"></i> Category</label>
                    <select id="category" name="category" class="form-control">
                        <option value="">All Categories</option>
                        <option value="Main Dishes" <?php echo $category === 'Main Dishes' ? 'selected' : ''; ?>>Main Dishes</option>
                        <option value="Desserts" <?php echo $category === 'Desserts' ? 'selected' : ''; ?>>Desserts</option>
                        <option value="Merienda" <?php echo $category === 'Merienda' ? 'selected' : ''; ?>>Merienda</option>
                        <option value="Vegetarian" <?php echo $category === 'Vegetarian' ? 'selected' : ''; ?>>Vegetarian</option>
                        <option value="Holiday Specials" <?php echo $category === 'Holiday Specials' ? 'selected' : ''; ?>>Holiday Specials</option>
                    </select>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                    <a href="manage-meals.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </div>
            </div>
        </form>
    </section>

    <div class="meals-grid" id="mealsGrid">
        <?php if (count($meals) > 0): ?>
            <?php foreach ($meals as $meal): ?>
                <?php
                // Determine availability badge class
                $availability_class = $meal['Availability'] == 'Available' ? 'badge-available' : 'badge-not-available';
                
                // Ensure image path is valid
                $meal_image = !empty($meal['ImagePath']) ? $meal['ImagePath'] : 'https://images.unsplash.com/photo-1565958011703-44f9829ba187?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80';
                
                // Format date
                $created_date = date('M d, Y', strtotime($meal['CreatedAt']));
                
                // Truncate description
                $description = htmlspecialchars($meal['Description']);
                if (strlen($description) > 150) {
                    $description = substr($description, 0, 150) . '...';
                }
                ?>
                
                <div class="meal-card">
                    <div class="meal-image">
                        <img src="<?php echo $meal_image; ?>" 
                             alt="<?php echo htmlspecialchars($meal['Title']); ?>"
                             onerror="this.src='https://images.unsplash.com/photo-1565958011703-44f9829ba187?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80'">
                        <div class="availability-badge <?php echo $availability_class; ?>">
                            <?php echo $meal['Availability']; ?>
                        </div>
                    </div>
                    
                    <div class="meal-info">
                        <div class="meal-header">
                            <h3 class="meal-title"><?php echo htmlspecialchars($meal['Title']); ?></h3>
                            <div class="meal-price">₱<?php echo number_format($meal['Price'], 2); ?></div>
                        </div>
                        
                        <div class="meal-meta">
                            <span class="meal-category"><?php echo $meal['Category']; ?></span>
                            <span class="meal-date">
                                <i class="far fa-calendar"></i>
                                <?php echo $created_date; ?>
                            </span>
                        </div>
                        
                        <p class="meal-description">
                            <?php echo $description; ?>
                        </p>
                        
                        <div class="meal-actions">
                            <button type="button" class="btn-action btn-edit" onclick="showEditMealModal(<?php echo $meal['MealID']; ?>)">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button type="button" class="btn-action btn-delete" 
                                    onclick="showDeleteModal(<?php echo $meal['MealID']; ?>, '<?php echo htmlspecialchars(addslashes($meal['Title'])); ?>')">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-utensils"></i>
                </div>
                <h3>No meals found</h3>
                <p>
                    <?php if (!empty($search) || !empty($category)): ?>
                        No meals match your search criteria. Try different filters or clear them to see all meals.
                    <?php else: ?>
                        You haven't added any meals yet. Start by adding your first meal!
                    <?php endif; ?>
                </p>
                <?php if (empty($search) && empty($category)): ?>
                    <button type="button" onclick="showAddMealModal()" class="btn btn-primary" style="margin-top: 15px;">
                        <i class="fas fa-plus-circle"></i> Add Your First Meal
                    </button>
                <?php else: ?>
                    <a href="manage-meals.php" class="btn btn-secondary" style="margin-top: 15px;">
                        <i class="fas fa-times"></i> Clear Filters
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                    <i class="fas fa-chevron-left"></i> Previous
                </a>
            <?php else: ?>
                <span class="disabled"><i class="fas fa-chevron-left"></i> Previous</span>
            <?php endif; ?>
            
            <?php
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
            
            for ($i = $start_page; $i <= $end_page; $i++):
            ?>
                <?php if ($i == $page): ?>
                    <span class="active"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                    Next <i class="fas fa-chevron-right"></i>
                </a>
            <?php else: ?>
                <span class="disabled">Next <i class="fas fa-chevron-right"></i></span>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</main>

<!-- Add Meal Modal -->
<div id="addMealModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle"></i> Add New Meal</h3>
            <button class="close-modal-btn" onclick="hideAddMealModal()">&times;</button>
        </div>
        
        <form method="POST" action="" enctype="multipart/form-data" id="addMealForm">
            <input type="hidden" name="add_meal" value="1">
            
            <div class="modal-body">
                <!-- Messages Container -->
                <div id="mealMessageContainer">
                    <?php if (isset($error_message) && strpos($_SERVER['REQUEST_URI'], 'manage-meals.php') !== false): ?>
                        <div class="modal-alert error">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo $error_message; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="mealTitle">Meal Title *</label>
                    <input type="text" id="mealTitle" name="title" class="form-control" required placeholder="e.g., Beef Caldereta with Rice" value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="mealDescription">Description *</label>
                    <textarea id="mealDescription" name="description" class="form-control" rows="3" required placeholder="Describe your meal..."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="mealPrice">Price (₱) *</label>
                        <input type="number" id="mealPrice" name="price" class="form-control" step="0.01" min="1" required placeholder="0.00" value="<?php echo isset($_POST['price']) ? htmlspecialchars($_POST['price']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="mealCategory">Category *</label>
                        <select id="mealCategory" name="category" class="form-control" required>
                            <option value="">Select Category</option>
                            <option value="Main Dishes" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Main Dishes') ? 'selected' : ''; ?>>Main Dishes</option>
                            <option value="Desserts" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Desserts') ? 'selected' : ''; ?>>Desserts</option>
                            <option value="Merienda" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Merienda') ? 'selected' : ''; ?>>Merienda</option>
                            <option value="Vegetarian" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Vegetarian') ? 'selected' : ''; ?>>Vegetarian</option>
                            <option value="Holiday Specials" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Holiday Specials') ? 'selected' : ''; ?>>Holiday Specials</option>
                        </select>
                    </div>
                </div>
                
                <!-- IMAGE UPLOAD -->
                <div class="meal-image-preview-container">
                    <label>Meal Image *</label>
                    <div class="image-upload-wrapper" id="addImageUploadWrapper">
                        <!-- Upload Placeholder -->
                        <div class="upload-placeholder" id="addUploadPlaceholder">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <div class="upload-placeholder-text">Click to upload meal image</div>
                            <div class="upload-placeholder-subtext">JPG, PNG or GIF (Max 5MB)</div>
                        </div>
                        
                        <!-- Image Preview -->
                        <div class="image-preview" id="addImagePreview">
                            <img class="preview-img" id="addPreviewImg" src="" alt="Meal Preview">
                            <button type="button" class="remove-preview-btn" onclick="removeAddImagePreview()">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        
                        <!-- Hidden File Input -->
                        <input type="file" id="addMealImage" name="meal_image" accept="image/*" class="file-input">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="mealAvailability">Availability *</label>
                    <select id="mealAvailability" name="availability" class="form-control" required>
                        <option value="Available" <?php echo (isset($_POST['availability']) && $_POST['availability'] == 'Available') ? 'selected' : 'selected'; ?>>Available</option>
                        <option value="Not Available" <?php echo (isset($_POST['availability']) && $_POST['availability'] == 'Not Available') ? 'selected' : ''; ?>>Not Available</option>
                    </select>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="hideAddMealModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn btn-primary" id="saveMealBtn">
                    <i class="fas fa-save"></i> Save Meal
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Meal Modal -->
<div id="editMealModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Edit Meal</h3>
            <button class="close-modal-btn" onclick="hideEditMealModal()">&times;</button>
        </div>
        
        <form method="POST" action="" enctype="multipart/form-data" id="editMealForm">
            <input type="hidden" name="edit_meal" value="1">
            <input type="hidden" name="meal_id" id="editMealId">
            
            <div class="modal-body">
                <!-- Messages Container -->
                <div id="editMealMessageContainer"></div>
                
                <div class="form-group">
                    <label for="editMealTitle">Meal Title *</label>
                    <input type="text" id="editMealTitle" name="title" class="form-control" required placeholder="e.g., Beef Caldereta with Rice">
                </div>
                
                <div class="form-group">
                    <label for="editMealDescription">Description *</label>
                    <textarea id="editMealDescription" name="description" class="form-control" rows="3" required placeholder="Describe your meal..."></textarea>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="editMealPrice">Price (₱) *</label>
                        <input type="number" id="editMealPrice" name="price" class="form-control" step="0.01" min="1" required placeholder="0.00">
                    </div>
                    
                    <div class="form-group">
                        <label for="editMealCategory">Category *</label>
                        <select id="editMealCategory" name="category" class="form-control" required>
                            <option value="">Select Category</option>
                            <option value="Main Dishes">Main Dishes</option>
                            <option value="Desserts">Desserts</option>
                            <option value="Merienda">Merienda</option>
                            <option value="Vegetarian">Vegetarian</option>
                            <option value="Holiday Specials">Holiday Specials</option>
                        </select>
                    </div>
                </div>
                
                <!-- IMAGE UPLOAD -->
                <div class="meal-image-preview-container">
                    <label>Meal Image</label>
                    <div class="image-upload-wrapper" id="editImageUploadWrapper">
                        <!-- Upload Placeholder -->
                        <div class="upload-placeholder" id="editUploadPlaceholder">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <div class="upload-placeholder-text">Click to upload meal image</div>
                            <div class="upload-placeholder-subtext">JPG, PNG or GIF (Max 5MB)</div>
                        </div>
                        
                        <!-- Image Preview -->
                        <div class="image-preview" id="editImagePreview">
                            <img class="preview-img" id="editPreviewImg" src="" alt="Meal Preview">
                            <button type="button" class="remove-preview-btn" onclick="removeEditImagePreview()">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        
                        <!-- Hidden File Input -->
                        <input type="file" id="editMealImage" name="meal_image" accept="image/*" class="file-input">
                    </div>
                    <small class="text-muted">Leave empty to keep current image</small>
                </div>
                
                <div class="form-group">
                    <label for="editMealAvailability">Availability *</label>
                    <select id="editMealAvailability" name="availability" class="form-control" required>
                        <option value="Available">Available</option>
                        <option value="Not Available">Not Available</option>
                    </select>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="hideEditMealModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn btn-primary" id="updateMealBtn">
                    <i class="fas fa-save"></i> Update Meal
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-exclamation-triangle"></i> Delete Meal</h3>
            <button class="close-modal-btn" onclick="hideDeleteModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="alert alert-error" style="margin-bottom: 20px;">
                <i class="fas fa-exclamation-circle"></i>
                <strong>Warning:</strong> This action cannot be undone.
            </div>
            <p>Are you sure you want to delete "<span id="mealNameToDelete" class="text-danger" style="font-weight: bold;"></span>"?</p>
            <p class="text-muted">This meal will be permanently removed from your listings.</p>
        </div>
        <div class="modal-footer">
            <form method="POST" action="" id="deleteForm" style="width: 100%;">
                <input type="hidden" name="meal_id" id="mealIdToDelete">
                <div style="display: flex; gap: 15px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="hideDeleteModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" name="delete_meal" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Delete Meal
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

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
    const pendingBadge = document.getElementById('pendingBadge');
    const notificationToggle = document.getElementById('notificationToggle');
    const notificationModal = document.getElementById('notificationModal');
    const closeNotification = document.getElementById('closeNotification');

    // Toggle profile dropdown
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

    // Toggle notification modal
    notificationToggle.addEventListener('click', function(e) {
        e.stopPropagation();
        notificationModal.classList.add('show');
        document.body.style.overflow = 'hidden';
    });

    // Close notification modal
    closeNotification.addEventListener('click', function() {
        notificationModal.classList.remove('show');
        document.body.style.overflow = 'auto';
    });

    // Close notification modal when clicking outside
    notificationModal.addEventListener('click', function(e) {
        if (e.target === notificationModal) {
            notificationModal.classList.remove('show');
            document.body.style.overflow = 'auto';
        }
    });

    // Close notification modal when clicking on notification items
    document.querySelectorAll('.notification-item').forEach(item => {
        item.addEventListener('click', function(e) {
            if (!e.target.closest('.notification-actions')) {
                const orderId = this.getAttribute('data-order-id');
                window.location.href = `seller-orders.php?view=${orderId}`;
            }
        });
    });

    // Animate pending badge
    if (pendingBadge) {
        pendingBadge.style.animation = 'pulse 2s infinite';
        setTimeout(() => {
            pendingBadge.style.animation = '';
        }, 3000);
    }

    // Delete modal
    function showDeleteModal(mealId, mealName) {
        document.getElementById('mealIdToDelete').value = mealId;
        document.getElementById('mealNameToDelete').textContent = mealName;
        document.getElementById('deleteModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function hideDeleteModal() {
        document.getElementById('deleteModal').style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    // Add meal modal
    function showAddMealModal() {
        document.getElementById('addMealModal').style.display = 'flex';
        document.getElementById('mealTitle').focus();
        document.body.style.overflow = 'hidden';
    }

    function hideAddMealModal() {
        document.getElementById('addMealModal').style.display = 'none';
        resetAddMealForm();
        document.body.style.overflow = 'auto';
    }

    // Edit meal modal - updated to fetch meal data
    function showEditMealModal(mealId) {
        // Fetch meal data via AJAX (you'll need to create a separate endpoint)
        // For now, let's use a simpler approach by passing data from PHP
        // or we'll need to create get-meal-data.php endpoint
        
        // First, let's hide the modal while we prepare data
        document.getElementById('editMealModal').style.display = 'flex';
        
        // Show loading state
        const messageContainer = document.getElementById('editMealMessageContainer');
        messageContainer.innerHTML = '<div class="modal-alert"><i class="fas fa-spinner fa-spin"></i> Loading meal data...</div>';
        
        // Fetch meal data
        fetch('get-meal-data.php?id=' + mealId)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success && data.meal) {
                    const meal = data.meal;
                    
                    // Populate form fields
                    document.getElementById('editMealId').value = mealId;
                    document.getElementById('editMealTitle').value = meal.Title || '';
                    document.getElementById('editMealDescription').value = meal.Description || '';
                    document.getElementById('editMealPrice').value = meal.Price || '';
                    document.getElementById('editMealCategory').value = meal.Category || '';
                    document.getElementById('editMealAvailability').value = meal.Availability || 'Available';
                    
                    // Set image preview if exists
                    if (meal.ImagePath) {
                        const preview = document.getElementById('editImagePreview');
                        const previewImg = document.getElementById('editPreviewImg');
                        const uploadPlaceholder = document.getElementById('editUploadPlaceholder');
                        const imageUploadWrapper = document.getElementById('editImageUploadWrapper');
                        
                        previewImg.src = meal.ImagePath;
                        preview.classList.add('active');
                        uploadPlaceholder.style.display = 'none';
                        imageUploadWrapper.classList.add('has-image');
                    }
                    
                    // Clear message
                    messageContainer.innerHTML = '';
                    
                    // Focus on title
                    document.getElementById('editMealTitle').focus();
                } else {
                    messageContainer.innerHTML = '<div class="modal-alert error"><i class="fas fa-exclamation-circle"></i> Error loading meal data: ' + (data.message || 'Unknown error') + '</div>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                messageContainer.innerHTML = '<div class="modal-alert error"><i class="fas fa-exclamation-circle"></i> Error loading meal data. Please try again.</div>';
            });
        
        document.body.style.overflow = 'hidden';
    }

    function hideEditMealModal() {
        document.getElementById('editMealModal').style.display = 'none';
        resetEditMealForm();
        document.body.style.overflow = 'auto';
    }

    // Image preview functionality for Add modal
    document.getElementById('addMealImage').addEventListener('change', function(event) {
        previewAddImage(event);
    });

    function previewAddImage(event) {
        const input = event.target;
        const preview = document.getElementById('addImagePreview');
        const previewImg = document.getElementById('addPreviewImg');
        const uploadPlaceholder = document.getElementById('addUploadPlaceholder');
        const imageUploadWrapper = document.getElementById('addImageUploadWrapper');
        
        if (input.files && input.files[0]) {
            const file = input.files[0];
            
            // Check file size (max 5MB)
            if (file.size > 5 * 1024 * 1024) {
                alert('File size too large! Please select an image under 5MB.');
                input.value = '';
                return;
            }
            
            // Check file type
            const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            if (!validTypes.includes(file.type)) {
                alert('Invalid file type! Please select JPG, PNG or GIF image.');
                input.value = '';
                return;
            }
            
            const reader = new FileReader();
            
            reader.onload = function(e) {
                previewImg.src = e.target.result;
                preview.classList.add('active');
                uploadPlaceholder.style.display = 'none';
                imageUploadWrapper.classList.add('has-image');
            }
            
            reader.onerror = function(e) {
                console.error('FileReader error:', e);
                alert('Error reading file. Please try again.');
            }
            
            reader.readAsDataURL(file);
        }
    }

    function removeAddImagePreview() {
        const input = document.getElementById('addMealImage');
        const preview = document.getElementById('addImagePreview');
        const uploadPlaceholder = document.getElementById('addUploadPlaceholder');
        const imageUploadWrapper = document.getElementById('addImageUploadWrapper');
        
        input.value = '';
        preview.classList.remove('active');
        document.getElementById('addPreviewImg').src = '';
        uploadPlaceholder.style.display = 'flex';
        imageUploadWrapper.classList.remove('has-image');
    }

    // Image preview functionality for Edit modal
    document.getElementById('editMealImage').addEventListener('change', function(event) {
        previewEditImage(event);
    });

    function previewEditImage(event) {
        const input = event.target;
        const preview = document.getElementById('editImagePreview');
        const previewImg = document.getElementById('editPreviewImg');
        const uploadPlaceholder = document.getElementById('editUploadPlaceholder');
        const imageUploadWrapper = document.getElementById('editImageUploadWrapper');
        
        if (input.files && input.files[0]) {
            const file = input.files[0];
            
            // Check file size (max 5MB)
            if (file.size > 5 * 1024 * 1024) {
                alert('File size too large! Please select an image under 5MB.');
                input.value = '';
                return;
            }
            
            // Check file type
            const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            if (!validTypes.includes(file.type)) {
                alert('Invalid file type! Please select JPG, PNG or GIF image.');
                input.value = '';
                return;
            }
            
            const reader = new FileReader();
            
            reader.onload = function(e) {
                previewImg.src = e.target.result;
                preview.classList.add('active');
                uploadPlaceholder.style.display = 'none';
                imageUploadWrapper.classList.add('has-image');
            }
            
            reader.onerror = function(e) {
                console.error('FileReader error:', e);
                alert('Error reading file. Please try again.');
            }
            
            reader.readAsDataURL(file);
        }
    }

    function removeEditImagePreview() {
        const input = document.getElementById('editMealImage');
        const preview = document.getElementById('editImagePreview');
        const uploadPlaceholder = document.getElementById('editUploadPlaceholder');
        const imageUploadWrapper = document.getElementById('editImageUploadWrapper');
        
        input.value = '';
        preview.classList.remove('active');
        document.getElementById('editPreviewImg').src = '';
        uploadPlaceholder.style.display = 'flex';
        imageUploadWrapper.classList.remove('has-image');
    }

    // Reset forms
    function resetAddMealForm() {
        document.getElementById('addMealForm').reset();
        removeAddImagePreview();
        const messageContainer = document.getElementById('mealMessageContainer');
        messageContainer.style.display = 'none';
        messageContainer.innerHTML = '';
    }

    function resetEditMealForm() {
        document.getElementById('editMealForm').reset();
        removeEditImagePreview();
        const messageContainer = document.getElementById('editMealMessageContainer');
        messageContainer.style.display = 'none';
        messageContainer.innerHTML = '';
        
        // Reset image preview to original
        const preview = document.getElementById('editImagePreview');
        const previewImg = document.getElementById('editPreviewImg');
        const uploadPlaceholder = document.getElementById('editUploadPlaceholder');
        const imageUploadWrapper = document.getElementById('editImageUploadWrapper');
        
        preview.classList.remove('active');
        previewImg.src = '';
        uploadPlaceholder.style.display = 'flex';
        imageUploadWrapper.classList.remove('has-image');
    }

    // Close modals when clicking outside
    document.addEventListener('click', function(event) {
        const deleteModal = document.getElementById('deleteModal');
        const addMealModal = document.getElementById('addMealModal');
        const editMealModal = document.getElementById('editMealModal');
        const notificationModal = document.getElementById('notificationModal');
        
        if (event.target === deleteModal) {
            hideDeleteModal();
        }
        
        if (event.target === addMealModal) {
            hideAddMealModal();
        }
        
        if (event.target === editMealModal) {
            hideEditMealModal();
        }
        
        if (event.target === notificationModal) {
            notificationModal.classList.remove('show');
            document.body.style.overflow = 'auto';
        }
    });

    // Logout
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
                    Are you sure you want to logout?
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
        dropdownMenu.classList.remove('show');
        
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

    // Close modals with Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            hideDeleteModal();
            hideAddMealModal();
            hideEditMealModal();
            notificationModal.classList.remove('show');
            document.body.style.overflow = 'auto';
        }
    });

    // Form validation for add meal
    document.getElementById('addMealForm').addEventListener('submit', function(e) {
        const mealImage = document.getElementById('addMealImage');
        const saveBtn = document.getElementById('saveMealBtn');
        
        if (!mealImage.files || !mealImage.files[0]) {
            e.preventDefault();
            alert('Please select an image for the meal.');
            return false;
        }
        
        // Show loading state
        const originalText = saveBtn.innerHTML;
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        saveBtn.disabled = true;
        
        setTimeout(() => {
            saveBtn.innerHTML = originalText;
            saveBtn.disabled = false;
        }, 5000);
        
        return true;
    });

    // Form validation for edit meal
    document.getElementById('editMealForm').addEventListener('submit', function(e) {
        const updateBtn = document.getElementById('updateMealBtn');
        
        // Show loading state
        const originalText = updateBtn.innerHTML;
        updateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
        updateBtn.disabled = true;
        
        setTimeout(() => {
            updateBtn.innerHTML = originalText;
            updateBtn.disabled = false;
        }, 5000);
        
        return true;
    });

    // Auto-hide success message
    <?php if (isset($success_message)): ?>
        setTimeout(function() {
            const alert = document.querySelector('.alert-success');
            if (alert) {
                alert.style.display = 'none';
            }
        }, 5000);
    <?php endif; ?>

    // Auto-hide error message
    <?php if (isset($error_message)): ?>
        setTimeout(function() {
            const alert = document.querySelector('.alert-error');
            if (alert) {
                alert.style.display = 'none';
            }
        }, 5000);
    <?php endif; ?>
</script>

</body>
</html>