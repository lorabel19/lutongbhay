<?php
session_start();

// Check if user is logged in (customer only)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'customer') {
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

// Get user details from Customer table
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

// Handle AJAX meal details request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'get_meal_details') {
    header('Content-Type: application/json');
    
    $meal_id = intval($_POST['meal_id']);
    
    $sql = "SELECT m.*, s.FullName as SellerName, s.ContactNo as SellerContact, s.Email as SellerEmail 
            FROM Meal m 
            JOIN Seller s ON m.SellerID = s.SellerID 
            WHERE m.MealID = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $meal_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $meal = $result->fetch_assoc();
        
        // Generate random rating for demo purposes
        $rating = rand(35, 50) / 10;
        $fullStars = floor($rating);
        $hasHalfStar = ($rating - $fullStars) >= 0.5;
        $emptyStars = 5 - $fullStars - ($hasHalfStar ? 1 : 0);
        $reviewCount = rand(5, 150);
        
        $response = [
            'success' => true,
            'meal' => $meal,
            'rating' => $rating,
            'fullStars' => $fullStars,
            'hasHalfStar' => $hasHalfStar,
            'emptyStars' => $emptyStars,
            'reviewCount' => $reviewCount
        ];
    } else {
        $response = [
            'success' => false,
            'message' => 'Meal not found'
        ];
    }
    
    $stmt->close();
    $conn->close();
    echo json_encode($response);
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

