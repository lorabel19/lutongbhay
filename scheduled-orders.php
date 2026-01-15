<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
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

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (isset($data['action'])) {
        $order_id = $data['order_id'] ?? null;
        
        switch($data['action']) {
            case 'cancel_order':
                $update_sql = "UPDATE `Order` SET Status = 'Cancelled' WHERE OrderID = ? AND CustomerID = ?";
                $stmt = $conn->prepare($update_sql);
                if ($stmt === false) {
                    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
                    exit();
                }
                $stmt->bind_param("ii", $order_id, $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Order cancelled successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to cancel order']);
                }
                $stmt->close();
                exit();
                
            case 'reschedule_order':
                $new_date = $data['new_date'] ?? null;
                if ($new_date) {
                    $schedule_datetime = $new_date . ' 12:00:00';
                    
                    $update_sql = "UPDATE `Order` SET ScheduleDate = ?, Status = 'Upcoming' WHERE OrderID = ? AND CustomerID = ?";
                    $stmt = $conn->prepare($update_sql);
                    if ($stmt === false) {
                        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
                        exit();
                    }
                    $stmt->bind_param("sii", $schedule_datetime, $order_id, $_SESSION['user_id']);
                    
                    if ($stmt->execute()) {
                        echo json_encode(['success' => true, 'message' => 'Order rescheduled successfully']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to reschedule order']);
                    }
                    $stmt->close();
                }
                exit();
                
            case 'complete_order':
                $update_sql = "UPDATE `Order` SET Status = 'Completed' WHERE OrderID = ? AND CustomerID = ?";
                $stmt = $conn->prepare($update_sql);
                if ($stmt === false) {
                    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
                    exit();
                }
                $stmt->bind_param("ii", $order_id, $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Order marked as completed']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to complete order']);
                }
                $stmt->close();
                exit();
        }
    }
}

// Get user details
$user_id = $_SESSION['user_id'];
$user_sql = "SELECT * FROM Customer WHERE CustomerID = ?";
$stmt = $conn->prepare($user_sql);
if ($stmt === false) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc();
$stmt->close();

if (!$user) {
    session_destroy();
    header('Location: index.php');
    exit();
}

// Get current date
$today = date('Y-m-d');

// Get orders for upcoming tab - ONLY SCHEDULED ORDERS
$upcoming_sql = "SELECT 
                o.OrderID,
                o.OrderDate,
                o.ScheduleDate,
                o.Status,
                o.TotalAmount,
                o.OrderType,
                o.DeliveryAddress,
                o.ContactNo,
                (SELECT GROUP_CONCAT(CONCAT(m.Title, ' (x', od2.Quantity, ')') SEPARATOR ', ') 
                 FROM OrderDetails od2 
                 JOIN Meal m ON od2.MealID = m.MealID 
                 WHERE od2.OrderID = o.OrderID) as MealTitlesWithQuantity,
                (SELECT GROUP_CONCAT(m.Title SEPARATOR ', ') 
                 FROM OrderDetails od2 
                 JOIN Meal m ON od2.MealID = m.MealID 
                 WHERE od2.OrderID = o.OrderID) as MealTitles,
                (SELECT GROUP_CONCAT(m.ImagePath SEPARATOR '|||') 
                 FROM OrderDetails od2 
                 JOIN Meal m ON od2.MealID = m.MealID 
                 WHERE od2.OrderID = o.OrderID) as MealImages,
                (SELECT GROUP_CONCAT(od2.Quantity SEPARATOR '|||') 
                 FROM OrderDetails od2 
                 WHERE od2.OrderID = o.OrderID) as Quantities,
                (SELECT SUM(od2.Quantity) FROM OrderDetails od2 WHERE od2.OrderID = o.OrderID) as TotalItems,
                (SELECT s.FullName 
                 FROM OrderDetails od5 
                 JOIN Meal m2 ON od5.MealID = m2.MealID 
                 JOIN Seller s ON m2.SellerID = s.SellerID 
                 WHERE od5.OrderID = o.OrderID LIMIT 1) as SellerName
            FROM `Order` o
            WHERE o.CustomerID = ? 
            AND o.OrderType = 'Scheduled'
            AND o.Status IN ('Upcoming', 'Confirmed')
            ORDER BY o.ScheduleDate ASC";

$upcoming_stmt = $conn->prepare($upcoming_sql);
if ($upcoming_stmt === false) {
    die("Prepare failed for upcoming orders: " . $conn->error);
}
$upcoming_stmt->bind_param("i", $user_id);
$upcoming_stmt->execute();
$upcoming_result = $upcoming_stmt->get_result();
$upcoming_orders = [];
while($row = $upcoming_result->fetch_assoc()) {
    $upcoming_orders[] = $row;
}
$upcoming_stmt->close();

