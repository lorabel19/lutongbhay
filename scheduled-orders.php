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

// Get scheduled orders with meal details
$orders_sql = "SELECT 
                o.OrderID,
                o.OrderDate,
                o.ScheduleDate,
                o.Status,
                o.TotalAmount,
                o.OrderType,
                od.OrderDetailID,
                od.Quantity as OrderQuantity,
                od.Subtotal,
                m.MealID,
                m.Title as MealTitle,
                m.Description,
                m.Price,
                m.ImagePath,
                m.Category,
                s.FullName as SellerName
            FROM `Order` o
            JOIN OrderDetails od ON o.OrderID = od.OrderID
            JOIN Meal m ON od.MealID = m.MealID
            JOIN Seller s ON m.SellerID = s.SellerID
            WHERE o.CustomerID = ? 
            AND o.OrderType = 'Scheduled'
            AND o.Status IN ('Upcoming', 'Today')
            ORDER BY o.ScheduleDate ASC, o.OrderDate DESC";

$stmt = $conn->prepare($orders_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$orders_result = $stmt->get_result();

// Organize orders by OrderID
$orders = [];
while($row = $orders_result->fetch_assoc()) {
    $order_id = $row['OrderID'];
    
    if (!isset($orders[$order_id])) {
        $orders[$order_id] = [
            'OrderID' => $row['OrderID'],
            'OrderDate' => $row['OrderDate'],
            'ScheduleDate' => $row['ScheduleDate'],
            'Status' => $row['Status'],
            'TotalAmount' => $row['TotalAmount'],
            'OrderType' => $row['OrderType'],
            'items' => []
        ];
    }
    
    $orders[$order_id]['items'][] = [
        'OrderDetailID' => $row['OrderDetailID'],
        'MealID' => $row['MealID'],
        'MealTitle' => $row['MealTitle'],
        'Description' => $row['Description'],
        'Price' => $row['Price'],
        'ImagePath' => $row['ImagePath'],
        'Category' => $row['Category'],
        'SellerName' => $row['SellerName'],
        'Quantity' => $row['OrderQuantity'],
        'Subtotal' => $row['Subtotal']
    ];
}
$stmt->close();

// Get cart count
$cart_sql = "SELECT COUNT(*) as cart_count FROM Cart WHERE CustomerID = ?";
$cart_stmt = $conn->prepare($cart_sql);
$cart_stmt->bind_param("i", $user_id);
$cart_stmt->execute();
$cart_result = $cart_stmt->get_result();
$cart_data = $cart_result->fetch_assoc();
$cart_count = $cart_data['cart_count'] ?: 0;
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
            --warning: #e9c46a;
            --info: #457b9d;
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
        
        .cart-icon-link {
            position: relative;
            text-decoration: none;
            color: var(--dark);
        }
        
        .cart-icon {
            position: relative;
            font-size: 1.5rem;
        }
        
        .cart-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background-color: var(--primary);
            color: white;
            font-size: 0.8rem;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
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
            border: 2px solid var(--secondary);
            border-radius: 50px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            color: var(--dark);
        }
        
        .order-tab:hover {
            border-color: var(--secondary);
            color: var(--secondary);
        }
        
        .order-tab.active {
            background-color: var(--secondary);
            border-color: var(--secondary);
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
        
        /* Order Cards */
        .order-card {
            background-color: white;
            border-radius: 15px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
            overflow: hidden;
            transition: var(--transition);
        }
        
        .order-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.15);
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background-color: var(--light);
            border-bottom: 1px solid var(--light-gray);
        }
        
        .order-info {
            flex: 1;
        }
        
        .order-id {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 8px;
        }
        
        .order-date {
            font-size: 1rem;
            color: var(--gray);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .order-status {
            padding: 8px 20px;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-upcoming {
            background-color: rgba(69, 123, 157, 0.15);
            color: var(--info);
        }
        
        .status-today {
            background-color: rgba(42, 157, 143, 0.15);
            color: var(--success);
        }
        
        .order-body {
            padding: 20px;
        }
        
        /* Order Items */
        .order-item {
            display: flex;
            gap: 20px;
            padding: 15px;
            border-bottom: 1px solid var(--light-gray);
            align-items: center;
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .item-image {
            width: 100px;
            height: 100px;
            border-radius: 15px;
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
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 8px;
        }
        
        .item-seller {
            font-size: 1rem;
            color: var(--gray);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .item-quantity {
            font-size: 1rem;
            color: var(--gray);
        }
        
        .item-price {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--primary);
            text-align: right;
            min-width: 120px;
        }
        
        .order-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background-color: var(--light);
            border-top: 1px solid var(--light-gray);
        }
        
        .total-amount {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--dark);
        }
        
        .total-label {
            font-size: 1.1rem;
            color: var(--gray);
            margin-right: 10px;
        }
        
        .order-actions {
            display: flex;
            gap: 12px;
        }
        
        .btn {
            padding: 12px 20px;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            font-size: 1rem;
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
            background-color: #248277;
            transform: translateY(-2px);
        }
        
        .btn-warning {
            background-color: var(--warning);
            color: var(--dark);
        }
        
        .btn-warning:hover {
            background-color: #dbb23d;
            transform: translateY(-2px);
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
        
        /* Animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .order-card {
            animation: fadeIn 0.5s ease forwards;
        }
        
        /* Tab Content */
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Responsive adjustments */
        @media (max-width: 1200px) {
            .footer-content {
                grid-template-columns: repeat(2, 1fr);
                gap: 30px;
            }
        }
        
        @media (max-width: 768px) {
            .order-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .order-status {
                align-self: flex-start;
            }
            
            .order-footer {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .order-actions {
                width: 100%;
                justify-content: flex-start;
                flex-wrap: wrap;
            }
            
            .order-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .item-price {
                text-align: left;
                width: 100%;
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
            
            .order-actions {
                flex-direction: column;
                width: 100%;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
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
                    <a href="cart.php" class="cart-icon-link">
                        <div class="cart-icon">
                            <i class="fas fa-shopping-cart"></i>
                            <span class="cart-count"><?php echo $cart_count; ?></span>
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
        <!-- Upcoming Orders Section -->
        <div id="upcoming-orders" class="tab-content active">
            <h2 class="section-title">Upcoming Orders</h2>
            
            <?php if (!empty($orders)): 
                $has_upcoming = false;
                foreach($orders as $order): 
                    if($order['Status'] == 'Upcoming'): 
                        $has_upcoming = true; ?>
                        
                        <div class="order-card">
                            <div class="order-header">
                                <div class="order-info">
                                    <div class="order-id">Order #<?php echo str_pad($order['OrderID'], 6, '0', STR_PAD_LEFT); ?></div>
                                    <div class="order-date">
                                        <i class="fas fa-calendar-alt"></i>
                                        Scheduled for: <?php echo date('F j, Y', strtotime($order['ScheduleDate'])); ?>
                                    </div>
                                </div>
                                <div class="order-status status-upcoming">Upcoming</div>
                            </div>
                            
                            <div class="order-body">
                                <?php foreach($order['items'] as $item): ?>
                                    <div class="order-item">
                                        <div class="item-image">
                                            <img src="<?php echo htmlspecialchars($item['ImagePath'] ? $item['ImagePath'] : 'https://images.unsplash.com/photo-1565958011703-44f9829ba187?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80'); ?>" alt="<?php echo htmlspecialchars($item['MealTitle']); ?>">
                                        </div>
                                        <div class="item-details">
                                            <div class="item-title"><?php echo htmlspecialchars($item['MealTitle']); ?></div>
                                            <div class="item-seller">
                                                <i class="fas fa-user"></i>
                                                <?php echo htmlspecialchars($item['SellerName']); ?>
                                            </div>
                                            <div class="item-quantity">Quantity: <?php echo $item['Quantity']; ?></div>
                                        </div>
                                        <div class="item-price">₱<?php echo number_format($item['Subtotal'], 2); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="order-footer">
                                <div class="total-amount">
                                    <span class="total-label">Total:</span>
                                    ₱<?php echo number_format($order['TotalAmount'], 2); ?>
                                </div>
                                <div class="order-actions">
                                    <button class="btn btn-primary" onclick="confirmOrder(<?php echo $order['OrderID']; ?>)">
                                        <i class="fas fa-check"></i> Confirm Order
                                    </button>
                                    <button class="btn btn-outline" onclick="rescheduleOrder(<?php echo $order['OrderID']; ?>)">
                                        <i class="fas fa-calendar"></i> Reschedule
                                    </button>
                                    <button class="btn btn-outline" onclick="cancelOrder(<?php echo $order['OrderID']; ?>)">
                                        <i class="fas fa-times"></i> Cancel
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                    <?php endif; 
                endforeach; 
                
                if(!$has_upcoming): ?>
                    <div class="no-orders">
                        <i class="fas fa-calendar"></i>
                        <h3>No Upcoming Orders</h3>
                        <p>You don't have any scheduled orders for future dates. Browse our meals to schedule your next order!</p>
                        <a href="browse-meals.php" class="btn btn-primary">
                            <i class="fas fa-utensils"></i> Browse Meals
                        </a>
                    </div>
                <?php endif; ?>
                
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
        
        <!-- Today's Orders Section -->
        <div id="today-orders" class="tab-content">
            <h2 class="section-title">Today's Orders</h2>
            
            <?php if (!empty($orders)): 
                $has_today = false;
                foreach($orders as $order): 
                    if($order['Status'] == 'Today'): 
                        $has_today = true; ?>
                        
                        <div class="order-card">
                            <div class="order-header">
                                <div class="order-info">
                                    <div class="order-id">Order #<?php echo str_pad($order['OrderID'], 6, '0', STR_PAD_LEFT); ?></div>
                                    <div class="order-date">
                                        <i class="fas fa-clock"></i>
                                        Schedule for today: <?php echo date('F j, Y', strtotime($order['ScheduleDate'])); ?>
                                    </div>
                                </div>
                                <div class="order-status status-today">Today</div>
                            </div>
                            
                            <div class="order-body">
                                <?php foreach($order['items'] as $item): ?>
                                    <div class="order-item">
                                        <div class="item-image">
                                            <img src="<?php echo htmlspecialchars($item['ImagePath'] ? $item['ImagePath'] : 'https://images.unsplash.com/photo-1565958011703-44f9829ba187?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80'); ?>" alt="<?php echo htmlspecialchars($item['MealTitle']); ?>">
                                        </div>
                                        <div class="item-details">
                                            <div class="item-title"><?php echo htmlspecialchars($item['MealTitle']); ?></div>
                                            <div class="item-seller">
                                                <i class="fas fa-user"></i>
                                                <?php echo htmlspecialchars($item['SellerName']); ?>
                                            </div>
                                            <div class="item-quantity">Quantity: <?php echo $item['Quantity']; ?></div>
                                        </div>
                                        <div class="item-price">₱<?php echo number_format($item['Subtotal'], 2); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="order-footer">
                                <div class="total-amount">
                                    <span class="total-label">Total:</span>
                                    ₱<?php echo number_format($order['TotalAmount'], 2); ?>
                                </div>
                                <div class="order-actions">
                                    <button class="btn btn-success" onclick="completeOrder(<?php echo $order['OrderID']; ?>)">
                                        <i class="fas fa-check-circle"></i> Mark as Received
                                    </button>
                                    <button class="btn btn-warning" onclick="contactSeller(<?php echo $order['OrderID']; ?>)">
                                        <i class="fas fa-phone"></i> Contact Seller
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                    <?php endif; 
                endforeach; 
                
                if(!$has_today): ?>
                    <div class="no-orders">
                        <i class="fas fa-clock"></i>
                        <h3>No Orders Today</h3>
                        <p>You don't have any scheduled orders for today. Check your upcoming orders or browse meals to schedule.</p>
                        <a href="browse-meals.php" class="btn btn-primary">
                            <i class="fas fa-utensils"></i> Browse Meals
                        </a>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="no-orders">
                    <i class="fas fa-clock"></i>
                    <h3>No Orders Today</h3>
                    <p>You don't have any scheduled orders for today. Check your upcoming orders or browse meals to schedule.</p>
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
        
        // Order action functions
        function confirmOrder(orderId) {
            if (confirm('Are you sure you want to confirm this order? This will notify the seller.')) {
                // In a real application, this would make an AJAX call
                showNotification('Order confirmed! The seller has been notified.');
            }
        }
        
        function rescheduleOrder(orderId) {
            const newDate = prompt('Enter new delivery date (YYYY-MM-DD):');
            if (newDate) {
                // In a real application, this would make an AJAX call
                showNotification('Order rescheduled for ' + newDate + '.');
            }
        }
        
        function cancelOrder(orderId) {
            if (confirm('Are you sure you want to cancel this order? This action cannot be undone.')) {
                // In a real application, this would make an AJAX call
                showNotification('Order cancelled successfully.');
            }
        }
        
        function completeOrder(orderId) {
            if (confirm('Mark this order as received and completed?')) {
                // In a real application, this would make an AJAX call
                showNotification('Order marked as completed. Thank you for your purchase!');
            }
        }
        
        function contactSeller(orderId) {
            // In a real application, this would show seller contact info
            showNotification('Seller contact information would appear here.');
        }
        
        // Show notification
        function showNotification(message) {
            const notification = document.createElement('div');
            notification.textContent = message;
            notification.style.cssText = `
                position: fixed;
                top: 30px;
                right: 30px;
                background-color: var(--success);
                color: white;
                padding: 15px 25px;
                border-radius: 10px;
                box-shadow: var(--shadow);
                z-index: 3000;
                animation: fadeIn 0.3s ease;
                font-size: 1rem;
                font-weight: 600;
            `;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'fadeIn 0.3s ease reverse';
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 300);
            }, 3000);
        }
        
        // Check if there are today's orders and switch to that tab
        document.addEventListener('DOMContentLoaded', function() {
            const todayOrders = document.querySelectorAll('.order-status.status-today').length;
            if (todayOrders > 0) {
                // Switch to Today's Orders tab
                tabs.forEach(t => t.classList.remove('active'));
                tabContents.forEach(c => c.classList.remove('active'));
                
                document.querySelector('.order-tab[data-tab="today"]').classList.add('active');
                document.getElementById('today-orders').classList.add('active');
            }
        });
    </script>

</body>
</html>