<?php
// profile.php
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

// Get user statistics
$total_orders = 0;
$scheduled_orders = 0;
$completed_orders = 0;

// Get total orders
$orders_sql = "SELECT COUNT(*) as total FROM `Order` WHERE CustomerID = ?";
$stmt = $conn->prepare($orders_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $total_orders = $row['total'];
}
$stmt->close();

// Get scheduled orders (Pending orders)
$scheduled_sql = "SELECT COUNT(*) as total FROM `Order` WHERE CustomerID = ? AND Status = 'Pending'";
$stmt = $conn->prepare($scheduled_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $scheduled_orders = $row['total'];
}
$stmt->close();

// Get completed orders
$completed_sql = "SELECT COUNT(*) as total FROM `Order` WHERE CustomerID = ? AND Status = 'Completed'";
$stmt = $conn->prepare($completed_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $completed_orders = $row['total'];
}
$stmt->close();

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

// Handle profile update
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $fullname = $_POST['fullname'];
        $email = $_POST['email'];
        $contactno = $_POST['contactno'];
        $address = $_POST['address'];
        
        // Check if email is already taken by another user
        $check_sql = "SELECT CustomerID FROM Customer WHERE Email = ? AND CustomerID != ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("si", $email, $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $message = "Email already exists. Please use a different email.";
            $message_type = "error";
        } else {
            // Update user profile
            $update_sql = "UPDATE Customer SET FullName = ?, Email = ?, ContactNo = ?, Address = ? WHERE CustomerID = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ssssi", $fullname, $email, $contactno, $address, $user_id);
            
            if ($update_stmt->execute()) {
                $message = "Profile updated successfully!";
                $message_type = "success";
                // Refresh user data
                $user['FullName'] = $fullname;
                $user['Email'] = $email;
                $user['ContactNo'] = $contactno;
                $user['Address'] = $address;
                
                // Update session data
                $_SESSION['full_name'] = $fullname;
                $_SESSION['email'] = $email;
            } else {
                $message = "Error updating profile: " . $conn->error;
                $message_type = "error";
            }
            $update_stmt->close();
        }
        $check_stmt->close();
    }
    
    // Handle profile picture upload
    if (isset($_POST['update_profile_pic']) && isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $file_type = $_FILES['profile_picture']['type'];
        $file_size = $_FILES['profile_picture']['size'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        // Validate file type and size
        if (!in_array($file_type, $allowed_types)) {
            $message = "Invalid file type. Only JPG, PNG, GIF, and WebP images are allowed.";
            $message_type = "error";
        } elseif ($file_size > $max_size) {
            $message = "File is too large. Maximum size is 5MB.";
            $message_type = "error";
        } else {
            // Generate unique filename
            $file_extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
            $new_filename = 'profile_' . $user_id . '_' . time() . '.' . $file_extension;
            $upload_dir = 'uploads/profile_pictures/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $upload_path = $upload_dir . $new_filename;
            
            // Move uploaded file
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                // Delete old profile picture if exists
                if (!empty($user['ImagePath']) && file_exists($user['ImagePath']) && $user['ImagePath'] !== 'uploads/profile_pictures/default.png') {
                    unlink($user['ImagePath']);
                }
                
                // Update database
                $update_pic_sql = "UPDATE Customer SET ImagePath = ? WHERE CustomerID = ?";
                $update_pic_stmt = $conn->prepare($update_pic_sql);
                $update_pic_stmt->bind_param("si", $upload_path, $user_id);
                
                if ($update_pic_stmt->execute()) {
                    $user['ImagePath'] = $upload_path;
                    $message = "Profile picture updated successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error updating profile picture in database.";
                    $message_type = "error";
                }
                $update_pic_stmt->close();
            } else {
                $message = "Error uploading file. Please try again.";
                $message_type = "error";
            }
        }
    }
    
    // Handle profile picture removal
    if (isset($_POST['remove_profile_pic'])) {
        if (!empty($user['ImagePath']) && file_exists($user['ImagePath']) && $user['ImagePath'] !== 'uploads/profile_pictures/default.png') {
            unlink($user['ImagePath']);
        }
        
        // Set default image path
        $default_image = 'uploads/profile_pictures/default.png';
        $update_pic_sql = "UPDATE Customer SET ImagePath = ? WHERE CustomerID = ?";
        $update_pic_stmt = $conn->prepare($update_pic_sql);
        $update_pic_stmt->bind_param("si", $default_image, $user_id);
        
        if ($update_pic_stmt->execute()) {
            $user['ImagePath'] = $default_image;
            $message = "Profile picture removed successfully!";
            $message_type = "success";
        } else {
            $message = "Error removing profile picture.";
            $message_type = "error";
        }
        $update_pic_stmt->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | LutongBahay</title>
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
        
        /* Profile Content */
        .profile-container {
            padding: 40px 0;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .profile-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .profile-header h1 {
            font-size: 2.5rem;
            color: var(--dark);
            margin-bottom: 10px;
        }
        
        .profile-header p {
            color: var(--gray);
            font-size: 1.1rem;
        }
        
        .profile-card {
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        
        .profile-card-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
        }
        
        /* Profile Picture Styles */
        .profile-pic-container {
            position: relative;
            display: inline-block;
            margin-bottom: 20px;
        }
        
        .profile-avatar {
            position: relative;
            width: 200px;
            height: 200px;
            border-radius: 50%;
            overflow: hidden;
            border: 5px solid rgba(255, 255, 255, 0.3);
            margin: 0 auto 20px;
            background-color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .profile-avatar:hover {
            transform: scale(1.05);
        }
        
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .profile-avatar-text {
            font-size: 4rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .profile-pic-upload {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background: var(--primary);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
            transition: var(--transition);
            border: 2px solid white;
            z-index: 10;
        }
        
        .profile-pic-upload:hover {
            background: var(--primary-dark);
            transform: scale(1.1);
        }
        
        .profile-pic-upload i {
            font-size: 1.2rem;
        }
        
        .profile-pic-options {
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            margin-top: 15px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.15);
            padding: 10px;
            min-width: 180px;
            z-index: 100;
            display: none;
            animation: fadeIn 0.2s ease;
        }
        
        .profile-pic-options.show {
            display: block;
        }
        
        .profile-pic-options::before {
            content: '';
            position: absolute;
            top: -8px;
            left: 50%;
            transform: translateX(-50%);
            width: 16px;
            height: 16px;
            background-color: white;
            transform: rotate(45deg);
            box-shadow: -2px -2px 5px rgba(0, 0, 0, 0.04);
        }
        
        .profile-pic-option {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 15px;
            color: var(--dark);
            text-decoration: none;
            cursor: pointer;
            transition: var(--transition);
            border-radius: 6px;
            font-size: 0.95rem;
            font-weight: 500;
        }
        
        .profile-pic-option:hover {
            background-color: var(--light-gray);
            color: var(--primary);
        }
        
        .profile-pic-option i {
            width: 20px;
            text-align: center;
        }
        
        .profile-pic-option.remove {
            color: var(--primary);
        }
        
        .profile-pic-option.remove:hover {
            background-color: rgba(230, 57, 70, 0.1);
        }
        
        .profile-card-header h2 {
            font-size: 1.8rem;
            margin-bottom: 5px;
        }
        
        .profile-card-header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .profile-card-body {
            padding: 40px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
            font-size: 1rem;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--light-gray);
            border-radius: 10px;
            font-size: 1rem;
            transition: var(--transition);
            background-color: #fff;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(230, 57, 70, 0.1);
        }
        
        .form-control:read-only {
            background-color: var(--light-gray);
            cursor: not-allowed;
        }
        
        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        .btn {
            padding: 14px 30px;
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
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }
        
        .btn-secondary {
            background-color: var(--light-gray);
            color: var(--dark);
        }
        
        .btn-secondary:hover {
            background-color: #ddd;
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }
        
        .profile-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-top: 40px;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            box-shadow: var(--shadow);
            transition: var(--transition);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: rgba(230, 57, 70, 0.1);
            color: var(--primary);
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 1.5rem;
            margin: 0 auto 15px;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: var(--gray);
            font-size: 0.95rem;
        }
        
        /* Message Styling */
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
        
        /* Profile Picture Modal */
        .pic-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            z-index: 2000;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s ease;
        }
        
        .pic-modal.show {
            display: flex;
        }
        
        .pic-modal-content {
            background: white;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            padding: 30px;
            text-align: center;
        }
        
        .pic-modal-header {
            margin-bottom: 25px;
        }
        
        .pic-modal-header h3 {
            font-size: 1.5rem;
            color: var(--dark);
            margin-bottom: 10px;
        }
        
        .pic-modal-body {
            margin-bottom: 25px;
        }
        
        .pic-preview {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            overflow: hidden;
            margin: 0 auto 20px;
            background-color: var(--light-gray);
        }
        
        .pic-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .pic-preview-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            font-size: 3rem;
            font-weight: 700;
        }
        
        .file-input-wrapper {
            position: relative;
            margin-bottom: 20px;
        }
        
        .file-input-label {
            display: inline-block;
            padding: 12px 25px;
            background-color: var(--primary);
            color: white;
            border-radius: 50px;
            cursor: pointer;
            transition: var(--transition);
            font-weight: 600;
        }
        
        .file-input-label:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }
        
        .file-input {
            position: absolute;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
            top: 0;
            left: 0;
        }
        
        .pic-modal-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
        }
        
        .pic-modal-btn {
            padding: 12px 30px;
            border-radius: 50px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            font-size: 1rem;
        }
        
        .pic-modal-btn.primary {
            background-color: var(--primary);
            color: white;
        }
        
        .pic-modal-btn.primary:hover {
            background-color: var(--primary-dark);
        }
        
        .pic-modal-btn.secondary {
            background-color: var(--light-gray);
            color: var(--dark);
        }
        
        .pic-modal-btn.secondary:hover {
            background-color: #ddd;
        }
        
        .pic-modal-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .pic-modal-btn:disabled:hover {
            transform: none;
            box-shadow: none;
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
            .profile-stats {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .footer-content {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .form-row {
                flex-direction: column;
                gap: 0;
            }
        }
        
        @media (max-width: 768px) {
            .profile-stats {
                grid-template-columns: 1fr;
            }
            
            .profile-card-body {
                padding: 25px;
            }
            
            .profile-header h1 {
                font-size: 2rem;
            }
            
            .footer-content {
                grid-template-columns: 1fr;
            }
            
            .nav-links {
                gap: 15px;
                font-size: 0.9rem;
            }
            
            .nav-container {
                flex-direction: column;
                gap: 15px;
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
            
            .profile-avatar {
                width: 120px;
                height: 120px;
            }
            
            .profile-avatar-text {
                font-size: 3rem;
            }
            
            .pic-modal-content {
                padding: 20px;
            }
            
            .pic-modal-header h3 {
                font-size: 1.3rem;
            }
            
            .pic-modal-actions {
                flex-direction: column;
            }
            
            .pic-modal-btn {
                width: 100%;
            }
        }
        
        @media (max-width: 576px) {
            .container {
                width: 95%;
                padding: 0 15px;
            }
            
            .profile-card-header {
                padding: 20px;
            }
            
            .profile-avatar {
                width: 100px;
                height: 100px;
            }
            
            .profile-avatar-text {
                font-size: 2.5rem;
            }
            
            .profile-header h1 {
                font-size: 1.8rem;
            }
            
            .profile-pic-upload {
                width: 35px;
                height: 35px;
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
                    <a href="orders.php">My Orders</a>
                    <a href="sellers.php">Sellers</a>
                </div>
                
                <div class="user-actions">
                    <a href="cart.php" class="cart-icon-link">
                        <div class="cart-icon">
                            <i class="fas fa-shopping-cart"></i>
                            <span class="cart-count" id="cartCount" style="display: <?php echo $cart_count > 0 ? 'flex' : 'none'; ?>;"><?php echo $cart_count; ?></span>
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

    <main class="container">
        <div class="profile-container">
            <div class="profile-header">
                <h1>My Profile</h1>
                <p>Manage your personal information and preferences</p>
            </div>
            
            <?php if ($message): ?>
                <div id="flashMessage" class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'error'; ?>">
                    <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <div class="profile-card">
                <div class="profile-card-header">
                    <!-- Profile Picture Section -->
                    <div class="profile-pic-container">
                        <div class="profile-avatar" id="profileAvatar">
                            <?php 
                            if (!empty($user['ImagePath']) && file_exists($user['ImagePath'])): 
                            ?>
                                <img src="<?php echo htmlspecialchars($user['ImagePath']); ?>" alt="Profile Picture">
                            <?php else: ?>
                                <div class="profile-avatar-text">
                                    <?php echo strtoupper(substr($user['FullName'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="profile-pic-upload" id="profilePicUpload">
                            <i class="fas fa-camera"></i>
                        </div>
                        <div class="profile-pic-options" id="profilePicOptions">
                            <div class="profile-pic-option" id="changeProfilePic">
                                <i class="fas fa-upload"></i>
                                Upload New Photo
                            </div>
                            <?php if (!empty($user['ImagePath']) && $user['ImagePath'] !== 'uploads/profile_pictures/default.png'): ?>
                                <div class="profile-pic-option remove" id="removeProfilePic">
                                    <i class="fas fa-trash-alt"></i>
                                    Remove Photo
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <h2><?php echo htmlspecialchars($user['FullName']); ?></h2>
                    <p><?php echo htmlspecialchars($user['Email']); ?></p>
                </div>
                
                <div class="profile-card-body">
                    <form method="POST" action="" enctype="multipart/form-data">
                        <input type="hidden" name="update_profile_pic" id="updateProfilePic" value="">
                        <input type="file" name="profile_picture" id="profilePictureInput" accept="image/*" style="display: none;">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="username">Username</label>
                                <input type="text" id="username" class="form-control" value="<?php echo htmlspecialchars($user['Username']); ?>" readonly>
                                <small style="color: var(--gray); margin-top: 5px; display: block;">Username cannot be changed</small>
                            </div>
                            <div class="form-group">
                                <label for="fullname">Full Name</label>
                                <input type="text" id="fullname" name="fullname" class="form-control" value="<?php echo htmlspecialchars($user['FullName']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['Email']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="contactno">Contact Number</label>
                                <input type="tel" id="contactno" name="contactno" class="form-control" value="<?php echo htmlspecialchars($user['ContactNo']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="address">Delivery Address</label>
                            <textarea id="address" name="address" class="form-control" rows="4" required><?php echo htmlspecialchars($user['Address']); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="createdAt">Member Since</label>
                            <input type="text" id="createdAt" class="form-control" value="<?php echo date('F d, Y', strtotime($user['CreatedAt'])); ?>" readonly>
                        </div>
                        
                        <div style="display: flex; gap: 15px; margin-top: 30px;">
                            <button type="submit" name="update_profile" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                            <a href="homepage.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Home
                            </a>
                        </div>
                    </form>
                    
                    <!-- Remove profile picture form -->
                    <form method="POST" action="" id="removeProfilePicForm" style="display: none;">
                        <input type="hidden" name="remove_profile_pic" value="1">
                    </form>
                    
                    <!-- Profile Stats -->
                    <div class="profile-stats">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                            <div class="stat-number"><?php echo $total_orders; ?></div>
                            <div class="stat-label">Total Orders</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div class="stat-number"><?php echo $scheduled_orders; ?></div>
                            <div class="stat-label">Pending Orders</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="stat-number"><?php echo $completed_orders; ?></div>
                            <div class="stat-label">Completed Orders</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Profile Picture Modal -->
    <div class="pic-modal" id="picModal">
        <div class="pic-modal-content">
            <div class="pic-modal-header">
                <h3>Update Profile Picture</h3>
                <p>Upload a new profile picture (max 5MB)</p>
            </div>
            <div class="pic-modal-body">
                <div class="pic-preview" id="picPreview">
                    <div class="pic-preview-placeholder" id="picPreviewPlaceholder">
                        <?php echo strtoupper(substr($user['FullName'], 0, 1)); ?>
                    </div>
                    <img src="" alt="Preview" id="picPreviewImage" style="display: none;">
                </div>
                <div class="file-input-wrapper">
                    <label for="modalProfilePicture" class="file-input-label">
                        <i class="fas fa-cloud-upload-alt"></i> Choose Image
                    </label>
                    <input type="file" id="modalProfilePicture" class="file-input" accept="image/*">
                </div>
                <p id="fileInfo" style="color: var(--gray); font-size: 0.9rem; margin-top: 10px;">
                    JPG, PNG, GIF, or WebP. Max 5MB.
                </p>
            </div>
            <div class="pic-modal-actions">
                <button type="button" class="pic-modal-btn secondary" id="cancelPicUpload">
                    Cancel
                </button>
                <button type="button" class="pic-modal-btn primary" id="uploadProfilePic" disabled>
                    <i class="fas fa-upload"></i> Upload
                </button>
            </div>
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
        
        // Profile Picture Elements
        const profilePicUpload = document.getElementById('profilePicUpload');
        const profilePicOptions = document.getElementById('profilePicOptions');
        const changeProfilePic = document.getElementById('changeProfilePic');
        const removeProfilePic = document.getElementById('removeProfilePic');
        const picModal = document.getElementById('picModal');
        const modalProfilePicture = document.getElementById('modalProfilePicture');
        const picPreview = document.getElementById('picPreview');
        const picPreviewPlaceholder = document.getElementById('picPreviewPlaceholder');
        const picPreviewImage = document.getElementById('picPreviewImage');
        const uploadProfilePic = document.getElementById('uploadProfilePic');
        const cancelPicUpload = document.getElementById('cancelPicUpload');
        const fileInfo = document.getElementById('fileInfo');
        const removeProfilePicForm = document.getElementById('removeProfilePicForm');
        const profilePictureInput = document.getElementById('profilePictureInput');

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
            
            // Close profile pic options
            if (!profilePicUpload.contains(e.target) && !profilePicOptions.contains(e.target)) {
                profilePicOptions.classList.remove('show');
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

        // Toggle profile picture options
        profilePicUpload.addEventListener('click', function(e) {
            e.stopPropagation();
            profilePicOptions.classList.toggle('show');
        });

        // Show profile picture modal
        changeProfilePic.addEventListener('click', function(e) {
            e.preventDefault();
            profilePicOptions.classList.remove('show');
            picModal.classList.add('show');
            
            // Reset modal
            picPreviewPlaceholder.style.display = 'flex';
            picPreviewImage.style.display = 'none';
            picPreviewImage.src = '';
            uploadProfilePic.disabled = true;
            modalProfilePicture.value = '';
            fileInfo.textContent = 'JPG, PNG, GIF, or WebP. Max 5MB.';
            fileInfo.style.color = 'var(--gray)';
        });

        // Remove profile picture
        if (removeProfilePic) {
            removeProfilePic.addEventListener('click', function(e) {
                e.preventDefault();
                if (confirm('Are you sure you want to remove your profile picture?')) {
                    profilePicOptions.classList.remove('show');
                    removeProfilePicForm.submit();
                }
            });
        }

        // Close profile picture modal
        cancelPicUpload.addEventListener('click', function() {
            picModal.classList.remove('show');
        });

        // Handle file selection in modal
        modalProfilePicture.addEventListener('change', function(e) {
            const file = e.target.files[0];
            
            if (file) {
                // Check file type
                const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                if (!validTypes.includes(file.type)) {
                    fileInfo.textContent = 'Invalid file type. Please select an image.';
                    fileInfo.style.color = 'var(--primary)';
                    uploadProfilePic.disabled = true;
                    return;
                }
                
                // Check file size (5MB max)
                if (file.size > 5 * 1024 * 1024) {
                    fileInfo.textContent = 'File is too large. Maximum size is 5MB.';
                    fileInfo.style.color = 'var(--primary)';
                    uploadProfilePic.disabled = true;
                    return;
                }
                
                // Show preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    picPreviewPlaceholder.style.display = 'none';
                    picPreviewImage.style.display = 'block';
                    picPreviewImage.src = e.target.result;
                };
                reader.readAsDataURL(file);
                
                // Enable upload button
                uploadProfilePic.disabled = false;
                fileInfo.textContent = `${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)`;
                fileInfo.style.color = 'var(--success)';
            }
        });

        // Handle profile picture upload
        uploadProfilePic.addEventListener('click', function() {
            if (modalProfilePicture.files.length > 0) {
                // Set the file in the hidden form input
                const file = modalProfilePicture.files[0];
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(file);
                profilePictureInput.files = dataTransfer.files;
                
                // Trigger form submission
                document.getElementById('updateProfilePic').value = '1';
                picModal.classList.remove('show');
                
                // Submit the form
                setTimeout(() => {
                    document.querySelector('form').submit();
                }, 300);
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

        // Format contact number input
        document.getElementById('contactno').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 11) {
                value = value.substring(0, 11);
            }
            e.target.value = value;
        });

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

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-hide flash messages after 2 seconds
            const flashMessage = document.getElementById('flashMessage');
            if (flashMessage) {
                setTimeout(() => {
                    flashMessage.style.opacity = '0';
                    setTimeout(() => {
                        flashMessage.style.display = 'none';
                    }, 500);
                }, 2000);
            }
            
            updateCartCount();
            
            // Close modal with Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    if (picModal.classList.contains('show')) {
                        picModal.classList.remove('show');
                    }
                }
            });
        });
    </script>
</body>
</html>