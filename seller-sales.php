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

// Get date range parameters
$date_range = isset($_GET['date_range']) ? $_GET['date_range'] : 'this_month';
$custom_from = isset($_GET['custom_from']) ? $_GET['custom_from'] : '';
$custom_to = isset($_GET['custom_to']) ? $_GET['custom_to'] : '';

// Calculate date range
$now = new DateTime();
switch ($date_range) {
    case 'today':
        $start_date = $now->format('Y-m-d');
        $end_date = $now->format('Y-m-d');
        break;
    case 'yesterday':
        $yesterday = clone $now;
        $yesterday->modify('-1 day');
        $start_date = $yesterday->format('Y-m-d');
        $end_date = $yesterday->format('Y-m-d');
        break;
    case 'this_week':
        $start_date = $now->modify('this week')->format('Y-m-d');
        $end_date = $now->modify('+6 days')->format('Y-m-d');
        break;
    case 'last_week':
        $start_date = $now->modify('last week')->format('Y-m-d');
        $end_date = $now->modify('+6 days')->format('Y-m-d');
        break;
    case 'this_month':
        $start_date = $now->format('Y-m-01');
        $end_date = $now->format('Y-m-t');
        break;
    case 'last_month':
        $start_date = $now->modify('first day of last month')->format('Y-m-d');
        $end_date = $now->modify('last day of last month')->format('Y-m-d');
        break;
    case 'this_year':
        $start_date = $now->format('Y-01-01');
        $end_date = $now->format('Y-12-31');
        break;
    case 'custom':
        $start_date = $custom_from;
        $end_date = $custom_to;
        break;
    default:
        $start_date = $now->format('Y-m-01');
        $end_date = $now->format('Y-m-t');
        break;
}

// Initialize variables
$pending_count = 0;
$pending_orders = [];

// Get pending orders count for notification badge
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
$pending_count = $pending_data['pending_count'] ?? 0;
$pending_stmt->close();

// Get all pending orders for notification modal
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

// Initialize summary data with default values
$summary = [
    'total_orders' => 0,
    'total_customers' => 0,
    'total_sales' => 0,
    'average_order_value' => 0,
    'total_items_sold' => 0
];

// Get overall sales summary
$summary_sql = "SELECT 
    COUNT(DISTINCT o.OrderID) as total_orders,
    COUNT(DISTINCT c.CustomerID) as total_customers,
    COALESCE(SUM(od.Subtotal), 0) as total_sales,
    COALESCE(AVG(od.Subtotal), 0) as average_order_value,
    COALESCE(SUM(od.Quantity), 0) as total_items_sold
    FROM `Order` o
    JOIN OrderDetails od ON o.OrderID = od.OrderID
    JOIN Meal m ON od.MealID = m.MealID
    JOIN Customer c ON o.CustomerID = c.CustomerID
    WHERE m.SellerID = ? 
    AND o.Status IN ('Confirmed', 'Completed')
    AND DATE(o.OrderDate) BETWEEN ? AND ?";
    
$summary_stmt = $conn->prepare($summary_sql);
$summary_stmt->bind_param("iss", $seller_id, $start_date, $end_date);
$summary_stmt->execute();
$summary_result = $summary_stmt->get_result();
if ($summary_result) {
    $summary = $summary_result->fetch_assoc();
    if (!$summary) {
        $summary = [
            'total_orders' => 0,
            'total_customers' => 0,
            'total_sales' => 0,
            'average_order_value' => 0,
            'total_items_sold' => 0
        ];
    }
}
$summary_stmt->close();

// Get sales by meal category
$category_data = [];
$total_category_sales = 0;

$category_sql = "SELECT 
    m.Category,
    COUNT(od.OrderDetailID) as order_count,
    SUM(od.Quantity) as total_quantity,
    SUM(od.Subtotal) as total_sales
    FROM OrderDetails od
    JOIN Meal m ON od.MealID = m.MealID
    JOIN `Order` o ON od.OrderID = o.OrderID
    WHERE m.SellerID = ? 
    AND o.Status IN ('Confirmed', 'Completed')
    AND DATE(o.OrderDate) BETWEEN ? AND ?
    GROUP BY m.Category
    ORDER BY total_sales DESC";
    
$category_stmt = $conn->prepare($category_sql);
$category_stmt->bind_param("iss", $seller_id, $start_date, $end_date);
$category_stmt->execute();
$category_result = $category_stmt->get_result();
if ($category_result) {
    while($row = $category_result->fetch_assoc()) {
        $category_data[] = $row;
        $total_category_sales += $row['total_sales'];
    }
}
$category_stmt->close();

