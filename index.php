<?php
session_start();
ob_start(); // Start output buffering

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

// Handle login if form was submitted via POST
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    ob_clean(); // Clear any output before JSON
    
    if ($_POST['action'] == 'login') {
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $user_type = $_POST['user_type'];
        
        if ($user_type === 'customer') {
            $sql = "SELECT * FROM Customer WHERE Email = ?";
            $redirect_page = 'homepage.php';
            $id_field = 'CustomerID';
        } else if ($user_type === 'seller') {
            $sql = "SELECT * FROM Seller WHERE Email = ?";
            $redirect_page = 'seller_dashboard.php';
            $id_field = 'SellerID';
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid user type selected'
            ]);
            exit();
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            
            // Verify password (plain text comparison for now)
            if ($password === $user['Password']) {
                $_SESSION['user_id'] = $user[$id_field];
                $_SESSION['user_type'] = $user_type;
                $_SESSION['full_name'] = $user['FullName'];
                $_SESSION['email'] = $user['Email'];
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Login successful!',
                    'redirect' => $redirect_page
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid email or password'
                ]);
            }
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid email or password'
            ]);
        }
        $stmt->close();
        $conn->close();
        exit();
    }
    
    // Handle signup
    if ($_POST['action'] == 'signup') {
        ob_clean();
        
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        $password = $_POST['password'];
        $user_type = $_POST['user_type'];
        
        // Check if email already exists
        if ($user_type === 'customer') {
            $check_sql = "SELECT Email FROM Customer WHERE Email = ?";
            $insert_sql = "INSERT INTO Customer (FullName, Email, ContactNo, Address, Password, Username) VALUES (?, ?, ?, ?, ?, ?)";
            $redirect_page = 'homepage.php';
            $id_field = 'CustomerID';
        } else if ($user_type === 'seller') {
            $check_sql = "SELECT Email FROM Seller WHERE Email = ?";
            $insert_sql = "INSERT INTO Seller (FullName, Email, ContactNo, Address, Password, Username) VALUES (?, ?, ?, ?, ?, ?)";
            $redirect_page = 'seller_dashboard.php';
            $id_field = 'SellerID';
        }
        
        // Check if email exists
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Email already exists'
            ]);
            $stmt->close();
            $conn->close();
            exit();
        }
        $stmt->close();
        
        // Generate username from email
        $username = explode('@', $email)[0];
        $username = preg_replace('/[^a-zA-Z0-9]/', '', $username);
        
        // Insert new user
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("ssssss", $full_name, $email, $phone, $address, $password, $username);
        
        if ($stmt->execute()) {
            $user_id = $stmt->insert_id;
            
            $_SESSION['user_id'] = $user_id;
            $_SESSION['user_type'] = $user_type;
            $_SESSION['full_name'] = $full_name;
            $_SESSION['email'] = $email;
            
            echo json_encode([
                'success' => true,
                'message' => 'Account created successfully!',
                'redirect' => $redirect_page
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Registration failed. Please try again.'
            ]);
        }
        $stmt->close();
        $conn->close();
        exit();
    }
}

// Get featured meals from database (only if not AJAX request)
$featured_meals = [];
$sql = "SELECT m.*, s.FullName as SellerName 
        FROM Meal m 
        JOIN Seller s ON m.SellerID = s.SellerID 
        WHERE m.Availability = 'Available' 
        ORDER BY m.CreatedAt DESC 
        LIMIT 6";

$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $featured_meals[] = $row;
    }
}

$conn->close();
ob_end_flush(); // End output buffering
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LutongBahay | Authentic Filipino Home-Cooked Meals</title>
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
        
        /* Header & Navigation - SIMPLE NO OVERLAP */
        header {
            background-color: white;
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 1000;
            width: 100%;
        }
        
        .container {
            width: 100%;
            max-width: 1200px;
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
    gap: 0;
    list-style: none;
    margin: 0;
    padding: 0;
}

.nav-links li {
    position: relative;
}

