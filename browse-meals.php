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
$category = isset($_GET['category']) ? $_GET['category'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'popular';

// Build SQL query
$sql = "SELECT m.*, s.FullName as SellerName, s.SellerID 
        FROM Meal m 
        JOIN Seller s ON m.SellerID = s.SellerID 
        WHERE m.Availability = 'Available'";

$params = [];
$types = "";

// Add search filter
if (!empty($search)) {
    $sql .= " AND (m.Title LIKE ? OR m.Description LIKE ? OR s.FullName LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
    $types .= "sss";
}

// Add category filter
if (!empty($category) && $category !== 'all') {
    $sql .= " AND m.Category = ?";
    $params[] = $category;
    $types .= "s";
}

// Add sorting
switch($sort) {
    case 'price-low':
        $sql .= " ORDER BY m.Price ASC";
        break;
    case 'price-high':
        $sql .= " ORDER BY m.Price DESC";
        break;
    case 'newest':
        $sql .= " ORDER BY m.CreatedAt DESC";
        break;
    case 'rating':
        $sql .= " ORDER BY m.Title ASC";
        break;
    case 'popular':
    default:
        $sql .= " ORDER BY m.Title ASC";
        break;
}

// Get all meals
$meals = [];
$result = null;

// Check if we have parameters
if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        die("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
} else {
    // No parameters, use simple query
    $result = $conn->query($sql);
}

// Fetch meals
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        // Generate random rating and orders for display
        $row['rating'] = 4.0 + (rand(0, 100) / 100); // Random rating 4.0-5.0
        $row['orders'] = rand(50, 500); // Random orders
        
        $meals[] = $row;
    }
}

// Get categories for filter
$categories_sql = "SELECT DISTINCT Category FROM Meal WHERE Category IS NOT NULL AND Category != '' ORDER BY Category";
$categories_result = $conn->query($categories_sql);
$categories = [];
if ($categories_result && $categories_result->num_rows > 0) {
    while($cat = $categories_result->fetch_assoc()) {
        $categories[] = $cat['Category'];
    }
}