// Get cart count
$cart_count = 0;
$cart_sql = "SELECT COUNT(*) as cart_count FROM Cart WHERE CustomerID = ?";
$cart_stmt = $conn->prepare($cart_sql);
if ($cart_stmt) {
    $cart_stmt->bind_param("i", $user_id);
    $cart_stmt->execute();
    $cart_result = $cart_stmt->get_result();
    if ($cart_result) {
        $cart_data = $cart_result->fetch_assoc();
        $cart_count = $cart_data['cart_count'] ?: 0;
    }
    $cart_stmt->close();
}

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
            --warning: #ffb703;
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

        /* Dropdown arrow */
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
        
        /* Hero Section */
        .hero {
            background: linear-gradient(rgba(0, 0, 0, 0.4), rgba(0, 0, 0, 0.4)), url('https://www.savoredjourneys.com/wp-content/uploads/2022/05/Bulalo.jpg');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 180px 0;
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
            height: 300px;
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
            margin-bottom: 8px;
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
        
        /* RATING STARS */
        .meal-rating {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
            color: var(--gray);
            font-size: 0.95rem;
        }
        
        .stars {
            display: flex;
            align-items: center;
            gap: 2px;
        }
        
        .star {
            color: var(--warning);
            font-size: 0.9rem;
        }
        
        .star.empty {
            color: var(--light-gray);
        }
        
        .rating-value {
            font-weight: 600;
            margin-right: 5px;
        }
        
        .review-count {
            color: var(--gray);
            font-size: 0.9rem;
        }
        
        .meal-seller {
            color: var(--gray);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1rem;
        }
        
        .meal-description {
            color: var(--gray);
            font-size: 0.95rem;
            margin-bottom: 20px;
            line-height: 1.6;
            height: 60px;
            overflow: hidden;
        }
        
        .meal-actions {
            display: flex;
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
        
        .btn-view-details {
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
        
        .btn-view-details:hover {
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
        
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
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
        
        /* Meal Details Modal Styles */
        .meal-details-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 2100;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .meal-details-modal.active {
            display: flex;
        }
        
        .meal-details-content {
            background-color: white;
            border-radius: 20px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            animation: modalSlideIn 0.3s ease;
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .meal-details-header {
            padding: 25px 30px;
            border-bottom: 1px solid var(--light-gray);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            background-color: white;
            z-index: 1;
            border-radius: 20px 20px 0 0;
        }
        
        .meal-details-header h2 {
            font-size: 1.8rem;
            color: var(--dark);
            font-weight: 700;
            margin: 0;
        }
        
        .meal-details-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--gray);
            transition: var(--transition);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .meal-details-close:hover {
            background-color: var(--light-gray);
            color: var(--primary);
        }
        
        .meal-details-body {
            padding: 30px;
        }
        
        .meal-details-image {
            width: 100%;
            height: 350px;
            border-radius: 15px;
            overflow: hidden;
            margin-bottom: 25px;
        }
        
        .meal-details-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .meal-details-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }
        
        .meal-details-info h3 {
            font-size: 1.8rem;
            margin-bottom: 15px;
            color: var(--dark);
        }
        
        .meal-details-price {
            font-size: 2rem;
            color: var(--primary);
            font-weight: 800;
            margin-bottom: 20px;
        }
        
        .meal-details-category {
            display: inline-block;
            background-color: var(--primary);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 20px;
        }
        
        .meal-details-description {
            font-size: 1.1rem;
            line-height: 1.7;
            color: var(--gray);
            margin-bottom: 25px;
        }
        
        .meal-details-seller {
            background-color: var(--light-gray);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
        }
        
        .seller-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .seller-icon {
            width: 50px;
            height: 50px;
            background-color: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }
        
        .seller-details h4 {
            margin: 0 0 5px 0;
            color: var(--dark);
        }
        
        .seller-contact {
            display: flex;
            gap: 15px;
            margin-top: 10px;
        }
        
        .seller-contact-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--gray);
            font-size: 0.9rem;
        }
        
        .meal-details-actions {
            display: flex;
            gap: 15px;
            margin-top: 25px;
        }
        
        .btn-details-order {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 50px;
            cursor: pointer;
            font-weight: 600;
            flex: 1;
            text-decoration: none;
            text-align: center;
            font-size: 1rem;
            transition: background-color 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-details-order:hover {
            background-color: var(--primary-dark);
        }
        
        .btn-details-back {
            background-color: transparent;
            color: var(--primary);
            border: 2px solid var(--primary);
            padding: 15px 30px;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            flex: 1;
            text-align: center;
            font-size: 1rem;
            transition: background-color 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-details-back:hover {
            background-color: rgba(230, 57, 70, 0.1);
        }
        
        .meal-details-sidebar {
            background-color: var(--light-gray);
            padding: 25px;
            border-radius: 15px;
        }
        
        .availability-status {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #ddd;
        }
        
        .availability-status .status {
            font-weight: 600;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.9rem;
        }
        
        .status-available {
            background-color: rgba(42, 157, 143, 0.2);
            color: var(--success);
        }
        
        .status-not-available {
            background-color: rgba(230, 57, 70, 0.2);
            color: var(--primary);
        }
        
        .meal-stats h4 {
            margin-bottom: 15px;
            color: var(--dark);
        }
        
        .stat-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
        }
        
        .stat-label {
            color: var(--gray);
        }
        
        .stat-value {
            font-weight: 600;
            color: var(--dark);
        }
        
        /* Loading spinner for meal details */
        .details-loading {
            text-align: center;
            padding: 50px;
        }
        
        .details-loading i {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 15px;
        }
        
        /* Error message in details modal */
        .details-error {
            text-align: center;
            padding: 50px;
            color: var(--primary);
        }
        
        .details-error i {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }
        
        /* Responsive Design */
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
            
            .meal-details-grid {
                grid-template-columns: 1fr;
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
            
            .meal-details-actions {
                flex-direction: column;
            }
            
            .meal-details-image {
                height: 250px;
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
    <!-- Meal Details Modal -->
    <div class="meal-details-modal" id="mealDetailsModal">
        <div class="meal-details-content">
            <div class="meal-details-header">
                <h2 id="detailsMealTitle">Meal Details</h2>
                <button class="meal-details-close" onclick="closeMealDetails()">&times;</button>
            </div>
            <div class="meal-details-body">
                <div id="detailsLoading" class="details-loading">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Loading meal details...</p>
                </div>
                
                <div id="detailsError" class="details-error" style="display: none;">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p id="errorMessage">Failed to load meal details</p>
                </div>
                
                <div id="detailsContent" style="display: none;">
                    <div class="meal-details-image">
                        <img id="detailsImage" src="" alt="Meal Image">
                    </div>
                    
                    <div class="meal-details-grid">
                        <div class="meal-details-info">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                <h3 id="detailsTitle">Meal Title</h3>
                                <div class="meal-details-price" id="detailsPrice">₱0.00</div>
                            </div>
                            
                            <div class="meal-details-category" id="detailsCategory">Category</div>
                            
                            <div class="meal-rating" id="detailsRating">
                                <!-- Rating stars will be inserted here -->
                            </div>
                            
                            <p class="meal-details-description" id="detailsDescription">
                                <!-- Description will be inserted here -->
                            </p>
                            
                            <div class="meal-details-seller">
                                <div class="seller-info">
                                    <div class="seller-icon">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <div class="seller-details">
                                        <h4 id="sellerName">Seller Name</h4>
                                        <p style="color: var(--gray); margin: 0;">Home Cook</p>
                                    </div>
                                </div>
                                
                                <div class="seller-contact">
                                    <div class="seller-contact-item">
                                        <i class="fas fa-envelope"></i>
                                        <span id="sellerEmail">seller@example.com</span>
                                    </div>
                                    <div class="seller-contact-item">
                                        <i class="fas fa-phone"></i>
                                        <span id="sellerPhone">Not provided</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="meal-details-actions">
                                <button class="btn-details-order add-to-cart-details-btn" onclick="addToCartFromDetails()">
                                    <i class="fas fa-shopping-cart"></i> Add to Cart
                                </button>
                                <button class="btn-details-back" onclick="closeMealDetails()">
                                    <i class="fas fa-arrow-left"></i> Back to Meals
                                </button>
                            </div>
                        </div>
                        
                        <div class="meal-details-sidebar">
                            <div class="availability-status">
                                <i class="fas fa-circle" id="availabilityIcon" style="color: #2a9d8f;"></i>
                                <div class="status status-available" id="availabilityStatus">Available</div>
                            </div>
                            
                            <div class="meal-stats">
                                <h4>Meal Information</h4>
                                <div class="stat-item">
                                    <span class="stat-label">Category:</span>
                                    <span class="stat-value" id="detailsCategoryText">Main Dishes</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-label">Posted:</span>
                                    <span class="stat-value" id="detailsPostedDate">Today</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-label">Rating:</span>
                                    <span class="stat-value" id="detailsRatingText">4.5/5</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-label">Reviews:</span>
                                    <span class="stat-value" id="detailsReviewCount">0</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Header & Navigation -->
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
                    <a href="orders.php">My Orders</a>
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

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <h1>Welcome, <?php echo htmlspecialchars(explode(' ', $user['FullName'])[0]); ?>!</h1>
            <p>Discover delicious homemade meals from small food entrepreneurs and home cooks in your community. Add to cart and order now!</p>
            
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="Search for your favorite meals (e.g. Adobo, Sinigang, Leche Flan)">
                <button id="searchButton"><i class="fas fa-search"></i></button>
            </div>
            
            <div class="hero-cta">
                <button class="btn btn-primary" id="exploreBtn">
                    <i class="fas fa-utensils"></i> Explore Meals
                </button>
                <a href="cart.php" class="btn btn-outline">
                    <i class="fas fa-shopping-cart"></i> View Cart
                </a>
            </div>
        </div>
    </section>

    <!-- Featured Categories -->
    <section class="container">
        <h2 class="section-title">Popular Categories</h2>
        <div class="categories">
            <div class="category-card" onclick="window.location.href='browse-meals.php?category=Main Dishes'">
                <img src="https://www.unileverfoodsolutions.com.ph/chef-inspiration/food-delivery/10-crowd-favorite-filipino-dishes/jcr:content/parsys/set1/row2/span12/columncontrol_copy_c_1292622576/columnctrl_parsys_2/textimage_copy/image.transform/jpeg-optimized/image.1697455717956.jpg" alt="Main Dishes">
                <div class="overlay">Main Dishes</div>
            </div>
            <div class="category-card" onclick="window.location.href='browse-meals.php?category=Desserts'">
                <img src="https://homefoodie.com.ph/uploads/2021/Dec%202021/Queso%20de%20Bola%20Leche%20Flan.JPG" alt="Desserts">
                <div class="overlay">Desserts</div>
            </div>
            <div class="category-card" onclick="window.location.href='browse-meals.php?category=Merienda'">
                <img src="https://gttp.images.tshiftcdn.com/264409/x/0/philippines-street-food-turon.jpg?crop=1.91%3A1&fit=crop&width=1200" alt="Merienda">
                <div class="overlay">Merienda</div>
            </div>
            <div class="category-card" onclick="window.location.href='browse-meals.php?category=Vegetarian'">
                <img src="https://www.seriouseats.com/thmb/BHTueEcNShZmWVlwc4_VVmhfLYs=/1500x0/filters:no_upscale():max_bytes(150000):strip_icc()/20210712-pinakbet-vicky-wasik-seriouseats-12-37ac6b9ea57145728de86f927dc5fef6.jpg" alt="Vegetarian">
                <div class="overlay">Vegetarian</div>
            </div>
            <div class="category-card" onclick="window.location.href='browse-meals.php?category=Holiday Specials'">
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
                <?php foreach ($featured_meals as $meal): 
                    $rating = rand(35, 50) / 10;
                    $fullStars = floor($rating);
                    $hasHalfStar = ($rating - $fullStars) >= 0.5;
                    $emptyStars = 5 - $fullStars - ($hasHalfStar ? 1 : 0);
                    $reviewCount = rand(5, 150);
                ?>
                    <div class="meal-card" data-meal-id="<?php echo $meal['MealID']; ?>">
                        <div class="meal-image">
                            <img src="<?php echo htmlspecialchars($meal['ImagePath'] ? $meal['ImagePath'] : 'https://images.unsplash.com/photo-1565958011703-44f9829ba187?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80'); ?>" alt="<?php echo htmlspecialchars($meal['Title']); ?>">
                        </div>
                        <div class="meal-info">
                            <div class="meal-header">
                                <h3 class="meal-title"><?php echo htmlspecialchars($meal['Title']); ?></h3>
                                <div class="meal-price">₱<?php echo number_format($meal['Price'], 2); ?></div>
                            </div>
                            
                            <!-- RATING STARS -->
                            <div class="meal-rating">
                                <div class="stars">
                                    <span class="rating-value"><?php echo number_format($rating, 1); ?></span>
                                    <?php for($i = 0; $i < $fullStars; $i++): ?>
                                        <i class="fas fa-star star"></i>
                                    <?php endfor; ?>
                                    
                                    <?php if($hasHalfStar): ?>
                                        <i class="fas fa-star-half-alt star"></i>
                                    <?php endif; ?>
                                    
                                    <?php for($i = 0; $i < $emptyStars; $i++): ?>
                                        <i class="far fa-star star empty"></i>
                                    <?php endfor; ?>
                                </div>
                                <span class="review-count">(<?php echo $reviewCount; ?> reviews)</span>
                            </div>
                            
                            <div class="meal-seller">
                                <i class="fas fa-user"></i>
                                <span><?php echo htmlspecialchars($meal['SellerName']); ?></span>
                            </div>
                            <p class="meal-description"><?php echo htmlspecialchars(substr($meal['Description'], 0, 100)) . '...'; ?></p>
                            <div class="meal-actions">
                                <button class="btn-order" onclick="addToCart(<?php echo $meal['MealID']; ?>, '<?php echo htmlspecialchars($meal['Title']); ?>')">
                                    <i class="fas fa-shopping-cart"></i> Add to Cart
                                </button>
                                <button class="btn-view-details" onclick="openMealDetails(<?php echo $meal['MealID']; ?>)">
                                    <i class="fas fa-eye"></i> View Details
                                </button>
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
                    <i class="fas fa-cart-plus"></i>
                </div>
                <h3 class="step-title">Add to Cart</h3>
                <p>Select your favorite meals and add them to your cart. Review your order before checking out.</p>
            </div>
            <div class="step-card">
                <div class="step-icon">
                    <i class="fas fa-credit-card"></i>
                </div>
                <h3 class="step-title">Checkout</h3>
                <p>Proceed to checkout, choose your payment method, and confirm your delivery details.</p>
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
        // Current meal ID for details modal
        let currentMealId = null;
        let currentMealTitle = null;
        
        // Function to open meal details modal
        function openMealDetails(mealId) {
            currentMealId = mealId;
            
            // Show loading state
            document.getElementById('detailsLoading').style.display = 'block';
            document.getElementById('detailsError').style.display = 'none';
            document.getElementById('detailsContent').style.display = 'none';
            
            // Open modal
            document.getElementById('mealDetailsModal').classList.add('active');
            
            // Fetch meal details
            const formData = new FormData();
            formData.append('action', 'get_meal_details');
            formData.append('meal_id', mealId);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                // Hide loading
                document.getElementById('detailsLoading').style.display = 'none';
                
                if (data.success) {
                    const meal = data.meal;
                    currentMealTitle = meal.Title;
                    
                    // Update modal content
                    document.getElementById('detailsTitle').textContent = meal.Title;
                    document.getElementById('detailsPrice').textContent = '₱' + parseFloat(meal.Price).toFixed(2);
                    document.getElementById('detailsCategory').textContent = meal.Category;
                    document.getElementById('detailsCategoryText').textContent = meal.Category;
                    document.getElementById('detailsDescription').textContent = meal.Description || 'No description provided.';
                    
                    // Set image
                    const imgElement = document.getElementById('detailsImage');
                    if (meal.ImagePath) {
                        imgElement.src = meal.ImagePath;
                    } else {
                        imgElement.src = 'https://images.unsplash.com/photo-1565958011703-44f9829ba187?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80';
                    }
                    imgElement.alt = meal.Title;
                    
                    // Set seller info
                    document.getElementById('sellerName').textContent = meal.SellerName;
                    if (meal.SellerEmail) {
                        document.getElementById('sellerEmail').textContent = meal.SellerEmail;
                    }
                    if (meal.SellerContact) {
                        document.getElementById('sellerPhone').textContent = meal.SellerContact;
                    } else {
                        document.getElementById('sellerPhone').textContent = 'Not provided';
                    }
                    
                    // Set availability
                    const availabilityStatus = document.getElementById('availabilityStatus');
                    const availabilityIcon = document.getElementById('availabilityIcon');
                    if (meal.Availability === 'Available') {
                        availabilityStatus.textContent = 'Available';
                        availabilityStatus.className = 'status status-available';
                        availabilityIcon.style.color = '#2a9d8f';
                    } else {
                        availabilityStatus.textContent = 'Not Available';
                        availabilityStatus.className = 'status status-not-available';
                        availabilityIcon.style.color = '#e63946';
                    }
                    
                    // Set rating
                    document.getElementById('detailsRatingText').textContent = data.rating.toFixed(1) + '/5';
                    document.getElementById('detailsReviewCount').textContent = data.reviewCount;
                    
                    // Update rating stars
                    const ratingDiv = document.getElementById('detailsRating');
                    let starsHTML = `
                        <div class="stars">
                            <span class="rating-value">${data.rating.toFixed(1)}</span>
                    `;
                    
                    // Add full stars
                    for (let i = 0; i < data.fullStars; i++) {
                        starsHTML += '<i class="fas fa-star star"></i>';
                    }
                    
                    // Add half star if needed
                    if (data.hasHalfStar) {
                        starsHTML += '<i class="fas fa-star-half-alt star"></i>';
                    }
                    
                    // Add empty stars
                    for (let i = 0; i < data.emptyStars; i++) {
                        starsHTML += '<i class="far fa-star star empty"></i>';
                    }
                    
                    starsHTML += `
                        </div>
                        <span class="review-count">(${data.reviewCount} reviews)</span>
                    `;
                    
                    ratingDiv.innerHTML = starsHTML;
                    
                    // Set posted date
                    if (meal.CreatedAt) {
                        const date = new Date(meal.CreatedAt);
                        document.getElementById('detailsPostedDate').textContent = date.toLocaleDateString('en-US', {
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric'
                        });
                    } else {
                        document.getElementById('detailsPostedDate').textContent = 'Recently';
                    }
                    
                    // Show content
                    document.getElementById('detailsContent').style.display = 'block';
                } else {
                    document.getElementById('detailsError').style.display = 'block';
                    document.getElementById('errorMessage').textContent = data.message || 'Failed to load meal details';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('detailsLoading').style.display = 'none';
                document.getElementById('detailsError').style.display = 'block';
                document.getElementById('errorMessage').textContent = 'Network error. Please try again.';
            });
        }
        
        // Function to close meal details modal
        function closeMealDetails() {
            document.getElementById('mealDetailsModal').classList.remove('active');
            currentMealId = null;
            currentMealTitle = null;
        }
        
        // Function to add to cart from details modal
        function addToCartFromDetails() {
            if (currentMealId && currentMealTitle) {
                addToCart(currentMealId, currentMealTitle);
            }
        }
        
        // DOM Elements
        const exploreBtn = document.getElementById('exploreBtn');
        const searchButton = document.getElementById('searchButton');
        const searchInput = document.getElementById('searchInput');
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

        // Update cart count with animation
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

        // Add to cart function
        function addToCart(mealId, mealTitle) {
            fetch('add-to-cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    meal_id: mealId,
                    quantity: 1
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(`"${mealTitle}" added to cart!`);
                    updateCartCount();
                } else {
                    showNotification(data.message || 'Error adding to cart', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error adding to cart', 'error');
            });
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('meal-details-modal')) {
                closeMealDetails();
            }
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

        // Initialize cart count display
        if (parseInt(cartCountElement.textContent) > 0) {
            cartCountElement.style.display = 'flex';
        } else {
            cartCountElement.style.display = 'none';
        }
    </script>

</body>
</html>