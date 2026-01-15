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

// Initialize seller variables with default values
$seller_name = isset($seller['FullName']) ? $seller['FullName'] : 'Seller';
$seller_email = isset($seller['Email']) ? $seller['Email'] : '';
$initial = isset($seller['FullName']) ? strtoupper(substr($seller['FullName'], 0, 1)) : 'S';

// Handle AJAX order status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status']) && isset($_POST['ajax'])) {
    $order_id = $_POST['order_id'];
    $new_status = $_POST['status'];
    
    // Verify the order contains seller's meals
    $verify_sql = "SELECT od.OrderID FROM OrderDetails od
                   JOIN Meal m ON od.MealID = m.MealID
                   WHERE od.OrderID = ? AND m.SellerID = ?";
    $verify_stmt = $conn->prepare($verify_sql);
    $verify_stmt->bind_param("ii", $order_id, $seller_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    
    $response = ['success' => false, 'message' => ''];
    
    if ($verify_result->num_rows > 0) {
        // Update order status
        $update_sql = "UPDATE `Order` SET Status = ? WHERE OrderID = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("si", $new_status, $order_id);
        
        if ($update_stmt->execute()) {
            $response['success'] = true;
            $response['message'] = "Order #" . str_pad($order_id, 6, '0', STR_PAD_LEFT) . " status updated to $new_status!";
            $response['new_status'] = $new_status;
            
            // If order is completed, update meal sales counts
            if ($new_status === 'Completed') {
                // This could trigger any post-completion logic
            }
        } else {
            $response['message'] = "Error updating order: " . $conn->error;
        }
        $update_stmt->close();
    } else {
        $response['message'] = "Order not found or unauthorized!";
    }
    $verify_stmt->close();
    
    // Return JSON response for AJAX request
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Handle regular form submission (non-AJAX fallback)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status']) && !isset($_POST['ajax'])) {
    $order_id = $_POST['order_id'];
    $new_status = $_POST['status'];
    
    // Verify the order contains seller's meals
    $verify_sql = "SELECT od.OrderID FROM OrderDetails od
                   JOIN Meal m ON od.MealID = m.MealID
                   WHERE od.OrderID = ? AND m.SellerID = ?";
    $verify_stmt = $conn->prepare($verify_sql);
    $verify_stmt->bind_param("ii", $order_id, $seller_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    
    if ($verify_result->num_rows > 0) {
        // Update order status
        $update_sql = "UPDATE `Order` SET Status = ? WHERE OrderID = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("si", $new_status, $order_id);
        
        if ($update_stmt->execute()) {
            $success_message = "Order #" . str_pad($order_id, 6, '0', STR_PAD_LEFT) . " status updated to $new_status!";
            
            // If order is completed, update meal sales counts
            if ($new_status === 'Completed') {
                // This could trigger any post-completion logic
            }
        } else {
            $error_message = "Error updating order: " . $conn->error;
        }
        $update_stmt->close();
    } else {
        $error_message = "Order not found or unauthorized!";
    }
    $verify_stmt->close();
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'active';

// Build SQL query for seller's orders
$orders_sql = "SELECT DISTINCT o.* 
               FROM `Order` o
               JOIN OrderDetails od ON o.OrderID = od.OrderID
               JOIN Meal m ON od.MealID = m.MealID
               WHERE m.SellerID = ?";
$params = [$seller_id];
$types = "i";

// Define active statuses (all except Completed and Cancelled)
$active_statuses = ['Pending', 'Confirmed', 'Preparing', 'Out for Delivery'];

if ($status_filter === 'active') {
    $orders_sql .= " AND o.Status IN (?, ?, ?, ?)";
    $params = array_merge($params, $active_statuses);
    $types .= "ssss";
} elseif ($status_filter !== 'all') {
    $orders_sql .= " AND o.Status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$orders_sql .= " ORDER BY o.OrderDate DESC";

// Get orders
$stmt = $conn->prepare($orders_sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$orders_result = $stmt->get_result();
$orders = [];
if ($orders_result && $orders_result->num_rows > 0) {
    while($order = $orders_result->fetch_assoc()) {
        $orders[] = $order;
    }
}
$stmt->close();

// Get order details for each order
$order_details = [];
if (count($orders) > 0) {
    foreach($orders as $order) {
        $details_sql = "SELECT od.*, m.Title, m.Price, m.ImagePath, m.SellerID, 
                               s.FullName as SellerName, c.FullName as CustomerName,
                               c.Email as CustomerEmail, c.ContactNo as CustomerContact
                       FROM OrderDetails od 
                       JOIN Meal m ON od.MealID = m.MealID 
                       JOIN Seller s ON m.SellerID = s.SellerID
                       JOIN `Order` o ON od.OrderID = o.OrderID
                       JOIN Customer c ON o.CustomerID = c.CustomerID
                       WHERE od.OrderID = ? AND m.SellerID = ?";
        $stmt = $conn->prepare($details_sql);
        $stmt->bind_param("ii", $order['OrderID'], $seller_id);
        $stmt->execute();
        $details_result = $stmt->get_result();
        $details = [];
        if ($details_result && $details_result->num_rows > 0) {
            while($detail = $details_result->fetch_assoc()) {
                $details[] = $detail;
            }
        }
        $order_details[$order['OrderID']] = $details;
        $stmt->close();
    }
}

// Get order statistics
$stats_sql = "SELECT 
    COUNT(DISTINCT o.OrderID) as total_orders,
    SUM(CASE WHEN o.Status = 'Pending' THEN 1 ELSE 0 END) as pending_orders,
    SUM(CASE WHEN o.Status = 'Confirmed' THEN 1 ELSE 0 END) as confirmed_orders,
    SUM(CASE WHEN o.Status = 'Preparing' THEN 1 ELSE 0 END) as preparing_orders,
    SUM(CASE WHEN o.Status = 'Out for Delivery' THEN 1 ELSE 0 END) as delivery_orders,
    SUM(CASE WHEN o.Status = 'Completed' THEN 1 ELSE 0 END) as completed_orders,
    COALESCE(SUM(od.Subtotal), 0) as total_sales
    FROM `Order` o
    JOIN OrderDetails od ON o.OrderID = od.OrderID
    JOIN Meal m ON od.MealID = m.MealID
    WHERE m.SellerID = ?";
    
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("i", $seller_id);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats_data = $stats_result->fetch_assoc();
$stats = [
    'total_orders' => $stats_data['total_orders'] ?? 0,
    'pending_orders' => $stats_data['pending_orders'] ?? 0,
    'confirmed_orders' => $stats_data['confirmed_orders'] ?? 0,
    'preparing_orders' => $stats_data['preparing_orders'] ?? 0,
    'delivery_orders' => $stats_data['delivery_orders'] ?? 0,
    'completed_orders' => $stats_data['completed_orders'] ?? 0,
    'total_sales' => $stats_data['total_sales'] ?? 0
];
$stats_stmt->close();

// Get active orders count for notification badge
$active_sql = "SELECT COUNT(DISTINCT o.OrderID) as active_count
                FROM `Order` o
                JOIN OrderDetails od ON o.OrderID = od.OrderID
                JOIN Meal m ON od.MealID = m.MealID
                WHERE m.SellerID = ? AND o.Status IN ('Pending', 'Confirmed', 'Preparing', 'Out for Delivery')";
$active_stmt = $conn->prepare($active_sql);
$active_stmt->bind_param("i", $seller_id);
$active_stmt->execute();
$active_result = $active_stmt->get_result();
$active_data = $active_result->fetch_assoc();
$active_count = $active_data ? ($active_data['active_count'] ?? 0) : 0;
$active_stmt->close();

// Get pending orders for notification modal
$pending_count = $stats['pending_orders'] ?? 0;
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
    <title>Manage Orders | LutongBahay Seller</title>
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
        
        /* Order Statistics */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
            gap: 15px;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 1.8rem;
            flex-shrink: 0;
        }
        
        .stat-icon.total { background-color: rgba(244, 162, 97, 0.1); color: var(--secondary); }
        .stat-icon.pending { background-color: rgba(233, 196, 106, 0.1); color: var(--warning); }
        .stat-icon.confirmed { background-color: rgba(230, 57, 70, 0.1); color: var(--primary); }
        .stat-icon.preparing { background-color: rgba(108, 117, 125, 0.1); color: #495057; }
        .stat-icon.delivery { background-color: rgba(42, 157, 143, 0.1); color: var(--success); }
        .stat-icon.completed { background-color: rgba(111, 207, 151, 0.1); color: #1a936f; }
        
        .stat-content {
            flex: 1;
            min-width: 0;
        }
        
        .stat-content h3 {
            font-size: 0.9rem;
            color: var(--gray);
            margin-bottom: 5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .stat-content .value {
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--dark);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .stat-content .value.sales {
            color: var(--primary);
        }
        
        /* Messages - UPDATED */
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
            background-color: var(--success);
            border-left: 4px solid #218674;
            color: white;
        }
        
        .alert-error {
            background-color: rgba(230, 57, 70, 0.1);
            border-left: 4px solid var(--danger);
            color: var(--danger);
        }
        
        /* AJAX Message */
        .ajax-message {
            position: fixed;
            top: 100px;
            right: 20px;
            z-index: 2000;
            max-width: 300px;
            animation: slideInRight 0.3s ease;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            padding: 15px 20px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .ajax-message.success {
            background-color: var(--success);
            color: white;
        }
        
        .ajax-message.error {
            background-color: rgba(230, 57, 70, 0.1);
            border-left: 4px solid var(--danger);
            color: var(--danger);
        }
        
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        /* Orders Controls */
        .orders-controls {
            background-color: white;
            padding: 20px 0;
            border-bottom: 1px solid var(--light-gray);
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.03);
        }
        
        .status-filter {
            display: flex;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .status-btn {
            padding: 10px 20px;
            border-radius: 50px;
            border: 2px solid var(--light-gray);
            background-color: white;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            color: var(--dark);
            text-align: center;
            min-width: 140px;
        }
        
        .status-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .status-btn.active {
            background-color: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        /* Orders Container */
        .orders-container {
            margin-bottom: 60px;
        }
        
        .orders-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .orders-header h2 {
            font-size: 1.8rem;
            color: var(--dark);
        }
        
        .orders-count {
            font-size: 1rem;
            color: var(--gray);
        }
        
        /* Order Cards */
        .order-card {
            background-color: white;
            border-radius: 15px;
            box-shadow: var(--shadow);
            margin-bottom: 25px;
            overflow: hidden;
            transition: var(--transition);
            animation: fadeIn 0.5s ease forwards;
        }
        
        .order-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.12);
        }
        
        .order-header {
            background-color: var(--light-gray);
            padding: 20px 25px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .order-info {
            display: flex;
            flex-direction: column;
            gap: 8px;
            flex: 1;
            min-width: 300px;
        }
        
        .order-id {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--dark);
        }
        
        .order-date {
            font-size: 0.9rem;
            color: var(--gray);
        }
        
        .order-address {
            font-size: 0.9rem;
            color: var(--gray);
        }
        
        .order-status {
            display: inline-block;
            padding: 6px 15px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.9rem;
            margin-left: 10px;
        }
        
        .status-pending {
            background-color: rgba(255, 193, 7, 0.1);
            color: #856404;
        }
        
        .status-confirmed {
            background-color: rgba(23, 162, 184, 0.1);
            color: #0c5460;
        }
        
        .status-preparing {
            background-color: rgba(108, 117, 125, 0.1);
            color: #495057;
        }
        
        .status-outfordelivery {
            background-color: rgba(42, 157, 143, 0.1);
            color: #155724;
        }
        
        .status-completed {
            background-color: rgba(42, 157, 143, 0.2);
            color: #0d3d35;
        }
        
        .status-cancelled {
            background-color: rgba(108, 117, 125, 0.2);
            color: #721c24;
        }
        
        .order-amount {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary);
            text-align: right;
            flex-shrink: 0;
        }
        
        /* Order Progress Bar */
        .order-progress {
            padding: 20px 25px;
            border-top: 1px solid var(--light-gray);
            border-bottom: 1px solid var(--light-gray);
        }
        
        .progress-title {
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--dark);
        }
        
        .progress-bar {
            height: 8px;
            background-color: var(--light-gray);
            border-radius: 10px;
            position: relative;
            overflow: hidden;
            margin-bottom: 10px;
        }
        
        .progress-fill {
            height: 100%;
            background-color: var(--success);
            border-radius: 10px;
            transition: width 0.5s ease;
            position: absolute;
            top: 0;
            left: 0;
        }
        
        .progress-steps {
            display: flex;
            justify-content: space-between;
            position: relative;
            margin-top: 15px;
        }
        
        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 2;
            flex: 1;
            max-width: 100px;
        }
        
        .step-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background-color: var(--light-gray);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            color: var(--gray);
            margin-bottom: 8px;
            transition: var(--transition);
        }
        
        .step.active .step-icon {
            background-color: var(--success);
            color: white;
        }
        
        .step.completed .step-icon {
            background-color: var(--success);
            color: white;
        }
        
        .step-label {
            font-size: 0.8rem;
            text-align: center;
            color: var(--gray);
            max-width: 80px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .step.active .step-label {
            color: var(--success);
            font-weight: 600;
        }
        
        .step.completed .step-label {
            color: var(--success);
        }
        
        .order-items {
            padding: 25px;
        }
        
        .order-item {
            display: flex;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid var(--light-gray);
            gap: 20px;
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .item-image {
            width: 80px;
            height: 80px;
            border-radius: 10px;
            overflow: hidden;
            flex-shrink: 0;
        }
        
        .item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .item-details {
            flex: 1;
        }
        
        .item-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 5px;
        }
        
        .item-seller {
            font-size: 0.9rem;
            color: var(--gray);
            margin-bottom: 5px;
        }
        
        .item-price {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .item-quantity {
            font-size: 0.9rem;
            color: var(--gray);
        }
        
        .order-actions {
            padding: 20px 25px;
            background-color: var(--light-gray);
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            font-size: 0.95rem;
            text-decoration: none;
            display: inline-flex;
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
        
        .btn-outline {
            background-color: transparent;
            color: var(--primary);
            border: 2px solid var(--primary);
        }
        
        .btn-outline:hover {
            background-color: rgba(230, 57, 70, 0.1);
            transform: translateY(-2px);
        }
        
        .btn-success {
            background-color: var(--success);
            color: white;
        }
        
        .btn-success:hover {
            background-color: #218674;
            transform: translateY(-2px);
        }
        
        .btn-warning {
            background-color: var(--warning);
            color: var(--dark);
        }
        
        .btn-warning:hover {
            background-color: #e0a800;
            transform: translateY(-2px);
        }
        
        .btn-info {
            background-color: #17a2b8;
            color: white;
        }
        
        .btn-info:hover {
            background-color: #138496;
            transform: translateY(-2px);
        }
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }
        
        /* No Orders Message */
        .no-orders {
            text-align: center;
            padding: 60px 40px;
            background-color: var(--light-gray);
            border-radius: 15px;
            margin-bottom: 60px;
        }
        
        .no-orders-icon {
            font-size: 3rem;
            color: var(--gray);
            margin-bottom: 20px;
        }
        
        .no-orders h3 {
            font-size: 1.5rem;
            color: var(--gray);
            margin-bottom: 15px;
        }
        
        .no-orders p {
            color: var(--gray);
            margin-bottom: 25px;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }
        
        /* Footer Styles */
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
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Responsive Design */
        @media (max-width: 1200px) {
            .footer-content {
                grid-template-columns: repeat(2, 1fr);
                gap: 30px;
            }
        }
        
        @media (max-width: 992px) {
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
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
            .footer-content {
                grid-template-columns: 1fr;
                gap: 30px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .order-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .order-amount {
                text-align: left;
                align-self: flex-start;
            }
            
            .order-actions {
                justify-content: center;
                flex-wrap: wrap;
            }
            
            .order-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .item-image {
                width: 100%;
                height: 150px;
            }
            
            .status-filter {
                flex-direction: column;
                align-items: center;
            }
            
            .status-btn {
                width: 100%;
                max-width: 300px;
            }
            
            .progress-steps {
                flex-wrap: wrap;
                gap: 10px;
            }
            
            .step {
                min-width: 70px;
                max-width: 90px;
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
        }
        
        @media (max-width: 576px) {
            .nav-container {
                flex-direction: column;
                gap: 15px;
            }
            
            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .user-actions {
                margin-top: 10px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .status-btn {
                padding: 8px 15px;
                font-size: 0.9rem;
            }
            
            .progress-steps {
                justify-content: space-between;
            }
            
            .step-label {
                font-size: 0.7rem;
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
                <a href="seller-orders.php" class="active">Orders</a>
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
                        <?php echo $initial; ?>
                    </div>
                    <div class="dropdown-menu" id="dropdownMenu">
                        <div class="dropdown-header">
                            <div class="user-info">
                                <div class="user-initial"><?php echo $initial; ?></div>
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
                <h1>Manage Orders</h1>
                <p>View and manage customer orders for your meals</p>
            </div>
        </div>
    </div>
</section>

<main class="container">
    <!-- AJAX Message Container -->
    <div id="ajaxMessageContainer"></div>
    
    <!-- Regular Messages -->
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

    <!-- Order Statistics -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon total">
                <i class="fas fa-clipboard-list"></i>
            </div>
            <div class="stat-content">
                <h3>Total Orders</h3>
                <div class="value"><?php echo $stats['total_orders']; ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon pending">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-content">
                <h3>Pending</h3>
                <div class="value"><?php echo $stats['pending_orders']; ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon confirmed">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-content">
                <h3>Confirmed</h3>
                <div class="value"><?php echo $stats['confirmed_orders']; ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon preparing">
                <i class="fas fa-utensils"></i>
            </div>
            <div class="stat-content">
                <h3>Preparing</h3>
                <div class="value"><?php echo $stats['preparing_orders']; ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon delivery">
                <i class="fas fa-shipping-fast"></i>
            </div>
            <div class="stat-content">
                <h3>Out for Delivery</h3>
                <div class="value"><?php echo $stats['delivery_orders']; ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon completed">
                <i class="fas fa-home"></i>
            </div>
            <div class="stat-content">
                <h3>Completed</h3>
                <div class="value"><?php echo $stats['completed_orders']; ?></div>
            </div>
        </div>
    </div>

    <!-- Orders Controls -->
    <section class="orders-controls">
        <div class="container">
            <div class="status-filter">
                <a href="seller-orders.php?status=active" class="status-btn <?php echo $status_filter === 'active' ? 'active' : ''; ?>">
                    <i class="fas fa-clock"></i> Active Orders
                </a>
                <a href="seller-orders.php?status=Completed" class="status-btn <?php echo $status_filter === 'Completed' ? 'active' : ''; ?>">
                    <i class="fas fa-check-circle"></i> Completed
                </a>
                <a href="seller-orders.php?status=Cancelled" class="status-btn <?php echo $status_filter === 'Cancelled' ? 'active' : ''; ?>">
                    <i class="fas fa-times-circle"></i> Cancelled
                </a>
            </div>
        </div>
    </section>

    <!-- Orders Container -->
    <section class="container">
        <div class="orders-container">
            <div class="orders-header">
                <h2>
                    <?php 
                        if ($status_filter === 'active') {
                            echo 'Active Orders';
                        } elseif ($status_filter === 'Completed') {
                            echo 'Completed Orders';
                        } elseif ($status_filter === 'Cancelled') {
                            echo 'Cancelled Orders';
                        } else {
                            echo 'Order History';
                        }
                    ?>
                </h2>
                <div class="orders-count">
                    <?php 
                        $status_text = $status_filter === 'active' ? 'Active' : $status_filter;
                    ?>
                    <?php echo $status_text; ?> Orders: <span style="color: var(--primary); font-weight: 600;"><?php echo count($orders); ?></span>
                </div>
            </div>
            
            <?php if (count($orders) > 0): ?>
                <?php foreach ($orders as $order): ?>
                    <div class="order-card" id="order-<?php echo $order['OrderID']; ?>">
                        <div class="order-header">
                            <div class="order-info">
                                <div>
                                    <span class="order-id">Order #<?php echo str_pad($order['OrderID'], 6, '0', STR_PAD_LEFT); ?></span>
                                    <span class="order-status status-<?php echo strtolower(str_replace(' ', '', $order['Status'])); ?>" id="status-<?php echo $order['OrderID']; ?>">
                                        <?php echo $order['Status']; ?>
                                    </span>
                                </div>
                                <div class="order-date">
                                    <i class="far fa-calendar"></i> 
                                    <?php echo date('F j, Y', strtotime($order['OrderDate'])); ?> at 
                                    <?php echo date('g:i A', strtotime($order['OrderDate'])); ?>
                                </div>
                                <?php if (!empty($order['DeliveryAddress'])): ?>
                                <div class="order-address">
                                    <i class="fas fa-map-marker-alt"></i> 
                                    <?php echo htmlspecialchars(substr($order['DeliveryAddress'], 0, 80)); ?><?php echo strlen($order['DeliveryAddress']) > 80 ? '...' : ''; ?>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Customer Information -->
                                <?php if (isset($order_details[$order['OrderID']]) && count($order_details[$order['OrderID']]) > 0): ?>
                                    <?php $first_item = $order_details[$order['OrderID']][0]; ?>
                                    <div class="order-address">
                                        <i class="fas fa-user"></i> 
                                        Customer: <?php echo htmlspecialchars($first_item['CustomerName']); ?>
                                    </div>
                                    <div class="order-address">
                                        <i class="fas fa-phone"></i> 
                                        Contact: <?php echo htmlspecialchars($first_item['CustomerContact']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="order-amount">
                                <!-- Calculate total for seller's items only -->
                                <?php 
                                $seller_total = 0;
                                if (isset($order_details[$order['OrderID']])) {
                                    foreach ($order_details[$order['OrderID']] as $item) {
                                        $seller_total += $item['Subtotal'];
                                    }
                                }
                                ?>
                                ₱<?php echo number_format($seller_total, 2); ?>
                            </div>
                        </div>
                        
                        <!-- Order Progress Bar - Only for active orders -->
                        <?php if (in_array($order['Status'], ['Pending', 'Confirmed', 'Preparing', 'Out for Delivery'])): ?>
                        <div class="order-progress">
                            <div class="progress-title">Order Progress</div>
                            <div class="progress-bar">
                                <?php 
                                    $progress_width = 0;
                                    if ($order['Status'] === 'Pending') $progress_width = 0;
                                    elseif ($order['Status'] === 'Confirmed') $progress_width = 25;
                                    elseif ($order['Status'] === 'Preparing') $progress_width = 50;
                                    elseif ($order['Status'] === 'Out for Delivery') $progress_width = 75;
                                    elseif ($order['Status'] === 'Completed') $progress_width = 100;
                                ?>
                                <div class="progress-fill" id="progress-<?php echo $order['OrderID']; ?>" style="width: <?php echo $progress_width; ?>%"></div>
                            </div>
                            <div class="progress-steps" id="steps-<?php echo $order['OrderID']; ?>">
                                <div class="step <?php echo in_array($order['Status'], ['Pending', 'Confirmed', 'Preparing', 'Out for Delivery', 'Completed']) ? 'completed' : ''; ?>">
                                    <div class="step-icon">
                                        <i class="fas fa-receipt"></i>
                                    </div>
                                    <div class="step-label">Ordered</div>
                                </div>
                                <div class="step <?php echo in_array($order['Status'], ['Confirmed', 'Preparing', 'Out for Delivery', 'Completed']) ? 'completed' : ''; ?>">
                                    <div class="step-icon">
                                        <i class="fas fa-check"></i>
                                    </div>
                                    <div class="step-label">Confirmed</div>
                                </div>
                                <div class="step <?php echo in_array($order['Status'], ['Preparing', 'Out for Delivery', 'Completed']) ? 'completed' : ''; ?>">
                                    <div class="step-icon">
                                        <i class="fas fa-utensils"></i>
                                    </div>
                                    <div class="step-label">Preparing</div>
                                </div>
                                <div class="step <?php echo in_array($order['Status'], ['Out for Delivery', 'Completed']) ? 'completed' : ''; ?>">
                                    <div class="step-icon">
                                        <i class="fas fa-shipping-fast"></i>
                                    </div>
                                    <div class="step-label">On the way</div>
                                </div>
                                <div class="step <?php echo $order['Status'] === 'Completed' ? 'completed' : ''; ?>">
                                    <div class="step-icon">
                                        <i class="fas fa-home"></i>
                                    </div>
                                    <div class="step-label">Delivered</div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="order-items">
                            <?php if (isset($order_details[$order['OrderID']]) && count($order_details[$order['OrderID']]) > 0): ?>
                                <?php foreach ($order_details[$order['OrderID']] as $item): ?>
                                    <div class="order-item">
                                        <div class="item-image">
                                            <?php if (!empty($item['ImagePath'])): ?>
                                                <img src="<?php echo htmlspecialchars($item['ImagePath']); ?>" alt="<?php echo htmlspecialchars($item['Title']); ?>">
                                            <?php else: ?>
                                                <img src="https://images.unsplash.com/photo-1565958011703-44f9829ba187?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80" alt="<?php echo htmlspecialchars($item['Title']); ?>">
                                            <?php endif; ?>
                                        </div>
                                        <div class="item-details">
                                            <div class="item-title"><?php echo htmlspecialchars($item['Title']); ?></div>
                                            <div class="item-price">₱<?php echo number_format($item['Price'], 2); ?> × <?php echo $item['Quantity']; ?></div>
                                        </div>
                                        <div class="item-quantity">
                                            Subtotal: ₱<?php echo number_format($item['Subtotal'], 2); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <div class="order-actions" id="actions-<?php echo $order['OrderID']; ?>">
                            <?php if ($order['Status'] === 'Pending'): ?>
                                <button type="button" class="btn btn-primary update-status-btn" 
                                        data-order-id="<?php echo $order['OrderID']; ?>" 
                                        data-status="Confirmed" 
                                        data-confirm-msg="Confirm this order?">
                                    <i class="fas fa-check"></i> Confirm Order
                                </button>
                                <button type="button" class="btn btn-outline update-status-btn" 
                                        data-order-id="<?php echo $order['OrderID']; ?>" 
                                        data-status="Cancelled" 
                                        data-confirm-msg="Cancel this order?">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                            <?php elseif ($order['Status'] === 'Confirmed'): ?>
                                <button type="button" class="btn btn-warning update-status-btn" 
                                        data-order-id="<?php echo $order['OrderID']; ?>" 
                                        data-status="Preparing" 
                                        data-confirm-msg="Mark order as preparing?">
                                    <i class="fas fa-utensils"></i> Start Preparing
                                </button>
                                <button type="button" class="btn btn-outline contact-customer-btn" 
                                        data-order-id="<?php echo $order['OrderID']; ?>">
                                    <i class="fas fa-comments"></i> Contact Customer
                                </button>
                            <?php elseif ($order['Status'] === 'Preparing'): ?>
                                <button type="button" class="btn btn-info update-status-btn" 
                                        data-order-id="<?php echo $order['OrderID']; ?>" 
                                        data-status="Out for Delivery" 
                                        data-confirm-msg="Mark order as out for delivery?">
                                    <i class="fas fa-shipping-fast"></i> Out for Delivery
                                </button>
                                <button type="button" class="btn btn-outline contact-customer-btn" 
                                        data-order-id="<?php echo $order['OrderID']; ?>">
                                    <i class="fas fa-comments"></i> Contact Customer
                                </button>
                            <?php elseif ($order['Status'] === 'Out for Delivery'): ?>
                                <button type="button" class="btn btn-success update-status-btn" 
                                        data-order-id="<?php echo $order['OrderID']; ?>" 
                                        data-status="Completed" 
                                        data-confirm-msg="Mark this order as completed?">
                                    <i class="fas fa-check-double"></i> Mark as Completed
                                </button>
                                <button type="button" class="btn btn-outline contact-customer-btn" 
                                        data-order-id="<?php echo $order['OrderID']; ?>">
                                    <i class="fas fa-comments"></i> Contact Customer
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-orders">
                    <div class="no-orders-icon">
                        <?php if ($status_filter === 'active'): ?>
                            <i class="fas fa-clock"></i>
                        <?php elseif ($status_filter === 'Completed'): ?>
                            <i class="fas fa-check-circle"></i>
                        <?php elseif ($status_filter === 'Cancelled'): ?>
                            <i class="fas fa-times-circle"></i>
                        <?php else: ?>
                            <i class="fas fa-clipboard-list"></i>
                        <?php endif; ?>
                    </div>
                    <h3>No Orders Found</h3>
                    <p>
                        <?php if ($status_filter === 'active'): ?>
                            You don't have any active orders at the moment.
                        <?php elseif ($status_filter === 'Completed'): ?>
                            You haven't completed any orders yet.
                        <?php elseif ($status_filter === 'Cancelled'): ?>
                            You don't have any cancelled orders.
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
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
    const notificationToggle = document.getElementById('notificationToggle');
    const notificationModal = document.getElementById('notificationModal');
    const closeNotification = document.getElementById('closeNotification');
    const ajaxMessageContainer = document.getElementById('ajaxMessageContainer');

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

    // Contact customer function
    function contactCustomer(orderId) {
        // In a real application, this would open a messaging interface
        alert('Opening customer messaging interface for Order #' + orderId);
        // window.location.href = `messages.php?order_id=${orderId}`;
    }

    // AJAX for updating order status without page refresh
    document.addEventListener('DOMContentLoaded', function() {
        // Update order status with AJAX
        document.querySelectorAll('.update-status-btn').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                
                const orderId = this.getAttribute('data-order-id');
                const newStatus = this.getAttribute('data-status');
                const confirmMsg = this.getAttribute('data-confirm-msg');
                
                if (confirm(confirmMsg)) {
                    // Show loading state
                    const originalText = this.innerHTML;
                    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
                    this.disabled = true;
                    
                    // Create form data
                    const formData = new FormData();
                    formData.append('order_id', orderId);
                    formData.append('status', newStatus);
                    formData.append('update_status', '1');
                    formData.append('ajax', '1');
                    
                    // Send AJAX request
                    fetch('seller-orders.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Success - update the UI without refreshing
                            updateOrderUI(orderId, newStatus, data.message);
                            
                            // Update the pending badge count if needed
                            if (newStatus === 'Confirmed' || newStatus === 'Cancelled') {
                                updatePendingBadge(-1);
                            }
                        } else {
                            // Error
                            showMessage(data.message, 'error');
                            this.innerHTML = originalText;
                            this.disabled = false;
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showMessage('Error updating order status', 'error');
                        this.innerHTML = originalText;
                        this.disabled = false;
                    });
                }
            });
        });
        
        // Contact customer button
        document.querySelectorAll('.contact-customer-btn').forEach(button => {
            button.addEventListener('click', function() {
                const orderId = this.getAttribute('data-order-id');
                contactCustomer(orderId);
            });
        });
    });
    
    // Function to update the order UI without refresh
    function updateOrderUI(orderId, newStatus, message) {
        const orderCard = document.getElementById(`order-${orderId}`);
        if (!orderCard) return;
        
        // Update status badge
        const statusBadge = document.getElementById(`status-${orderId}`);
        if (statusBadge) {
            statusBadge.textContent = newStatus;
            statusBadge.className = 'order-status status-' + newStatus.toLowerCase().replace(/\s+/g, '');
        }
        
        // Update progress bar for active orders
        const progressBar = document.getElementById(`progress-${orderId}`);
        if (progressBar && ['Pending', 'Confirmed', 'Preparing', 'Out for Delivery', 'Completed'].includes(newStatus)) {
            updateProgressBar(orderId, newStatus);
        }
        
        // Update step indicators
        updateStepIndicators(orderId, newStatus);
        
        // Update action buttons
        updateActionButtons(orderId, newStatus);
        
        // Show success message
        showMessage(message, 'success');
    }
    
    // Update progress bar width
    function updateProgressBar(orderId, status) {
        const progressBar = document.getElementById(`progress-${orderId}`);
        if (!progressBar) return;
        
        let width = 0;
        switch(status) {
            case 'Pending': width = 0; break;
            case 'Confirmed': width = 25; break;
            case 'Preparing': width = 50; break;
            case 'Out for Delivery': width = 75; break;
            case 'Completed': width = 100; break;
        }
        
        progressBar.style.width = width + '%';
    }
    
    // Update step indicators
    function updateStepIndicators(orderId, status) {
        const stepsContainer = document.getElementById(`steps-${orderId}`);
        if (!stepsContainer) return;
        
        const steps = stepsContainer.querySelectorAll('.step');
        if (!steps.length) return;
        
        // Reset all steps
        steps.forEach(step => {
            step.classList.remove('active', 'completed');
        });
        
        // Set completed and active steps based on status
        switch(status) {
            case 'Pending':
                steps[0].classList.add('active');
                break;
            case 'Confirmed':
                steps[0].classList.add('completed');
                steps[1].classList.add('active');
                break;
            case 'Preparing':
                steps[0].classList.add('completed');
                steps[1].classList.add('completed');
                steps[2].classList.add('active');
                break;
            case 'Out for Delivery':
                steps[0].classList.add('completed');
                steps[1].classList.add('completed');
                steps[2].classList.add('completed');
                steps[3].classList.add('active');
                break;
            case 'Completed':
                steps.forEach(step => step.classList.add('completed'));
                steps[4].classList.add('active');
                break;
        }
    }
    
    // Update action buttons based on new status
    function updateActionButtons(orderId, newStatus) {
        const actionsContainer = document.getElementById(`actions-${orderId}`);
        if (!actionsContainer) return;
        
        let newButtons = '';
        
        switch(newStatus) {
            case 'Confirmed':
                newButtons = `
                    <button type="button" class="btn btn-warning update-status-btn" 
                            data-order-id="${orderId}" 
                            data-status="Preparing" 
                            data-confirm-msg="Mark order as preparing?">
                        <i class="fas fa-utensils"></i> Start Preparing
                    </button>
                    <button type="button" class="btn btn-outline contact-customer-btn" 
                            data-order-id="${orderId}">
                        <i class="fas fa-comments"></i> Contact Customer
                    </button>
                `;
                break;
            case 'Preparing':
                newButtons = `
                    <button type="button" class="btn btn-info update-status-btn" 
                            data-order-id="${orderId}" 
                            data-status="Out for Delivery" 
                            data-confirm-msg="Mark order as out for delivery?">
                        <i class="fas fa-shipping-fast"></i> Out for Delivery
                    </button>
                    <button type="button" class="btn btn-outline contact-customer-btn" 
                            data-order-id="${orderId}">
                        <i class="fas fa-comments"></i> Contact Customer
                    </button>
                `;
                break;
            case 'Out for Delivery':
                newButtons = `
                    <button type="button" class="btn btn-success update-status-btn" 
                            data-order-id="${orderId}" 
                            data-status="Completed" 
                            data-confirm-msg="Mark this order as completed?">
                        <i class="fas fa-check-double"></i> Mark as Completed
                    </button>
                    <button type="button" class="btn btn-outline contact-customer-btn" 
                            data-order-id="${orderId}">
                        <i class="fas fa-comments"></i> Contact Customer
                    </button>
                `;
                break;
            case 'Completed':
            case 'Cancelled':
                newButtons = '<span style="color: var(--gray); font-style: italic;">No actions available</span>';
                break;
        }
        
        actionsContainer.innerHTML = newButtons;
        
        // Reattach event listeners to new buttons
        if (newStatus !== 'Completed' && newStatus !== 'Cancelled') {
            const newUpdateBtns = actionsContainer.querySelectorAll('.update-status-btn');
            newUpdateBtns.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    const orderId = this.getAttribute('data-order-id');
                    const newStatus = this.getAttribute('data-status');
                    const confirmMsg = this.getAttribute('data-confirm-msg');
                    
                    if (confirm(confirmMsg)) {
                        // Show loading state
                        const originalText = this.innerHTML;
                        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
                        this.disabled = true;
                        
                        // Create form data
                        const formData = new FormData();
                        formData.append('order_id', orderId);
                        formData.append('status', newStatus);
                        formData.append('update_status', '1');
                        formData.append('ajax', '1');
                        
                        // Send AJAX request
                        fetch('seller-orders.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                updateOrderUI(orderId, newStatus, data.message);
                                
                                if (newStatus === 'Confirmed' || newStatus === 'Cancelled') {
                                    updatePendingBadge(-1);
                                }
                            } else {
                                showMessage(data.message, 'error');
                                this.innerHTML = originalText;
                                this.disabled = false;
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            showMessage('Error updating order status', 'error');
                            this.innerHTML = originalText;
                            this.disabled = false;
                        });
                    }
                });
            });
            
            const newContactBtns = actionsContainer.querySelectorAll('.contact-customer-btn');
            newContactBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const orderId = this.getAttribute('data-order-id');
                    contactCustomer(orderId);
                });
            });
        }
    }
    
    // Show message function - UPDATED
    function showMessage(message, type) {
        // Remove any existing messages
        const existingMsg = document.querySelector('.ajax-message');
        if (existingMsg) existingMsg.remove();
        
        // Create new message
        const messageDiv = document.createElement('div');
        messageDiv.className = `ajax-message ${type}`;
        messageDiv.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
            ${message}
        `;
        
        document.body.appendChild(messageDiv);
        
        // Remove after 5 seconds
        setTimeout(() => {
            if (messageDiv.parentNode) {
                messageDiv.parentNode.removeChild(messageDiv);
            }
        }, 5000);
    }
    
    // Update pending badge count
    function updatePendingBadge(change) {
        const pendingBadge = document.getElementById('pendingBadge');
        if (pendingBadge) {
            let currentCount = parseInt(pendingBadge.textContent);
            currentCount += change;
            
            if (currentCount <= 0) {
                pendingBadge.style.display = 'none';
            } else {
                pendingBadge.textContent = currentCount;
                pendingBadge.style.display = 'flex';
                // Restart animation
                pendingBadge.style.animation = 'pulse 2s infinite';
            }
        }
    }
</script>
</body>
</html>