// Get orders for today's tab - ONLY IMMEDIATE ORDERS PLACED TODAY
$today_sql = "SELECT 
                o.OrderID,
                o.OrderDate,
                o.ScheduleDate,
                o.Status,
                o.TotalAmount,
                o.OrderType,
                o.DeliveryAddress,
                o.ContactNo,
                (SELECT GROUP_CONCAT(CONCAT(m.Title, ' (x', od2.Quantity, ')') SEPARATOR ', ') 
                 FROM OrderDetails od2 
                 JOIN Meal m ON od2.MealID = m.MealID 
                 WHERE od2.OrderID = o.OrderID) as MealTitlesWithQuantity,
                (SELECT GROUP_CONCAT(m.Title SEPARATOR ', ') 
                 FROM OrderDetails od2 
                 JOIN Meal m ON od2.MealID = m.MealID 
                 WHERE od2.OrderID = o.OrderID) as MealTitles,
                (SELECT GROUP_CONCAT(m.ImagePath SEPARATOR '|||') 
                 FROM OrderDetails od2 
                 JOIN Meal m ON od2.MealID = m.MealID 
                 WHERE od2.OrderID = o.OrderID) as MealImages,
                (SELECT GROUP_CONCAT(od2.Quantity SEPARATOR '|||') 
                 FROM OrderDetails od2 
                 WHERE od2.OrderID = o.OrderID) as Quantities,
                (SELECT SUM(od2.Quantity) FROM OrderDetails od2 WHERE od2.OrderID = o.OrderID) as TotalItems,
                (SELECT s.FullName 
                 FROM OrderDetails od5 
                 JOIN Meal m2 ON od5.MealID = m2.MealID 
                 JOIN Seller s ON m2.SellerID = s.SellerID 
                 WHERE od5.OrderID = o.OrderID LIMIT 1) as SellerName
            FROM `Order` o
            WHERE o.CustomerID = ? 
            AND o.OrderType = 'Immediate'
            AND o.Status = 'Today'
            AND DATE(o.OrderDate) = ?
            ORDER BY o.OrderDate DESC";

$today_stmt = $conn->prepare($today_sql);
if ($today_stmt === false) {
    die("Prepare failed for today orders: " . $conn->error);
}
$today_stmt->bind_param("is", $user_id, $today);
$today_stmt->execute();
$today_result = $today_stmt->get_result();
$today_orders = [];
while($row = $today_result->fetch_assoc()) {
    $today_orders[] = $row;
}
$today_stmt->close();

