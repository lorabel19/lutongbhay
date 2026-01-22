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

// Initialize seller variables with default values
$seller_name = isset($seller['FullName']) ? $seller['FullName'] : 'Seller';
$seller_email = isset($seller['Email']) ? $seller['Email'] : '';
$seller_image = isset($seller['ImagePath']) ? $seller['ImagePath'] : '';
$first_name = isset($seller['FullName']) ? explode(' ', $seller['FullName'])[0] : 'Seller';
$initial = isset($seller['FullName']) ? strtoupper(substr($seller['FullName'], 0, 1)) : 'S';

// Get seller's stats with default values
$stats = [
    'total_meals' => 0,
    'available_meals' => 0,
    'total_sales' => 0,
    'total_items_sold' => 0
];

$meals_sql = "SELECT COUNT(*) as total_meals, 
                     SUM(CASE WHEN Availability = 'Available' THEN 1 ELSE 0 END) as available_meals
              FROM Meal 
              WHERE SellerID = ?";
$stmt = $conn->prepare($meals_sql);
if ($stmt) {
    $stmt->bind_param("i", $seller_id);
    $stmt->execute();
    $meals_result = $stmt->get_result();
    if ($meals_result) {
        $meals_data = $meals_result->fetch_assoc();
        if ($meals_data) {
            $stats['total_meals'] = $meals_data['total_meals'] ?: 0;
            $stats['available_meals'] = $meals_data['available_meals'] ?: 0;
        }
    }
    $stmt->close();
}

// Get total sales
$sales_sql = "SELECT COALESCE(SUM(od.Subtotal), 0) as total_sales,
                     COALESCE(SUM(od.Quantity), 0) as total_items_sold
              FROM OrderDetails od
              JOIN Meal m ON od.MealID = m.MealID
              JOIN `Order` o ON od.OrderID = o.OrderID
              WHERE m.SellerID = ? AND o.Status IN ('Confirmed', 'Completed')";
$stmt = $conn->prepare($sales_sql);
if ($stmt) {
    $stmt->bind_param("i", $seller_id);
    $stmt->execute();
    $sales_result = $stmt->get_result();
    if ($sales_result) {
        $sales_data = $sales_result->fetch_assoc();
        if ($sales_data) {
            $stats['total_sales'] = $sales_data['total_sales'] ?: 0;
            $stats['total_items_sold'] = $sales_data['total_items_sold'] ?: 0;
        }
    }
    $stmt->close();
}

// Get recent orders
$recent_orders = [];
$orders_sql = "SELECT o.OrderID, o.OrderDate, o.Status, o.TotalAmount,
                      c.FullName as CustomerName, c.ContactNo as CustomerContact,
                      GROUP_CONCAT(CONCAT(m.Title, ' (x', od.Quantity, ')') SEPARATOR ', ') as Items
               FROM `Order` o
               JOIN OrderDetails od ON o.OrderID = od.OrderID
               JOIN Meal m ON od.MealID = m.MealID
               JOIN Customer c ON o.CustomerID = c.CustomerID
               WHERE m.SellerID = ?
               GROUP BY o.OrderID
               ORDER BY o.OrderDate DESC
               LIMIT 5";
$stmt = $conn->prepare($orders_sql);
if ($stmt) {
    $stmt->bind_param("i", $seller_id);
    $stmt->execute();
    $orders_result = $stmt->get_result();
    if ($orders_result) {
        while($row = $orders_result->fetch_assoc()) {
            $recent_orders[] = $row;
        }
    }
    $stmt->close();
}

// Get seller's recent meals (LIMIT TO 4)
$recent_meals = [];
$meals_sql = "SELECT * FROM Meal 
              WHERE SellerID = ? 
              ORDER BY CreatedAt DESC 
              LIMIT 4";
