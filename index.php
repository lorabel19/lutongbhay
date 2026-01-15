<?php
session_start();

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

// Function to check password strength
function checkPasswordStrength($password) {
    $errors = [];
    
    // Minimum length
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }
    
    // Check for uppercase letter
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter";
    }
    
    // Check for lowercase letter
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter";
    }
    
    // Check for number
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number";
    }
    
    // Check for special character
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = "Password must contain at least one special character";
    }
    
    return $errors;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
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
            $redirect_page = 'seller-homepage.php';
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
            
            // VERIFY PASSWORD USING PASSWORD_VERIFY
            if (password_verify($password, $user['Password'])) {
                $_SESSION['user_id'] = $user[$id_field];
                $_SESSION['user_type'] = $user_type;
                $_SESSION['full_name'] = $user['FullName'];
                $_SESSION['email'] = $user['Email'];
                $_SESSION['username'] = $user['Username'];
                
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
        $full_name = trim($_POST['full_name']);
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $password = $_POST['password'];
        $user_type = $_POST['user_type'];
        
        // Validate required fields
        if (empty($full_name) || empty($username) || empty($email) || empty($password) || empty($user_type)) {
            echo json_encode([
                'success' => false,
                'message' => 'Please fill all required fields'
            ]);
            exit();
        }
        
        // Validate username (alphanumeric, underscores, hyphens)
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
            echo json_encode([
                'success' => false,
                'message' => 'Username can only contain letters, numbers, underscores and hyphens'
            ]);
            exit();
        }
        
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid email format'
            ]);
            exit();
        }
        
        // Check password strength
        $passwordErrors = checkPasswordStrength($password);
        if (!empty($passwordErrors)) {
            echo json_encode([
                'success' => false,
                'message' => implode('<br>', $passwordErrors)
            ]);
            exit();
        }
        
        // Check if email or username already exists
        if ($user_type === 'customer') {
            $check_sql = "SELECT Email, Username FROM Customer WHERE Email = ? OR Username = ?";
            $insert_sql = "INSERT INTO Customer (FullName, Username, Email, ContactNo, Address, Password) VALUES (?, ?, ?, ?, ?, ?)";
            $redirect_page = 'login.php';
            $id_field = 'CustomerID';
        } else if ($user_type === 'seller') {
            $check_sql = "SELECT Email, Username FROM Seller WHERE Email = ? OR Username = ?";
            $insert_sql = "INSERT INTO Seller (FullName, Username, Email, ContactNo, Address, Password) VALUES (?, ?, ?, ?, ?, ?)";
            $redirect_page = 'login.php';
            $id_field = 'SellerID';
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid user type selected'
            ]);
            exit();
        }
        
        // Check if email or username exists
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("ss", $email, $username);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            // Check which one exists
            $stmt->bind_result($existing_email, $existing_username);
            $stmt->fetch();
            
            if ($existing_email === $email) {
                $message = 'Email already exists. Please use a different email.';
            } else {
                $message = 'Username already exists. Please choose a different username.';
            }
            
            echo json_encode([
                'success' => false,
                'message' => $message
            ]);
            $stmt->close();
            $conn->close();
            exit();
        }
        $stmt->close();
        
        // HASH PASSWORD BEFORE SAVING TO DATABASE
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert new user
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("ssssss", $full_name, $username, $email, $phone, $address, $hashed_password);
        
        if ($stmt->execute()) {
            $user_id = $stmt->insert_id;
            
            echo json_encode([
                'success' => true,
                'message' => 'Account created successfully! You can now login with your credentials.',
                'redirect' => $redirect_page,
                'showLoginModal' => true
            ]);
        } else {
            error_log("Database error: " . $stmt->error);
            echo json_encode([
                'success' => false,
                'message' => 'Registration failed. Please try again. Error: ' . $stmt->error
            ]);
        }
        $stmt->close();
        $conn->close();
        exit();
    }
    
    // Handle meal details request
    if ($_POST['action'] == 'get_meal_details') {
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
}

// If not an AJAX request, continue to display the page
// Get featured meals from database
$featured_meals = [];

// SIMPLIFIED SQL QUERY
$sql = "SELECT m.*, s.FullName as SellerName 
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