// Get cart count
$cart_count = 0;
$cart_sql = "SELECT COUNT(*) as cart_count FROM Cart WHERE CustomerID = ?";
$cart_stmt = $conn->prepare($cart_sql);
if ($cart_stmt === false) {
    die("Prepare failed for cart count: " . $conn->error);
}
$cart_stmt->bind_param("i", $user_id);
$cart_stmt->execute();
$cart_result = $cart_stmt->get_result();
if ($cart_result) {
    $cart_data = $cart_result->fetch_assoc();
    $cart_count = $cart_data['cart_count'] ?: 0;
}
$cart_stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scheduled Orders | LutongBahay</title>
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
            --success: #2a9d8f;
            --rating-yellow: #ffc107;
            --warning: #e9c46a;
            --info: #457b9d;
            --pending: #ff9800;
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
        
        /* Cart Icon Styles */
        .cart-icon-link {
            position: relative;
            text-decoration: none;
            display: inline-block;
            transition: var(--transition);
            padding: 8px;
            border-radius: 50%;
        }
        
        .cart-icon-link:hover {
            background-color: rgba(230, 57, 70, 0.1);
            transform: translateY(-2px);
        }
        
        .cart-icon {
            position: relative;
            font-size: 1.5rem;
            color: var(--dark);
        }
        
        .cart-count {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: var(--primary);
            color: white;
            font-size: 0.8rem;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-weight: 600;
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
        }
        
        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 60px 0 30px;
            text-align: center;
        }
        
        .page-header h1 {
            font-size: 2.8rem;
            margin-bottom: 15px;
            font-weight: 800;
        }
        
        .page-header p {
            font-size: 1.2rem;
            max-width: 800px;
            margin: 0 auto;
            opacity: 0.9;
        }
        
        /* Order Status Tabs */
        .order-tabs {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin: 40px 0;
            flex-wrap: wrap;
        }
        
        .order-tab {
            padding: 12px 30px;
            background-color: white;
            border: 2px solid var(--light-gray);
            border-radius: 50px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            color: var(--dark);
        }
        
        .order-tab:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .order-tab.active {
            background-color: var(--primary);
            border-color: var(--primary);
            color: white;
        }
        
        /* Orders Container */
        .orders-container {
            margin-bottom: 80px;
        }
        
        .section-title {
            font-size: 1.8rem;
            color: var(--dark);
            margin-bottom: 25px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--light-gray);
        }
        
        /* Table Styles */
        .orders-table {
            width: 100%;
            background-color: white;
            border-radius: 15px;
            box-shadow: var(--shadow);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .table-header {
            display: grid;
            grid-template-columns: 1fr 1.5fr 1.5fr 1fr 1.2fr 1.5fr;
            background-color: var(--light);
            padding: 20px;
            font-weight: 600;
            color: var(--dark);
            border-bottom: 2px solid var(--light-gray);
        }
        
        .table-row {
            display: grid;
            grid-template-columns: 1fr 1.5fr 1.5fr 1fr 1.2fr 1.5fr;
            padding: 20px;
            border-bottom: 1px solid var(--light-gray);
            align-items: center;
            transition: var(--transition);
        }
        
        .table-row:hover {
            background-color: rgba(248, 249, 250, 0.8);
        }
        
        .table-row:last-child {
            border-bottom: none;
        }
        
        /* Meal Image Styles */
        .meal-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .meal-image-container {
            width: 70px;
            height: 70px;
            border-radius: 10px;
            overflow: hidden;
            flex-shrink: 0;
            box-shadow: 0 3px 8px rgba(0,0,0,0.1);
        }

        .meal-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .meal-image:hover {
            transform: scale(1.05);
        }

        .meal-details {
            flex: 1;
        }

        .meal-title {
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 1rem;
            color: var(--dark);
        }

        .meal-count {
            font-size: 0.9rem;
            color: var(--gray);
        }

        /* Multiple Meal Images */
        .multiple-meals {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .small-meal-image {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            object-fit: cover;
            border: 2px solid white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .more-count {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            background-color: var(--light-gray);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--gray);
            flex-direction: column;
        }
        
        .more-count span:first-child {
            font-size: 1.2rem;
            font-weight: 700;
        }
        
        .more-count span:last-child {
            font-size: 0.7rem;
            margin-top: 2px;
        }
        
        .status-badge {
            padding: 6px 15px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            text-align: center;
            display: inline-block;
        }
        
        .status-upcoming {
            background-color: rgba(69, 123, 157, 0.15);
            color: var(--info);
        }
        
        .status-confirmed {
            background-color: rgba(233, 196, 106, 0.15);
            color: #d4a017;
        }
        
        .status-today {
            background-color: rgba(42, 157, 143, 0.15);
            color: var(--success);
        }
        
        .status-immediate {
            background-color: rgba(230, 57, 70, 0.15);
            color: var(--primary);
        }
        
        .table-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 8px 15px;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            font-size: 0.9rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
        }
        
        .btn-outline {
            background-color: transparent;
            color: var(--primary);
            border: 2px solid var(--primary);
        }
        
        .btn-outline:hover {
            background-color: rgba(230, 57, 70, 0.1);
        }
        
        .btn-success {
            background-color: var(--success);
            color: white;
        }
        
        .btn-success:hover {
            background-color: #248277;
        }
        
        .btn-warning {
            background-color: var(--warning);
            color: var(--dark);
        }
        
        .btn-warning:hover {
            background-color: #dbb23d;
        }
        
        /* No Orders Message */
        .no-orders {
            text-align: center;
            padding: 60px 40px;
            background-color: white;
            border-radius: 15px;
            box-shadow: var(--shadow);
        }
        
        .no-orders i {
            font-size: 3rem;
            color: var(--light-gray);
            margin-bottom: 20px;
        }
        
        .no-orders h3 {
            font-size: 1.8rem;
            color: var(--dark);
            margin-bottom: 15px;
        }
        
        .no-orders p {
            color: var(--gray);
            margin-bottom: 25px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
            font-size: 1.1rem;
            line-height: 1.6;
        }
        
        /* Footer */
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
        
        /* Tab Content */
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Order type indicator */
        .order-type-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-top: 5px;
        }
        
        .order-type-scheduled {
            background-color: rgba(69, 123, 157, 0.1);
            color: var(--info);
        }
        
        .order-type-immediate {
            background-color: rgba(230, 57, 70, 0.1);
            color: var(--primary);
        }
        
        /* Responsive adjustments */
        @media (max-width: 1200px) {
            .footer-content {
                grid-template-columns: repeat(2, 1fr);
                gap: 30px;
            }
            
            .table-header,
            .table-row {
                grid-template-columns: 1fr 1.5fr 1fr 1fr 1.5fr;
            }
            
            .table-header div:nth-child(5),
            .table-row div:nth-child(5) {
                display: none;
            }
        }
        
        @media (max-width: 992px) {
            .table-header,
            .table-row {
                grid-template-columns: 1fr 1.5fr 1fr 1.5fr;
            }
            
            .table-header div:nth-child(4),
            .table-row div:nth-child(4) {
                display: none;
            }
            
            .small-meal-image {
                width: 40px;
                height: 40px;
            }
            
            .more-count {
                width: 40px;
                height: 40px;
                font-size: 0.8rem;
            }
        }
        
        @media (max-width: 768px) {
            .table-header,
            .table-row {
                grid-template-columns: 1fr 1fr 1fr;
                padding: 15px;
            }
            
            .table-header div:nth-child(3),
            .table-row div:nth-child(3) {
                display: none;
            }
            
            .table-actions {
                flex-direction: column;
                gap: 5px;
            }
            
            .btn {
                padding: 6px 12px;
                font-size: 0.85rem;
            }
            
            .footer-content {
                grid-template-columns: 1fr;
                gap: 30px;
            }
            
            .page-header h1 {
                font-size: 2.2rem;
            }
            
            .page-header p {
                font-size: 1rem;
            }
            
            .meal-info {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .meal-image-container {
                width: 60px;
                height: 60px;
            }
            
            .small-meal-image {
                width: 35px;
                height: 35px;
            }
            
            .more-count {
                width: 35px;
                height: 35px;
                font-size: 0.7rem;
            }
        }
        
        @media (max-width: 576px) {
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
            
            .order-tabs {
                flex-direction: column;
                align-items: center;
            }
            
            .order-tab {
                width: 100%;
                text-align: center;
            }
            
            .table-header,
            .table-row {
                grid-template-columns: 1fr 1fr;
                font-size: 0.9rem;
            }
            
            .table-header div:nth-child(2),
            .table-row div:nth-child(2) {
                display: none;
            }
            
            .status-badge {
                font-size: 0.8rem;
                padding: 4px 10px;
            }
            
            .meal-image-container {
                width: 50px;
                height: 50px;
            }
            
            .small-meal-image {
                width: 30px;
                height: 30px;
            }
            
            .more-count {
                width: 30px;
                height: 30px;
                font-size: 0.7rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header & Navigation -->
    <header>
        <div class="container">
            <div class="nav-container">
                <a href="homepage.php" class="logo">
                    <i class="fas fa-utensils"></i>
                    LutongBahay
                </a>
                
                <div class="nav-links">
                    <a href="homepage.php">Home</a>
                    <a href="browse-meals.php">Browse Meals</a>
                    <a href="scheduled-orders.php" class="active">Scheduled Orders</a>
                    <a href="past-orders.php">Past Orders</a>
                    <a href="sellers.php">Sellers</a>
                </div>
                
                <div class="user-actions">
                    <!-- Cart icon with count -->
                    <a href="cart.php" class="cart-icon-link">
                        <div class="cart-icon">
                            <i class="fas fa-shopping-cart"></i>
                            <span class="cart-count" id="cartCount"><?php echo $cart_count; ?></span>
                        </div>
                    </a>
                    
                    <div class="user-profile">
                        <?php echo strtoupper(substr($user['FullName'], 0, 1)); ?>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <h1>Scheduled Orders</h1>
            <p>Manage your upcoming meal deliveries and view your scheduled orders.</p>
        </div>
    </section>

    <!-- Order Status Tabs -->
    <section class="container">
        <div class="order-tabs">
            <button class="order-tab active" data-tab="upcoming">Upcoming Orders</button>
            <button class="order-tab" data-tab="today">Today's Orders</button>
        </div>
    </section>

    <!-- Orders Container -->
    <section class="container orders-container">
        <!-- Upcoming Orders Section - ONLY SCHEDULED ORDERS -->
        <div id="upcoming-orders" class="tab-content active">
            <h2 class="section-title">Upcoming Orders</h2>
            
            <?php if (!empty($upcoming_orders)): ?>
                <div class="orders-table">
                    <div class="table-header">
                        <div>Order ID</div>
                        <div>Meals</div>
                        <div>Seller</div>
                        <div>Schedule Date</div>
                        <div>Status</div>
                        <div>Actions</div>
                    </div>
                    
                    <?php foreach($upcoming_orders as $order): ?>
                        <div class="table-row" data-order-id="<?php echo $order['OrderID']; ?>">
                            <div>
                                <strong>#<?php echo str_pad($order['OrderID'], 6, '0', STR_PAD_LEFT); ?></strong>
                                <div style="font-size: 0.9rem; color: var(--gray); margin-top: 5px;">
                                    ₱<?php echo number_format($order['TotalAmount'], 2); ?>
                                </div>
                                <div class="order-type-badge order-type-scheduled">
                                    Scheduled
                                </div>
                            </div>
                            <div>
                                <?php
                                // Parse meal data
                                $meal_titles = isset($order['MealTitles']) ? explode(', ', $order['MealTitles']) : [];
                                $meal_images = isset($order['MealImages']) ? explode('|||', $order['MealImages']) : [];
                                $quantities = isset($order['Quantities']) ? explode('|||', $order['Quantities']) : [];
                                $total_items = isset($order['TotalItems']) ? $order['TotalItems'] : 0;
                                $item_count = count($meal_titles);
                                
                                if ($item_count == 1) {
                                    // Single meal - show larger image
                                    $image_path = !empty($meal_images[0]) ? $meal_images[0] : 'images/default-meal.jpg';
                                    $quantity = isset($quantities[0]) ? $quantities[0] : 1;
                                ?>
                                    <div class="meal-info">
                                        <div class="meal-image-container">
                                            <img src="<?php echo htmlspecialchars($image_path); ?>" 
                                                 alt="<?php echo htmlspecialchars($meal_titles[0]); ?>" 
                                                 class="meal-image"
                                                 onerror="this.src='images/default-meal.jpg'">
                                        </div>
                                        <div class="meal-details">
                                            <div class="meal-title"><?php echo htmlspecialchars($meal_titles[0]); ?></div>
                                            <div class="meal-count">
                                                <?php echo $quantity; ?> item<?php echo $quantity > 1 ? 's' : ''; ?>
                                                (<?php echo $total_items; ?> total)
                                            </div>
                                        </div>
                                    </div>
                                <?php } else { 
                                    // Multiple meals - show multiple images
                                ?>
                                    <div class="meal-info">
                                        <div class="multiple-meals">
                                            <?php 
                                            $display_limit = min(3, count($meal_images));
                                            for ($i = 0; $i < $display_limit; $i++): 
                                                $image_path = !empty($meal_images[$i]) ? $meal_images[$i] : 'images/default-meal.jpg';
                                            ?>
                                                <img src="<?php echo htmlspecialchars($image_path); ?>" 
                                                     alt="<?php echo htmlspecialchars($meal_titles[$i] ?? 'Meal'); ?>" 
                                                     class="small-meal-image"
                                                     onerror="this.src='images/default-meal.jpg'"
                                                     title="<?php echo htmlspecialchars($meal_titles[$i] ?? 'Meal'); ?>">
                                            <?php endfor; ?>
                                            
                                            <?php if (count($meal_images) > 3): 
                                                $remaining_items = $total_items;
                                                for ($i = 0; $i < 3; $i++) {
                                                    $remaining_items -= isset($quantities[$i]) ? $quantities[$i] : 1;
                                                }
                                            ?>
                                                <div class="more-count" title="And <?php echo count($meal_images) - 3; ?> more meals">
                                                    <span>+<?php echo count($meal_images) - 3; ?></span>
                                                    <span><?php echo $remaining_items; ?> items</span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="meal-details" style="margin-left: 15px;">
                                            <div class="meal-title">
                                                <?php 
                                                $display_titles = [];
                                                for ($i = 0; $i < min(2, count($meal_titles)); $i++) {
                                                    $quantity = isset($quantities[$i]) ? $quantities[$i] : 1;
                                                    $display_titles[] = htmlspecialchars($meal_titles[$i]) . ($quantity > 1 ? " (x{$quantity})" : "");
                                                }
                                                echo implode(', ', $display_titles);
                                                if (count($meal_titles) > 2) echo '...';
                                                ?>
                                            </div>
                                            <div class="meal-count"><?php echo $total_items; ?> total items</div>
                                        </div>
                                    </div>
                                <?php } ?>
                            </div>
                            <div>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <i class="fas fa-user" style="color: var(--gray);"></i>
                                    <?php echo htmlspecialchars($order['SellerName']); ?>
                                </div>
                            </div>
                            <div>
                                <div style="font-weight: 600;">
                                    <?php echo date('M j, Y', strtotime($order['ScheduleDate'])); ?>
                                </div>
                                <div style="font-size: 0.9rem; color: var(--gray);">
                                    <?php echo date('h:i A', strtotime($order['ScheduleDate'])); ?>
                                </div>
                            </div>
                            <div>
                                <?php if($order['Status'] == 'Upcoming'): ?>
                                    <span class="status-badge status-upcoming">
                                        <i class="fas fa-hourglass-half"></i> Waiting Confirmation
                                    </span>
                                <?php elseif($order['Status'] == 'Confirmed'): ?>
                                    <span class="status-badge status-confirmed">
                                        <i class="fas fa-check-circle"></i> Confirmed
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="table-actions">
                                <button class="btn btn-outline" onclick="rescheduleOrder(<?php echo $order['OrderID']; ?>)">
                                    <i class="fas fa-calendar"></i> Reschedule
                                </button>
                                <button class="btn btn-outline" onclick="cancelOrder(<?php echo $order['OrderID']; ?>)">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-orders">
                    <i class="fas fa-calendar"></i>
                    <h3>No Upcoming Orders</h3>
                    <p>You don't have any scheduled orders for future dates. Browse our meals to schedule your next order!</p>
                    <a href="browse-meals.php" class="btn btn-primary">
                        <i class="fas fa-utensils"></i> Browse Meals
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Today's Orders Section - ONLY IMMEDIATE ORDERS -->
        <div id="today-orders" class="tab-content">
            <h2 class="section-title">Today's Orders</h2>
            
            <?php if (!empty($today_orders)): ?>
                <div class="orders-table">
                    <div class="table-header">
                        <div>Order ID</div>
                        <div>Meals</div>
                        <div>Seller</div>
                        <div>Order Time</div>
                        <div>Status</div>
                        <div>Actions</div>
                    </div>
                    
                    <?php foreach($today_orders as $order): ?>
                        <div class="table-row" data-order-id="<?php echo $order['OrderID']; ?>">
                            <div>
                                <strong>#<?php echo str_pad($order['OrderID'], 6, '0', STR_PAD_LEFT); ?></strong>
                                <div style="font-size: 0.9rem; color: var(--gray); margin-top: 5px;">
                                    ₱<?php echo number_format($order['TotalAmount'], 2); ?>
                                </div>
                                <div class="order-type-badge order-type-immediate">
                                    Immediate
                                </div>
                            </div>
                            <div>
                                <?php
                                // Parse meal data
                                $meal_titles = isset($order['MealTitles']) ? explode(', ', $order['MealTitles']) : [];
                                $meal_images = isset($order['MealImages']) ? explode('|||', $order['MealImages']) : [];
                                $quantities = isset($order['Quantities']) ? explode('|||', $order['Quantities']) : [];
                                $total_items = isset($order['TotalItems']) ? $order['TotalItems'] : 0;
                                $item_count = count($meal_titles);
                                
                                if ($item_count == 1) {
                                    // Single meal - show larger image
                                    $image_path = !empty($meal_images[0]) ? $meal_images[0] : 'images/default-meal.jpg';
                                    $quantity = isset($quantities[0]) ? $quantities[0] : 1;
                                ?>
                                    <div class="meal-info">
                                        <div class="meal-image-container">
                                            <img src="<?php echo htmlspecialchars($image_path); ?>" 
                                                 alt="<?php echo htmlspecialchars($meal_titles[0]); ?>" 
                                                 class="meal-image"
                                                 onerror="this.src='images/default-meal.jpg'">
                                        </div>
                                        <div class="meal-details">
                                            <div class="meal-title"><?php echo htmlspecialchars($meal_titles[0]); ?></div>
                                            <div class="meal-count">
                                                <?php echo $quantity; ?> item<?php echo $quantity > 1 ? 's' : ''; ?>
                                                (<?php echo $total_items; ?> total)
                                            </div>
                                        </div>
                                    </div>
                                <?php } else { 
                                    // Multiple meals - show multiple images
                                ?>
                                    <div class="meal-info">
                                        <div class="multiple-meals">
                                            <?php 
                                            $display_limit = min(3, count($meal_images));
                                            for ($i = 0; $i < $display_limit; $i++): 
                                                $image_path = !empty($meal_images[$i]) ? $meal_images[$i] : 'images/default-meal.jpg';
                                            ?>
                                                <img src="<?php echo htmlspecialchars($image_path); ?>" 
                                                     alt="<?php echo htmlspecialchars($meal_titles[$i] ?? 'Meal'); ?>" 
                                                     class="small-meal-image"
                                                     onerror="this.src='images/default-meal.jpg'"
                                                     title="<?php echo htmlspecialchars($meal_titles[$i] ?? 'Meal'); ?>">
                                            <?php endfor; ?>
                                            
                                            <?php if (count($meal_images) > 3): 
                                                $remaining_items = $total_items;
                                                for ($i = 0; $i < 3; $i++) {
                                                    $remaining_items -= isset($quantities[$i]) ? $quantities[$i] : 1;
                                                }
                                            ?>
                                                <div class="more-count" title="And <?php echo count($meal_images) - 3; ?> more meals">
                                                    <span>+<?php echo count($meal_images) - 3; ?></span>
                                                    <span><?php echo $remaining_items; ?> items</span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="meal-details" style="margin-left: 15px;">
                                            <div class="meal-title">
                                                <?php 
                                                $display_titles = [];
                                                for ($i = 0; $i < min(2, count($meal_titles)); $i++) {
                                                    $quantity = isset($quantities[$i]) ? $quantities[$i] : 1;
                                                    $display_titles[] = htmlspecialchars($meal_titles[$i]) . ($quantity > 1 ? " (x{$quantity})" : "");
                                                }
                                                echo implode(', ', $display_titles);
                                                if (count($meal_titles) > 2) echo '...';
                                                ?>
                                            </div>
                                            <div class="meal-count"><?php echo $total_items; ?> total items</div>
                                        </div>
                                    </div>
                                <?php } ?>
                            </div>
                            <div>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <i class="fas fa-user" style="color: var(--gray);"></i>
                                    <?php echo htmlspecialchars($order['SellerName']); ?>
                                </div>
                            </div>
                            <div>
                                <div style="font-weight: 600;">
                                    <?php echo date('h:i A', strtotime($order['OrderDate'])); ?>
                                </div>
                                <div style="font-size: 0.9rem; color: var(--gray);">
                                    ASAP
                                </div>
                            </div>
                            <div>
                                <span class="status-badge status-today">
                                    <i class="fas fa-clock"></i> Today
                                </span>
                            </div>
                            <div class="table-actions">
                                <button class="btn btn-success" onclick="completeOrder(<?php echo $order['OrderID']; ?>)">
                                    <i class="fas fa-check-circle"></i> Received
                                </button>
                                <button class="btn btn-warning" onclick="contactSeller('<?php echo htmlspecialchars($order['SellerName']); ?>', <?php echo $order['OrderID']; ?>)">
                                    <i class="fas fa-phone"></i> Contact
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-orders">
                    <i class="fas fa-clock"></i>
                    <h3>No Orders Today</h3>
                    <p>You don't have any orders placed today. Check your upcoming orders or browse meals to place an order.</p>
                    <a href="browse-meals.php" class="btn btn-primary">
                        <i class="fas fa-utensils"></i> Browse Meals
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-content">
                <div>
                    <div class="footer-logo">
                        <i class="fas fa-utensils"></i>
                        LutongBahay
                    </div>
                    <p>Connecting small food entrepreneurs with customers online. Supporting Filipino home cooks and food businesses since 2024.</p>
                </div>
                
                <div class="footer-links">
                    <h3>For Customers</h3>
                    <ul>
                        <li><a href="browse-meals.php">Browse Meals</a></li>
                        <li><a href="scheduled-orders.php" class="active">Schedule Orders</a></li>
                        <li><a href="past-orders.php">Past Orders</a></li>
                        <li><a href="help.php">Help Center</a></li>
                    </ul>
                </div>
                
                <div class="footer-links">
                    <h3>For Sellers</h3>
                    <ul>
                        <li><a href="become-seller.php">Become a Seller</a></li>
                        <li><a href="seller-dashboard.php">Seller Dashboard</a></li>
                        <li><a href="seller-resources.php">Seller Resources</a></li>
                        <li><a href="seller-support.php">Support</a></li>
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
                &copy; 2026 LutongBahay. Polytechnic University of the Philippines - Parañaque City Campus. All rights reserved.
            </div>
        </div>
    </footer>

    <script>
        // Tab functionality
        const tabs = document.querySelectorAll('.order-tab');
        const tabContents = document.querySelectorAll('.tab-content');
        
        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                const tabId = tab.getAttribute('data-tab');
                
                // Update active tab
                tabs.forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                
                // Show active content
                tabContents.forEach(content => {
                    content.classList.remove('active');
                    if (content.id === `${tabId}-orders`) {
                        content.classList.add('active');
                    }
                });
            });
        });
        
        // Show notification function
        function showNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.textContent = message;
            
            const bgColor = type === 'success' ? 'var(--success)' : 'var(--primary)';
            
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
            `;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'fadeIn 0.3s ease reverse';
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 300);
            }, 3000);
        }

        // Order action functions with AJAX
        function rescheduleOrder(orderId) {
            // Get current schedule date from the table row
            const tableRow = document.querySelector(`[data-order-id="${orderId}"]`);
            const dateText = tableRow.querySelector('div:nth-child(4) div:first-child').textContent;
            const timeText = tableRow.querySelector('div:nth-child(4) div:nth-child(2)').textContent;
            
            // Parse date from text (e.g., "Mar 15, 2024")
            const dateParts = dateText.split(' ');
            const monthNames = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
            const monthIndex = monthNames.indexOf(dateParts[0]);
            const day = dateParts[1].replace(',', '');
            const year = dateParts[2];
            
            const currentDate = `${year}-${String(monthIndex + 1).padStart(2, '0')}-${day.padStart(2, '0')}`;
            
            // Ask for new date
            const newDate = prompt('Enter new delivery date (YYYY-MM-DD):', currentDate);
            if (newDate) {
                // Validate date format
                if (!/^\d{4}-\d{2}-\d{2}$/.test(newDate)) {
                    showNotification('Please enter a valid date in YYYY-MM-DD format', 'error');
                    return;
                }
                
                // Ask for time
                const times = ['09:00', '12:00', '15:00', '18:00'];
                const timeOptions = times.map((time, index) => `${index + 1}. ${time} ${time < '12' ? 'AM' : 'PM'}`).join('\n');
                const timeInput = prompt('Select preferred time:\n' + timeOptions + '\nEnter 1-4:');
                
                let selectedTime = '12:00';
                if (timeInput) {
                    const index = parseInt(timeInput) - 1;
                    if (index >= 0 && index < times.length) {
                        selectedTime = times[index];
                    }
                }
                
                // Send AJAX request
                sendRescheduleRequest(orderId, newDate, selectedTime);
            }
        }
        
        async function sendRescheduleRequest(orderId, newDate, time) {
            try {
                const response = await fetch('scheduled-orders.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'reschedule_order',
                        order_id: orderId,
                        new_date: newDate + ' ' + time
                    })
                });
                
                const data = await response.json();
                if (data.success) {
                    showNotification(data.message);
                    // Reload page after 2 seconds
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showNotification(data.message || 'Failed to reschedule order', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Error rescheduling order', 'error');
            }
        }
        
        async function cancelOrder(orderId) {
            if (confirm('Are you sure you want to cancel this order? This action cannot be undone.')) {
                try {
                    const response = await fetch('scheduled-orders.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            action: 'cancel_order',
                            order_id: orderId
                        })
                    });
                    
                    const data = await response.json();
                    if (data.success) {
                        showNotification(data.message);
                        // Remove table row from UI
                        const tableRow = document.querySelector(`[data-order-id="${orderId}"]`);
                        if (tableRow) {
                            tableRow.style.opacity = '0.5';
                            setTimeout(() => {
                                tableRow.remove();
                                // Check if no more orders
                                if (document.querySelectorAll('.table-row').length === 0) {
                                    location.reload();
                                }
                            }, 500);
                        }
                    } else {
                        showNotification(data.message || 'Failed to cancel order', 'error');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showNotification('Error cancelling order', 'error');
                }
            }
        }
        
        async function completeOrder(orderId) {
            if (confirm('Mark this order as received and completed?')) {
                try {
                    const response = await fetch('scheduled-orders.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            action: 'complete_order',
                            order_id: orderId
                        })
                    });
                    
                    const data = await response.json();
                    if (data.success) {
                        showNotification(data.message);
                        // Remove table row from UI
                        const tableRow = document.querySelector(`[data-order-id="${orderId}"]`);
                        if (tableRow) {
                            tableRow.style.opacity = '0.5';
                            setTimeout(() => {
                                tableRow.remove();
                                // Check if no more orders
                                if (document.querySelectorAll('.table-row').length === 0) {
                                    location.reload();
                                }
                            }, 500);
                        }
                    } else {
                        showNotification(data.message || 'Failed to complete order', 'error');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showNotification('Error completing order', 'error');
                }
            }
        }
        
        function contactSeller(sellerName, orderId) {
            // In a real application, this would show seller contact info
            // For now, show a notification
            showNotification(`Contact information for ${sellerName} (Order #${orderId}) would appear here.`);
        }
        
        // Function to handle broken images
        function handleImageError(img) {
            img.onerror = null; // Prevent infinite loop
            img.src = 'images/default-meal.jpg';
            img.alt = 'Image not available';
        }

        // Initialize image error handlers
        document.addEventListener('DOMContentLoaded', function() {
            const images = document.querySelectorAll('.meal-image, .small-meal-image');
            images.forEach(img => {
                img.addEventListener('error', function() {
                    this.src = 'images/default-meal.jpg';
                    this.alt = 'Image not available';
                });
            });
            
            // Check if there are today's orders and switch to that tab
            const todayOrders = document.querySelectorAll('.status-badge.status-today').length;
            
            // If there are orders for today, switch to Today's tab
            if (todayOrders > 0) {
                tabs.forEach(t => t.classList.remove('active'));
                tabContents.forEach(c => c.classList.remove('active'));
                
                document.querySelector('.order-tab[data-tab="today"]').classList.add('active');
                document.getElementById('today-orders').classList.add('active');
            }
        });
    </script>

</body>
</html>