.nav-links a {
    color: var(--dark);
    text-decoration: none;
    font-weight: 600;
    font-size: 1rem;
    padding: 5px 20px; /* Increased padding for better spacing */
    display: block;
    position: relative;
    white-space: nowrap;
    /* REMOVE: border-bottom: 3px solid transparent; */
    transition: var(--transition);
}

/* HOVER EFFECT */
.nav-links a:hover {
    color: var(--primary);
    /* REMOVE: border-bottom: 2px solid var(--primary); */
}

/* ACTIVE STATE */
.nav-links a.active {
    color: var(--primary);
    font-weight: 700;
    /* REMOVE: border-bottom: 2px solid var(--primary); */
}

/* SHORTER UNDERLINE - Using pseudo-element only */
.nav-links a::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 0; /* Start with 0 width */
    height: 2px;
    background-color: var(--primary);
    transition: width 0.3s ease;
}

.nav-links a:hover::after,
.nav-links a.active::after {
    width: 70%; /* Expand to 70% on hover/active */
}
        
        
        .user-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .btn {
            padding: 10px 25px;
            border-radius: 50px;
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
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(230, 57, 70, 0.3);
        }
        
        .btn-outline {
            background-color: transparent;
            color: var(--primary);
            border: 2px solid var(--primary);
        }
        
        .btn-outline:hover {
            background-color: var(--primary);
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(230, 57, 70, 0.3);
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
            font-size: 2.8rem;
            margin-bottom: 20px;
            font-weight: 700;
        }
        
        .hero p {
            font-size: 1.2rem;
            max-width: 700px;
            margin: 0 auto 30px;
            opacity: 0.9;
        }
        
        .search-box {
            max-width: 600px;
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
            font-size: 1rem;
        }
        
        .search-box button {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 0 30px;
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
        
        /* Featured Categories */
        .section-title {
            text-align: center;
            margin: 60px 0 40px;
            font-size: 2.2rem;
            color: var(--dark);
            font-weight: 700;
        }
        
        .categories {
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 60px;
        }
        
        .category-card {
            width: 180px;
            height: 180px;
            border-radius: 15px;
            overflow: hidden;
            position: relative;
            box-shadow: var(--shadow);
            cursor: pointer;
            transition: var(--transition);
        }
        
        .category-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.15);
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
            padding: 15px;
            text-align: center;
            font-weight: 700;
            font-size: 1.1rem;
        }
        
        /* Featured Meals */
        .meals-container {
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
            position: relative;
        }
        
        .meal-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.12);
        }
        
        .meal-image {
            height: 200px;
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
            font-weight: 700;
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
            line-height: 1.5;
            height: 60px;
            overflow: hidden;
        }
        
        /* Meal Buttons */
        .meal-actions {
            display: flex;
            gap: 10px;
        }
        
        .meal-btn {
            padding: 10px 15px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            font-size: 0.9rem;
            text-decoration: none;
            text-align: center;
            flex: 1;
            border: none;
            min-height: 38px;
        }
        
        .btn-login {
            background-color: var(--primary);
            color: white;
        }
        
        .btn-login:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(230, 57, 70, 0.2);
        }
        
        .btn-signup {
            background-color: white;
            color: var(--primary);
            border: 2px solid var(--primary);
        }
        
        .btn-signup:hover {
            background-color: var(--primary);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(230, 57, 70, 0.2);
        }
        
        /* How It Works */
        .steps-container {
            display: flex;
            justify-content: center;
            gap: 40px;
            margin-bottom: 60px;
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
        
        /* Why Choose Section */
        .features {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 25px;
            margin: 50px 0;
            text-align: center;
        }
        
        .feature-item {
            padding: 20px;
        }
        
        .feature-icon {
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 15px;
        }
        
        .feature-item h3 {
            font-size: 1.3rem;
            margin-bottom: 10px;
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
            line-height: 1.6;
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
            justify-content: center;
            align-items: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background-color: white;
            border-radius: 20px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
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
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 25px 30px;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .modal-header h2 {
            font-size: 1.8rem;
            color: var(--dark);
            font-weight: 700;
        }
        
        .close-modal {
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
        
        .close-modal:hover {
            background-color: var(--light-gray);
            color: var(--primary);
        }
        
        .modal-body {
            padding: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
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
            padding: 14px 18px;
            border: 2px solid var(--light-gray);
            border-radius: 10px;
            font-size: 1rem;
            transition: var(--transition);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(230, 57, 70, 0.1);
        }
        
        .user-type-selector {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .user-type-btn {
            flex: 1;
            padding: 12px;
            text-align: center;
            border: 2px solid var(--light-gray);
            border-radius: 10px;
            cursor: pointer;
            transition: var(--transition);
            font-weight: 600;
            background-color: white;
            font-size: 0.95rem;
        }
        
        .user-type-btn:hover {
            border-color: var(--primary);
            background-color: rgba(230, 57, 70, 0.05);
        }
        
        .user-type-btn.active {
            border-color: var(--primary);
            background-color: rgba(230, 57, 70, 0.1);
            color: var(--primary);
        }
        
        .modal-footer {
            padding: 20px 30px;
            border-top: 1px solid var(--light-gray);
            text-align: center;
        }
        
        .modal-footer p {
            color: var(--gray);
            margin-bottom: 15px;
        }
        
        .switch-modal {
            color: var(--primary);
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
        }
        
        .switch-modal:hover {
            text-decoration: underline;
        }
        
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: var(--gray);
        }
        
        .password-group {
            position: relative;
        }
        
        .password-group .form-control {
            padding-right: 50px;
        }
        
        /* Alert Messages */
        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 600;
            font-size: 0.9rem;
            display: none;
        }
        
        .alert.active {
            display: block;
        }
        
        .alert-danger {
            background-color: rgba(230, 57, 70, 0.1);
            color: var(--primary);
            border: 1px solid rgba(230, 57, 70, 0.2);
        }
        
        .alert-success {
            background-color: rgba(42, 157, 143, 0.1);
            color: var(--success);
            border: 1px solid rgba(42, 157, 143, 0.2);
        }
        
        /* Responsive */
        @media (max-width: 1100px) {
            .meals-container {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .features {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .nav-links a {
                padding: 15px 15px;
                font-size: 0.95rem;
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
                width: 100%;
            }
            
            .nav-links li {
                flex: 1;
                min-width: 120px;
                text-align: center;
            }
            
            .nav-links a {
                padding: 12px 5px;
                font-size: 0.9rem;
            }
            
            .hero h1 {
                font-size: 2.2rem;
            }
            
            .meals-container {
                grid-template-columns: 1fr;
            }
            
            .footer-content {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .categories {
                gap: 15px;
            }
            
            .category-card {
                width: 150px;
                height: 150px;
            }
            
            .meal-actions {
                flex-direction: column;
            }
            
            .modal-content {
                width: 95%;
                max-height: 95vh;
            }
            
            .user-type-selector {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="container">
            <div class="nav-container">
                <a href="index.php" class="logo">
                    <i class="fas fa-utensils"></i>
                    LutongBahay
                </a>
                
                <ul class="nav-links">
                    <li><a href="index.php" class="active">Home</a></li>
                    <li><a href="#categories">Categories</a></li>
                    <li><a href="#meals">Featured Meals</a></li>
                    <li><a href="#how-it-works">How It Works</a></li>
                    <li><a href="#about">About</a></li>
                </ul>
                
                <div class="user-actions">
                    <div class="auth-buttons">
                        <button class="btn btn-outline" onclick="openLoginModal()">Login</button>
                        <button class="btn btn-primary" onclick="openSignupModal()">Sign Up</button>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <h1>Authentic Filipino Home-Cooked Meals</h1>
            <p>Discover delicious homemade meals from small food entrepreneurs and home cooks in your community. Order now or schedule for future dates!</p>
            
            <div class="search-box">
                <input type="text" placeholder="Search for meals (e.g. Adobo, Sinigang, Leche Flan)">
                <button><i class="fas fa-search"></i></button>
            </div>
            
            <div class="hero-cta">
                <a href="#meals" class="btn btn-primary">
                    <i class="fas fa-utensils"></i> Browse Meals
                </a>
                <button class="btn btn-outline" style="color: white; border-color: white;" onclick="openSignupModal('seller')">
                    <i class="fas fa-store"></i> Become a Seller
                </button>
            </div>
        </div>
    </section>

    <!-- Featured Categories -->
    <section class="container" id="categories">
        <h2 class="section-title">Popular Categories</h2>
        <div class="categories">
            <div class="category-card" onclick="redirectToBrowse('main')">
                <img src="https://www.unileverfoodsolutions.com.ph/chef-inspiration/food-delivery/10-crowd-favorite-filipino-dishes/jcr:content/parsys/set1/row2/span12/columncontrol_copy_c_1292622576/columnctrl_parsys_2/textimage_copy/image.transform/jpeg-optimized/image.1697455717956.jpg" alt="Main Dishes">
                <div class="overlay">Main Dishes</div>
            </div>
            <div class="category-card" onclick="redirectToBrowse('desserts')">
                <img src="https://homefoodie.com.ph/uploads/2021/Dec%202021/Queso%20de%20Bola%20Leche%20Flan.JPG" alt="Desserts">
                <div class="overlay">Desserts</div>
            </div>
            <div class="category-card" onclick="redirectToBrowse('merienda')">
                <img src="https://gttp.images.tshiftcdn.com/264409/x/0/philippines-street-food-turon.jpg?crop=1.91%3A1&fit=crop&width=1200" alt="Merienda">
                <div class="overlay">Merienda</div>
            </div>
            <div class="category-card" onclick="redirectToBrowse('vegetarian')">
                <img src="https://www.seriouseats.com/thmb/BHTueEcNShZmWVlwc4_VVmhfLYs=/1500x0/filters:no_upscale():max_bytes(150000):strip_icc()/20210712-pinakbet-vicky-wasik-seriouseats-12-37ac6b9ea57145728de86f927dc5fef6.jpg" alt="Vegetarian">
                <div class="overlay">Vegetarian</div>
            </div>
            <!-- Holiday Specials Category -->
            <div class="category-card" onclick="redirectToBrowse('holiday')">
                <img src="https://kanamit.com.ph/cdn/shop/products/08-pancit-bihon.jpg?v=1678151947" alt="Holiday Specials">
                <div class="overlay">Holiday Specials</div>
            </div>
        </div>
    </section>

    <!-- Featured Meals -->
    <section class="container" id="meals">
        <h2 class="section-title">Featured Meals Today</h2>
        <div class="meals-container">
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
                                <span style="margin-left: 10px; background-color: var(--light-gray); padding: 2px 8px; border-radius: 10px; font-size: 0.8rem;">
                                    <?php echo htmlspecialchars($meal['Category']); ?>
                                </span>
                            </div>
                            <p class="meal-description"><?php echo htmlspecialchars(substr($meal['Description'], 0, 100)) . '...'; ?></p>
                            <div class="meal-actions">
                                <button class="meal-btn btn-login" onclick="openLoginModal('order', <?php echo $meal['MealID']; ?>)">
                                    <i class="fas fa-sign-in-alt"></i> Login to Order
                                </button>
                                <button class="meal-btn btn-signup" onclick="openSignupModal('customer', 'order', <?php echo $meal['MealID']; ?>)">
                                    <i class="fas fa-user-plus"></i> Sign Up to Order
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 40px;">
                    <div style="font-size: 1.5rem; color: var(--gray); margin-bottom: 20px;">
                        <i class="fas fa-utensils" style="font-size: 2.5rem; color: #ddd;"></i>
                    </div>
                    <h3 style="color: var(--gray); margin-bottom: 15px;">No meals available yet</h3>
                    <p style="color: var(--gray); margin-bottom: 25px;">Be the first to showcase your home-cooked meals!</p>
                    <button class="btn btn-primary" onclick="openSignupModal('seller')">Become a Seller</button>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Interactive CTA after meals -->
        <div style="text-align: center; margin-top: 40px; padding: 30px; background-color: var(--light-gray); border-radius: 15px;">
            <h3 style="font-size: 1.5rem; margin-bottom: 15px; color: var(--dark);">Ready to Order?</h3>
            <p style="margin-bottom: 20px; color: var(--gray);">Sign up now to access all meals and place your orders!</p>
            <div style="display: flex; gap: 20px; justify-content: center;">
                <button class="btn btn-primary" onclick="openLoginModal()" style="display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-sign-in-alt"></i> Login to Existing Account
                </button>
                <button class="btn btn-outline" onclick="openSignupModal()" style="display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-user-plus"></i> Create New Account
                </button>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section class="container" id="how-it-works">
        <h2 class="section-title">How LutongBahay Works</h2>
        <div class="steps-container">
            <div class="step-card">
                <div class="step-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <h3 class="step-title">1. Create Account</h3>
                <p>Sign up as a customer or seller to start using our platform.</p>
            </div>
            <div class="step-card">
                <div class="step-icon">
                    <i class="fas fa-search"></i>
                </div>
                <h3 class="step-title">2. Browse Meals</h3>
                <p>Explore authentic Filipino dishes from home cooks and small food entrepreneurs.</p>
            </div>
            <div class="step-card">
                <div class="step-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <h3 class="step-title">3. Order Flexibly</h3>
                <p>Choose between immediate orders or schedule for future dates like Christmas!</p>
            </div>
            <div class="step-card">
                <div class="step-icon">
                    <i class="fas fa-utensils"></i>
                </div>
                <h3 class="step-title">4. Enjoy Meals</h3>
                <p>Savor delicious homemade meals while supporting local businesses.</p>
            </div>
        </div>
    </section>

    <!-- Why Choose Section -->
    <section class="container" id="about">
        <h2 class="section-title">Why Choose LutongBahay?</h2>
        <div class="features">
            <div class="feature-item">
                <div class="feature-icon">
                    <i class="fas fa-home"></i>
                </div>
                <h3>Home-Cooked</h3>
                <p>Authentic Filipino home cooking prepared with traditional recipes.</p>
            </div>
            <div class="feature-item">
                <div class="feature-icon">
                    <i class="fas fa-users"></i>
                </div>
                <h3>Support Local</h3>
                <p>Help small food entrepreneurs grow their businesses.</p>
            </div>
            <div class="feature-item">
                <div class="feature-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <h3>Flexible Orders</h3>
                <p>Order immediately or schedule for holidays and special occasions.</p>
            </div>
            <div class="feature-item">
                <div class="feature-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h3>Secure Platform</h3>
                <p>Safe and reliable platform connecting sellers with customers.</p>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer id="contact">
        <div class="container">
            <div class="footer-content">
                <div>
                    <div class="footer-logo">
                        <i class="fas fa-utensils"></i>
                        LutongBahay
                    </div>
                    <p>Connecting small food entrepreneurs with customers online. Supporting Filipino home cooks and food businesses since 2015.</p>
                </div>
                
                <div class="footer-links">
                    <h3>Quick Links</h3>
                    <ul>
                        <li><a href="index.php">Home</a></li>
                        <li><a href="#categories">Categories</a></li>
                        <li><a href="#meals">Featured Meals</a></li>
                        <li><a href="#how-it-works">How It Works</a></li>
                    </ul>
                </div>
                
                <div class="footer-links">
                    <h3>Account</h3>
                    <ul>
                        <li><a href="#" onclick="openLoginModal(); return false;">Login</a></li>
                        <li><a href="#" onclick="openSignupModal(); return false;">Sign Up</a></li>
                        <li><a href="#" onclick="openSignupModal('seller'); return false;">Become a Seller</a></li>
                        <li><a href="#">Help Center</a></li>
                    </ul>
                </div>
                
                <div class="footer-links">
                    <h3>Contact</h3>
                    <ul>
                        <li><a href="#"><i class="fas fa-map-marker-alt"></i> PUP Parañaque</a></li>
                        <li><a href="#"><i class="fas fa-phone"></i> (02) 1234-5678</a></li>
                        <li><a href="#"><i class="fas fa-envelope"></i> info@lutongbahay.com</a></li>
                    </ul>
                </div>
            </div>
            
            <div class="copyright">
                &copy; 2026 LutongBahay - Polytechnic University of the Philippines - Parañaque City Campus.
            </div>
        </div>
    </footer>

    <!-- Login Modal -->
    <div class="modal" id="loginModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Login to Your Account</h2>
                <button class="close-modal" onclick="closeModal('loginModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger" id="loginError"></div>
                <div class="alert alert-success" id="loginSuccess"></div>
                
                <form id="loginForm">
                    <div class="user-type-selector">
                        <div class="user-type-btn active" data-type="customer" onclick="selectUserType('customer', 'login')">
                            <i class="fas fa-user"></i> Customer
                        </div>
                        <div class="user-type-btn" data-type="seller" onclick="selectUserType('seller', 'login')">
                            <i class="fas fa-store"></i> Seller
                        </div>
                    </div>
                    
                    <input type="hidden" name="action" value="login">
                    <input type="hidden" name="user_type" id="loginUserType" value="customer">
                    
                    <div class="form-group">
                        <label for="loginEmail">Email Address</label>
                        <input type="email" class="form-control" id="loginEmail" name="email" placeholder="Enter your email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="loginPassword">Password</label>
                        <div class="password-group">
                            <input type="password" class="form-control" id="loginPassword" name="password" placeholder="Enter your password" required>
                            <button type="button" class="password-toggle" onclick="togglePassword('loginPassword')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-group" style="display: flex; justify-content: space-between; align-items: center;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <input type="checkbox" id="rememberMe" name="remember">
                            <label for="rememberMe" style="margin: 0;">Remember me</label>
                        </div>
                        <a href="#" style="color: var(--primary); font-size: 0.9rem;">Forgot Password?</a>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%; padding: 15px; font-size: 1.1rem;">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                </form>
            </div>
            <div class="modal-footer">
                <p>Don't have an account? <a href="#" class="switch-modal" onclick="switchToSignup()">Sign up here</a></p>
            </div>
        </div>
    </div>

    <!-- Signup Modal -->
    <div class="modal" id="signupModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Create Your Account</h2>
                <button class="close-modal" onclick="closeModal('signupModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger" id="signupError"></div>
                <div class="alert alert-success" id="signupSuccess"></div>
                
                <form id="signupForm">
                    <div class="user-type-selector">
                        <div class="user-type-btn active" data-type="customer" onclick="selectUserType('customer', 'signup')">
                            <i class="fas fa-user"></i> Customer
                        </div>
                        <div class="user-type-btn" data-type="seller" onclick="selectUserType('seller', 'signup')">
                            <i class="fas fa-store"></i> Seller
                        </div>
                    </div>
                    
                    <input type="hidden" name="action" value="signup">
                    <input type="hidden" name="user_type" id="signupUserType" value="customer">
                    <input type="hidden" name="redirect_type" id="redirectType" value="">
                    <input type="hidden" name="meal_id" id="mealId" value="">
                    
                    <div class="form-group">
                        <label for="signupFullName">Full Name</label>
                        <input type="text" class="form-control" id="signupFullName" name="full_name" placeholder="Enter your full name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="signupEmail">Email Address</label>
                        <input type="email" class="form-control" id="signupEmail" name="email" placeholder="Enter your email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="signupPhone">Phone Number</label>
                        <input type="tel" class="form-control" id="signupPhone" name="phone" placeholder="Enter your phone number" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="signupAddress">Address</label>
                        <input type="text" class="form-control" id="signupAddress" name="address" placeholder="Enter your address">
                    </div>
                    
                    <div class="form-group">
                        <label for="signupPassword">Password</label>
                        <div class="password-group">
                            <input type="password" class="form-control" id="signupPassword" name="password" placeholder="Create a password" required>
                            <button type="button" class="password-toggle" onclick="togglePassword('signupPassword')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="signupConfirmPassword">Confirm Password</label>
                        <div class="password-group">
                            <input type="password" class="form-control" id="signupConfirmPassword" name="confirm_password" placeholder="Confirm your password" required>
                            <button type="button" class="password-toggle" onclick="togglePassword('signupConfirmPassword')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <div style="display: flex; align-items: flex-start; gap: 10px;">
                            <input type="checkbox" id="termsAgreement" name="terms" required>
                            <label for="termsAgreement" style="margin: 0; font-size: 0.9rem;">
                                I agree to the <a href="#" style="color: var(--primary);">Terms of Service</a> and <a href="#" style="color: var(--primary);">Privacy Policy</a>
                            </label>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%; padding: 15px; font-size: 1.1rem;">
                        <i class="fas fa-user-plus"></i> Create Account
                    </button>
                </form>
            </div>
            <div class="modal-footer">
                <p>Already have an account? <a href="#" class="switch-modal" onclick="switchToLogin()">Login here</a></p>
            </div>
        </div>
    </div>

    <script>
        // Modal Functions
        function openLoginModal(redirectType = '', mealId = '') {
            closeModal('signupModal');
            const modal = document.getElementById('loginModal');
            modal.classList.add('active');
            
            if (redirectType) {
                document.getElementById('loginForm').dataset.redirect = redirectType;
                document.getElementById('loginForm').dataset.mealId = mealId;
            }
            
            // Reset form and errors
            document.getElementById('loginError').classList.remove('active');
            document.getElementById('loginSuccess').classList.remove('active');
            document.getElementById('loginForm').reset();
        }
        
        function openSignupModal(userType = '', redirectType = '', mealId = '') {
            closeModal('loginModal');
            const modal = document.getElementById('signupModal');
            modal.classList.add('active');
            
            // Set user type if specified
            if (userType) {
                selectUserType(userType, 'signup');
            }
            
            // Set redirect info
            if (redirectType) {
                document.getElementById('redirectType').value = redirectType;
            }
            if (mealId) {
                document.getElementById('mealId').value = mealId;
            }
            
            // Reset form and errors
            document.getElementById('signupError').classList.remove('active');
            document.getElementById('signupSuccess').classList.remove('active');
            document.getElementById('signupForm').reset();
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
        
        function switchToSignup() {
            closeModal('loginModal');
            openSignupModal();
        }
        
        function switchToLogin() {
            closeModal('signupModal');
            openLoginModal();
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }
        
        // User type selection
        function selectUserType(type, formType) {
            const buttons = document.querySelectorAll(`#${formType}Modal .user-type-btn`);
            buttons.forEach(btn => {
                btn.classList.remove('active');
            });
            
            const activeBtn = document.querySelector(`#${formType}Modal .user-type-btn[data-type="${type}"]`);
            if (activeBtn) {
                activeBtn.classList.add('active');
                document.getElementById(`${formType}UserType`).value = type;
            }
        }
        
        // Toggle password visibility
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const toggleBtn = input.parentElement.querySelector('.password-toggle i');
            
            if (input.type === 'password') {
                input.type = 'text';
                toggleBtn.classList.remove('fa-eye');
                toggleBtn.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                toggleBtn.classList.remove('fa-eye-slash');
                toggleBtn.classList.add('fa-eye');
            }
        }
        
        // Form Submission - AJAX (FIXED VERSION)
        document.getElementById('loginForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const errorDiv = document.getElementById('loginError');
            const successDiv = document.getElementById('loginSuccess');
            
            // Clear previous messages
            errorDiv.classList.remove('active');
            successDiv.classList.remove('active');
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Logging in...';
            submitBtn.disabled = true;
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                    },
                    body: formData
                });
                
                // Get response as text first
                const responseText = await response.text();
                
                try {
                    const result = JSON.parse(responseText);
                    
                    if (result.success) {
                        successDiv.textContent = result.message;
                        successDiv.classList.add('active');
                        
                        // Redirect after 1 second
                        setTimeout(() => {
                            window.location.href = result.redirect;
                        }, 1000);
                    } else {
                        errorDiv.textContent = result.message || 'Login failed';
                        errorDiv.classList.add('active');
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    }
                } catch (jsonError) {
                    console.error('JSON parse error:', jsonError);
                    console.error('Response text:', responseText);
                    errorDiv.textContent = 'Server error. Please try again.';
                    errorDiv.classList.add('active');
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
            } catch (error) {
                console.error('Fetch error:', error);
                errorDiv.textContent = 'Network error. Please check your connection.';
                errorDiv.classList.add('active');
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        });
        
        document.getElementById('signupForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const errorDiv = document.getElementById('signupError');
            const successDiv = document.getElementById('signupSuccess');
            
            // Clear previous messages
            errorDiv.classList.remove('active');
            successDiv.classList.remove('active');
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating account...';
            submitBtn.disabled = true;
            
            // Basic client-side validation
            const password = document.getElementById('signupPassword').value;
            const confirmPassword = document.getElementById('signupConfirmPassword').value;
            
            if (password !== confirmPassword) {
                errorDiv.textContent = 'Passwords do not match';
                errorDiv.classList.add('active');
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                return;
            }
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                    },
                    body: formData
                });
                
                // Get response as text first
                const responseText = await response.text();
                
                try {
                    const result = JSON.parse(responseText);
                    
                    if (result.success) {
                        successDiv.textContent = result.message;
                        successDiv.classList.add('active');
                        
                        // Redirect after 1 second
                        setTimeout(() => {
                            window.location.href = result.redirect;
                        }, 1000);
                    } else {
                        errorDiv.textContent = result.message || 'Registration failed';
                        errorDiv.classList.add('active');
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    }
                } catch (jsonError) {
                    console.error('JSON parse error:', jsonError);
                    console.error('Response text:', responseText);
                    errorDiv.textContent = 'Server error. Please try again.';
                    errorDiv.classList.add('active');
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
            } catch (error) {
                console.error('Fetch error:', error);
                errorDiv.textContent = 'Network error. Please check your connection.';
                errorDiv.classList.add('active');
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        });
        
        // Helper functions
        function showError(elementId, message) {
            const element = document.getElementById(elementId);
            element.textContent = message;
            element.classList.add('active');
            
            // Hide after 5 seconds
            setTimeout(() => {
                element.classList.remove('active');
            }, 5000);
        }
        
        function redirectToBrowse(category) {
            openSignupModal('customer', 'browse');
            // You could set category in hidden field if needed
        }
        
        // Search functionality
        document.querySelector('.search-box button').addEventListener('click', function() {
            const searchTerm = document.querySelector('.search-box input').value;
            if(searchTerm.trim()) {
                openSignupModal('customer', 'search');
                // You could store the search term in sessionStorage or a hidden field
            }
        });

        document.querySelector('.search-box input').addEventListener('keypress', function(e) {
            if(e.key === 'Enter') {
                document.querySelector('.search-box button').click();
            }
        });
        
        // TANGGALIN ANG AUTO-ACTIVE NA JAVASCRIPT
        // Simple scrolling only, walang active class change sa scroll
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const targetId = this.getAttribute('href');
                if(targetId === '#') return;
                
                const targetElement = document.querySelector(targetId);
                if(targetElement) {
                    window.scrollTo({
                        top: targetElement.offsetTop - 80,
                        behavior: 'smooth'
                    });
                }
            });
        });

        // MANUAL ACTIVE STATE - NO AUTO SCROLL DETECTION
        document.querySelectorAll('.nav-links a').forEach(link => {
            link.addEventListener('click', function() {
                // Remove active from all
                document.querySelectorAll('.nav-links a').forEach(l => {
                    l.classList.remove('active');
                });
                // Add active to clicked
                this.classList.add('active');
            });
        });

        // Interactive hover effects for meal buttons
        document.querySelectorAll('.meal-btn').forEach(button => {
            button.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px)';
            });
            
            button.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });

        // Debug function to test login manually
        window.testLogin = async function(email, password, userType = 'customer') {
            const formData = new FormData();
            formData.append('action', 'login');
            formData.append('email', email);
            formData.append('password', password);
            formData.append('user_type', userType);
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                const text = await response.text();
                console.log('Response:', text);
                return JSON.parse(text);
            } catch (error) {
                console.error('Test error:', error);
                return null;
            }
        };
    </script>
</body>
</html>