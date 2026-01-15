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

// Check if user exists
if (!$user) {
    session_destroy();
    header('Location: index.php');
    exit();
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'active';

// Build SQL query for orders
$orders_sql = "SELECT o.* FROM `Order` o WHERE o.CustomerID = ?";
$params = [$user_id];
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

// Get order details for each order and group by seller
$seller_orders = [];
$order_details = [];

if (count($orders) > 0) {
    foreach($orders as $order) {
        $details_sql = "SELECT od.*, m.Title, m.Price, m.ImagePath, m.SellerID, 
                               s.FullName as SellerName, s.ContactNo as SellerContact,
                               s.ImagePath as SellerImage
                       FROM OrderDetails od 
                       JOIN Meal m ON od.MealID = m.MealID 
                       JOIN Seller s ON m.SellerID = s.SellerID 
                       WHERE od.OrderID = ?";
        $stmt = $conn->prepare($details_sql);
        $stmt->bind_param("i", $order['OrderID']);
        $stmt->execute();
        $details_result = $stmt->get_result();
        $details = [];
        if ($details_result && $details_result->num_rows > 0) {
            while($detail = $details_result->fetch_assoc()) {
                $details[] = $detail;
                
                // Group by seller
                $seller_id = $detail['SellerID'];
                if (!isset($seller_orders[$seller_id])) {
                    $seller_orders[$seller_id] = [
                        'seller_name' => $detail['SellerName'],
                        'seller_contact' => $detail['SellerContact'],
                        'seller_image' => $detail['SellerImage'],
                        'orders' => []
                    ];
                }
                
                // Add order to this seller's list if not already added
                if (!isset($seller_orders[$seller_id]['orders'][$order['OrderID']])) {
                    $seller_orders[$seller_id]['orders'][$order['OrderID']] = [
                        'order_info' => $order,
                        'items' => []
                    ];
                }
                
                // Add item to this seller's order
                $seller_orders[$seller_id]['orders'][$order['OrderID']]['items'][] = $detail;
            }
        }
        $order_details[$order['OrderID']] = $details;
        $stmt->close();
    }
}

// Get cart count
$cart_count = 0;
$cart_sql = "SELECT COUNT(*) as cart_count FROM Cart WHERE CustomerID = ?";
$cart_stmt = $conn->prepare($cart_sql);
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
    <title>My Orders | LutongBahay</title>
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
            --warning: #ffc107;
            --info: #17a2b8;
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
        
        /* Profile Dropdown Styles */
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
        
        /* HERO SECTION */
        .hero-section {
            background: var(--primary);
            color: white;
            padding: 60px 0 30px;
            text-align: center;
        }
        
        .hero-content {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .hero-title {
            font-size: 2.8rem;
            font-weight: 800;
            margin-bottom: 15px;
            line-height: 1.2;
        }
        
        .hero-subtitle {
            font-size: 1.2rem;
            opacity: 0.9;
            line-height: 1.6;
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
        
        /* Seller Group */
        .seller-group {
            background-color: white;
            border-radius: 15px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
            overflow: hidden;
        }
        
        .seller-header {
            background-color: var(--light-gray);
            padding: 20px 25px;
            display: flex;
            align-items: center;
            gap: 15px;
            border-bottom: 2px solid var(--primary);
        }
        
        .seller-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            overflow: hidden;
            background-color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.2rem;
        }
        
        .seller-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .seller-info {
            flex: 1;
        }
        
        .seller-name {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 5px;
        }
        
        .seller-contact {
            font-size: 0.9rem;
            color: var(--gray);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .seller-contact i {
            font-size: 0.8rem;
        }
        
        /* Order Cards */
        .order-card {
            background-color: white;
            border-radius: 10px;
            margin: 20px;
            border: 1px solid var(--light-gray);
            transition: var(--transition);
        }
        
        .order-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }
        
        .order-header {
            background-color: rgba(248, 249, 250, 0.5);
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .order-info {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .order-id {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--dark);
        }
        
        .order-date {
            font-size: 0.85rem;
            color: var(--gray);
        }
        
        .order-address {
            font-size: 0.85rem;
            color: var(--gray);
            max-width: 300px;
        }
        
        .order-status {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.85rem;
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
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--primary);
        }
        
        .order-items {
            padding: 15px 20px;
        }
        
        .order-item {
            display: flex;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid var(--light-gray);
            gap: 15px;
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .item-image {
            width: 70px;
            height: 70px;
            border-radius: 8px;
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
            font-size: 1rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 5px;
        }
        
        .item-seller {
            font-size: 0.85rem;
            color: var(--gray);
            margin-bottom: 5px;
        }
        
        .item-price {
            font-size: 1rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .item-quantity {
            font-size: 0.85rem;
            color: var(--gray);
        }
        
        /* Order Progress Bar */
        .order-progress {
            padding: 15px 20px;
            border-top: 1px solid var(--light-gray);
            border-bottom: 1px solid var(--light-gray);
        }
        
        .progress-title {
            font-weight: 600;
            margin-bottom: 12px;
            color: var(--dark);
            font-size: 0.9rem;
        }
        
        .progress-bar {
            height: 6px;
            background-color: var(--light-gray);
            border-radius: 10px;
            position: relative;
            overflow: hidden;
            margin-bottom: 8px;
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
            margin-top: 12px;
        }
        
        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 2;
        }
        
        .step-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background-color: var(--light-gray);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            color: var(--gray);
            margin-bottom: 6px;
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
            font-size: 0.75rem;
            text-align: center;
            color: var(--gray);
            max-width: 70px;
        }
        
        .step.active .step-label {
            color: var(--success);
            font-weight: 600;
        }
        
        .step.completed .step-label {
            color: var(--success);
        }
        
        /* Order Actions */
        .order-actions {
            padding: 15px 20px;
            background-color: var(--light-gray);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .btn {
            padding: 8px 20px;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            font-size: 0.9rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
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
            background-color: #218838;
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
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 2000;
            animation: fadeIn 0.3s ease;
            overflow-y: auto;
            padding: 20px;
        }
        
        .modal.show {
            display: flex;
            justify-content: center;
            align-items: flex-start;
        }
        
        .modal-content {
            background-color: white;
            border-radius: 15px;
            width: 90%;
            max-width: 800px;
            margin: 50px auto;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
            animation: slideDown 0.3s ease;
            overflow: hidden;
            position: relative;
        }
        
        .modal-header {
            padding: 25px 30px;
            background-color: var(--light-gray);
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h2 {
            font-size: 1.8rem;
            color: var(--dark);
            margin: 0;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 2rem;
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
        
        .modal-close:hover {
            background-color: rgba(0, 0, 0, 0.1);
            color: var(--primary);
        }
        
        .modal-body {
            padding: 30px;
            max-height: 70vh;
            overflow-y: auto;
        }
        
        /* Review Modal Specific Styles */
        .review-modal-content {
            max-width: 600px;
        }
        
        .review-form {
            padding: 20px 0;
        }
        
        .review-order-info {
            background-color: var(--light-gray);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .order-item-review {
            display: flex;
            align-items: center;
            padding: 15px;
            border: 1px solid var(--light-gray);
            border-radius: 10px;
            margin-bottom: 15px;
        }
        
        .rating-section {
            margin-bottom: 30px;
        }
        
        .rating-section h3 {
            margin-bottom: 15px;
            color: var(--dark);
        }
        
        .rating-stars {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .star {
            font-size: 2.5rem;
            color: var(--light-gray);
            cursor: pointer;
            transition: var(--transition);
        }
        
        .star:hover,
        .star.active {
            color: #ffc107;
            transform: scale(1.1);
        }
        
        .rating-value {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark);
            margin-left: 15px;
        }
        
        .review-textarea {
            width: 100%;
            padding: 20px;
            border: 2px solid var(--light-gray);
            border-radius: 10px;
            font-size: 1rem;
            resize: vertical;
            min-height: 150px;
            margin-bottom: 20px;
            transition: var(--transition);
            font-family: inherit;
        }
        
        .review-textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(230, 57, 70, 0.2);
        }
        
        .review-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 30px;
        }
        
        /* Loading Spinner */
        .loading-spinner {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px;
            color: var(--gray);
        }
        
        .loading-spinner i {
            font-size: 2rem;
            margin-bottom: 15px;
        }
        
        /* Order Details in Modal */
        .modal-order-info {
            background-color: var(--light-gray);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .info-item {
            margin-bottom: 15px;
        }
        
        .info-label {
            font-weight: 600;
            color: var(--gray);
            font-size: 0.9rem;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .info-value {
            font-size: 1rem;
            color: var(--dark);
        }
        
        .modal-order-items {
            margin-top: 30px;
        }
        
        .modal-order-items h3 {
            font-size: 1.3rem;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--light-gray);
        }
        
        .modal-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border: 1px solid var(--light-gray);
            border-radius: 10px;
            margin-bottom: 15px;
            transition: var(--transition);
        }
        
        .modal-item:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow);
        }
        
        .modal-item-image {
            width: 80px;
            height: 80px;
            border-radius: 10px;
            overflow: hidden;
            flex-shrink: 0;
            margin-right: 20px;
        }
        
        .modal-item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .modal-item-details {
            flex: 1;
        }
        
        .modal-item-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 5px;
        }
        
        .modal-item-seller {
            font-size: 0.9rem;
            color: var(--gray);
            margin-bottom: 5px;
        }
        
        .modal-item-price {
            font-size: 1rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .modal-item-quantity {
            font-size: 0.9rem;
            color: var(--gray);
            margin-left: 15px;
        }
        
        .modal-total {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 2px solid var(--light-gray);
            text-align: right;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 1.1rem;
        }
        
        .total-row:last-child {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary);
            margin-top: 10px;
        }
        
        /* Notification Toast */
        .notification-toast {
            position: fixed;
            top: 100px;
            right: 30px;
            background-color: var(--success);
            color: white;
            padding: 15px 25px;
            border-radius: 10px;
            box-shadow: var(--shadow);
            z-index: 1001;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideInRight 0.3s ease;
            display: none;
        }
        
        .notification-toast.show {
            display: flex;
        }
        
        .notification-toast.hide {
            animation: slideOutRight 0.3s ease;
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
        
        /* Seller Stats */
        .seller-stats {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        
        .stat-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            color: var(--gray);
        }
        
        .stat-item i {
            color: var(--primary);
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(100px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        @keyframes slideOutRight {
            from { opacity: 1; transform: translateX(0); }
            to { opacity: 0; transform: translateX(100px); }
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        
        .seller-group {
            animation: fadeIn 0.5s ease forwards;
        }
        
        /* Responsive Design */
        @media (max-width: 1200px) {
            .footer-content {
                grid-template-columns: repeat(2, 1fr);
                gap: 30px;
            }
        }
        
        @media (max-width: 768px) {
            .footer-content {
                grid-template-columns: 1fr;
                gap: 30px;
            }
            
            .hero-title {
                font-size: 2.2rem;
            }
            
            .hero-subtitle {
                font-size: 1rem;
            }
            
            .seller-header {
                flex-direction: column;
                text-align: center;
                gap: 10px;
            }
            
            .seller-avatar {
                width: 60px;
                height: 60px;
            }
            
            .order-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .order-actions {
                justify-content: center;
                flex-wrap: wrap;
            }
            
            .order-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .item-image {
                width: 100%;
                height: 120px;
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
                justify-content: center;
            }
            
            .step {
                min-width: 60px;
            }
            
            /* Modal Responsive */
            .modal-content {
                width: 95%;
                margin: 20px auto;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .modal-item {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .modal-item-image {
                width: 100%;
                height: 120px;
                margin-right: 0;
                margin-bottom: 15px;
            }
            
            /* Review Modal Responsive */
            .review-actions {
                flex-direction: column;
            }
            
            .star {
                font-size: 2rem;
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
            
            .notification-toast {
                top: 80px;
                right: 15px;
                left: 15px;
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
            
            .status-filter {
                justify-content: center;
            }
            
            .status-btn {
                padding: 8px 15px;
                font-size: 0.9rem;
            }
            
            .hero-title {
                font-size: 1.8rem;
            }
            
            .hero-subtitle {
                font-size: 0.95rem;
            }
            
            .hero-section {
                padding: 40px 0 20px;
            }
            
            .progress-steps {
                justify-content: space-between;
            }
            
            .step-label {
                font-size: 0.7rem;
            }
            
            .modal-header h2 {
                font-size: 1.5rem;
            }
            
            .modal-header {
                padding: 20px;
            }
            
            .modal-body {
                padding: 20px;
            }
            
            .seller-stats {
                flex-direction: column;
                align-items: center;
                gap: 5px;
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
                    <a href="orders.php" class="active">My Orders</a>
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
                    
                    <div class="profile-dropdown">
                        <div class="user-profile" id="profileToggle">
                            <?php 
                            if (!empty($user['ImagePath']) && file_exists($user['ImagePath'])): 
                            ?>
                                <img src="<?php echo htmlspecialchars($user['ImagePath']); ?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover;">
                            <?php else: ?>
                                <?php echo strtoupper(substr($user['FullName'], 0, 1)); ?>
                            <?php endif; ?>
                        </div>
                        <div class="dropdown-menu" id="dropdownMenu">
                            <div class="dropdown-header">
                                <div class="user-info">
                                    <div class="user-initial">
                                        <?php 
                                        if (!empty($user['ImagePath']) && file_exists($user['ImagePath'])): 
                                        ?>
                                            <img src="<?php echo htmlspecialchars($user['ImagePath']); ?>" alt="Profile" style="width: 45px; height: 45px; border-radius: 50%; object-fit: cover;">
                                        <?php else: ?>
                                            <?php echo strtoupper(substr($user['FullName'], 0, 1)); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="user-details">
                                        <div class="user-name"><?php echo htmlspecialchars($user['FullName']); ?></div>
                                        <div class="user-email"><?php echo htmlspecialchars($user['Email']); ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="dropdown-divider"></div>
                            <a href="profile.php" class="dropdown-item active">
                                <i class="fas fa-user"></i> My Profile
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="settings.php" class="dropdown-item">
                                <i class="fas fa-cog"></i> Settings
                            </a>
                            <a href="help.php" class="dropdown-item">
                                <i class="fas fa-question-circle"></i> Help Center
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

    <!-- Notification Toast -->
    <div class="notification-toast" id="notificationToast">
        <i class="fas fa-bell"></i>
        <span id="notificationText">Order status updated!</span>
    </div>

    <!-- Order Details Modal -->
    <div class="modal" id="orderDetailsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalOrderTitle">Order Details</h2>
                <button class="modal-close" id="modalCloseBtn">&times;</button>
            </div>
            <div class="modal-body" id="modalOrderDetails">
                <!-- Dynamic content will be loaded here -->
                <div class="loading-spinner">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Loading order details...</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Review Modal -->
    <div class="modal" id="reviewModal">
        <div class="modal-content review-modal-content">
            <div class="modal-header">
                <h2>Write a Review</h2>
                <button class="modal-close" id="reviewModalCloseBtn">&times;</button>
            </div>
            <div class="modal-body" id="reviewModalBody">
                <!-- Dynamic content will be loaded here -->
                <div class="loading-spinner">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Loading order information...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- HERO SECTION -->
    <section class="hero-section">
        <div class="container">
            <div class="hero-content">
                <h1 class="hero-title">My Orders</h1>
                <p class="hero-subtitle">Track your orders grouped by seller for easier management</p>
            </div>
        </div>
    </section>

    <!-- Orders Controls -->
    <section class="orders-controls">
        <div class="container">
            <div class="status-filter">
                <a href="orders.php?status=active" class="status-btn <?php echo $status_filter === 'active' ? 'active' : ''; ?>">
                    <i class="fas fa-clock"></i> Active Orders
                </a>
                <a href="orders.php?status=Completed" class="status-btn <?php echo $status_filter === 'Completed' ? 'active' : ''; ?>">
                    <i class="fas fa-check-circle"></i> Completed
                </a>
                <a href="orders.php?status=Cancelled" class="status-btn <?php echo $status_filter === 'Cancelled' ? 'active' : ''; ?>">
                    <i class="fas fa-times-circle"></i> Cancelled
                </a>
                <a href="orders.php?status=all" class="status-btn <?php echo $status_filter === 'all' ? 'active' : ''; ?>">
                    <i class="fas fa-list"></i> All Orders
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
                        } elseif ($status_filter === 'all') {
                            echo 'All Orders';
                        } else {
                            echo 'Order History';
                        }
                    ?>
                </h2>
                <div class="orders-count">
                    <?php 
                        $status_text = $status_filter === 'active' ? 'Active' : ($status_filter === 'all' ? 'All' : $status_filter);
                        $seller_count = count($seller_orders);
                        $order_count = count($orders);
                    ?>
                    <?php echo $status_text; ?> Orders: <span style="color: var(--primary); font-weight: 600;"><?php echo $order_count; ?></span> from 
                    <span style="color: var(--secondary); font-weight: 600;"><?php echo $seller_count; ?></span> sellers
                </div>
            </div>
            
            <?php if (count($seller_orders) > 0): ?>
                <?php foreach ($seller_orders as $seller_id => $seller_data): ?>
                    <div class="seller-group" id="seller-<?php echo $seller_id; ?>">
                        <div class="seller-header">
                            <div class="seller-avatar">
                                <?php if (!empty($seller_data['seller_image'])): ?>
                                    <img src="<?php echo htmlspecialchars($seller_data['seller_image']); ?>" alt="<?php echo htmlspecialchars($seller_data['seller_name']); ?>">
                                <?php else: ?>
                                    <?php echo strtoupper(substr($seller_data['seller_name'], 0, 1)); ?>
                                <?php endif; ?>
                            </div>
                            <div class="seller-info">
                                <div class="seller-name"><?php echo htmlspecialchars($seller_data['seller_name']); ?></div>
                                <div class="seller-contact">
                                    <i class="fas fa-phone"></i>
                                    <?php echo htmlspecialchars($seller_data['seller_contact']); ?>
                                </div>
                                <div class="seller-stats">
                                    <div class="stat-item">
                                        <i class="fas fa-clipboard-list"></i>
                                        <span><?php echo count($seller_data['orders']); ?> order<?php echo count($seller_data['orders']) !== 1 ? 's' : ''; ?></span>
                                    </div>
                                    <?php 
                                        $total_items = 0;
                                        foreach ($seller_data['orders'] as $order_data) {
                                            $total_items += count($order_data['items']);
                                        }
                                    ?>
                                    <div class="stat-item">
                                        <i class="fas fa-utensils"></i>
                                        <span><?php echo $total_items; ?> item<?php echo $total_items !== 1 ? 's' : ''; ?></span>
                                    </div>
                                    <?php 
                                        $seller_total = 0;
                                        foreach ($seller_data['orders'] as $order_data) {
                                            $seller_total += $order_data['order_info']['TotalAmount'];
                                        }
                                    ?>
                                    <div class="stat-item">
                                        <i class="fas fa-peso-sign"></i>
                                        <span>₱<?php echo number_format($seller_total, 2); ?> total</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php foreach ($seller_data['orders'] as $order_id => $order_data): ?>
                            <?php $order = $order_data['order_info']; ?>
                            <div class="order-card" id="order-<?php echo $order_id; ?>-<?php echo $seller_id; ?>" data-order-id="<?php echo $order_id; ?>" data-seller-id="<?php echo $seller_id; ?>" data-status="<?php echo $order['Status']; ?>">
                                <div class="order-header">
                                    <div class="order-info">
                                        <div>
                                            <span class="order-id">Order #<?php echo str_pad($order_id, 6, '0', STR_PAD_LEFT); ?></span>
                                            <span class="order-status status-<?php echo strtolower(str_replace(' ', '', $order['Status'])); ?>" id="status-<?php echo $order_id; ?>-<?php echo $seller_id; ?>">
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
                                            <?php echo htmlspecialchars(substr($order['DeliveryAddress'], 0, 60)); ?><?php echo strlen($order['DeliveryAddress']) > 60 ? '...' : ''; ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="order-amount">
                                        ₱<?php echo number_format($order['TotalAmount'], 2); ?>
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
                                        <div class="progress-fill" id="progress-fill-<?php echo $order_id; ?>-<?php echo $seller_id; ?>" style="width: <?php echo $progress_width; ?>%"></div>
                                    </div>
                                    <div class="progress-steps" id="progress-steps-<?php echo $order_id; ?>-<?php echo $seller_id; ?>">
                                        <?php 
                                            $status_steps = ['Pending', 'Confirmed', 'Preparing', 'Out for Delivery', 'Completed'];
                                            $step_labels = ['Ordered', 'Confirmed', 'Preparing', 'On the way', 'Delivered'];
                                            $step_icons = ['fa-receipt', 'fa-check', 'fa-utensils', 'fa-shipping-fast', 'fa-home'];
                                            
                                            for ($i = 0; $i < 5; $i++):
                                                $step_class = '';
                                                if ($i < array_search($order['Status'], $status_steps)) {
                                                    $step_class = 'completed';
                                                } elseif ($i == array_search($order['Status'], $status_steps)) {
                                                    $step_class = 'active';
                                                }
                                        ?>
                                            <div class="step <?php echo $step_class; ?>" data-step="<?php echo $status_steps[$i]; ?>">
                                                <div class="step-icon">
                                                    <i class="fas <?php echo $step_icons[$i]; ?>"></i>
                                                </div>
                                                <div class="step-label"><?php echo $step_labels[$i]; ?></div>
                                            </div>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <div class="order-items">
                                    <?php if (count($order_data['items']) > 0): ?>
                                        <?php foreach ($order_data['items'] as $item): ?>
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
                                                    <div class="item-seller">Sold by: <?php echo htmlspecialchars($seller_data['seller_name']); ?></div>
                                                    <div class="item-price">₱<?php echo number_format($item['Price'], 2); ?> × <?php echo $item['Quantity']; ?></div>
                                                </div>
                                                <div class="item-quantity">
                                                    Subtotal: ₱<?php echo number_format($item['Subtotal'], 2); ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="order-actions" id="actions-<?php echo $order_id; ?>-<?php echo $seller_id; ?>">
                                    <?php if ($order['Status'] === 'Pending'): ?>
                                        <button class="btn btn-outline" onclick="cancelOrder(<?php echo $order_id; ?>, <?php echo $seller_id; ?>)">
                                            <i class="fas fa-times"></i> Cancel Order
                                        </button>
                                    <?php elseif ($order['Status'] === 'Completed'): ?>
                                        <button class="btn btn-primary" onclick="openReviewModal(<?php echo $order_id; ?>, <?php echo $seller_id; ?>)">
                                            <i class="fas fa-star"></i> Write Review
                                        </button>
                                    <?php endif; ?>
                                    <button class="btn btn-outline view-details-btn" 
                                            onclick="viewOrderDetails(<?php echo $order_id; ?>, <?php echo $seller_id; ?>)"
                                            data-order-id="<?php echo $order_id; ?>"
                                            data-seller-id="<?php echo $seller_id; ?>">
                                        <i class="fas fa-eye"></i> View Details
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
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
                        <?php else: ?>
                            You haven't placed any orders yet.
                        <?php endif; ?>
                    </p>
                    <a href="browse-meals.php" class="btn btn-primary">
                        <i class="fas fa-shopping-cart"></i> Browse Meals
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
                        <li><a href="cart.php">My Cart</a></li>
                        <li><a href="orders.php" class="active">My Orders</a></li>
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
        // DOM Elements
        const cartCountElement = document.getElementById('cartCount');
        const profileToggle = document.getElementById('profileToggle');
        const dropdownMenu = document.getElementById('dropdownMenu');
        const logoutLink = document.getElementById('logoutLink');
        const notificationToast = document.getElementById('notificationToast');
        const notificationText = document.getElementById('notificationText');
        const orderDetailsModal = document.getElementById('orderDetailsModal');
        const modalCloseBtn = document.getElementById('modalCloseBtn');
        const modalOrderTitle = document.getElementById('modalOrderTitle');
        const modalOrderDetails = document.getElementById('modalOrderDetails');
        const reviewModal = document.getElementById('reviewModal');
        const reviewModalCloseBtn = document.getElementById('reviewModalCloseBtn');
        const reviewModalBody = document.getElementById('reviewModalBody');

        // Current review data
        let currentReviewOrderId = null;
        let currentReviewSellerId = null;
        let currentReviewRating = 0;
        
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

        // Modal Functions
        function openModal(modal) {
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeModal(modal) {
            modal.classList.remove('show');
            document.body.style.overflow = 'auto';
            // Clear modal content
            if (modal === orderDetailsModal) {
                modalOrderDetails.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i><p>Loading order details...</p></div>';
            } else if (modal === reviewModal) {
                reviewModalBody.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i><p>Loading order information...</p></div>';
                currentReviewOrderId = null;
                currentReviewSellerId = null;
                currentReviewRating = 0;
            }
        }

        // Close order details modal
        modalCloseBtn.addEventListener('click', () => closeModal(orderDetailsModal));

        // Close review modal
        reviewModalCloseBtn.addEventListener('click', () => closeModal(reviewModal));

        // Close modal when clicking outside modal content
        orderDetailsModal.addEventListener('click', function(e) {
            if (e.target === orderDetailsModal) {
                closeModal(orderDetailsModal);
            }
        });

        reviewModal.addEventListener('click', function(e) {
            if (e.target === reviewModal) {
                closeModal(reviewModal);
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (orderDetailsModal.classList.contains('show')) {
                    closeModal(orderDetailsModal);
                } else if (reviewModal.classList.contains('show')) {
                    closeModal(reviewModal);
                }
            }
        });

        // Function to load order details via AJAX
        function viewOrderDetails(orderId, sellerId) {
            openModal(orderDetailsModal);
            modalOrderTitle.textContent = `Order #${String(orderId).padStart(6, '0')} Details`;
            
            // Fetch order details WITH seller ID
            fetch('get-order-details.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `order_id=${orderId}&seller_id=${sellerId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderOrderDetails(data.order, data.items, data.seller);
                } else {
                    modalOrderDetails.innerHTML = `
                        <div style="text-align: center; padding: 40px;">
                            <i class="fas fa-exclamation-triangle" style="font-size: 3rem; color: var(--warning); margin-bottom: 20px;"></i>
                            <h3 style="color: var(--dark); margin-bottom: 15px;">Error Loading Order Details</h3>
                            <p style="color: var(--gray);">${data.message || 'Unable to load order details. Please try again.'}</p>
                            <button onclick="viewOrderDetails(${orderId}, ${sellerId})" class="btn btn-primary" style="margin-top: 20px;">
                                <i class="fas fa-redo"></i> Try Again
                            </button>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                modalOrderDetails.innerHTML = `
                    <div style="text-align: center; padding: 40px;">
                        <i class="fas fa-exclamation-circle" style="font-size: 3rem; color: var(--primary); margin-bottom: 20px;"></i>
                        <h3 style="color: var(--dark); margin-bottom: 15px;">Network Error</h3>
                        <p style="color: var(--gray);">Unable to connect to the server. Please check your internet connection.</p>
                        <button onclick="viewOrderDetails(${orderId}, ${sellerId})" class="btn btn-primary" style="margin-top: 20px;">
                            <i class="fas fa-redo"></i> Retry
                        </button>
                    </div>
                `;
            });
        }

        // Function to open review modal
        function openReviewModal(orderId, sellerId) {
            currentReviewOrderId = orderId;
            currentReviewSellerId = sellerId;
            openModal(reviewModal);
            
            // Fetch order details for review
            fetch('get-order-details.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `order_id=${orderId}&seller_id=${sellerId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderReviewForm(data.order, data.items, data.seller);
                } else {
                    reviewModalBody.innerHTML = `
                        <div style="text-align: center; padding: 40px;">
                            <i class="fas fa-exclamation-triangle" style="font-size: 3rem; color: var(--warning); margin-bottom: 20px;"></i>
                            <h3 style="color: var(--dark); margin-bottom: 15px;">Error Loading Order Details</h3>
                            <p style="color: var(--gray);">${data.message || 'Unable to load order details. Please try again.'}</p>
                            <button onclick="openReviewModal(${orderId}, ${sellerId})" class="btn btn-primary" style="margin-top: 20px;">
                                <i class="fas fa-redo"></i> Try Again
                            </button>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                reviewModalBody.innerHTML = `
                    <div style="text-align: center; padding: 40px;">
                        <i class="fas fa-exclamation-circle" style="font-size: 3rem; color: var(--primary); margin-bottom: 20px;"></i>
                        <h3 style="color: var(--dark); margin-bottom: 15px;">Network Error</h3>
                        <p style="color: var(--gray);">Unable to connect to the server. Please check your internet connection.</p>
                        <button onclick="openReviewModal(${orderId}, ${sellerId})" class="btn btn-primary" style="margin-top: 20px;">
                            <i class="fas fa-redo"></i> Retry
                        </button>
                    </div>
                `;
            });
        }

        // Function to render review form
        function renderReviewForm(order, items, seller) {
            const orderDate = new Date(order.OrderDate);
            const formattedDate = orderDate.toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric'
            });
            
            // Get seller name from the parameter
            const sellerName = seller ? seller.FullName : 'Seller';
            
            let reviewContent = `
                <div class="review-form">
                    <div class="review-order-info">
                        <h3 style="margin-bottom: 15px; color: var(--dark);">Order #${String(order.OrderID).padStart(6, '0')}</h3>
                        <p style="color: var(--gray); margin-bottom: 10px;">
                            <i class="fas fa-calendar-alt"></i> Ordered on ${formattedDate}
                        </p>
                        <p style="color: var(--gray); margin-bottom: 5px;">
                            <i class="fas fa-store"></i> Seller: ${sellerName}
                        </p>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <h3 style="margin-bottom: 15px; color: var(--dark);">Order Items</h3>
            `;
            
            // Add order items
            items.forEach(item => {
                reviewContent += `
                    <div class="order-item-review">
                        <div style="flex: 1;">
                            <div style="font-weight: 600; color: var(--dark); margin-bottom: 5px;">${item.Title}</div>
                            <div style="font-size: 0.9rem; color: var(--gray); margin-bottom: 5px;">Quantity: ${item.Quantity}</div>
                        </div>
                        <div style="font-weight: 600; color: var(--primary);">
                            ₱${(parseFloat(item.Price) * parseInt(item.Quantity)).toFixed(2)}
                        </div>
                    </div>
                `;
            });
            
            reviewContent += `
                    </div>
                    
                    <div class="rating-section">
                        <h3>Overall Rating</h3>
                        <div class="rating-stars" id="ratingStars">
                            <div class="star" data-rating="1"><i class="fas fa-star"></i></div>
                            <div class="star" data-rating="2"><i class="fas fa-star"></i></div>
                            <div class="star" data-rating="3"><i class="fas fa-star"></i></div>
                            <div class="star" data-rating="4"><i class="fas fa-star"></i></div>
                            <div class="star" data-rating="5"><i class="fas fa-star"></i></div>
                            <span class="rating-value" id="ratingValue">0/5</span>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <h3 style="margin-bottom: 15px; color: var(--dark);">Your Review</h3>
                        <textarea 
                            class="review-textarea" 
                            id="reviewText" 
                            placeholder="Share your experience with this order. How was the food quality, delivery time, and overall service?"
                        ></textarea>
                    </div>
                    
                    <div class="review-actions">
                        <button class="btn btn-outline" onclick="closeModal(reviewModal)">
                            Cancel
                        </button>
                        <button class="btn btn-primary" onclick="submitReview(${order.OrderID}, ${currentReviewSellerId})">
                            <i class="fas fa-paper-plane"></i> Submit Review
                        </button>
                    </div>
                </div>
            `;
            
            reviewModalBody.innerHTML = reviewContent;
            
            // Initialize star rating
            initializeStarRating();
        }

        // Initialize star rating functionality
        function initializeStarRating() {
            const stars = document.querySelectorAll('.star');
            const ratingValue = document.getElementById('ratingValue');
            
            stars.forEach(star => {
                star.addEventListener('click', function() {
                    const rating = parseInt(this.getAttribute('data-rating'));
                    currentReviewRating = rating;
                    
                    // Update stars
                    stars.forEach((s, index) => {
                        if (index < rating) {
                            s.classList.add('active');
                        } else {
                            s.classList.remove('active');
                        }
                    });
                    
                    // Update rating value text
                    ratingValue.textContent = `${rating}/5`;
                });
                
                // Add hover effect
                star.addEventListener('mouseover', function() {
                    const rating = parseInt(this.getAttribute('data-rating'));
                    
                    stars.forEach((s, index) => {
                        if (index < rating) {
                            s.style.color = '#ffc107';
                        } else {
                            s.style.color = '';
                        }
                    });
                });
                
                star.addEventListener('mouseout', function() {
                    stars.forEach((s, index) => {
                        if (index < currentReviewRating) {
                            s.style.color = '#ffc107';
                        } else {
                            s.style.color = '';
                        }
                    });
                });
            });
        }

        // Submit review function
        function submitReview(orderId, sellerId) {
            const reviewText = document.getElementById('reviewText').value;
            
            if (currentReviewRating === 0) {
                alert('Please select a rating before submitting your review.');
                return;
            }
            
            if (reviewText.trim().length === 0) {
                alert('Please write a review before submitting.');
                return;
            }
            
            // Submit review via AJAX
            fetch('submit-review.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `order_id=${orderId}&seller_id=${sellerId}&rating=${currentReviewRating}&review_text=${encodeURIComponent(reviewText)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Review submitted successfully!', 'success');
                    closeModal(reviewModal);
                    
                    // Disable the review button
                    const reviewBtn = document.querySelector(`#actions-${orderId}-${sellerId} .btn-primary`);
                    if (reviewBtn) {
                        reviewBtn.innerHTML = '<i class="fas fa-check"></i> Reviewed';
                        reviewBtn.disabled = true;
                        reviewBtn.classList.remove('btn-primary');
                        reviewBtn.classList.add('btn-outline');
                    }
                } else {
                    showNotification(data.message || 'Error submitting review', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error submitting review', 'error');
            });
        }

        // Function to render order details in modal
        function renderOrderDetails(order, items, seller) {
            const orderDate = new Date(order.OrderDate);
            const formattedDate = orderDate.toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            
            // Get seller name from the parameter
            const sellerName = seller ? seller.FullName : 'Seller';
            const sellerContact = seller ? seller.ContactNo : '';
            
            // Get status class
            const statusClass = 'status-' + order.Status.toLowerCase().replace(' ', '');
            
            let modalContent = `
                <div class="modal-order-info">
                    <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 20px;">
                        <div style="width: 50px; height: 50px; border-radius: 50%; background-color: var(--primary); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700;">
                            ${sellerName ? sellerName.charAt(0).toUpperCase() : 'S'}
                        </div>
                        <div>
                            <div style="font-weight: 700; color: var(--dark);">${sellerName}</div>
                            ${sellerContact ? `<div style="font-size: 0.9rem; color: var(--gray);"><i class="fas fa-phone"></i> ${sellerContact}</div>` : ''}
                        </div>
                    </div>
                    
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">
                                <i class="fas fa-calendar-alt"></i> Order Date
                            </div>
                            <div class="info-value">${formattedDate}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">
                                <i class="fas fa-tag"></i> Status
                            </div>
                            <div class="info-value">
                                <span class="order-status ${statusClass}" style="display: inline-block; padding: 4px 12px; border-radius: 50px; font-weight: 600;">
                                    ${order.Status}
                                </span>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">
                                <i class="fas fa-map-marker-alt"></i> Delivery Address
                            </div>
                            <div class="info-value">${order.DeliveryAddress || 'Not specified'}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">
                                <i class="fas fa-phone"></i> Contact Number
                            </div>
                            <div class="info-value">${order.ContactNo || 'Not specified'}</div>
                        </div>
                        ${order.Notes ? `
                        <div class="info-item">
                            <div class="info-label">
                                <i class="fas fa-sticky-note"></i> Notes
                            </div>
                            <div class="info-value">${order.Notes}</div>
                        </div>
                        ` : ''}
                    </div>
                </div>
                
                <div class="modal-order-items">
                    <h3>Order Items from ${sellerName} (${items.length})</h3>
            `;
            
            // Calculate total for this seller
            let sellerTotal = 0;
            
            // Add each item
            items.forEach(item => {
                const itemImage = item.ImagePath || 'https://images.unsplash.com/photo-1565958011703-44f9829ba187?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80';
                const subtotal = (parseFloat(item.Price) * parseInt(item.Quantity)).toFixed(2);
                sellerTotal += parseFloat(subtotal);
                
                modalContent += `
                    <div class="modal-item">
                        <div class="modal-item-image">
                            <img src="${itemImage}" alt="${item.Title}">
                        </div>
                        <div class="modal-item-details">
                            <div class="modal-item-title">${item.Title}</div>
                            <div style="font-size: 0.9rem; color: var(--gray); margin-bottom: 5px;">Quantity: ${item.Quantity}</div>
                            <div>
                                <span class="modal-item-price">₱${parseFloat(item.Price).toFixed(2)} each</span>
                            </div>
                        </div>
                        <div style="font-weight: 600; color: var(--primary);">
                            ₱${subtotal}
                        </div>
                    </div>
                `;
            });
            
            // Add total for this seller
            modalContent += `
                    <div style="margin-top: 25px; padding-top: 20px; border-top: 2px solid var(--light-gray); text-align: right;">
                        <div style="font-size: 1.2rem; font-weight: 700; color: var(--dark);">
                            Total for ${sellerName}: 
                            <span style="color: var(--primary); font-size: 1.5rem;">₱${sellerTotal.toFixed(2)}</span>
                        </div>
                        <div style="font-size: 0.9rem; color: var(--gray); margin-top: 5px;">
                            ${items.length} item${items.length !== 1 ? 's' : ''}
                        </div>
                    </div>
                </div>
            `;
            
            modalOrderDetails.innerHTML = modalContent;
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
                        Are you sure you want to logout from LutongBahay?
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

        // Show notification
        function showNotification(message, type = 'info') {
            notificationText.textContent = message;
            
            // Set color based on type
            if (type === 'success') {
                notificationToast.style.backgroundColor = 'var(--success)';
            } else if (type === 'error') {
                notificationToast.style.backgroundColor = 'var(--primary)';
            } else if (type === 'info') {
                notificationToast.style.backgroundColor = 'var(--info)';
            }
            
            // Show notification
            notificationToast.classList.add('show');
            
            // Auto hide after 3 seconds
            setTimeout(() => {
                notificationToast.classList.remove('show');
            }, 3000);
        }

        // Update cart count
        function updateCartCount() {
            // Animate the cart count
            cartCountElement.style.animation = 'bounce 0.5s ease';
            
            // Fetch updated cart count from server
            fetch('get-cart-count.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const newCount = data.cart_count;
                        cartCountElement.textContent = newCount;
                        
                        // Show/hide cart count badge
                        if (newCount > 0) {
                            cartCountElement.style.display = 'flex';
                        } else {
                            cartCountElement.style.display = 'none';
                        }
                    }
                })
                .catch(error => {
                    console.error('Error fetching cart count:', error);
                });
            
            // Reset animation
            setTimeout(() => {
                cartCountElement.style.animation = '';
            }, 500);
        }

        // Function to get progress width based on status
        function getProgressWidth(status) {
            switch(status) {
                case 'Pending': return 0;
                case 'Confirmed': return 25;
                case 'Preparing': return 50;
                case 'Out for Delivery': return 75;
                case 'Completed': return 100;
                default: return 0;
            }
        }

        // Function to get status class
        function getStatusClass(status) {
            return 'status-' + status.toLowerCase().replace(' ', '');
        }

        // Function to update order progress dynamically
        function updateOrderProgress(orderId, sellerId, newStatus) {
            console.log(`Updating order ${orderId} for seller ${sellerId} to status: ${newStatus}`);
            
            // Create unique ID for this order-seller combination
            const uniqueId = `${orderId}-${sellerId}`;
            const orderCard = document.getElementById(`order-${uniqueId}`);
            
            if (!orderCard) {
                console.log(`Order card not found for order ${orderId} and seller ${sellerId}`);
                return;
            }
            
            const currentStatus = orderCard.getAttribute('data-status');
            
            // If status hasn't changed, do nothing
            if (currentStatus === newStatus) {
                console.log(`Status unchanged for order ${orderId} and seller ${sellerId}`);
                return;
            }
            
            // Update the status attribute
            orderCard.setAttribute('data-status', newStatus);
            
            // Update status badge
            const statusBadge = document.getElementById(`status-${uniqueId}`);
            if (statusBadge) {
                statusBadge.textContent = newStatus;
                statusBadge.className = `order-status ${getStatusClass(newStatus)}`;
                
                // Animate the status change
                statusBadge.style.animation = 'pulse 0.5s ease';
                setTimeout(() => {
                    statusBadge.style.animation = '';
                }, 500);
            }
            
            // Update progress bar if it exists - use unique ID
            const progressFill = document.getElementById(`progress-fill-${uniqueId}`);
            if (progressFill) {
                const progressWidth = getProgressWidth(newStatus);
                
                // Animate progress bar
                progressFill.style.transition = 'width 1s ease';
                progressFill.style.width = `${progressWidth}%`;
                
                // Update step indicators
                updateProgressSteps(orderId, sellerId, newStatus);
            }
            
            // Update action buttons if needed
            updateActionButtons(orderId, sellerId, newStatus);
            
            // Show notification
            showNotification(`Order #${String(orderId).padStart(6, '0')} is now ${newStatus}`, 'info');
            
            console.log(`Order ${orderId} for seller ${sellerId} updated successfully to ${newStatus}`);
        }

        // Function to update progress step indicators
        function updateProgressSteps(orderId, sellerId, newStatus) {
            const uniqueId = `${orderId}-${sellerId}`;
            const progressSteps = document.getElementById(`progress-steps-${uniqueId}`);
            if (!progressSteps) return;
            
            const steps = progressSteps.querySelectorAll('.step');
            const statusOrder = ['Pending', 'Confirmed', 'Preparing', 'Out for Delivery', 'Completed'];
            
            // Get the index of the new status
            const newStatusIndex = statusOrder.indexOf(newStatus);
            
            // Update each step
            steps.forEach((step, index) => {
                step.classList.remove('completed', 'active');
                
                if (index < newStatusIndex) {
                    step.classList.add('completed');
                } else if (index === newStatusIndex) {
                    step.classList.add('active');
                }
            });
        }

        // Function to update action buttons based on status
        function updateActionButtons(orderId, sellerId, newStatus) {
            const uniqueId = `${orderId}-${sellerId}`;
            const actionContainer = document.getElementById(`actions-${uniqueId}`);
            if (!actionContainer) return;
            
            let newButtons = '';
            
            if (newStatus === 'Pending') {
                newButtons = `
                    <button class="btn btn-outline" onclick="cancelOrder(${orderId}, ${sellerId})">
                        <i class="fas fa-times"></i> Cancel Order
                    </button>
                `;
            } else if (newStatus === 'Completed') {
                newButtons = `
                    <button class="btn btn-primary" onclick="openReviewModal(${orderId}, ${sellerId})">
                        <i class="fas fa-star"></i> Write Review
                    </button>
                `;
            } else if (newStatus === 'Cancelled') {
                newButtons = '';
            }
            
            newButtons += `
                <button class="btn btn-outline view-details-btn" 
                        onclick="viewOrderDetails(${orderId}, ${sellerId})"
                        data-order-id="${orderId}"
                        data-seller-id="${sellerId}">
                    <i class="fas fa-eye"></i> View Details
                </button>
            `;
            
            actionContainer.innerHTML = newButtons;
        }

        // REAL-TIME UPDATES USING AJAX POLLING
        let orderUpdateInterval;
        
        function startOrderUpdates() {
            // Check for active orders
            const activeOrderCards = document.querySelectorAll('.order-card[data-status]');
            const hasActiveOrders = Array.from(activeOrderCards).some(card => {
                const status = card.getAttribute('data-status');
                return ['Pending', 'Confirmed', 'Preparing', 'Out for Delivery'].includes(status);
            });
            
            if (!hasActiveOrders) {
                console.log('No active orders to monitor');
                return;
            }
            
            console.log('Starting order updates polling...');
            
            // Clear any existing interval
            if (orderUpdateInterval) {
                clearInterval(orderUpdateInterval);
            }
            
            // Poll for updates every 5 seconds
            orderUpdateInterval = setInterval(() => {
                checkForOrderUpdates();
            }, 5000);
            
            // Also check immediately
            checkForOrderUpdates();
        }
        
        function checkForOrderUpdates() {
            // Get all active order IDs with seller IDs
            const orderCards = document.querySelectorAll('.order-card[data-order-id]');
            
            if (orderCards.length === 0) return;
            
            // Prepare data to send
            const orderData = [];
            orderCards.forEach(card => {
                const orderId = card.getAttribute('data-order-id');
                const sellerId = card.getAttribute('data-seller-id');
                orderData.push({ order_id: orderId, seller_id: sellerId });
            });
            
            // Fetch order status updates
            fetch('check-order-updates.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `order_data=${JSON.stringify(orderData)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.updates) {
                    // Process each update
                    data.updates.forEach(update => {
                        updateOrderProgress(update.order_id, update.seller_id, update.status);
                    });
                }
            })
            .catch(error => {
                console.error('Error checking order updates:', error);
            });
        }
        
        // Start order updates when page loads
        window.addEventListener('load', function() {
            // Start polling for updates
            startOrderUpdates();
            
            // Also listen for visibility change to resume polling when tab becomes active
            document.addEventListener('visibilitychange', function() {
                if (!document.hidden) {
                    // Tab is now visible, check for updates immediately
                    checkForOrderUpdates();
                }
            });
        });
        
        // Clean up interval when page unloads
        window.addEventListener('beforeunload', function() {
            if (orderUpdateInterval) {
                clearInterval(orderUpdateInterval);
            }
        });

        // Order actions
        function cancelOrder(orderId, sellerId) {
            if (confirm('Are you sure you want to cancel this order?')) {
                fetch('cancel-order.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `order_id=${orderId}&seller_id=${sellerId}&action=cancel`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Order cancelled successfully', 'success');
                        // Update status immediately
                        updateOrderProgress(orderId, sellerId, 'Cancelled');
                    } else {
                        showNotification(data.message || 'Error cancelling order', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Error cancelling order', 'error');
                });
            }
        }

        // Initialize cart count display
        if (parseInt(cartCountElement.textContent) > 0) {
            cartCountElement.style.display = 'flex';
        } else {
            cartCountElement.style.display = 'none';
        }

        // Add event listeners to all view details buttons
        document.addEventListener('DOMContentLoaded', function() {
            // Add event listeners to all view details buttons
            document.querySelectorAll('.view-details-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const orderId = this.getAttribute('data-order-id');
                    const sellerId = this.getAttribute('data-seller-id');
                    viewOrderDetails(orderId, sellerId);
                });
            });
        });
    </script>
</body>
</html>