$stmt = $conn->prepare($meals_sql);
if ($stmt) {
    $stmt->bind_param("i", $seller_id);
    $stmt->execute();
    $meals_result = $stmt->get_result();
    if ($meals_result) {
        while($row = $meals_result->fetch_assoc()) {
            $recent_meals[] = $row;
        }
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

// Get all pending orders for notification modal
$pending_orders = [];
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
$stmt = $conn->prepare($all_pending_sql);
if ($stmt) {
    $stmt->bind_param("i", $seller_id);
    $stmt->execute();
    $all_pending_result = $stmt->get_result();
    if ($all_pending_result) {
        while($row = $all_pending_result->fetch_assoc()) {
            $pending_orders[] = $row;
        }
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
    <title>Seller Dashboard | LutongBahay</title>
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
            color: var(--danger);
        }

        .dropdown-item.logout i {
            color: var(--danger);
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
        
        .notification-empty p {
            font-size: 1rem;
            line-height: 1.5;
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
        
        /* Welcome Section */
        .welcome-section {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 60px 0;
            margin-bottom: 40px;
            border-radius: 0 0 20px 20px;
        }
        
        .welcome-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .welcome-text h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            font-weight: 800;
        }
        
        .welcome-text p {
            font-size: 1.1rem;
            opacity: 0.9;
            max-width: 600px;
        }
        
        .welcome-stats {
            display: flex;
            gap: 20px;
            background: rgba(255, 255, 255, 0.1);
            padding: 20px;
            border-radius: 15px;
            backdrop-filter: blur(10px);
        }
        
        .stat-item {
            text-align: center;
            padding: 0 20px;
        }
        
        .stat-item:not(:last-child) {
            border-right: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .stat-value {
            font-size: 2.2rem;
            font-weight: 800;
            display: block;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.8;
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: var(--shadow);
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            background-color: rgba(230, 57, 70, 0.1);
            display: flex;
            justify-content: center;
            align-items: center;
            color: var(--primary);
            font-size: 1.8rem;
        }
        
        .stat-content h3 {
            font-size: 1.1rem;
            color: var(--gray);
            margin-bottom: 8px;
        }
        
        .stat-content .value {
            font-size: 2rem;
            font-weight: 800;
            color: var(--dark);
        }
        
        .stat-content .value.sales {
            color: var(--primary);
        }
        
        /* Quick Actions */
        .quick-actions {
            margin-bottom: 40px;
        }
        
        .section-title {
            font-size: 1.8rem;
            color: var(--dark);
            margin-bottom: 25px;
            font-weight: 700;
        }
        
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .action-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            box-shadow: var(--shadow);
            transition: var(--transition);
            cursor: pointer;
            text-decoration: none;
            color: inherit;
        }
        
        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            background-color: var(--primary);
            color: white;
        }
        
        .action-card:hover .action-icon {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
        }
        
        .action-icon {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background-color: rgba(230, 57, 70, 0.1);
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0 auto 20px;
            color: var(--primary);
            font-size: 1.8rem;
            transition: var(--transition);
        }
        
        .action-card h3 {
            font-size: 1.2rem;
            margin-bottom: 10px;
        }
        
        .action-card p {
            font-size: 0.9rem;
            opacity: 0.8;
        }
        
        /* Recent Orders */
        .recent-orders {
            margin-bottom: 40px;
        }
        
        .orders-table {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--shadow);
        }
        
        .table-header {
            display: grid;
            grid-template-columns: 1fr 2fr 1fr 1fr 1fr;
            background-color: var(--light-gray);
            padding: 20px;
            font-weight: 600;
            color: var(--dark);
        }
        
        .table-row {
            display: grid;
            grid-template-columns: 1fr 2fr 1fr 1fr 1fr;
            padding: 20px;
            border-bottom: 1px solid var(--light-gray);
            align-items: center;
            transition: var(--transition);
        }
        
        .table-row:hover {
            background-color: #f9f9f9;
        }
        
        .table-row:last-child {
            border-bottom: none;
        }
        
        .status-badge {
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-pending {
            background-color: rgba(233, 196, 106, 0.2);
            color: #b38b00;
        }
        
        .status-confirmed {
            background-color: rgba(230, 57, 70, 0.2);
            color: var(--primary);
        }
        
        .status-completed {
            background-color: rgba(111, 207, 151, 0.2);
            color: #1a936f;
        }
        
        .status-cancelled {
            background-color: rgba(230, 57, 70, 0.2);
            color: var(--primary);
        }
        
        .view-all {
            text-align: center;
            padding: 20px;
            background: white;
            border-radius: 0 0 15px 15px;
        }
        
        .view-all a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        /* Meal Card Styles (from manage-meals.php) */
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
        
        /* Animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .stat-card, .action-card, .meal-card {
            animation: fadeIn 0.5s ease forwards;
        }
        
        /* Responsive Design */
        @media (max-width: 1200px) {
            .welcome-stats {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .stat-item {
                border: none !important;
            }
            
            .footer-content {
                grid-template-columns: repeat(2, 1fr);
                gap: 30px;
            }
        }
        
        @media (max-width: 992px) {
            .welcome-content {
                flex-direction: column;
                text-align: center;
            }
            
            .meals-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .table-header, .table-row {
                grid-template-columns: 1fr 1fr;
            }
            
            .table-header .mobile-hide,
            .table-row .mobile-hide {
                display: none;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
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
            
            .welcome-text h1 {
                font-size: 2rem;
            }
            
            .stat-value {
                font-size: 1.8rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .actions-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .meals-grid {
                grid-template-columns: 1fr;
            }
            
            /* Mobile dropdown adjustments */
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
            
            /* Modal adjustments */
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
        }
        
        @media (max-width: 576px) {
            .stats-grid,
            .actions-grid {
                grid-template-columns: 1fr;
            }
            
            .welcome-stats {
                flex-direction: column;
                gap: 15px;
            }
            
            .stat-item {
                padding: 0;
            }
            
            .table-header, .table-row {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            
            .table-row {
                padding: 15px;
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
            
            .modal-header,
            .modal-body,
            .modal-footer {
                padding: 20px 15px;
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
                <a href="seller-homepage.php" class="active">Dashboard</a>
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
                        <?php if (!empty($seller_image)): ?>
                            <img src="<?php echo $seller_image; ?>" alt="<?php echo $seller_name; ?>">
                        <?php else: ?>
                            <?php echo $initial; ?>
                        <?php endif; ?>
                    </div>
                    <div class="dropdown-menu" id="dropdownMenu">
                        <div class="dropdown-header">
                            <div class="user-info">
                                <div class="user-initial">
                                    <?php if (!empty($seller_image)): ?>
                                        <img src="<?php echo $seller_image; ?>" alt="<?php echo $seller_name; ?>">
                                    <?php else: ?>
                                        <?php echo $initial; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="user-details">
                                    <div class="user-name"><?php echo $seller_name; ?></div>
                                    <div class="user-email"><?php echo $seller_email; ?></div>
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

<!-- Welcome Section -->
<section class="welcome-section">
    <div class="container">
        <div class="welcome-content">
            <div class="welcome-text">
                <h1>Welcome back, <?php echo htmlspecialchars($first_name); ?>!</h1>
                <p>Manage your meals, track orders, and grow your home-cooked food business.</p>
            </div>
            <div class="welcome-stats">
                <div class="stat-item">
                    <span class="stat-value">₱<?php echo number_format($stats['total_sales'], 2); ?></span>
                    <span class="stat-label">Total Sales</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value"><?php echo $stats['total_items_sold']; ?></span>
                    <span class="stat-label">Items Sold</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value"><?php echo $stats['total_meals']; ?></span>
                    <span class="stat-label">Total Meals</span>
                </div>
            </div>
        </div>
    </div>
</section>

<main class="container">
    <!-- Stats Overview -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-utensils"></i>
            </div>
            <div class="stat-content">
                <h3>Available Meals</h3>
                <div class="value"><?php echo $stats['available_meals']; ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-shopping-cart"></i>
            </div>
            <div class="stat-content">
                <h3>Pending Orders</h3>
                <div class="value"><?php echo $pending_count; ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="stat-content">
                <h3>This Month Sales</h3>
                <div class="value sales">₱<?php echo number_format($stats['total_sales'], 2); ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-content">
                <h3>Performance</h3>
                <div class="value"><?php echo $stats['total_meals'] > 0 ? ceil(($stats['available_meals'] / $stats['total_meals']) * 100) : 0; ?>%</div>
            </div>
        </div>
    </div>

    <!-- Recent Orders -->
    <section class="recent-orders">
        <h2 class="section-title">Recent Orders</h2>
        <div class="orders-table">
            <div class="table-header">
                <div>Order ID</div>
                <div>Customer / Items</div>
                <div class="mobile-hide">Date</div>
                <div>Status</div>
                <div>Amount</div>
            </div>
            <?php if (count($recent_orders) > 0): ?>
                <?php foreach ($recent_orders as $order): ?>
                    <div class="table-row">
                        <div><strong>#<?php echo str_pad($order['OrderID'], 6, '0', STR_PAD_LEFT); ?></strong></div>
                        <div>
                            <div><strong><?php echo htmlspecialchars($order['CustomerName']); ?></strong></div>
                            <div style="font-size: 0.9rem; color: var(--gray);"><?php echo htmlspecialchars($order['Items']); ?></div>
                        </div>
                        <div class="mobile-hide"><?php echo date('M d, Y', strtotime($order['OrderDate'])); ?></div>
                        <div>
                            <span class="status-badge status-<?php echo strtolower($order['Status']); ?>">
                                <?php echo $order['Status']; ?>
                            </span>
                        </div>
                        <div><strong>₱<?php echo number_format($order['TotalAmount'], 2); ?></strong></div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="table-row">
                    <div colspan="5" style="grid-column: 1/-1; text-align: center; padding: 40px;">
                        <div style="font-size: 1.5rem; color: #ddd; margin-bottom: 15px;">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <h3 style="color: var(--gray); margin-bottom: 10px;">No orders yet</h3>
                        <p style="color: var(--gray);">Your orders will appear here</p>
                    </div>
                </div>
            <?php endif; ?>
            <div class="view-all">
                <a href="seller-orders.php">
                    <i class="fas fa-arrow-right"></i> View All Orders
                </a>
            </div>
        </div>
    </section>

    <!-- Recent Meals (Updated with manage-meals.php design) -->
    <section class="recent-meals">
        <h2 class="section-title">Your Recent Meals</h2>
        <div class="meals-grid">
            <?php if (count($recent_meals) > 0): ?>
                <?php foreach ($recent_meals as $meal): ?>
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
                            
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-utensils"></i>
                    </div>
                    <h3>No meals found</h3>
                    <p>You haven't added any meals yet. Start by adding your first meal!</p>
                    <a href="manage-meals.php" class="btn btn-primary" style="margin-top: 15px;">
                        <i class="fas fa-plus-circle"></i> Add Your First Meal
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <?php 
        // Count total meals to show "View All" button if there are more than 4
        $total_meals_sql = "SELECT COUNT(*) as total FROM Meal WHERE SellerID = ?";
        $conn = new mysqli($servername, $username, $password, $dbname, $port);
        $stmt = $conn->prepare($total_meals_sql);
        $stmt->bind_param("i", $seller_id);
        $stmt->execute();
        $total_result = $stmt->get_result();
        $total_data = $total_result->fetch_assoc();
        $total_meals = $total_data['total'] ?: 0;
        $stmt->close();
        $conn->close();
        
        if ($total_meals > 4): ?>
            <div class="view-all" style="text-align: center; margin-top: 30px;">
                <a href="manage-meals.php" style="background-color: var(--primary); color: white; padding: 12px 30px; border-radius: 50px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 8px;">
                    <i class="fas fa-list"></i> View All Meals
                </a>
            </div>
        <?php endif; ?>
    </section>
</main>

<!-- Edit Meal Modal -->
<div id="editMealModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Edit Meal</h3>
            <button class="close-modal-btn" onclick="hideEditMealModal()">&times;</button>
        </div>
        
        <form method="POST" action="manage-meals.php" enctype="multipart/form-data" id="editMealForm">
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
            <form method="POST" action="manage-meals.php" id="deleteForm" style="width: 100%;">
                <input type="hidden" name="meal_id" id="mealIdToDelete">
                <input type="hidden" name="delete_meal" value="1">
                <div style="display: flex; gap: 15px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="hideDeleteModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Delete Meal
                    </button>
                </div>
            </form>
        </div>
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
                    
                    // Update pending count in stats
                    const pendingStat = document.querySelector('.stat-content .value:nth-child(2)');
                    if (pendingStat) {
                        pendingStat.textContent = pendingCount;
                    }
                }
            })
            .catch(error => console.error('Error refreshing notifications:', error));
    }

    // Start auto-refresh
    setInterval(refreshNotificationBadge, 30000); // Refresh every 30 seconds

    // Logout confirmation
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
                <div style="font-size: 3rem; color: var(--danger); margin-bottom: 15px;">
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
                <button id="confirmLogout" style="padding: 12px 30px; background-color: var(--danger); border: none; border-radius: 50px; font-weight: 600; cursor: pointer; transition: var(--transition); color: white;">
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

    // Show notification
    function showNotification(message, type = 'success') {
        const notification = document.createElement('div');
        notification.textContent = message;
        
        const bgColor = type === 'success' ? 'var(--success)' : type === 'warning' ? 'var(--warning)' : 'var(--danger)';
        
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

    // Delete modal functions
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

    // Edit meal modal functions
    function showEditMealModal(mealId) {
        // Fetch meal data via AJAX
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
        document.body.style.overflow = 'auto';
    }

    // Close modals when clicking outside
    document.addEventListener('click', function(event) {
        const deleteModal = document.getElementById('deleteModal');
        const editMealModal = document.getElementById('editMealModal');
        const notificationModal = document.getElementById('notificationModal');
        
        if (event.target === deleteModal) {
            hideDeleteModal();
        }
        
        if (event.target === editMealModal) {
            hideEditMealModal();
        }
        
        if (event.target === notificationModal) {
            notificationModal.classList.remove('show');
            document.body.style.overflow = 'auto';
        }
    });

    // Close modals with Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            hideDeleteModal();
            hideEditMealModal();
            notificationModal.classList.remove('show');
            document.body.style.overflow = 'auto';
        }
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

    // Animate pending badge when there are new orders
    if (pendingBadge) {
        pendingBadge.style.animation = 'pulse 2s infinite';
        setTimeout(() => {
            pendingBadge.style.animation = '';
        }, 3000);
    }
</script>

</body>
</html>