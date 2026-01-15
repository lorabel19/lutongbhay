<?php
// help.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
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
$user_type = $_SESSION['user_type'];

// Get user info with profile picture
if ($user_type === 'customer') {
    $user_sql = "SELECT * FROM Customer WHERE CustomerID = ?";
} else {
    $user_sql = "SELECT * FROM Seller WHERE SellerID = ?";
}

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

// Get cart count (for customers only)
$cart_count = 0;
if ($user_type === 'customer') {
    $cart_sql = "SELECT COUNT(*) as cart_count FROM Cart WHERE CustomerID = ?";
    $cart_stmt = $conn->prepare($cart_sql);
    $cart_stmt->bind_param("i", $user_id);
    $cart_stmt->execute();
    $cart_result = $cart_stmt->get_result();
    $cart_data = $cart_result->fetch_assoc();
    $cart_count = $cart_data['cart_count'] ?: 0;
    $cart_stmt->close();
}

// Handle contact form submission
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_contact'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $subject = $_POST['subject'];
    $message_content = $_POST['message'];
    
    // In a real application, you would save this to a database or send an email
    $message = "Thank you for your message! We'll get back to you within 24 hours.";
    $message_type = "success";
}

$conn->close();

// FAQ data - Different FAQs for customers and sellers
if ($user_type === 'customer') {
    $faqs = [
        [
            'question' => 'How do I place an order?',
            'answer' => 'Browse meals from the homepage or Browse Meals page, select your desired meal, and click "Order Now" for immediate orders or "Add to Cart" for multiple items.'
        ],
        [
            'question' => 'How do payments work?',
            'answer' => 'Currently, we support cash on delivery/pickup. Payment is made directly to the seller when you receive your order.'
        ],
        [
            'question' => 'What if I need to cancel an order?',
            'answer' => 'You can cancel orders from your Orders page. For pending orders, you can cancel up to 1 hour before preparation time.'
        ],
        [
            'question' => 'How do I contact a seller?',
            'answer' => 'After placing an order, you can contact the seller through the order details page. Sellers will also contact you for delivery coordination.'
        ],
        [
            'question' => 'What if I have food allergies?',
            'answer' => 'Please check the meal description for ingredients. You can also contact the seller directly through the order page to discuss any dietary concerns.'
        ],
        [
            'question' => 'How do I become a seller?',
            'answer' => 'Click on "Become a Seller" in the footer or navigation menu to apply. You\'ll need to provide some basic information about your food business.'
        ],
        [
            'question' => 'Is there a delivery fee?',
            'answer' => 'Delivery arrangements and any associated fees are coordinated directly between you and the seller during the order process.'
        ],
        [
            'question' => 'How do I track my order?',
            'answer' => 'Go to "My Orders" page to see the status of your current orders. You can also contact the seller directly for updates.'
        ]
    ];
} else {
    $faqs = [
        [
            'question' => 'How do I create a meal listing?',
            'answer' => 'Go to your Seller Dashboard and click "Add New Meal". Fill in the meal details including title, description, price, category, and upload an image.'
        ],
        [
            'question' => 'How do I manage my orders?',
            'answer' => 'All orders appear in your Seller Dashboard. You can view order details, contact customers, and update order status from there.'
        ],
        [
            'question' => 'How do I get paid?',
            'answer' => 'Customers pay directly to you upon delivery or pickup. LutongBahay does not handle payments - you coordinate directly with customers.'
        ],
        [
            'question' => 'Can I set my own delivery terms?',
            'answer' => 'Yes! You can specify your delivery area, minimum order amount, delivery fees, and time slots in your seller settings.'
        ],
        [
            'question' => 'How do I update my availability?',
            'answer' => 'You can mark meals as "Sold Out" or update your weekly schedule in the Seller Dashboard under "Availability Settings".'
        ],
        [
            'question' => 'What if a customer wants a refund?',
            'answer' => 'Refunds are handled directly between you and the customer. We recommend setting clear refund policies in your meal descriptions.'
        ],
        [
            'question' => 'How can I promote my meals?',
            'answer' => 'Use high-quality photos, write detailed descriptions, and ask satisfied customers to leave reviews. Featured meals get more visibility!'
        ],
        [
            'question' => 'What fees does LutongBahay charge?',
            'answer' => 'LutongBahay is currently free for sellers! We don\'t charge any commission or listing fees.'
        ]
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help Center | LutongBahay</title>
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
            background-color: #f5f5f5;
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
            overflow: hidden;
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
        
        /* Help Center Content */
        .help-container {
            padding: 40px 0;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .help-header {
            text-align: center;
            margin-bottom: 50px;
        }
        
        .help-header h1 {
            font-size: 2.5rem;
            color: var(--dark);
            margin-bottom: 10px;
        }
        
        .help-header p {
            color: var(--gray);
            font-size: 1.1rem;
            max-width: 700px;
            margin: 0 auto;
        }
        
        /* Alert Styling */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.3s ease;
        }
        
        .alert-success {
            background-color: rgba(42, 157, 143, 0.1);
            color: var(--success);
            border-left: 4px solid var(--success);
        }
        
        .alert-error {
            background-color: rgba(230, 57, 70, 0.1);
            color: var(--primary);
            border-left: 4px solid var(--primary);
        }
        
        .alert i {
            font-size: 1.2rem;
        }
        
        /* Search Bar */
        .search-help {
            max-width: 600px;
            margin: 40px auto;
            position: relative;
        }
        
        .search-help input {
            width: 100%;
            padding: 15px 25px;
            border: 2px solid var(--light-gray);
            border-radius: 50px;
            font-size: 1.1rem;
            transition: var(--transition);
            background-color: white;
        }
        
        .search-help input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(230, 57, 70, 0.1);
        }
        
        .search-help button {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background-color: var(--primary);
            color: white;
            border: none;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            cursor: pointer;
            transition: var(--transition);
            font-size: 1.1rem;
        }
        
        .search-help button:hover {
            background-color: var(--primary-dark);
        }
        
        /* Quick Links */
        .quick-links {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 25px;
            margin-bottom: 60px;
        }
        
        .quick-link-card {
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
        
        .quick-link-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
            color: var(--primary);
        }
        
        .quick-link-icon {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background-color: rgba(230, 57, 70, 0.1);
            color: var(--primary);
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 1.8rem;
            margin: 0 auto 20px;
            transition: var(--transition);
        }
        
        .quick-link-card:hover .quick-link-icon {
            background-color: var(--primary);
            color: white;
        }
        
        .quick-link-card h3 {
            font-size: 1.3rem;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .quick-link-card p {
            color: var(--gray);
            font-size: 0.95rem;
        }
        
        /* FAQ Section */
        .section-title {
            font-size: 2.2rem;
            color: var(--dark);
            margin-bottom: 40px;
            text-align: center;
            font-weight: 700;
        }
        
        .faq-container {
            max-width: 900px;
            margin: 0 auto 60px;
        }
        
        .faq-item {
            background: white;
            border-radius: 15px;
            margin-bottom: 15px;
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: var(--transition);
        }
        
        .faq-question {
            padding: 25px 30px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 1.1rem;
            font-weight: 600;
            transition: var(--transition);
        }
        
        .faq-question:hover {
            background-color: var(--light-gray);
        }
        
        .faq-question i {
            color: var(--primary);
            transition: var(--transition);
        }
        
        .faq-item.active .faq-question i {
            transform: rotate(180deg);
        }
        
        .faq-answer {
            padding: 0 30px;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }
        
        .faq-item.active .faq-answer {
            padding: 0 30px 25px;
            max-height: 500px;
        }
        
        .faq-answer p {
            color: var(--gray);
            line-height: 1.8;
        }
        
        /* Contact Form */
        .contact-section {
            background: white;
            border-radius: 20px;
            padding: 50px;
            box-shadow: var(--shadow);
            max-width: 800px;
            margin: 0 auto 60px;
        }
        
        .contact-section h2 {
            font-size: 2rem;
            color: var(--dark);
            margin-bottom: 30px;
            text-align: center;
            font-weight: 700;
        }
        
        .contact-form .form-group {
            margin-bottom: 25px;
        }
        
        .contact-form label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
            font-size: 1rem;
        }
        
        .contact-form input,
        .contact-form textarea,
        .contact-form select {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid var(--light-gray);
            border-radius: 10px;
            font-size: 1rem;
            transition: var(--transition);
            background-color: #fff;
        }
        
        .contact-form input:focus,
        .contact-form textarea:focus,
        .contact-form select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(230, 57, 70, 0.1);
        }
        
        .contact-form textarea {
            resize: vertical;
            min-height: 150px;
        }
        
        .btn {
            padding: 16px 35px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            font-size: 1rem;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: white;
            width: 100%;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }
        
        /* Contact Info */
        .contact-info {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            margin-top: 50px;
        }
        
        .contact-method {
            text-align: center;
            padding: 30px;
            background: var(--light-gray);
            border-radius: 15px;
            transition: var(--transition);
        }
        
        .contact-method:hover {
            background: #e2e6ea;
            transform: translateY(-5px);
        }
        
        .contact-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: var(--primary);
            color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 1.5rem;
            margin: 0 auto 20px;
        }
        
        .contact-method h3 {
            font-size: 1.3rem;
            margin-bottom: 10px;
            color: var(--dark);
            font-weight: 600;
        }
        
        .contact-method p {
            color: var(--gray);
            margin-bottom: 5px;
            font-size: 0.95rem;
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
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }
        
        /* Responsive Design */
        @media (max-width: 992px) {
            .quick-links {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .footer-content {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .contact-info {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .nav-container {
                flex-direction: column;
                gap: 15px;
            }
            
            .nav-links {
                gap: 15px;
                font-size: 0.9rem;
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
            
            .help-container {
                padding: 30px 0;
            }
            
            .help-header h1 {
                font-size: 2rem;
            }
            
            .help-header p {
                font-size: 1rem;
            }
            
            .quick-links {
                grid-template-columns: 1fr;
            }
            
            .contact-info {
                grid-template-columns: 1fr;
            }
            
            .contact-section {
                padding: 30px;
            }
            
            .section-title {
                font-size: 1.8rem;
            }
            
            .footer-content {
                grid-template-columns: 1fr;
            }
            
            .faq-question {
                padding: 20px;
                font-size: 1rem;
            }
        }
        
        @media (max-width: 576px) {
            .container {
                width: 95%;
                padding: 0 15px;
            }
            
            .help-header h1 {
                font-size: 1.8rem;
            }
            
            .contact-section {
                padding: 20px;
            }
            
            .quick-link-card {
                padding: 20px;
            }
            
            .contact-method {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="nav-container">
                <a href="homepage.php" class="logo">
                    <i class="fas fa-utensils"></i>
                    LutongBahay
                </a>
                
                <!-- Updated Navigation Links -->
                <div class="nav-links">
                    <a href="homepage.php">Home</a>
                    <a href="browse-meals.php">Browse Meals</a>
                    <?php if ($user_type === 'customer'): ?>
                        <a href="orders.php">My Orders</a>
                    <?php else: ?>
                        <a href="seller-dashboard.php">Dashboard</a>
                    <?php endif; ?>
                    <a href="sellers.php">Sellers</a>
                </div>
                
                <div class="user-actions">
                    <?php if ($user_type === 'customer'): ?>
                        <a href="cart.php" class="cart-icon-link">
                            <div class="cart-icon">
                                <i class="fas fa-shopping-cart"></i>
                                <span class="cart-count" id="cartCount" style="display: <?php echo $cart_count > 0 ? 'flex' : 'none'; ?>;"><?php echo $cart_count; ?></span>
                            </div>
                        </a>
                    <?php endif; ?>
                    
                    <div class="profile-dropdown">
                        <div class="user-profile" id="profileToggle">
                            <?php 
                            // Check if user has a profile picture
                            $hasProfilePic = false;
                            $profilePicPath = '';
                            
                            if ($user_type === 'customer') {
                                $hasProfilePic = !empty($user['ImagePath']) && file_exists($user['ImagePath']);
                                $profilePicPath = $hasProfilePic ? $user['ImagePath'] : '';
                            } else {
                                $hasProfilePic = !empty($user['ProfileImage']) && file_exists($user['ProfileImage']);
                                $profilePicPath = $hasProfilePic ? $user['ProfileImage'] : '';
                            }
                            
                            if ($hasProfilePic): 
                            ?>
                                <img src="<?php echo htmlspecialchars($profilePicPath); ?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover;">
                            <?php else: ?>
                                <?php echo strtoupper(substr($user['FullName'], 0, 1)); ?>
                            <?php endif; ?>
                        </div>
                        <div class="dropdown-menu" id="dropdownMenu">
                            <div class="dropdown-header">
                                <div class="user-info">
                                    <div class="user-initial">
                                        <?php 
                                        if ($hasProfilePic): 
                                        ?>
                                            <img src="<?php echo htmlspecialchars($profilePicPath); ?>" alt="Profile" style="width: 45px; height: 45px; border-radius: 50%; object-fit: cover;">
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
                            <a href="profile.php" class="dropdown-item">
                                <i class="fas fa-user"></i> My Profile
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="settings.php" class="dropdown-item">
                                <i class="fas fa-cog"></i> Settings
                            </a>
                            <a href="help.php" class="dropdown-item active">
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

    <main class="container">
        <div class="help-container">
            <div class="help-header">
                <h1>Help Center</h1>
                <p>Get answers to your questions or contact our support team</p>
                
                <div class="search-help">
                    <input type="text" id="helpSearch" placeholder="Search for help articles...">
                    <button id="searchHelpBtn"><i class="fas fa-search"></i></button>
                </div>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'error'; ?>">
                    <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <!-- Quick Links -->
            <div class="quick-links">
                <div class="quick-link-card" id="orderingLink">
                    <div class="quick-link-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <h3>Ordering</h3>
                    <p>How to place and manage orders</p>
                </div>
                
                <div class="quick-link-card" id="paymentsLink">
                    <div class="quick-link-icon">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <h3>Payments</h3>
                    <p>Payment methods and billing</p>
                </div>
                
                <div class="quick-link-card" id="deliveryLink">
                    <div class="quick-link-icon">
                        <i class="fas fa-truck"></i>
                    </div>
                    <h3>Delivery</h3>
                    <p>Shipping and delivery information</p>
                </div>
                
                <div class="quick-link-card" id="accountLink">
                    <div class="quick-link-icon">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <h3>Account</h3>
                    <p>Manage your account settings</p>
                </div>
            </div>
            
            <!-- FAQ Section -->
            <h2 class="section-title">Frequently Asked Questions</h2>
            <div class="faq-container">
                <?php foreach ($faqs as $index => $faq): ?>
                    <div class="faq-item" id="faq-<?php echo $index + 1; ?>">
                        <div class="faq-question">
                            <span><?php echo htmlspecialchars($faq['question']); ?></span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p><?php echo htmlspecialchars($faq['answer']); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Contact Form -->
            <div class="contact-section">
                <h2>Still need help? Contact Us</h2>
                <form method="POST" action="" class="contact-form">
                    <div class="form-group">
                        <label for="name">Your Name</label>
                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['FullName']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['Email']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="subject">Subject</label>
                        <select id="subject" name="subject" required>
                            <option value="">Select a subject</option>
                            <option value="Order Issue">Order Issue</option>
                            <option value="Account Problem">Account Problem</option>
                            <option value="Payment Question">Payment Question</option>
                            <option value="Technical Support">Technical Support</option>
                            <option value="Feedback">Feedback</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="message">Your Message</label>
                        <textarea id="message" name="message" placeholder="Please describe your issue in detail..." required></textarea>
                    </div>
                    
                    <button type="submit" name="submit_contact" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Send Message
                    </button>
                </form>
                
                <!-- Contact Information -->
                <div class="contact-info">
                    <div class="contact-method">
                        <div class="contact-icon">
                            <i class="fas fa-phone"></i>
                        </div>
                        <h3>Call Us</h3>
                        <p>+63 2 8888 9999</p>
                        <p>Mon-Fri, 9AM-6PM</p>
                    </div>
                    
                    <div class="contact-method">
                        <div class="contact-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <h3>Email Us</h3>
                        <p>support@lutongbahay.com</p>
                        <p>Response within 24 hours</p>
                    </div>
                    
                    <div class="contact-method">
                        <div class="contact-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <h3>Visit Us</h3>
                        <p>PUP Parañaque Campus</p>
                        <p>Parañaque City, Metro Manila</p>
                    </div>
                </div>
            </div>
        </div>
    </main>

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
                        <li><a href="orders.php">My Orders</a></li>
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

        // FAQ functionality
        const faqItems = document.querySelectorAll('.faq-item');
        
        faqItems.forEach(item => {
            const question = item.querySelector('.faq-question');
            
            question.addEventListener('click', () => {
                // Close all other items
                faqItems.forEach(otherItem => {
                    if (otherItem !== item) {
                        otherItem.classList.remove('active');
                    }
                });
                
                // Toggle current item
                item.classList.toggle('active');
            });
        });
        
        // Help search functionality
        const helpSearch = document.getElementById('helpSearch');
        const searchHelpBtn = document.getElementById('searchHelpBtn');
        
        searchHelpBtn.addEventListener('click', searchHelp);
        helpSearch.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') searchHelp();
        });
        
        function searchHelp() {
            const query = helpSearch.value.trim().toLowerCase();
            if (!query) {
                // Reset if search is cleared
                faqItems.forEach(item => {
                    item.style.display = 'block';
                });
                return;
            }
            
            // Search in FAQ questions and answers
            faqItems.forEach(item => {
                const question = item.querySelector('.faq-question span').textContent.toLowerCase();
                const answer = item.querySelector('.faq-answer p').textContent.toLowerCase();
                
                if (question.includes(query) || answer.includes(query)) {
                    item.style.display = 'block';
                    item.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    item.classList.add('active'); // Open the matching FAQ
                } else {
                    item.style.display = 'none';
                    item.classList.remove('active');
                }
            });
        }
        
        // Quick link click functionality
        document.getElementById('orderingLink').addEventListener('click', function() {
            document.getElementById('faq-1').scrollIntoView({ behavior: 'smooth' });
            document.getElementById('faq-1').classList.add('active');
        });
        
        document.getElementById('paymentsLink').addEventListener('click', function() {
            document.getElementById('faq-2').scrollIntoView({ behavior: 'smooth' });
            document.getElementById('faq-2').classList.add('active');
        });
        
        document.getElementById('deliveryLink').addEventListener('click', function() {
            document.getElementById('faq-7').scrollIntoView({ behavior: 'smooth' });
            document.getElementById('faq-7').classList.add('active');
        });
        
        document.getElementById('accountLink').addEventListener('click', function() {
            document.getElementById('faq-6').scrollIntoView({ behavior: 'smooth' });
            document.getElementById('faq-6').classList.add('active');
        });

        // Update cart count with animation
        function updateCartCount() {
            if (cartCountElement) {
                // Animate the cart count
                cartCountElement.style.animation = 'bounce 0.5s ease';
                
                // Reset animation
                setTimeout(() => {
                    cartCountElement.style.animation = '';
                }, 500);
            }
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateCartCount();
            
            // Close dropdown with Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    dropdownMenu.classList.remove('show');
                }
            });
        });
    </script>
</body>
</html>