$conn->close();
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
            padding: 5px 20px;
            display: block;
            position: relative;
            white-space: nowrap;
            transition: var(--transition);
        }
        
        .nav-links a:hover {
            color: var(--primary);
        }
        
        .nav-links a.active {
            color: var(--primary);
            font-weight: 700;
        }
        
        .nav-links a::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 2px;
            background-color: var(--primary);
            transition: width 0.3s ease;
        }
        
        .nav-links a:hover::after,
        .nav-links a.active::after {
            width: 70%;
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
            background: linear-gradient(rgba(0, 0, 0, 0.4), rgba(0, 0, 0, 0.4)), url('https://www.savoredjourneys.com/wp-content/uploads/2022/05/Bulalo.jpg');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 185px 0;
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
            font-size: 1rem;
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
        
        /* RATING STARS - From homepage.php */
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
            color: var(--dark);
        }
        
        .review-count {
            color: var(--gray);
            font-size: 0.9rem;
        }
        
        /* Featured Meals - From homepage.php with BIGGER IMAGE */
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
        
        .btn-order:disabled {
            opacity: 0.6;
            cursor: not-allowed;
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
        
        /* Staggered animation for 6 meal cards - From homepage.php */
        .meal-card:nth-child(1) { animation-delay: 0.1s; }
        .meal-card:nth-child(2) { animation-delay: 0.2s; }
        .meal-card:nth-child(3) { animation-delay: 0.3s; }
        .meal-card:nth-child(4) { animation-delay: 0.4s; }
        .meal-card:nth-child(5) { animation-delay: 0.5s; }
        .meal-card:nth-child(6) { animation-delay: 0.6s; }
        
        /* Category animations - From homepage.php */
        .category-card:nth-child(1) { animation-delay: 0.1s; }
        .category-card:nth-child(2) { animation-delay: 0.2s; }
        .category-card:nth-child(3) { animation-delay: 0.3s; }
        .category-card:nth-child(4) { animation-delay: 0.4s; }
        .category-card:nth-child(5) { animation-delay: 0.5s; }
        
        /* SIMPLIFIED MODAL STYLES */
        .simple-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }
        
        .simple-modal.active {
            display: flex;
        }
        
        .simple-modal-content {
            background-color: white;
            border-radius: 10px;
            width: 90%;
            max-width: 400px;
            overflow: hidden;
        }
        
        .simple-modal-header {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .simple-modal-header h2 {
            font-size: 1.3rem;
            margin: 0;
            color: var(--dark);
        }
        
        .simple-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #888;
        }
        
        .simple-modal-body {
            padding: 20px;
        }
        
        .simple-btn {
            padding: 12px 20px;
            border-radius: 5px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            font-size: 1rem;
            transition: all 0.2s;
        }
        
        .simple-btn-primary {
            background-color: var(--primary);
            color: white;
            border-radius: 50px;
        }
        
        .simple-btn-primary:hover {
            background-color: var(--primary-dark);
        }
        
        .simple-btn-outline {
            background-color: transparent;
            color: var(--primary);
            border: 2px solid var(--primary);
            border-radius: 50px;
        }
        
        .simple-btn-outline:hover {
            background-color: var(--primary);
            color: white;
        }
        
        .simple-btn-link {
            background: none;
            border: none;
            color: var(--primary);
            cursor: pointer;
            font-size: 0.9rem;
            padding: 5px 10px;
        }
        
        .simple-btn-link:hover {
            text-decoration: underline;
        }
        
        /* ORIGINAL MODAL STYLES (for login/signup) */
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
        
        /* Password Strength Indicator */
        .password-strength {
            margin-top: 8px;
            font-size: 0.85rem;
        }
        
        .password-requirements {
            background-color: var(--light-gray);
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
            font-size: 0.85rem;
        }
        
        .password-requirements h4 {
            margin-bottom: 8px;
            color: var(--dark);
            font-size: 0.9rem;
        }
        
        .password-requirements ul {
            list-style: none;
            padding-left: 0;
        }
        
        .password-requirements li {
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .password-requirements li i {
            font-size: 0.8rem;
        }
        
        .requirement-met {
            color: var(--success);
        }
        
        .requirement-not-met {
            color: var(--gray);
        }
        
        .strength-meter {
            height: 5px;
            background-color: var(--light-gray);
            border-radius: 5px;
            margin-top: 5px;
            overflow: hidden;
        }
        
        .strength-fill {
            height: 100%;
            width: 0%;
            border-radius: 5px;
            transition: width 0.3s ease, background-color 0.3s ease;
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
        
        /* Notification Styles */
        @keyframes fadeInOut {
            0% { opacity: 0; transform: translateY(-10px); }
            15% { opacity: 1; transform: translateY(0); }
            85% { opacity: 1; transform: translateY(0); }
            100% { opacity: 0; transform: translateY(-10px); }
        }
        
        /* Meal Details Modal - From homepage.php */
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
        
        /* Responsive - From homepage.php */
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
                gap: 20px;
            }
            
            .footer-content {
                grid-template-columns: repeat(2, 1fr);
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
            
            .meal-actions {
                flex-direction: column;
            }
            
            .meal-details-actions {
                flex-direction: column;
            }
            
            .meal-details-image {
                height: 250px;
            }
        }
        
        @media (max-width: 576px) {
            .hero h1 {
                font-size: 1.8rem;
            }
            
            .hero p {
                font-size: 1rem;
            }
            
            .section-title {
                font-size: 1.8rem;
            }
            
            .features {
                grid-template-columns: 1fr;
            }
            
            .footer-content {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- SIMPLIFIED Login Required Modal -->
    <div class="simple-modal" id="loginRequiredModal">
        <div class="simple-modal-content">
            <div class="simple-modal-header">
                <h2>Login Required</h2>
                <button class="simple-close" onclick="closeModal('loginRequiredModal')">&times;</button>
            </div>
            <div class="simple-modal-body">
                <div style="text-align: center; margin: 20px 0;">
                    <i class="fas fa-shopping-cart" style="font-size: 50px; color: var(--primary); margin-bottom: 15px;"></i>
                    <p style="margin-bottom: 20px;">Please login to add items to your cart.</p>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button class="simple-btn simple-btn-primary" onclick="openLoginModal()" style="flex: 1;">
                        Login
                    </button>
                    <button class="simple-btn simple-btn-outline" onclick="openSignupModal()" style="flex: 1;">
                        Sign Up
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Meal Details Modal - From homepage.php -->
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
                <input type="text" placeholder="Search for meals (e.g. Adobo, Sinigang, Leche Flan)" id="searchInput">
                <button id="searchBtn"><i class="fas fa-search"></i></button>
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
            <div class="category-card" onclick="redirectToBrowse('holiday')">
                <img src="https://kanamit.com.ph/cdn/shop/products/08-pancit-bihon.jpg?v=1678151947" alt="Holiday Specials">
                <div class="overlay">Holiday Specials</div>
            </div>
        </div>
    </section>

    <!-- Featured Meals - Updated with homepage.php style -->
    <section class="container" id="meals">
        <h2 class="section-title">Featured Meals Today</h2>
        <div class="meals-container">
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
                                <button class="btn-order add-to-cart-btn" data-meal-id="<?php echo $meal['MealID']; ?>" data-meal-title="<?php echo htmlspecialchars($meal['Title']); ?>">
                                    <i class="fas fa-shopping-cart"></i> Add to Cart
                                </button>
                                <button class="btn-view-details view-details-btn" data-meal-id="<?php echo $meal['MealID']; ?>">
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
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <h3 class="step-title">3. Add to Cart</h3>
                <p>Select your favorite meals and add them to your shopping cart.</p>
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
                    
                    <div class="form-group">
                        <label for="signupFullName">Full Name</label>
                        <input type="text" class="form-control" id="signupFullName" name="full_name" placeholder="Enter your full name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="signupUsername">Username</label>
                        <input type="text" class="form-control" id="signupUsername" name="username" placeholder="Enter your username" required>
                        <small style="color: var(--gray); margin-top: 5px; display: block;">Letters, numbers, underscores and hyphens only</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="signupEmail">Email Address</label>
                        <input type="email" class="form-control" id="signupEmail" name="email" placeholder="Enter your email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="signupPhone">Phone Number</label>
                        <input type="tel" class="form-control" id="signupPhone" name="phone" placeholder="Enter your phone number">
                    </div>
                    
                    <div class="form-group">
                        <label for="signupAddress">Address</label>
                        <input type="text" class="form-control" id="signupAddress" name="address" placeholder="Enter your address">
                    </div>
                    
                    <div class="form-group">
                        <label for="signupPassword">Password</label>
                        <div class="password-group">
                            <input type="password" class="form-control" id="signupPassword" name="password" placeholder="Create a strong password" required minlength="8" oninput="checkPasswordStrength()">
                            <button type="button" class="password-toggle" onclick="togglePassword('signupPassword')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        
                        <!-- Password Strength Meter -->
                        <div class="password-strength">
                            <div>Password Strength: <span id="strengthText">None</span></div>
                            <div class="strength-meter">
                                <div class="strength-fill" id="strengthFill"></div>
                            </div>
                        </div>
                        
                        <!-- Password Requirements -->
                        <div class="password-requirements">
                            <h4>Password Requirements:</h4>
                            <ul>
                                <li id="reqLength"><i class="fas fa-circle"></i> At least 8 characters</li>
                                <li id="reqUppercase"><i class="fas fa-circle"></i> At least one uppercase letter</li>
                                <li id="reqLowercase"><i class="fas fa-circle"></i> At least one lowercase letter</li>
                                <li id="reqNumber"><i class="fas fa-circle"></i> At least one number</li>
                                <li id="reqSpecial"><i class="fas fa-circle"></i> At least one special character</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="signupConfirmPassword">Confirm Password</label>
                        <div class="password-group">
                            <input type="password" class="form-control" id="signupConfirmPassword" name="confirm_password" placeholder="Confirm your password" required minlength="8" oninput="checkPasswordMatch()">
                            <button type="button" class="password-toggle" onclick="togglePassword('signupConfirmPassword')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="password-strength">
                            <div id="passwordMatchText"></div>
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
        
        // SIMPLIFIED Add to Cart Function
        function addToCart(mealId, mealTitle) {
            console.log('Adding to cart:', mealId, mealTitle);
            
            // Check if user is logged in
            <?php if (!isset($_SESSION['user_id'])): ?>
                showLoginRequired();
                return;
            <?php endif; ?>
            
            // Check if user is a customer
            <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] !== 'customer'): ?>
                showSimpleMessage('Only customers can add to cart', 'error');
                return;
            <?php endif; ?>
            
            // Show loading state
            const button = document.querySelector(`.add-to-cart-btn[data-meal-id="${mealId}"]`);
            const detailsButton = document.querySelector('.add-to-cart-details-btn');
            
            if (button) {
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                button.disabled = true;
                
                if (detailsButton) {
                    const detailsOriginalText = detailsButton.innerHTML;
                    detailsButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                    detailsButton.disabled = true;
                }
                
                // Simple AJAX request
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
                    // Restore buttons
                    button.innerHTML = originalText;
                    button.disabled = false;
                    
                    if (detailsButton) {
                        detailsButton.innerHTML = detailsOriginalText;
                        detailsButton.disabled = false;
                    }
                    
                    if (data.success) {
                        showSimpleMessage(`${mealTitle} added to cart!`);
                    } else {
                        if (data.message && data.message.includes('login')) {
                            showLoginRequired();
                        } else {
                            showSimpleMessage(data.message || 'Error adding to cart', 'error');
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    button.innerHTML = originalText;
                    button.disabled = false;
                    if (detailsButton) {
                        detailsButton.innerHTML = detailsOriginalText;
                        detailsButton.disabled = false;
                    }
                    showSimpleMessage('Network error', 'error');
                });
            }
        }
        
        function showLoginRequired() {
            closeModal('loginModal');
            closeModal('signupModal');
            closeMealDetails();
            document.getElementById('loginRequiredModal').classList.add('active');
        }
        
        function showSimpleMessage(message, type = 'success') {
            // Create simple message element
            const messageDiv = document.createElement('div');
            messageDiv.textContent = message;
            messageDiv.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background-color: ${type === 'error' ? '#e63946' : '#2a9d8f'};
                color: white;
                padding: 10px 20px;
                border-radius: 5px;
                z-index: 1000;
                font-weight: 600;
                animation: fadeInOut 3s ease;
            `;
            
            document.body.appendChild(messageDiv);
            
            // Remove after 3 seconds
            setTimeout(() => {
                messageDiv.remove();
            }, 3000);
        }
        
        // Modal Functions
        function openLoginModal(userType = '') {
            closeModal('signupModal');
            closeModal('loginRequiredModal');
            closeMealDetails();
            const modal = document.getElementById('loginModal');
            modal.classList.add('active');
            
            if (userType) {
                selectUserType(userType, 'login');
            }
            
            // Reset form and errors
            document.getElementById('loginError').classList.remove('active');
            document.getElementById('loginSuccess').classList.remove('active');
            document.getElementById('loginForm').reset();
        }
        
        function openSignupModal(userType = '') {
            closeModal('loginModal');
            closeModal('loginRequiredModal');
            closeMealDetails();
            const modal = document.getElementById('signupModal');
            modal.classList.add('active');
            
            // Set user type if specified
            if (userType) {
                selectUserType(userType, 'signup');
            }
            
            // Reset form and errors
            document.getElementById('signupError').classList.remove('active');
            document.getElementById('signupSuccess').classList.remove('active');
            document.getElementById('signupForm').reset();
            
            // Reset password strength indicators
            resetPasswordStrength();
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
            if (event.target.classList.contains('modal') || event.target.classList.contains('simple-modal') || event.target.classList.contains('meal-details-modal')) {
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
        
        // Password Strength Checker
        function checkPasswordStrength() {
            const password = document.getElementById('signupPassword').value;
            const strengthFill = document.getElementById('strengthFill');
            const strengthText = document.getElementById('strengthText');
            
            let strength = 0;
            
            // Check requirements
            const hasLength = password.length >= 8;
            const hasUppercase = /[A-Z]/.test(password);
            const hasLowercase = /[a-z]/.test(password);
            const hasNumber = /[0-9]/.test(password);
            const hasSpecial = /[^A-Za-z0-9]/.test(password);
            
            // Update requirement indicators
            updateRequirement('reqLength', hasLength);
            updateRequirement('reqUppercase', hasUppercase);
            updateRequirement('reqLowercase', hasLowercase);
            updateRequirement('reqNumber', hasNumber);
            updateRequirement('reqSpecial', hasSpecial);
            
            // Calculate strength
            if (hasLength) strength += 20;
            if (hasUppercase) strength += 20;
            if (hasLowercase) strength += 20;
            if (hasNumber) strength += 20;
            if (hasSpecial) strength += 20;
            
            // Update strength meter
            strengthFill.style.width = `${strength}%`;
            
            // Update strength text and color
            if (strength === 0) {
                strengthText.textContent = "None";
                strengthFill.style.backgroundColor = "#ddd";
            } else if (strength < 40) {
                strengthText.textContent = "Weak";
                strengthFill.style.backgroundColor = "#e63946";
            } else if (strength < 80) {
                strengthText.textContent = "Fair";
                strengthFill.style.backgroundColor = "#f4a261";
            } else if (strength < 100) {
                strengthText.textContent = "Good";
                strengthFill.style.backgroundColor = "#2a9d8f";
            } else {
                strengthText.textContent = "Strong";
                strengthFill.style.backgroundColor = "#2a9d8f";
            }
            
            // Check password match
            checkPasswordMatch();
        }
        
        function updateRequirement(elementId, isMet) {
            const element = document.getElementById(elementId);
            const icon = element.querySelector('i');
            
            if (isMet) {
                element.classList.add('requirement-met');
                element.classList.remove('requirement-not-met');
                icon.className = 'fas fa-check-circle';
            } else {
                element.classList.add('requirement-not-met');
                element.classList.remove('requirement-met');
                icon.className = 'fas fa-circle';
            }
        }
        
        function resetPasswordStrength() {
            const strengthFill = document.getElementById('strengthFill');
            const strengthText = document.getElementById('strengthText');
            
            strengthFill.style.width = '0%';
            strengthFill.style.backgroundColor = '#ddd';
            strengthText.textContent = 'None';
            
            // Reset requirement indicators
            ['reqLength', 'reqUppercase', 'reqLowercase', 'reqNumber', 'reqSpecial'].forEach(id => {
                const element = document.getElementById(id);
                const icon = element.querySelector('i');
                element.classList.add('requirement-not-met');
                element.classList.remove('requirement-met');
                icon.className = 'fas fa-circle';
            });
            
            // Reset password match text
            document.getElementById('passwordMatchText').textContent = '';
        }
        
        function checkPasswordMatch() {
            const password = document.getElementById('signupPassword').value;
            const confirmPassword = document.getElementById('signupConfirmPassword').value;
            const matchText = document.getElementById('passwordMatchText');
            
            if (confirmPassword === '') {
                matchText.textContent = '';
                matchText.style.color = '';
                return;
            }
            
            if (password === confirmPassword) {
                matchText.textContent = '✓ Passwords match';
                matchText.style.color = 'var(--success)';
            } else {
                matchText.textContent = '✗ Passwords do not match';
                matchText.style.color = 'var(--primary)';
            }
        }
        
        // Form Submission - AJAX
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
                
                const result = await response.json();
                
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
            } catch (error) {
                console.error('Error:', error);
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
            const username = document.getElementById('signupUsername').value;
            const password = document.getElementById('signupPassword').value;
            const confirmPassword = document.getElementById('signupConfirmPassword').value;
            
            // Validate username format
            const usernameRegex = /^[a-zA-Z0-9_-]+$/;
            if (!usernameRegex.test(username)) {
                errorDiv.innerHTML = 'Username can only contain letters, numbers, underscores and hyphens';
                errorDiv.classList.add('active');
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                return;
            }
            
            // Check password requirements
            const passwordErrors = [];
            if (password.length < 8) passwordErrors.push("Password must be at least 8 characters long");
            if (!/[A-Z]/.test(password)) passwordErrors.push("Password must contain at least one uppercase letter");
            if (!/[a-z]/.test(password)) passwordErrors.push("Password must contain at least one lowercase letter");
            if (!/[0-9]/.test(password)) passwordErrors.push("Password must contain at least one number");
            if (!/[^A-Za-z0-9]/.test(password)) passwordErrors.push("Password must contain at least one special character");
            
            if (passwordErrors.length > 0) {
                errorDiv.innerHTML = passwordErrors.join('<br>');
                errorDiv.classList.add('active');
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                return;
            }
            
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
                
                const result = await response.json();
                
                if (result.success) {
                    successDiv.textContent = result.message;
                    successDiv.classList.add('active');
                    
                    // Close modal after 1.5 seconds
                    setTimeout(() => {
                        closeModal('signupModal');
                    }, 1500);
                    
                    // Open login modal after 2 seconds
                    setTimeout(() => {
                        openLoginModal(document.getElementById('signupUserType').value);
                    }, 2000);
                    
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                } else {
                    errorDiv.innerHTML = result.message || 'Registration failed';
                    errorDiv.classList.add('active');
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
            } catch (error) {
                console.error('Error:', error);
                errorDiv.textContent = 'Network error. Please check your connection.';
                errorDiv.classList.add('active');
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        });
        
        // Helper functions
        function redirectToBrowse(category) {
            openSignupModal('customer');
        }
        
        // Search functionality
        document.getElementById('searchBtn').addEventListener('click', function() {
            const searchTerm = document.getElementById('searchInput').value;
            if(searchTerm.trim()) {
                openSignupModal('customer');
            }
        });

        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if(e.key === 'Enter') {
                document.getElementById('searchBtn').click();
            }
        });
        
        // Navigation scrolling
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

        // Set active state on nav links
        document.querySelectorAll('.nav-links a').forEach(link => {
            link.addEventListener('click', function() {
                document.querySelectorAll('.nav-links a').forEach(l => {
                    l.classList.remove('active');
                });
                this.classList.add('active');
            });
        });
        
        // Initialize add to cart and view details buttons
        document.addEventListener('DOMContentLoaded', function() {
            // Add event listeners to all "Add to Cart" buttons
            const addToCartButtons = document.querySelectorAll('.add-to-cart-btn');
            
            addToCartButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const mealId = this.getAttribute('data-meal-id');
                    const mealTitle = this.getAttribute('data-meal-title');
                    
                    if (mealId && mealTitle) {
                        addToCart(mealId, mealTitle);
                    } else {
                        showSimpleMessage('Error: Missing meal information', 'error');
                    }
                });
            });
            
            // Add event listeners to all "View Details" buttons
            const viewDetailsButtons = document.querySelectorAll('.view-details-btn');
            
            viewDetailsButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const mealId = this.getAttribute('data-meal-id');
                    
                    if (mealId) {
                        openMealDetails(mealId);
                    }
                });
            });
        });
    </script>
</body>
</html>