// Get cart count
$cart_count = 0; // Default to 0 for now

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Meals | LutongBahay</title>
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
        
        /* Browse Controls */
        .browse-controls {
            background-color: white;
            padding: 25px 0;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }
        
        .controls-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 25px;
            flex-wrap: wrap;
        }
        
        .filter-sort {
            display: flex;
            gap: 20px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .filter-btn, .sort-select {
            padding: 12px 25px;
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
        
        .filter-btn:hover, .sort-select:hover {
            border-color: var(--primary);
        }
        
        .search-box-browse {
            flex: 1;
            max-width: 500px;
            position: relative;
        }
        
        .search-box-browse input {
            width: 100%;
            padding: 14px 20px 14px 50px;
            border-radius: 50px;
            border: 2px solid var(--light-gray);
            font-size: 1rem;
            transition: var(--transition);
        }
        
        .search-box-browse input:focus {
            border-color: var(--primary);
            outline: none;
        }
        
        .search-box-browse i {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 1.2rem;
            color: var(--gray);
        }
        
        /* SMALLER FILTER MODAL */
        .filter-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            justify-content: center;
            align-items: flex-start;
            padding-top: 80px;
        }
        
        .filter-modal.active {
            display: flex;
        }
        
        .filter-modal-content {
            background-color: white;
            border-radius: 15px;
            box-shadow: var(--shadow);
            padding: 25px;
            width: 90%;
            max-width: 500px; /* Smaller modal */
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .filter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .filter-header h3 {
            font-size: 1.4rem; /* Smaller font */
            color: var(--dark);
        }
        
        .close-filter {
            background: none;
            border: none;
            font-size: 1.6rem;
            color: var(--gray);
            cursor: pointer;
        }
        
        .category-options {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 20px;
        }
        
        .category-option {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            padding: 10px 15px;
            border-radius: 10px;
            transition: var(--transition);
        }
        
        .category-option:hover {
            background-color: var(--light-gray);
        }
        
        .category-option input {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .category-option label {
            font-size: 1rem; /* Smaller font */
            cursor: pointer;
            flex: 1;
        }
        
        .filter-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--light-gray);
        }
        
        /* Meals Grid */
        .meals-grid-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .meals-grid-header h2 {
            font-size: 1.8rem;
            color: var(--dark);
        }
        
        .results-count {
            font-size: 1.1rem;
            color: var(--gray);
        }
        
        .meals-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            margin-bottom: 60px;
        }
        
        .meal-card {
            background-color: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: var(--transition);
            height: 100%;
        }
        
        .meal-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.15);
        }
        
        .meal-image {
            height: 220px;
            overflow: hidden;
            position: relative;
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
        
        .meal-category {
            position: absolute;
            top: 15px;
            left: 15px;
            background-color: var(--primary);
            color: white;
            padding: 6px 15px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.8rem;
        }
        
        .meal-info {
            padding: 20px;
        }
        
        .meal-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        
        .meal-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--dark);
            line-height: 1.3;
        }
        
        .meal-price {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--primary);
        }
        
        .meal-seller {
            color: var(--gray);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1rem;
        }
        
        .meal-description {
            color: var(--gray);
            font-size: 1rem;
            margin-bottom: 20px;
            line-height: 1.6;
            height: 60px;
            overflow: hidden;
        }
        
        .meal-stats {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .meal-stat {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .meal-stat .value {
            font-weight: 700;
            font-size: 1.2rem;
            color: var(--dark);
        }
        
        .meal-stat .label {
            font-size: 0.8rem;
            color: var(--gray);
        }
        
        /* YELLOW RATINGS STYLES */
        .rating-stars {
            color: var(--rating-yellow);
            font-size: 1rem;
        }
        
        .rating-value {
            color: var(--rating-yellow);
            font-weight: 700;
        }
        
        .meal-actions {
            display: flex;
            justify-content: space-between;
            gap: 12px;
        }
        
        .btn-order {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1rem;
            flex: 1;
            justify-content: center;
            text-decoration: none;
        }
        
        .btn-order:hover {
            background-color: var(--primary-dark);
        }
        
        .btn-schedule {
            background-color: transparent;
            color: var(--primary);
            border: 2px solid var(--primary);
            padding: 10px 18px;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1rem;
            flex: 1;
            justify-content: center;
            text-decoration: none;
        }
        
        .btn-schedule:hover {
            background-color: var(--light-gray);
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin-bottom: 60px;
        }
        
        .pagination-btn {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: white;
            border: 2px solid var(--light-gray);
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            color: var(--dark);
        }
        
        .pagination-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .pagination-btn.active {
            background-color: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .pagination-btn.arrow {
            font-size: 1.2rem;
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
        
        .meal-card {
            animation: fadeIn 0.5s ease forwards;
        }
        
        /* Responsive adjustments */
        @media (max-width: 1200px) {
            .meals-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 25px;
            }
            
            .footer-content {
                grid-template-columns: repeat(2, 1fr);
                gap: 30px;
            }
        }
        
        @media (max-width: 768px) {
            .meals-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .controls-container {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-box-browse {
                max-width: 100%;
            }
            
            .filter-sort {
                justify-content: center;
            }
            
            .meals-grid-header {
                flex-direction: column;
                align-items: flex-start;
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
            
            .filter-modal {
                padding-top: 50px;
                align-items: center;
            }
            
            .filter-modal-content {
                width: 95%;
                padding: 20px;
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
            
            .pagination {
                flex-wrap: wrap;
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
                    <a href="browse-meals.php" class="active">Browse Meals</a>
                    <a href="scheduled-orders.php">Scheduled Orders</a>
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
            <h1>Browse Authentic Filipino Meals</h1>
            <p>Discover delicious homemade meals from small food entrepreneurs and home cooks in your community. Order now or schedule for future dates!</p>
        </div>
    </section>

    <!-- Browse Controls -->
    <section class="browse-controls">
        <div class="container">
            <div class="controls-container">
                <div class="filter-sort">
                    <button class="filter-btn" id="filterToggle">
                        <i class="fas fa-filter"></i> Filter by Category
                    </button>
                    <div class="sort-select">
                        <i class="fas fa-sort-amount-down"></i>
                        <select id="sortSelect" style="border: none; font-size: 1rem; cursor: pointer; outline: none;">
                            <option value="popular" <?php echo ($sort == 'popular') ? 'selected' : ''; ?>>Most Popular</option>
                            <option value="price-low" <?php echo ($sort == 'price-low') ? 'selected' : ''; ?>>Price: Low to High</option>
                            <option value="price-high" <?php echo ($sort == 'price-high') ? 'selected' : ''; ?>>Price: High to Low</option>
                            <option value="newest" <?php echo ($sort == 'newest') ? 'selected' : ''; ?>>Newest First</option>
                            <option value="rating" <?php echo ($sort == 'rating') ? 'selected' : ''; ?>>Highest Rated</option>
                        </select>
                    </div>
                </div>
                
                <form method="GET" action="browse-meals.php" class="search-box-browse" id="searchForm">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" id="searchInput" placeholder="Search for meals, sellers, or categories..." value="<?php echo htmlspecialchars($search); ?>">
                    <input type="hidden" name="sort" id="sortInput" value="<?php echo htmlspecialchars($sort); ?>">
                    <input type="hidden" name="category" id="categoryInput" value="<?php echo htmlspecialchars($category); ?>">
                </form>
            </div>
        </div>
    </section>

    <!-- SMALLER FILTER MODAL -->
    <div class="filter-modal" id="filterModal">
        <div class="filter-modal-content">
            <div class="filter-header">
                <h3>Filter by Category</h3>
                <button class="close-filter" id="closeFilter">&times;</button>
            </div>
            
            <form method="GET" action="browse-meals.php" id="filterForm">
                <input type="hidden" name="sort" id="filterSort" value="<?php echo htmlspecialchars($sort); ?>">
                <input type="hidden" name="search" id="filterSearch" value="<?php echo htmlspecialchars($search); ?>">
                
                <div class="category-options">
                    <div class="category-option">
                        <input type="radio" id="modal-cat-all" name="category" value="all" <?php echo empty($category) || $category == 'all' ? 'checked' : ''; ?>>
                        <label for="modal-cat-all">All Categories</label>
                    </div>
                    <?php foreach($categories as $cat): ?>
                    <div class="category-option">
                        <input type="radio" id="modal-cat-<?php echo strtolower(str_replace(' ', '-', $cat)); ?>" 
                               name="category" value="<?php echo htmlspecialchars($cat); ?>"
                               <?php echo $category == $cat ? 'checked' : ''; ?>>
                        <label for="modal-cat-<?php echo strtolower(str_replace(' ', '-', $cat)); ?>"><?php echo htmlspecialchars($cat); ?></label>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="filter-actions">
                    <button type="button" class="btn-schedule" id="resetFilters">Clear Filter</button>
                    <button type="submit" class="btn-order" id="applyFilters">Apply Category</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Meals Grid -->
    <section class="container">
        <div class="meals-grid-header">
            <h2>Available Meals</h2>
            <div class="results-count">
                Showing <span id="resultsCount"><?php echo count($meals); ?></span> meals
                <?php if (!empty($category) && $category !== 'all'): ?>
                    in <span style="color: var(--primary); font-weight: 600;"><?php echo htmlspecialchars($category); ?></span>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="meals-grid" id="mealsGrid">
            <?php if (count($meals) > 0): ?>
                <?php foreach ($meals as $meal): ?>
                    <div class="meal-card">
                        <div class="meal-image">
                            <img src="<?php echo htmlspecialchars($meal['ImagePath'] ? $meal['ImagePath'] : 'https://images.unsplash.com/photo-1565958011703-44f9829ba187?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80'); ?>" alt="<?php echo htmlspecialchars($meal['Title']); ?>">
                            <div class="meal-category"><?php echo htmlspecialchars($meal['Category'] ?: 'Uncategorized'); ?></div>
                        </div>
                        <div class="meal-info">
                            <div class="meal-header">
                                <h3 class="meal-title"><?php echo htmlspecialchars($meal['Title']); ?></h3>
                                <div class="meal-price">₱<?php echo number_format($meal['Price'], 2); ?></div>
                            </div>
                            <div class="meal-seller">
                                <i class="fas fa-user"></i>
                                <span><?php echo htmlspecialchars($meal['SellerName']); ?></span>
                            </div>
                            <p class="meal-description"><?php echo htmlspecialchars(substr($meal['Description'], 0, 100)) . '...'; ?></p>
                            
                            <div class="meal-stats">
                                <div class="meal-stat">
                                    <span class="value rating-value"><?php echo number_format($meal['rating'], 1); ?></span>
                                    <span class="label rating-stars">
                                        <?php 
                                        $fullStars = floor($meal['rating']);
                                        $hasHalf = ($meal['rating'] - $fullStars) >= 0.5;
                                        echo str_repeat('★', $fullStars) . ($hasHalf ? '½' : '');
                                        ?>
                                    </span>
                                </div>
                                <div class="meal-stat">
                                    <span class="value"><?php echo $meal['orders']; ?></span>
                                    <span class="label">Orders</span>
                                </div>
                                <div class="meal-stat">
                                    <span class="value"><?php echo $meal['Availability'] == 'Available' ? 'Yes' : 'No'; ?></span>
                                    <span class="label">Available</span>
                                </div>
                            </div>
                            
                            <div class="meal-actions">
                                <a href="order.php?meal_id=<?php echo $meal['MealID']; ?>" class="btn-order">
                                    <i class="fas fa-shopping-cart"></i> Order Now
                                </a>
                                <a href="schedule-order.php?meal_id=<?php echo $meal['MealID']; ?>" class="btn-schedule">
                                    <i class="fas fa-calendar-alt"></i> Schedule
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 40px; background-color: var(--light-gray); border-radius: 15px;">
                    <div style="font-size: 1.5rem; color: var(--gray); margin-bottom: 20px;">
                        <i class="fas fa-utensils" style="font-size: 2.5rem; color: #ddd;"></i>
                    </div>
                    <h3 style="color: var(--gray); margin-bottom: 15px;">No meals found</h3>
                    <p style="color: var(--gray); margin-bottom: 25px;">
                        <?php if (!empty($category) && $category !== 'all'): ?>
                            No meals found in <strong><?php echo htmlspecialchars($category); ?></strong> category.
                        <?php elseif (!empty($search)): ?>
                            No meals found for "<?php echo htmlspecialchars($search); ?>"
                        <?php else: ?>
                            No meals available at the moment.
                        <?php endif; ?>
                    </p>
                    <a href="browse-meals.php" class="btn-order" style="padding: 12px 30px;">Clear Filters</a>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Pagination -->
        <?php if (count($meals) > 0): ?>
        <div class="pagination">
            <a href="#" class="pagination-btn arrow"><i class="fas fa-chevron-left"></i></a>
            <a href="#" class="pagination-btn active">1</a>
            <a href="#" class="pagination-btn">2</a>
            <a href="#" class="pagination-btn">3</a>
            <a href="#" class="pagination-btn">4</a>
            <a href="#" class="pagination-btn">5</a>
            <a href="#" class="pagination-btn arrow"><i class="fas fa-chevron-right"></i></a>
        </div>
        <?php endif; ?>
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
                        <li><a href="browse-meals.php" class="active">Browse Meals</a></li>
                        <li><a href="scheduled-orders.php">Schedule Orders</a></li>
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
        // DOM Elements
        const filterToggle = document.getElementById('filterToggle');
        const filterModal = document.getElementById('filterModal');
        const closeFilter = document.getElementById('closeFilter');
        const searchInput = document.getElementById('searchInput');
        const sortSelect = document.getElementById('sortSelect');
        const sortInput = document.getElementById('sortInput');
        const categoryInput = document.getElementById('categoryInput');
        const searchForm = document.getElementById('searchForm');
        const filterForm = document.getElementById('filterForm');
        const filterSort = document.getElementById('filterSort');
        const filterSearch = document.getElementById('filterSearch');
        const resetFilters = document.getElementById('resetFilters');

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

        // Filter modal toggle
        filterToggle.addEventListener('click', function() {
            filterModal.classList.add('active');
        });

        closeFilter.addEventListener('click', function() {
            filterModal.classList.remove('active');
        });

        // Sort functionality
        sortSelect.addEventListener('change', function() {
            sortInput.value = this.value;
            
            // Get current category from form
            const checkedCategory = document.querySelector('input[name="category"]:checked');
            if (checkedCategory) {
                categoryInput.value = checkedCategory.value;
            } else {
                categoryInput.value = 'all';
            }
            
            searchForm.submit();
        });

        // Search functionality
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                // Get current category from form
                const checkedCategory = document.querySelector('input[name="category"]:checked');
                if (checkedCategory) {
                    categoryInput.value = checkedCategory.value;
                } else {
                    categoryInput.value = 'all';
                }
                
                searchForm.submit();
            }
        });

        // Reset filters
        if (resetFilters) {
            resetFilters.addEventListener('click', function() {
                // Uncheck all radio buttons in modal
                document.querySelectorAll('#filterModal input[name="category"]').forEach(radio => {
                    radio.checked = false;
                });
                // Check "All Categories" in modal
                document.getElementById('modal-cat-all').checked = true;
                
                // Close modal
                filterModal.classList.remove('active');
                
                // Submit filter form
                filterForm.submit();
            });
        }

        // Update form values when category changes in modal
        const modalCategoryRadios = document.querySelectorAll('#filterModal input[name="category"]');
        modalCategoryRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.checked) {
                    categoryInput.value = this.value;
                }
            });
        });

        // Apply filters button functionality
        const applyFiltersBtn = document.getElementById('applyFilters');
        applyFiltersBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Get selected category
            const checkedRadio = document.querySelector('#filterModal input[name="category"]:checked');
            if (checkedRadio) {
                categoryInput.value = checkedRadio.value;
            } else {
                categoryInput.value = 'all';
            }
            
            // Close modal
            filterModal.classList.remove('active');
            
            // Submit search form with updated category
            searchForm.submit();
        });

        // Add event listeners to order buttons
        document.querySelectorAll('.btn-order').forEach(button => {
            button.addEventListener('click', function(e) {
                // For demo purposes, show notification
                showNotification('Item added to cart!');
            });
        });

        // Add event listeners to schedule buttons
        document.querySelectorAll('.btn-schedule').forEach(button => {
            button.addEventListener('click', function(e) {
                // For demo purposes, show notification
                showNotification('Redirecting to schedule page...');
            });
        });

        // Close modal when clicking outside
        filterModal.addEventListener('click', function(e) {
            if (e.target === filterModal) {
                filterModal.classList.remove('active');
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && filterModal.classList.contains('active')) {
                filterModal.classList.remove('active');
            }
        });

        // Update category input when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Sync modal radio buttons with current category
            const currentCategory = "<?php echo $category; ?>";
            if (currentCategory) {
                const modalRadio = document.getElementById('modal-cat-' + currentCategory.toLowerCase().replace(/ /g, '-'));
                if (modalRadio) {
                    modalRadio.checked = true;
                }
            } else {
                document.getElementById('modal-cat-all').checked = true;
            }
            
            // Update category input value
            const checkedCategory = document.querySelector('#filterModal input[name="category"]:checked');
            if (checkedCategory) {
                categoryInput.value = checkedCategory.value;
            }
        });
    </script>

</body>
</html>