// Get top selling meals
$top_meals = [];

$top_meals_sql = "SELECT 
    m.MealID,
    m.Title,
    m.Category,
    m.Price,
    COUNT(od.OrderDetailID) as times_ordered,
    SUM(od.Quantity) as total_quantity,
    SUM(od.Subtotal) as total_sales
    FROM OrderDetails od
    JOIN Meal m ON od.MealID = m.MealID
    JOIN `Order` o ON od.OrderID = o.OrderID
    WHERE m.SellerID = ? 
    AND o.Status IN ('Confirmed', 'Completed')
    AND DATE(o.OrderDate) BETWEEN ? AND ?
    GROUP BY m.MealID
    ORDER BY total_sales DESC
    LIMIT 10";
    
$top_meals_stmt = $conn->prepare($top_meals_sql);
$top_meals_stmt->bind_param("iss", $seller_id, $start_date, $end_date);
$top_meals_stmt->execute();
$top_meals_result = $top_meals_stmt->get_result();
if ($top_meals_result) {
    while($row = $top_meals_result->fetch_assoc()) {
        $top_meals[] = $row;
    }
}
$top_meals_stmt->close();

// Get sales trend (daily for selected period)
$sales_trend = [];
$labels = [];
$sales_data = [];
$orders_data = [];

$trend_sql = "SELECT 
    DATE(o.OrderDate) as sale_date,
    COUNT(DISTINCT o.OrderID) as order_count,
    SUM(od.Subtotal) as daily_sales,
    SUM(od.Quantity) as items_sold
    FROM `Order` o
    JOIN OrderDetails od ON o.OrderID = od.OrderID
    JOIN Meal m ON od.MealID = m.MealID
    WHERE m.SellerID = ? 
    AND o.Status IN ('Confirmed', 'Completed')
    AND DATE(o.OrderDate) BETWEEN ? AND ?
    GROUP BY DATE(o.OrderDate)
    ORDER BY sale_date ASC";
    
$trend_stmt = $conn->prepare($trend_sql);
$trend_stmt->bind_param("iss", $seller_id, $start_date, $end_date);
$trend_stmt->execute();
$trend_result = $trend_stmt->get_result();
if ($trend_result) {
    while($row = $trend_result->fetch_assoc()) {
        $sales_trend[] = $row;
        $labels[] = date('M d', strtotime($row['sale_date']));
        $sales_data[] = floatval($row['daily_sales']);
        $orders_data[] = intval($row['order_count']);
    }
}
$trend_stmt->close();

// Get customer statistics
$top_customers = [];

$customer_sql = "SELECT 
    c.CustomerID,
    c.FullName,
    c.Email,
    COUNT(DISTINCT o.OrderID) as order_count,
    SUM(od.Subtotal) as total_spent,
    MAX(o.OrderDate) as last_order_date
    FROM Customer c
    JOIN `Order` o ON c.CustomerID = o.CustomerID
    JOIN OrderDetails od ON o.OrderID = od.OrderID
    JOIN Meal m ON od.MealID = m.MealID
    WHERE m.SellerID = ? 
    AND o.Status IN ('Confirmed', 'Completed')
    AND DATE(o.OrderDate) BETWEEN ? AND ?
    GROUP BY c.CustomerID
    ORDER BY total_spent DESC
    LIMIT 10";
    
