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

// Get search parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'popular';

// Build SQL query for sellers
$sellers_sql = "SELECT s.*, 
                COUNT(DISTINCT m.MealID) as meal_count,
                AVG(m.Price) as avg_price
                FROM Seller s 
                LEFT JOIN Meal m ON s.SellerID = m.SellerID 
                WHERE m.Availability = 'Available'";

$params = [];
$types = "";

// Add search filter
if (!empty($search)) {
    $sellers_sql .= " AND (s.FullName LIKE ? OR s.Email LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term]);
    $types .= "ss";
}

$sellers_sql .= " GROUP BY s.SellerID";

// Add sorting
switch($sort) {
    case 'newest':
        $sellers_sql .= " ORDER BY s.CreatedAt DESC";
        break;
    case 'name':
        $sellers_sql .= " ORDER BY s.FullName ASC";
        break;
    case 'meals':
        $sellers_sql .= " ORDER BY meal_count DESC";
        break;
    case 'popular':
    default:
        $sellers_sql .= " ORDER BY meal_count DESC, s.FullName ASC";
        break;
}

// Get sellers
$sellers = [];
$result = null;

// Check if we have parameters
if (!empty($params)) {
    $stmt = $conn->prepare($sellers_sql);
    if ($stmt === false) {
        die("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
} else {
    // No parameters, use simple query
    $result = $conn->query($sellers_sql);
}

// Fetch sellers and get their meal counts
if ($result && $result->num_rows > 0) {
    while($seller = $result->fetch_assoc()) {
        // Get seller's categories
        $seller_id = $seller['SellerID'];
        $categories_sql = "SELECT DISTINCT Category FROM Meal 
                          WHERE SellerID = ? AND Availability = 'Available' 
                          AND Category IS NOT NULL AND Category != ''
                          LIMIT 3";
        $cat_stmt = $conn->prepare($categories_sql);
        $cat_stmt->bind_param("i", $seller_id);
        $cat_stmt->execute();
        $cat_result = $cat_stmt->get_result();
        $categories = [];
        if ($cat_result && $cat_result->num_rows > 0) {
            while($cat = $cat_result->fetch_assoc()) {
                $categories[] = $cat['Category'];
            }
        }
        $cat_stmt->close();
        
        $seller['categories'] = $categories;
        $sellers[] = $seller;
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
    <title>Our Sellers | LutongBahay</title>
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
        
        /* Header & Navigation - MATCH HOMEPAGE STYLE */
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
        
        /* NEW: HERO SECTION - LIKE SETTINGS PAGE */
        .hero-section {
            background: var(--primary); /* Plain solid color */
            color: white;
            padding: 60px 0 30px; /* Like settings page header */
            text-align: center;
        }
        
        .hero-content {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .hero-title {
            font-size: 2.8rem; /* Like settings page */
            font-weight: 800;
            margin-bottom: 15px;
            line-height: 1.2;
        }
        
        .hero-subtitle {
            font-size: 1.2rem; /* Like settings page */
            opacity: 0.9;
            line-height: 1.6;
        }
        
        /* Sellers Controls */
        .sellers-controls {
            background-color: white;
            padding: 20px 0;
            border-bottom: 1px solid var(--light-gray);
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.03);
        }
        
        .controls-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .sort-select {
            padding: 10px 20px;
            border-radius: 50px;
            border: 2px solid var(--light-gray);
            background-color: white;
            font-size: 1rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
        }
        
        .sort-select:hover {
            border-color: var(--primary);
            background-color: rgba(230, 57, 70, 0.05);
        }
        
        .sort-select select {
            border: none;
            font-size: 1rem;
            cursor: pointer;
            outline: none;
            background: transparent;
        }
        
        .search-box-sellers {
            flex: 1;
            max-width: 400px;
            position: relative;
        }
        
        .search-box-sellers input {
            width: 100%;
            padding: 12px 20px 12px 45px;
            border-radius: 50px;
            border: 2px solid var(--light-gray);
            font-size: 1rem;
            transition: var(--transition);
        }
        
        .search-box-sellers input:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(230, 57, 70, 0.1);
        }
        
        .search-box-sellers i {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 1.1rem;
            color: var(--gray);
        }
        
        /* Sellers Container */
        .sellers-container {
            margin-bottom: 60px;
        }
        
        .sellers-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .sellers-header h2 {
            font-size: 1.8rem;
            color: var(--dark);
        }
        
        .sellers-count {
            font-size: 1rem;
            color: var(--gray);
        }
        
        /* Sellers Grid */
        .sellers-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            margin-bottom: 60px;
        }
        
        .seller-card {
            background-color: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: var(--transition);
            height: 100%;
            position: relative;
        }
        
        .seller-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.15);
        }
        
        .seller-image {
            height: 300px;
            overflow: hidden;
            position: relative;
        }
        
        .seller-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: var(--transition);
        }
        
        .seller-card:hover .seller-image img {
            transform: scale(1.05);
        }
        
        .seller-verified {
            position: absolute;
            top: 15px;
            right: 15px;
            background-color: var(--primary);
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 0.9rem;
        }
        
        .seller-info {
            padding: 20px;
        }
        
        .seller-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        
        .seller-name {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--dark);
            line-height: 1.3;
        }
        
        .seller-since {
            font-size: 0.9rem;
            color: var(--gray);
            margin-bottom: 12px;
        }
        
        .seller-email {
            color: var(--gray);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
        }
        
        .seller-location {
            color: var(--gray);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
        }
        
        .seller-stats {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            padding: 15px 0;
            border-top: 1px solid var(--light-gray);
            border-bottom: 1px solid var(--light-gray);
        }
        
        .seller-stat {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 1;
        }
        
        .seller-stat .value {
            font-weight: 700;
            font-size: 1.2rem;
            color: var(--primary);
        }
        
        .seller-stat .label {
            font-size: 0.8rem;
            color: var(--gray);
            text-align: center;
        }
        
        .seller-categories {
            margin-bottom: 20px;
        }
        
        .categories-label {
            font-size: 0.9rem;
            color: var(--gray);
            margin-bottom: 8px;
        }
        
        .categories-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .category-tag {
            background-color: var(--light-gray);
            color: var(--dark);
            padding: 4px 10px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .seller-actions {
            display: flex;
            gap: 12px;
        }
        
        .btn {
            padding: 10px 20px;
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
            flex: 1;
            justify-content: center;
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
        
        /* No Sellers Message */
        .no-sellers {
            text-align: center;
            padding: 60px 40px;
            background-color: var(--light-gray);
            border-radius: 15px;
            margin-bottom: 60px;
        }
        
        .no-sellers-icon {
            font-size: 3rem;
            color: var(--gray);
            margin-bottom: 20px;
        }
        
        .no-sellers h3 {
            font-size: 1.5rem;
            color: var(--gray);
            margin-bottom: 15px;
        }
        
        .no-sellers p {
            color: var(--gray);
            margin-bottom: 25px;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }
        
        /* Become Seller CTA */
        .become-seller-cta {
            text-align: center;
            padding: 40px;
            background: var(--primary); /* Plain solid color like hero section */
            color: white;
            border-radius: 15px;
            margin-bottom: 60px;
        }
        
        .become-seller-cta h3 {
            font-size: 1.8rem;
            margin-bottom: 15px;
        }
        
        .become-seller-cta p {
            font-size: 1.1rem;
            margin-bottom: 25px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
            opacity: 0.9;
        }
        
        .btn-white {
            background-color: white;
            color: var(--primary);
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-white:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(255, 255, 255, 0.2);
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
        
        .seller-card {
            animation: fadeIn 0.5s ease forwards;
        }
        
        /* Responsive Design */
        @media (max-width: 1200px) {
            .sellers-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 25px;
            }
            
            .footer-content {
                grid-template-columns: repeat(2, 1fr);
                gap: 30px;
            }
        }
        
        @media (max-width: 768px) {
            .sellers-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .footer-content {
                grid-template-columns: 1fr;
                gap: 30px;
            }
            
            .controls-container {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-box-sellers {
                max-width: 100%;
            }
            
            .sellers-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .hero-title {
                font-size: 2.2rem;
            }
            
            .hero-subtitle {
                font-size: 1rem;
            }
            
            .seller-actions {
                flex-direction: column;
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
            
            .hero-title {
                font-size: 1.8rem;
            }
            
            .hero-subtitle {
                font-size: 0.95rem;
            }
            
            .hero-section {
                padding: 40px 0 20px;
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
                    <a href="orders.php">My Orders</a>
                    <a href="sellers.php" class="active">Sellers</a>
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
    <!-- NEW: HERO SECTION - LIKE SETTINGS PAGE -->
    <section class="hero-section">
        <div class="container">
            <div class="hero-content">
                <h1 class="hero-title">Our Food Sellers</h1>
                <p class="hero-subtitle">Discover talented home cooks and food entrepreneurs who bring authentic Filipino flavors to your table</p>
            </div>
        </div>
    </section>

    <!-- Sellers Controls -->
    <section class="sellers-controls">
        <div class="container">
            <div class="controls-container">
                <div class="sort-select">
                    <i class="fas fa-sort-amount-down"></i>
                    <select id="sortSelect" style="border: none; cursor: pointer; outline: none; background: transparent;">
                        <option value="popular" <?php echo ($sort == 'popular') ? 'selected' : ''; ?>>Most Popular</option>
                        <option value="newest" <?php echo ($sort == 'newest') ? 'selected' : ''; ?>>Newest Sellers</option>
                        <option value="name" <?php echo ($sort == 'name') ? 'selected' : ''; ?>>Name (A-Z)</option>
                        <option value="meals" <?php echo ($sort == 'meals') ? 'selected' : ''; ?>>Most Meals</option>
                    </select>
                </div>
                
                <form method="GET" action="sellers.php" class="search-box-sellers" id="searchForm">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" id="searchInput" placeholder="Search for sellers..." value="<?php echo htmlspecialchars($search); ?>">
                    <input type="hidden" name="sort" id="sortInput" value="<?php echo htmlspecialchars($sort); ?>">
                </form>
            </div>
        </div>
    </section>

    <!-- Sellers Container -->
    <section class="container">
        <div class="sellers-container">
            <div class="sellers-header">
                <h2>Available Sellers</h2>
                <div class="sellers-count">
                    <?php if (!empty($search)): ?>
                        Showing <span style="color: var(--primary); font-weight: 600;"><?php echo count($sellers); ?></span> results for "<?php echo htmlspecialchars($search); ?>"
                    <?php else: ?>
                        <span style="color: var(--primary); font-weight: 600;"><?php echo count($sellers); ?></span> active sellers
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="sellers-grid" id="sellersGrid">
                <?php if (count($sellers) > 0): ?>
                    <?php foreach ($sellers as $seller): ?>
                        <?php 
                        // Format date
                        $join_date = date('F Y', strtotime($seller['CreatedAt']));
                        // Get seller initial for avatar
                        $seller_initial = strtoupper(substr($seller['FullName'], 0, 1));
                        ?>
                        <div class="seller-card">
                            <div class="seller-image">
                                <?php if (!empty($seller['ImagePath'])): ?>
                                    <img src="<?php echo htmlspecialchars($seller['ImagePath']); ?>" alt="<?php echo htmlspecialchars($seller['FullName']); ?>">
                                <?php else: ?>
                                    <div style="width: 100%; height: 100%; background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); display: flex; justify-content: center; align-items: center; color: white; font-size: 4rem; font-weight: 700;">
                                        <?php echo $seller_initial; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="seller-verified" title="Verified Seller">
                                    <i class="fas fa-check"></i>
                                </div>
                            </div>
                            
                            <div class="seller-info">
                                <div class="seller-header">
                                    <h3 class="seller-name"><?php echo htmlspecialchars($seller['FullName']); ?></h3>
                                </div>
                                
                                <div class="seller-since">
                                    <i class="far fa-calendar-alt"></i> Selling since <?php echo $join_date; ?>
                                </div>
                                
                                <div class="seller-email">
                                    <i class="fas fa-envelope"></i>
                                    <?php echo htmlspecialchars($seller['Email']); ?>
                                </div>
                                
                                <?php if (!empty($seller['Address'])): ?>
                                <div class="seller-location">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <?php echo htmlspecialchars(substr($seller['Address'], 0, 50)); ?>
                                    <?php echo strlen($seller['Address']) > 50 ? '...' : ''; ?>
                                </div>
                                <?php endif; ?>
                                
                                <div class="seller-stats">
                                    <div class="seller-stat">
                                        <span class="value"><?php echo $seller['meal_count'] ?: 0; ?></span>
                                        <span class="label">Meals</span>
                                    </div>
                                    <div class="seller-stat">
                                        <span class="value">₱<?php echo number_format($seller['avg_price'] ?: 0, 0); ?></span>
                                        <span class="label">Avg Price</span>
                                    </div>
                                    <div class="seller-stat">
                                        <span class="value">4.8</span>
                                        <span class="label">Rating</span>
                                    </div>
                                </div>
                                
                                <?php if (!empty($seller['categories'])): ?>
                                <div class="seller-categories">
                                    <div class="categories-label">Specialties:</div>
                                    <div class="categories-list">
                                        <?php foreach ($seller['categories'] as $category): ?>
                                            <span class="category-tag"><?php echo htmlspecialchars($category); ?></span>
                                        <?php endforeach; ?>
                                        <?php if (count($seller['categories']) >= 3): ?>
                                            <span class="category-tag">+ more</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <div class="seller-actions">
                                    <button class="btn btn-primary" onclick="viewSellerMeals(<?php echo $seller['SellerID']; ?>, '<?php echo addslashes($seller['FullName']); ?>')">
                                        <i class="fas fa-utensils"></i> View Meals
                                    </button>
                                    <button class="btn btn-outline">
    <i class="fas fa-comment"></i> Contact
</button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="grid-column: 1/-1; text-align: center; padding: 40px; background-color: var(--light-gray); border-radius: 15px;">
                        <div style="font-size: 1.5rem; color: var(--gray); margin-bottom: 20px;">
                            <i class="fas fa-store-alt" style="font-size: 2.5rem; color: #ddd;"></i>
                        </div>
                        <h3 style="color: var(--gray); margin-bottom: 15px;">No sellers found</h3>
                        <p style="color: var(--gray); margin-bottom: 25px;">
                            <?php if (!empty($search)): ?>
                                No sellers found for "<?php echo htmlspecialchars($search); ?>"
                            <?php else: ?>
                                No active sellers at the moment.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        

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
                        <li><a href="sellers.php" class="active">Sellers</a></li>
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
        const searchInput = document.getElementById('searchInput');
        const sortSelect = document.getElementById('sortSelect');
        const sortInput = document.getElementById('sortInput');
        const searchForm = document.getElementById('searchForm');

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

        // Show notification
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

        // Seller actions - UPDATED FUNCTIONS
        function viewSellerMeals(sellerId, sellerName) {
            // Add loading state to button
            const button = event.target;
            const originalHTML = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
            button.disabled = true;
            
            // URL encode the seller name for safety
            const encodedName = encodeURIComponent(sellerName);
            
            // Redirect to browse-meals.php with seller filter
            setTimeout(() => {
                window.location.href = `browse-meals.php?seller_id=${sellerId}&seller_name=${encodedName}`;
            }, 300);
        }

        function contactSeller(sellerId) {
            // In a real app, this would open a chat/message modal
            // For now, redirect to contact page with seller ID
            window.location.href = `contact-seller.php?id=${sellerId}`;
        }

        // Sort functionality
        sortSelect.addEventListener('change', function() {
            sortInput.value = this.value;
            searchForm.submit();
        });

        // Search functionality
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchForm.submit();
            }
        });

        // Initialize cart count display
        document.addEventListener('DOMContentLoaded', function() {
            if (parseInt(cartCountElement.textContent) > 0) {
                cartCountElement.style.display = 'flex';
            } else {
                cartCountElement.style.display = 'none';
            }
        });
    </script>
</body>
</html>