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

// Get featured meals from database
$featured_meals = [];
$sql = "SELECT m.*, s.FullName as SellerName, s.SellerID
        FROM Meal m 
        JOIN Seller s ON m.SellerID = s.SellerID 
        WHERE m.Availability = 'Available' 
        ORDER BY m.CreatedAt DESC 
        LIMIT 6";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $featured_meals[] = $row;
    }
}

// Get cart count (assuming you have a cart system)
$cart_count = 0; // Default to 0

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to LutongBahay | Authentic Filipino Home-Cooked Meals</title>
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
        }
        
        .cart-icon {
            position: relative;
            font-size: 1.5rem;
            color: var(--dark);
            cursor: pointer;
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
        
        .user-profile img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        /* Hero Section */
        .hero {
            background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('https://www.savoredjourneys.com/wp-content/uploads/2022/05/Bulalo.jpg');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 80px 0;
            text-align: center;
        }
        
        .hero h1 {
            font-size: 3rem;
            margin-bottom: 20px;
            font-weight: 800;
        }
        
        .hero p {
            font-size: 1.2rem;
            max-width: 800px;
            margin: 0 auto 30px;
            opacity: 0.9;
        }
        
        .search-box {
            max-width: 700px;
            margin: 30px auto;
            display: flex;
            border-radius: 50px;
            overflow: hidden;
            box-shadow: var(--shadow);
        }
        
        .search-box input {
            flex: 1;
            padding: 18px 25px;
            border: none;
            font-size: 1.1rem;
        }
        
        .search-box button {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 0 35px;
            cursor: pointer;
            font-size: 1.2rem;
            transition: var(--transition);
        }
        
        .search-box button:hover {
            background-color: var(--primary-dark);
        }
        
        .hero-cta {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 30px;
        }
        
        .btn {
            padding: 14px 35px;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            font-size: 1.1rem;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            transform: translateY(-3px);
        }
        
        .btn-outline {
            background-color: transparent;
            color: white;
            border: 2px solid white;
        }
        
        .btn-outline:hover {
            background-color: white;
            color: var(--dark);
            transform: translateY(-3px);
        }
        
        /* Featured Categories */
        .section-title {
            text-align: center;
            margin: 70px 0 40px;
            font-size: 2.2rem;
            color: var(--dark);
            font-weight: 700;
        }
        
        .categories {
            display: flex;
            justify-content: center;
            gap: 25px;
            flex-wrap: wrap;
            margin-bottom: 70px;
        }
        
        .category-card {
            width: 220px;
            height: 220px;
            border-radius: 20px;
            overflow: hidden;
            position: relative;
            box-shadow: var(--shadow);
            cursor: pointer;
            transition: var(--transition);
        }
        
        .category-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
        }
        
        .category-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        
        .category-card:hover img {
            transform: scale(1.1);
        }
        
        .category-card .overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(0, 0, 0, 0.85));
            color: white;
            padding: 20px;
            text-align: center;
            font-weight: 700;
            font-size: 1.3rem;
        }
        
        /* Featured Meals */
        .meals-container {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            margin-bottom: 70px;
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
        
        /* How It Works */
        .steps-container {
            display: flex;
            justify-content: center;
            gap: 40px;
            margin-bottom: 70px;
            flex-wrap: wrap;
        }
        
        .step-card {
            text-align: center;
            max-width: 250px;
            padding: 20px;
        }
        
        .step-icon {
            width: 80px;
            height: 80px;
            background-color: var(--light-gray);
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0 auto 20px;
            font-size: 2rem;
            color: var(--primary);
        }
        
        .step-title {
            font-size: 1.4rem;
            margin-bottom: 12px;
            color: var(--dark);
            font-weight: 700;
        }
        
        .step-card p {
            font-size: 1rem;
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
        
        .meal-card, .category-card, .step-card {
            animation: fadeIn 0.5s ease forwards;
        }
        
        /* Staggered animation for 6 meal cards */
        .meal-card:nth-child(1) { animation-delay: 0.1s; }
        .meal-card:nth-child(2) { animation-delay: 0.2s; }
        .meal-card:nth-child(3) { animation-delay: 0.3s; }
        .meal-card:nth-child(4) { animation-delay: 0.4s; }
        .meal-card:nth-child(5) { animation-delay: 0.5s; }
        .meal-card:nth-child(6) { animation-delay: 0.6s; }
        
        /* Category animations */
        .category-card:nth-child(1) { animation-delay: 0.1s; }
        .category-card:nth-child(2) { animation-delay: 0.2s; }
        .category-card:nth-child(3) { animation-delay: 0.3s; }
        .category-card:nth-child(4) { animation-delay: 0.4s; }
        .category-card:nth-child(5) { animation-delay: 0.5s; }
        
        /* Responsive Design */
        @media (max-width: 1400px) {
            .container {
                width: 95%;
            }
        }
        
        @media (max-width: 1200px) {
            .meals-container {
                grid-template-columns: repeat(2, 1fr);
                gap: 25px;
            }
            
            .categories {
                gap: 20px;
            }
            
            .category-card {
                width: 200px;
                height: 200px;
            }
            
            .footer-content {
                grid-template-columns: repeat(2, 1fr);
                gap: 30px;
            }
        }
        
        @media (max-width: 992px) {
            .container {
                width: 95%;
                padding: 0 15px;
            }
            
            .nav-links {
                gap: 15px;
                font-size: 0.9rem;
            }
            
            .hero h1 {
                font-size: 2.5rem;
            }
            
            .hero p {
                font-size: 1.1rem;
            }
            
            .step-card {
                max-width: 45%;
            }
        }
        
        @media (max-width: 768px) {
            .meals-container {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .categories {
                gap: 15px;
            }
            
            .category-card {
                width: 160px;
                height: 160px;
            }
            
            .category-card .overlay {
                padding: 15px;
                font-size: 1.1rem;
            }
            
            .steps-container {
                gap: 30px;
            }
            
            .step-card {
                max-width: 100%;
            }
            
            .footer-content {
                grid-template-columns: 1fr;
                gap: 30px;
            }
            
            .hero-cta {
                flex-direction: column;
                align-items: center;
                gap: 15px;
            }
            
            .hero-cta .btn {
                width: 100%;
                max-width: 300px;
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
            
            .hero h1 {
                font-size: 2rem;
            }
            
            .hero p {
                font-size: 1rem;
            }
            
            .search-box {
                flex-direction: column;
                border-radius: 15px;
            }
            
            .search-box input {
                border-radius: 15px 15px 0 0;
            }
            
            .search-box button {
                border-radius: 0 0 15px 15px;
                padding: 15px;
            }
            
            .section-title {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body>
<!-- Updated Header & Navigation -->
<header>
    <div class="container">
        <div class="nav-container">
            <a href="homepage.php" class="logo">
                <i class="fas fa-utensils"></i>
                LutongBahay
            </a>
            
            <div class="nav-links">
                <a href="homepage.php" class="active">Home</a>
                <a href="browse-meals.php">Browse Meals</a>
                <a href="scheduled-orders.php">Scheduled Orders</a>
                <a href="past-orders.php">Past Orders</a>
                <a href="sellers.php">Sellers</a>
            </div>
            
            <div class="user-actions">
                <!-- Cart icon with count -->
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

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <h1>Welcome, <?php echo htmlspecialchars(explode(' ', $user['FullName'])[0]); ?>!</h1>
            <p>Discover delicious homemade meals from small food entrepreneurs and home cooks in your community. Order now or schedule for future dates!</p>
            
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="Search for your favorite meals (e.g. Adobo, Sinigang, Leche Flan)">
                <button id="searchButton"><i class="fas fa-search"></i></button>
            </div>
            
            <div class="hero-cta">
                <button class="btn btn-primary" id="exploreBtn">
                    <i class="fas fa-utensils"></i> Explore Meals
                </button>
                <a href="scheduled-orders.php" class="btn btn-outline">
                    <i class="fas fa-calendar-alt"></i> Schedule Order
                </a>
            </div>
        </div>
    </section>

    <!-- Featured Categories -->
    <section class="container">
        <h2 class="section-title">Popular Categories</h2>
        <div class="categories">
            <div class="category-card" onclick="window.location.href='browse-meals.php?category=main'">
                <img src="https://www.unileverfoodsolutions.com.ph/chef-inspiration/food-delivery/10-crowd-favorite-filipino-dishes/jcr:content/parsys/set1/row2/span12/columncontrol_copy_c_1292622576/columnctrl_parsys_2/textimage_copy/image.transform/jpeg-optimized/image.1697455717956.jpg" alt="Main Dishes">
                <div class="overlay">Main Dishes</div>
            </div>
            <div class="category-card" onclick="window.location.href='browse-meals.php?category=desserts'">
                <img src="https://homefoodie.com.ph/uploads/2021/Dec%202021/Queso%20de%20Bola%20Leche%20Flan.JPG" alt="Desserts">
                <div class="overlay">Desserts</div>
            </div>
            <div class="category-card" onclick="window.location.href='browse-meals.php?category=merienda'">
                <img src="https://gttp.images.tshiftcdn.com/264409/x/0/philippines-street-food-turon.jpg?crop=1.91%3A1&fit=crop&width=1200" alt="Merienda">
                <div class="overlay">Merienda</div>
            </div>
            <div class="category-card" onclick="window.location.href='browse-meals.php?category=vegetarian'">
                <img src="https://www.seriouseats.com/thmb/BHTueEcNShZmWVlwc4_VVmhfLYs=/1500x0/filters:no_upscale():max_bytes(150000):strip_icc()/20210712-pinakbet-vicky-wasik-seriouseats-12-37ac6b9ea57145728de86f927dc5fef6.jpg" alt="Vegetarian">
                <div class="overlay">Vegetarian</div>
            </div>
            <div class="category-card" onclick="window.location.href='browse-meals.php?category=holiday'">
                <img src="https://kanamit.com.ph/cdn/shop/products/08-pancit-bihon.jpg?v=1678151947" alt="Holiday Specials">
                <div class="overlay">Holiday Specials</div>
            </div>
        </div>
    </section>

    <!-- Featured Meals -->
    <section class="container">
        <h2 class="section-title">Featured Meals Today</h2>
        <div class="meals-container" id="mealsContainer">
            <?php if (count($featured_meals) > 0): ?>
                <?php foreach ($featured_meals as $meal): ?>
                    <div class="meal-card">
                        <div class="meal-image">
                            <img src="<?php echo htmlspecialchars($meal['ImagePath'] ? $meal['ImagePath'] : 'https://images.unsplash.com/photo-1565958011703-44f9829ba187?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80'); ?>" alt="<?php echo htmlspecialchars($meal['Title']); ?>">
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
                    <h3 style="color: var(--gray); margin-bottom: 15px;">No meals available yet</h3>
                    <p style="color: var(--gray); margin-bottom: 25px;">Check back later for new meal offerings!</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- How It Works -->
    <section class="container">
        <h2 class="section-title">How LutongBahay Works</h2>
        <div class="steps-container">
            <div class="step-card">
                <div class="step-icon">
                    <i class="fas fa-search"></i>
                </div>
                <h3 class="step-title">Browse Meals</h3>
                <p>Explore authentic Filipino dishes from home cooks and small food entrepreneurs in your area.</p>
            </div>
            <div class="step-card">
                <div class="step-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <h3 class="step-title">Order Now or Schedule</h3>
                <p>Choose between immediate orders or schedule for a future date for special occasions.</p>
            </div>
            <div class="step-card">
                <div class="step-icon">
                    <i class="fas fa-truck"></i>
                </div>
                <h3 class="step-title">Pick Up or Delivery</h3>
                <p>Arrange for pick-up or coordinate delivery directly with the seller based on your preference.</p>
            </div>
            <div class="step-card">
                <div class="step-icon">
                    <i class="fas fa-utensils"></i>
                </div>
                <h3 class="step-title">Enjoy Your Meal</h3>
                <p>Savor delicious homemade meals while supporting small food businesses in your community.</p>
            </div>
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
        const exploreBtn = document.getElementById('exploreBtn');
        const searchButton = document.getElementById('searchButton');
        const searchInput = document.getElementById('searchInput');

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

        // Event Listeners
        exploreBtn.addEventListener('click', function() {
            document.querySelector('.categories').scrollIntoView({ behavior: 'smooth' });
        });

        // Search functionality
        searchButton.addEventListener('click', function() {
            const searchTerm = searchInput.value.trim();
            if (searchTerm) {
                window.location.href = `browse-meals.php?search=${encodeURIComponent(searchTerm)}`;
            }
        });

        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchButton.click();
            }
        });

        // Add event listeners to existing order buttons
        document.querySelectorAll('.btn-order').forEach(button => {
            button.addEventListener('click', function(e) {
                // For demo purposes, show notification
                showNotification('Item added to cart!');
            });
        });

        // Add event listeners to existing schedule buttons
        document.querySelectorAll('.btn-schedule').forEach(button => {
            button.addEventListener('click', function(e) {
                // For demo purposes, show notification
                showNotification('Redirecting to schedule page...');
            });
        });
    </script>

</body>
</html>