$customer_stmt = $conn->prepare($customer_sql);
$customer_stmt->bind_param("iss", $seller_id, $start_date, $end_date);
$customer_stmt->execute();
$customer_result = $customer_stmt->get_result();
if ($customer_result) {
    while($row = $customer_result->fetch_assoc()) {
        $top_customers[] = $row;
    }
}
$customer_stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Report | LutongBahay Seller</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            background-color: #f5f7fa;
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
        
        /* Page Header */
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
        
        /* Date Range Selector */
        .date-range-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }
        
        .date-range-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
        
        .btn-export {
            background-color: var(--primary);
            color: white;
        }
        
        .btn-export:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        .btn-sm {
            padding: 8px 15px;
            font-size: 0.9rem;
        }
        
        /* Summary Stats */
        .summary-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
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
            font-size: 1rem;
            color: var(--gray);
            margin-bottom: 5px;
        }
        
        .stat-content .value {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--dark);
        }
        
        .stat-content .value.sales {
            color: var(--primary);
        }
        
        .stat-content .trend {
            font-size: 0.9rem;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .trend.up {
            color: var(--success);
        }
        
        .trend.down {
            color: var(--danger);
        }
        
        /* Charts Section */
        .charts-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .chart-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: var(--shadow);
        }
        
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .chart-header h3 {
            font-size: 1.3rem;
            color: var(--dark);
            font-weight: 700;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        
        /* Data Tables */
        .data-tables {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .data-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: var(--shadow);
        }
        
        .data-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .data-header h3 {
            font-size: 1.3rem;
            color: var(--dark);
            font-weight: 700;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th {
            background-color: var(--light-gray);
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: var(--dark);
            border-bottom: 2px solid #ddd;
        }
        
        .data-table td {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .data-table tr:last-child td {
            border-bottom: none;
        }
        
        .data-table tr:hover {
            background-color: #f9f9f9;
        }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background-color: #eee;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 5px;
        }
        
        .progress-fill {
            height: 100%;
            background-color: var(--primary);
            border-radius: 4px;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background-color: white;
            border-radius: 15px;
            box-shadow: var(--shadow);
            margin-bottom: 40px;
            grid-column: 1/-1;
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
        
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }
        
        /* Responsive Design */
        @media (max-width: 1200px) {
            .footer-content {
                grid-template-columns: repeat(2, 1fr);
                gap: 30px;
            }
        }
        
        @media (max-width: 992px) {
            .charts-section,
            .data-tables {
                grid-template-columns: 1fr;
            }
            
            .header-content {
                flex-direction: column;
                text-align: center;
            }
            
            .date-range-grid {
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
            
            .summary-stats {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .chart-container {
                height: 250px;
            }
            
            .data-table {
                display: block;
                overflow-x: auto;
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
        }
        
        @media (max-width: 576px) {
            .summary-stats {
                grid-template-columns: 1fr;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .date-range-section {
                padding: 20px;
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
                <a href="manage-meals.php">Manage Meals</a>
                <a href="seller-orders.php">Orders</a>
                <a href="seller-sales.php" class="active">Sales Report</a>
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
                        <?php echo strtoupper(substr($seller['FullName'], 0, 1)); ?>
                    </div>
                    <div class="dropdown-menu" id="dropdownMenu">
                        <div class="dropdown-header">
                            <div class="user-info">
                                <div class="user-initial"><?php echo strtoupper(substr($seller['FullName'], 0, 1)); ?></div>
                                <div class="user-details">
                                    <div class="user-name"><?php echo htmlspecialchars($seller['FullName']); ?></div>
                                    <div class="user-email"><?php echo htmlspecialchars($seller['Email']); ?></div>
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

<!-- Page Header -->
<section class="page-header">
    <div class="container">
        <div class="header-content">
            <div class="header-text">
                <h1>Sales Report & Analytics</h1>
                <p>Track your sales performance and business growth from <?php echo date('F d, Y', strtotime($start_date)); ?> to <?php echo date('F d, Y', strtotime($end_date)); ?></p>
            </div>
        </div>
    </div>
</section>

<main class="container">
    <!-- Date Range Selector -->
    <section class="date-range-section">
        <form method="GET" action="" id="dateRangeForm">
            <div class="date-range-grid">
                <div class="form-group">
                    <label for="date_range"><i class="fas fa-calendar-alt"></i> Date Range</label>
                    <select id="date_range" name="date_range" class="form-control" onchange="toggleCustomDates()">
                        <option value="today" <?php echo $date_range === 'today' ? 'selected' : ''; ?>>Today</option>
                        <option value="yesterday" <?php echo $date_range === 'yesterday' ? 'selected' : ''; ?>>Yesterday</option>
                        <option value="this_week" <?php echo $date_range === 'this_week' ? 'selected' : ''; ?>>This Week</option>
                        <option value="last_week" <?php echo $date_range === 'last_week' ? 'selected' : ''; ?>>Last Week</option>
                        <option value="this_month" <?php echo $date_range === 'this_month' ? 'selected' : ''; ?>>This Month</option>
                        <option value="last_month" <?php echo $date_range === 'last_month' ? 'selected' : ''; ?>>Last Month</option>
                        <option value="this_year" <?php echo $date_range === 'this_year' ? 'selected' : ''; ?>>This Year</option>
                        <option value="custom" <?php echo $date_range === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                    </select>
                </div>
                
                <div class="form-group" id="customFromGroup" style="<?php echo $date_range !== 'custom' ? 'display: none;' : ''; ?>">
                    <label for="custom_from">From Date</label>
                    <input type="date" id="custom_from" name="custom_from" class="form-control" 
                           value="<?php echo htmlspecialchars($custom_from); ?>">
                </div>
                
                <div class="form-group" id="customToGroup" style="<?php echo $date_range !== 'custom' ? 'display: none;' : ''; ?>">
                    <label for="custom_to">To Date</label>
                    <input type="date" id="custom_to" name="custom_to" class="form-control" 
                           value="<?php echo htmlspecialchars($custom_to); ?>">
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Apply Range
                    </button>
                    <button type="button" class="btn btn-export" onclick="exportSalesReport()">
                        <i class="fas fa-download"></i> Export Report
                    </button>
                </div>
            </div>
        </form>
    </section>

    <!-- Summary Statistics -->
    <div class="summary-stats">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="stat-content">
                <h3>Total Sales</h3>
                <div class="value sales">₱<?php echo number_format($summary['total_sales'], 2); ?></div>
                <div class="trend up">
                    <i class="fas fa-arrow-up"></i>
                    <span>From selected period</span>
                </div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-shopping-cart"></i>
            </div>
            <div class="stat-content">
                <h3>Total Orders</h3>
                <div class="value"><?php echo number_format($summary['total_orders']); ?></div>
                <div class="trend up">
                    <i class="fas fa-arrow-up"></i>
                    <span><?php echo number_format($summary['total_orders']); ?> orders</span>
                </div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-content">
                <h3>Total Customers</h3>
                <div class="value"><?php echo number_format($summary['total_customers']); ?></div>
                <div class="trend up">
                    <i class="fas fa-arrow-up"></i>
                    <span><?php echo number_format($summary['total_customers']); ?> unique customers</span>
                </div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-content">
                <h3>Avg. Order Value</h3>
                <div class="value">₱<?php echo number_format($summary['average_order_value'], 2); ?></div>
                <div class="trend up">
                    <i class="fas fa-arrow-up"></i>
                    <span>Per order average</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <section class="charts-section">
        <!-- Sales Trend Chart -->
        <div class="chart-card">
            <div class="chart-header">
                <h3>Sales Trend</h3>
                <span class="btn btn-sm btn-secondary"><?php echo count($sales_trend); ?> days</span>
            </div>
            <div class="chart-container">
                <canvas id="salesTrendChart"></canvas>
            </div>
        </div>
        
        <!-- Category Sales Chart -->
        <div class="chart-card">
            <div class="chart-header">
                <h3>Sales by Category</h3>
                <span class="btn btn-sm btn-secondary"><?php echo count($category_data); ?> categories</span>
            </div>
            <div class="chart-container">
                <canvas id="categoryChart"></canvas>
            </div>
        </div>
    </section>

    <!-- Data Tables Section -->
    <section class="data-tables">
        <!-- Top Selling Meals -->
        <div class="data-card">
            <div class="data-header">
                <h3>Top Selling Meals</h3>
                <span class="btn btn-sm btn-secondary">Top 10</span>
            </div>
            <?php if (count($top_meals) > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Meal</th>
                            <th>Category</th>
                            <th>Quantity</th>
                            <th>Sales</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_meals as $meal): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($meal['Title']); ?></div>
                                    <div style="font-size: 0.9rem; color: var(--gray);">₱<?php echo number_format($meal['Price'], 2); ?> each</div>
                                </td>
                                <td><?php echo $meal['Category']; ?></td>
                                <td><?php echo $meal['total_quantity']; ?></td>
                                <td style="font-weight: 700; color: var(--primary);">₱<?php echo number_format($meal['total_sales'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: var(--gray);">
                    <i class="fas fa-utensils" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.3;"></i>
                    <p>No sales data for this period</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Top Customers -->
        <div class="data-card">
            <div class="data-header">
                <h3>Top Customers</h3>
                <span class="btn btn-sm btn-secondary">Top 10</span>
            </div>
            <?php if (count($top_customers) > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Orders</th>
                            <th>Total Spent</th>
                            <th>Last Order</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_customers as $customer): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($customer['FullName']); ?></div>
                                    <div style="font-size: 0.9rem; color: var(--gray);"><?php echo htmlspecialchars($customer['Email']); ?></div>
                                </td>
                                <td><?php echo $customer['order_count']; ?></td>
                                <td style="font-weight: 700; color: var(--primary);">₱<?php echo number_format($customer['total_spent'], 2); ?></td>
                                <td><?php echo date('M d, Y', strtotime($customer['last_order_date'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: var(--gray);">
                    <i class="fas fa-users" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.3;"></i>
                    <p>No customer data for this period</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Category Breakdown -->
    <section class="data-card" style="margin-bottom: 30px;">
        <div class="data-header">
            <h3>Category Performance</h3>
            <span class="btn btn-sm btn-secondary"><?php echo count($category_data); ?> categories</span>
        </div>
        <?php if (count($category_data) > 0): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Orders</th>
                        <th>Quantity Sold</th>
                        <th>Total Sales</th>
                        <th>Percentage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($category_data as $category): 
                        $percentage = $total_category_sales > 0 ? ($category['total_sales'] / $total_category_sales) * 100 : 0;
                    ?>
                        <tr>
                            <td style="font-weight: 600;"><?php echo $category['Category']; ?></td>
                            <td><?php echo $category['order_count']; ?></td>
                            <td><?php echo $category['total_quantity']; ?></td>
                            <td style="font-weight: 700; color: var(--primary);">₱<?php echo number_format($category['total_sales'], 2); ?></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <span><?php echo number_format($percentage, 1); ?>%</span>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $percentage; ?>%;"></div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div style="text-align: center; padding: 40px; color: var(--gray);">
                <i class="fas fa-chart-pie" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.3;"></i>
                <p>No category data for this period</p>
            </div>
        <?php endif; ?>
    </section>
</main>

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
    const dateRangeForm = document.getElementById('dateRangeForm');
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
                    Are you sure you want to logout from Seller Dashboard?
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
        
        document.getElementById('cancelLogout').addEventListener('click', function() {
            document.body.removeChild(confirmModal);
        });
        
        document.getElementById('confirmLogout').addEventListener('click', function() {
            window.location.href = 'logout.php';
        });
        
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

    // Toggle custom date inputs
    function toggleCustomDates() {
        const dateRange = document.getElementById('date_range').value;
        const fromGroup = document.getElementById('customFromGroup');
        const toGroup = document.getElementById('customToGroup');
        
        if (dateRange === 'custom') {
            fromGroup.style.display = 'block';
            toGroup.style.display = 'block';
        } else {
            fromGroup.style.display = 'none';
            toGroup.style.display = 'none';
        }
    }

    // Validate date range
    document.addEventListener('DOMContentLoaded', function() {
        const fromInput = document.getElementById('custom_from');
        const toInput = document.getElementById('custom_to');
        
        if (fromInput && toInput) {
            fromInput.addEventListener('change', function() {
                if (toInput.value && this.value > toInput.value) {
                    alert('From date cannot be later than To date');
                    this.value = '';
                }
            });
            
            toInput.addEventListener('change', function() {
                if (fromInput.value && this.value < fromInput.value) {
                    alert('To date cannot be earlier than From date');
                    this.value = '';
                }
            });
        }
    });

    // Export function
    function exportSalesReport() {
        showNotification('Preparing sales report for download...', 'success');
        // In a real implementation, this would trigger a server-side export
        setTimeout(() => {
            showNotification('Report generated! Starting download...', 'success');
        }, 1500);
    }

    // Initialize Charts
    document.addEventListener('DOMContentLoaded', function() {
        // Sales Trend Chart
        const salesTrendCtx = document.getElementById('salesTrendChart').getContext('2d');
        const salesTrendChart = new Chart(salesTrendCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($labels); ?>,
                datasets: [
                    {
                        label: 'Daily Sales (₱)',
                        data: <?php echo json_encode($sales_data); ?>,
                        borderColor: 'rgb(230, 57, 70)',
                        backgroundColor: 'rgba(230, 57, 70, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Orders',
                        data: <?php echo json_encode($orders_data); ?>,
                        borderColor: 'rgb(244, 162, 97)',
                        backgroundColor: 'rgba(244, 162, 97, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₱' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Category Chart
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        
        // Prepare category data
        const categoryLabels = <?php echo json_encode(array_column($category_data, 'Category')); ?>;
        const categorySales = <?php echo json_encode(array_column($category_data, 'total_sales')); ?>;
        const categoryColors = [
            'rgba(230, 57, 70, 0.8)',
            'rgba(244, 162, 97, 0.8)',
            'rgba(233, 196, 106, 0.8)',
            'rgba(42, 157, 143, 0.8)',
            'rgba(111, 207, 151, 0.8)'
        ];

        const categoryChart = new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: categoryLabels,
                datasets: [{
                    data: categorySales,
                    backgroundColor: categoryColors,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                label += '₱' + context.parsed.toLocaleString();
                                return label;
                            }
                        }
                    }
                }
            }
        });

        // Update charts on window resize
        window.addEventListener('resize', function() {
            salesTrendChart.resize();
            categoryChart.resize();
        });
    });
</script>

</body>
</html>