<?php
session_start();

// Check if user is logged in (seller only)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'seller') {
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

// Get seller details
$seller_id = $_SESSION['user_id'];
$seller_sql = "SELECT * FROM Seller WHERE SellerID = ?";
$stmt = $conn->prepare($seller_sql);
if ($stmt === false) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$seller_result = $stmt->get_result();
$seller = $seller_result->fetch_assoc();
$stmt->close();

// Check if seller exists
if (!$seller) {
    session_destroy();
    header('Location: index.php');
    exit();
}

// Initialize seller variables
$seller_name = isset($seller['FullName']) ? $seller['FullName'] : 'Seller';
$seller_email = isset($seller['Email']) ? $seller['Email'] : '';
$seller_phone = isset($seller['ContactNo']) ? $seller['ContactNo'] : 'Not provided';
$seller_address = isset($seller['Address']) ? $seller['Address'] : 'Not provided';
$seller_username = isset($seller['Username']) ? $seller['Username'] : '';
$seller_image = isset($seller['ImagePath']) ? $seller['ImagePath'] : '';
$seller_created = isset($seller['CreatedAt']) ? $seller['CreatedAt'] : date('Y-m-d');
$initial = isset($seller['FullName']) ? strtoupper(substr($seller['FullName'], 0, 1)) : 'S';

// Handle profile update
$update_success = false;
$update_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    
    // Validation
    if (empty($fullname) || empty($email)) {
        $update_error = "Full name and email are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $update_error = "Please enter a valid email address.";
    } else {
        // Check if email already exists for another seller
        $check_email_sql = "SELECT SellerID FROM Seller WHERE Email = ? AND SellerID != ?";
        $stmt = $conn->prepare($check_email_sql);
        $stmt->bind_param("si", $email, $seller_id);
        $stmt->execute();
        $check_result = $stmt->get_result();
        $stmt->close();
        
        if ($check_result->num_rows > 0) {
            $update_error = "Email is already registered by another seller.";
        } else {
            // Update seller profile
            $update_sql = "UPDATE Seller SET 
                           FullName = ?, 
                           Email = ?, 
                           ContactNo = ?, 
                           Address = ? 
                           WHERE SellerID = ?";
            $stmt = $conn->prepare($update_sql);
            if ($stmt) {
                $stmt->bind_param("ssssi", $fullname, $email, $phone, $address, $seller_id);
                if ($stmt->execute()) {
                    $update_success = true;
                    // Update session variables
                    $_SESSION['user_name'] = $fullname;
                    // Refresh seller data
                    $seller['FullName'] = $fullname;
                    $seller['Email'] = $email;
                    $seller['ContactNo'] = $phone;
                    $seller['Address'] = $address;
                    $seller_name = $fullname;
                    $seller_email = $email;
                    $seller_phone = $phone;
                    $seller_address = $address;
                    $initial = strtoupper(substr($fullname, 0, 1));
                } else {
                    $update_error = "Failed to update profile. Please try again.";
                }
                $stmt->close();
            } else {
                $update_error = "Database error. Please try again.";
            }
        }
    }
}

// Handle profile picture upload
$upload_success = false;
$upload_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_image'])) {
    $target_dir = "uploads/seller_profile/";
    
    // Create directory if it doesn't exist
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $file_name = time() . '_' . basename($_FILES["profile_image"]["name"]);
    $target_file = $target_dir . $file_name;
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    
    // Check if image file is actual image
    $check = getimagesize($_FILES["profile_image"]["tmp_name"]);
    if ($check === false) {
        $upload_error = "File is not an image.";
    }
    // Check file size (5MB limit)
    elseif ($_FILES["profile_image"]["size"] > 5000000) {
        $upload_error = "Sorry, your file is too large. Maximum size is 5MB.";
    }
    // Allow certain file formats
    elseif (!in_array($imageFileType, ['jpg', 'jpeg', 'png', 'gif'])) {
        $upload_error = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
    }
    // Try to upload file
    elseif (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $target_file)) {
        // First, delete old image if exists
        if ($seller_image && file_exists($seller_image)) {
            unlink($seller_image);
        }
        
        // Update database with new image path
        $update_image_sql = "UPDATE Seller SET ImagePath = ? WHERE SellerID = ?";
        $stmt = $conn->prepare($update_image_sql);
        $stmt->bind_param("si", $target_file, $seller_id);
        
        if ($stmt->execute()) {
            $upload_success = true;
            $seller_image = $target_file;
        } else {
            $upload_error = "Failed to update profile picture in database.";
        }
        $stmt->close();
    } else {
        $upload_error = "Sorry, there was an error uploading your file.";
    }
}

// Handle profile picture removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_picture'])) {
    // Delete the image file if it exists
    if ($seller_image && file_exists($seller_image)) {
        unlink($seller_image);
    }
    
    // Update database to remove image path
    $remove_image_sql = "UPDATE Seller SET ImagePath = NULL WHERE SellerID = ?";
    $stmt = $conn->prepare($remove_image_sql);
    $stmt->bind_param("i", $seller_id);
    
    if ($stmt->execute()) {
        $upload_success = true;
        $seller_image = '';
    } else {
        $upload_error = "Failed to remove profile picture from database.";
    }
    $stmt->close();
}

// Handle password change
$password_success = false;
$password_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = trim($_POST['current_password']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    // Validation
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $password_error = "All password fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $password_error = "New password and confirmation do not match.";
    } elseif (strlen($new_password) < 8) {
        $password_error = "New password must be at least 8 characters long.";
    } else {
        // Verify current password
        if (password_verify($current_password, $seller['Password'])) {
            // Hash new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update password in database
            $update_password_sql = "UPDATE Seller SET Password = ? WHERE SellerID = ?";
            $stmt = $conn->prepare($update_password_sql);
            $stmt->bind_param("si", $hashed_password, $seller_id);
            
            if ($stmt->execute()) {
                $password_success = true;
                // Show success message instead of auto-logout
                // Continue session so user can see the success message
            } else {
                $password_error = "Failed to change password. Please try again.";
            }
            $stmt->close();
        } else {
            $password_error = "Current password is incorrect.";
        }
    }
}

// Handle account deletion
$delete_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {
    $confirm_password = trim($_POST['confirm_delete_password']);
    $confirmation_text = trim($_POST['confirmation_text']);
    
    // Validation
    if (empty($confirm_password)) {
        $delete_error = "Please enter your password to confirm account deletion.";
    } elseif ($confirmation_text !== "DELETE MY ACCOUNT") {
        $delete_error = "Please type 'DELETE MY ACCOUNT' exactly as shown to confirm.";
    } elseif (!password_verify($confirm_password, $seller['Password'])) {
        $delete_error = "Incorrect password. Account deletion cancelled.";
    } else {
        // Delete profile picture if exists
        if ($seller_image && file_exists($seller_image)) {
            unlink($seller_image);
        }
        
        // Delete seller from database
        $delete_sql = "DELETE FROM Seller WHERE SellerID = ?";
        $stmt = $conn->prepare($delete_sql);
        $stmt->bind_param("i", $seller_id);
        
        if ($stmt->execute()) {
            // Destroy session
            session_destroy();
            // Redirect to homepage with deletion message
            header('Location: index.php?account_deleted=1');
            exit();
        } else {
            $delete_error = "Failed to delete account. Please try again or contact support.";
        }
        $stmt->close();
    }
}

// Get pending orders count
$pending_count = 0;
$pending_sql = "SELECT COUNT(DISTINCT o.OrderID) as pending_count
                FROM `Order` o
                JOIN OrderDetails od ON o.OrderID = od.OrderID
                JOIN Meal m ON od.MealID = m.MealID
                WHERE m.SellerID = ? AND o.Status = 'Pending'";
$stmt = $conn->prepare($pending_sql);
if ($stmt) {
    $stmt->bind_param("i", $seller_id);
    if ($stmt->execute()) {
        $pending_result = $stmt->get_result();
        if ($pending_result && $pending_result->num_rows > 0) {
            $pending_data = $pending_result->fetch_assoc();
            $pending_count = $pending_data['pending_count'] ?: 0;
        }
    }
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Profile | LutongBahay</title>
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
            --warning: #e9c46a;
            --danger: #e63946;
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
        
        /* Notification Badge */
        .notification-badge {
            position: relative;
            text-decoration: none;
            display: inline-block;
            transition: var(--transition);
            padding: 8px;
            border-radius: 50%;
            cursor: pointer;
        }
        
        .notification-badge:hover {
            background-color: rgba(230, 57, 70, 0.1);
            transform: translateY(-2px);
        }
        
        .notification-icon-bell {
            position: relative;
            font-size: 1.5rem;
            color: var(--dark);
        }
        
        .badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: var(--primary);
            color: white;
            font-size: 0.8rem;
            min-width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-weight: 600;
            padding: 0 4px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(230, 57, 70, 0.7);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(230, 57, 70, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(230, 57, 70, 0);
            }
        }
        
        /* Profile Dropdown - UPDATED WITH CIRCULAR IMAGE STYLES */
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

        .user-profile img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
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

        .user-initial img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
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
        
        /* Notification Modal */
        .notification-modal {
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
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        
        .notification-modal.show {
            opacity: 1;
            visibility: visible;
        }
        
        .notification-content {
            background-color: white;
            width: 90%;
            max-width: 800px;
            max-height: 80vh;
            border-radius: 20px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            transform: translateY(30px);
            transition: transform 0.3s ease;
        }
        
        .notification-modal.show .notification-content {
            transform: translateY(0);
        }
        
        .notification-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 25px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .notification-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .notification-header h2 i {
            font-size: 1.3rem;
        }
        
        .close-notification {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
        }
        
        .close-notification:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }
        
        .notification-body {
            padding: 0;
            max-height: 60vh;
            overflow-y: auto;
        }
        
        .notification-empty {
            padding: 60px 30px;
            text-align: center;
            color: var(--gray);
        }
        
        .notification-empty i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        
        .notification-empty h3 {
            font-size: 1.5rem;
            margin-bottom: 10px;
            color: var(--dark);
        }
        
        .notification-empty p {
            color: var(--gray);
        }
        
        /* Profile Container */
        .profile-container {
            padding: 40px 0;
        }
        
        .profile-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .profile-header h1 {
            font-size: 2.5rem;
            color: var(--dark);
            margin-bottom: 10px;
            font-weight: 800;
        }
        
        .profile-header p {
            color: var(--gray);
            font-size: 1.1rem;
        }
        
        .profile-content {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 40px;
        }
        
        @media (max-width: 992px) {
            .profile-content {
                grid-template-columns: 1fr;
            }
        }
        
        /* Profile Sidebar */
        .profile-sidebar {
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow);
            padding: 30px;
            height: fit-content;
            position: sticky;
            top: 100px;
        }
        
        .profile-avatar {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .avatar-circle {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 3.5rem;
            font-weight: 700;
            border: 5px solid white;
            box-shadow: 0 0 0 5px rgba(230, 57, 70, 0.1);
            overflow: hidden;
            position: relative;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .avatar-circle:hover {
            transform: scale(1.05);
        }
        
        .avatar-circle img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            position: absolute;
            top: 0;
            left: 0;
        }
        
        .avatar-circle .initial {
            position: absolute;
            z-index: 1;
        }
        
        .avatar-upload {
            display: none;
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 10px;
            font-size: 0.9rem;
            z-index: 2;
        }
        
        .avatar-circle:hover .avatar-upload {
            display: block;
        }
        
        .avatar-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .btn-avatar {
            padding: 12px 20px;
            border-radius: 10px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-avatar-primary {
            background-color: var(--primary);
            color: white;
        }
        
        .btn-avatar-primary:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        .btn-avatar-secondary {
            background-color: var(--light-gray);
            color: var(--dark);
        }
        
        .btn-avatar-secondary:hover {
            background-color: #ddd;
            transform: translateY(-2px);
        }
        
        .profile-stats {
            margin-top: 30px;
            border-top: 1px solid var(--light-gray);
            padding-top: 30px;
        }
        
        .profile-stats h3 {
            font-size: 1.2rem;
            margin-bottom: 20px;
            color: var(--dark);
            font-weight: 700;
        }
        
        .stat-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .stat-item:last-child {
            border-bottom: none;
        }
        
        .stat-label {
            color: var(--gray);
            font-size: 0.95rem;
        }
        
        .stat-value {
            font-weight: 600;
            color: var(--dark);
        }
        
        /* Profile Main Content */
        .profile-main {
            display: flex;
            flex-direction: column;
            gap: 40px;
        }
        
        .profile-section {
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow);
            padding: 20px 25px;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .section-header h2 {
            font-size: 1.5rem;
            color: var(--dark);
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-header h2 i {
            color: var(--primary);
            font-size: 1.3rem;
        }
        
        .btn-edit {
            background-color: transparent;
            border: 2px solid var(--primary);
            color: var(--primary);
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-edit:hover {
            background-color: var(--primary);
            color: white;
        }
        
        /* Info Grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
        }
        
        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .info-item.full-width {
            grid-column: 1 / -1;
        }
        
        .info-item label {
            font-size: 0.9rem;
            color: var(--gray);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .info-item .value {
            font-size: 1.1rem;
            color: var(--dark);
            font-weight: 600;
            padding: 12px 15px;
            background-color: var(--light-gray);
            border-radius: 10px;
            border-left: 4px solid var(--primary);
        }
        
        /* Form Styles - SINGLE COLUMN FOR CHANGE PASSWORD */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        
        /* Single column for change password */
        #changePasswordForm .form-grid {
            display: block;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 3px;
            margin-bottom: 12px;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-group label {
            font-size: 0.9rem;
            color: var(--dark);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 2px;
        }
        
        .form-group label i {
            color: var(--primary);
            font-size: 0.9rem;
        }
        
        .form-group .required {
            color: var(--primary);
            font-weight: 700;
        }
        
        /* UPDATED FORM CONTROL STYLES - FIXED HEIGHT */
        .form-control-container {
            position: relative;
            width: 100%;
        }
        
        .form-control {
            padding: 10px 45px 10px 12px;
            border: 2px solid var(--light-gray);
            border-radius: 8px;
            font-size: 0.95rem;
            color: var(--dark);
            transition: var(--transition);
            background-color: white;
            width: 100%;
            box-sizing: border-box;
            height: 42px; /* FIXED HEIGHT */
        }
        
        /* PASSWORD FIELDS WITH CONSISTENT STYLING */
        .password-input-group {
            position: relative;
            width: 100%;
        }
        
        .password-input-group .form-control {
            padding-right: 45px;
            width: 100%;
            margin-bottom: 3px;
            height: 42px; /* SAME HEIGHT */
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(230, 57, 70, 0.1);
        }
        
        .form-control::placeholder {
            color: #aaa;
        }
        
        .form-text {
            font-size: 0.8rem;
            color: var(--gray);
            margin-top: 2px;
        }
        
        /* TOGGLE PASSWORD STYLES - CONSISTENT FOR ALL */
        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--gray);
            cursor: pointer;
            font-size: 0.9rem;
            padding: 0;
            width: 25px;
            height: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
            z-index: 10;
        }
        
        .toggle-password:hover {
            color: var(--primary);
            background-color: rgba(0, 0, 0, 0.03);
            border-radius: 50%;
        }
        
        /* Password strength indicator - REMOVED FROM INSIDE INPUT */
        .password-strength-indicator {
            display: none; /* REMOVED */
        }
        
        /* Alerts */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: fadeIn 0.3s ease;
        }
        
        .alert-success {
            background-color: rgba(42, 157, 143, 0.1);
            border: 1px solid rgba(42, 157, 143, 0.2);
            color: var(--success);
        }
        
        .alert-danger {
            background-color: rgba(230, 57, 70, 0.1);
            border: 1px solid rgba(230, 57, 70, 0.2);
            color: var(--danger);
        }
        
        .alert i {
            font-size: 1.2rem;
        }
        
        /* Buttons */
        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 20px;
        }
        
        .btn {
            padding: 12px 25px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            font-size: 0.95rem;
            display: flex;
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
        
        .btn-secondary {
            background-color: var(--light-gray);
            color: var(--dark);
        }
        
        .btn-secondary:hover {
            background-color: #ddd;
            transform: translateY(-2px);
        }
        
        /* Danger Button Style */
        .btn-danger {
            background-color: var(--danger) !important;
            color: white !important;
            border: 2px solid var(--danger) !important;
        }
        
        .btn-danger:hover {
            background-color: #c1121f !important;
            border-color: #c1121f !important;
            transform: translateY(-2px);
        }
        
        /* Upload Modal */
        .upload-modal {
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
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        
        .upload-modal.show {
            opacity: 1;
            visibility: visible;
        }
        
        .upload-content {
            background-color: white;
            width: 90%;
            max-width: 500px;
            border-radius: 20px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            transform: translateY(30px);
            transition: transform 0.3s ease;
        }
        
        .upload-modal.show .upload-content {
            transform: translateY(0);
        }
        
        .upload-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 25px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .upload-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .upload-header h2 i {
            font-size: 1.3rem;
        }
        
        .close-upload {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
        }
        
        .close-upload:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }
        
        .upload-body {
            padding: 30px;
        }
        
        .preview-container {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .image-preview {
            width: 200px;
            height: 200px;
            border-radius: 50%;
            margin: 0 auto 20px;
            overflow: hidden;
            border: 3px solid var(--light-gray);
        }
        
        .image-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .upload-actions {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .file-input {
            position: relative;
        }
        
        .file-input input[type="file"] {
            position: absolute;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }
        
        .file-label {
            display: block;
            padding: 15px;
            background-color: var(--light-gray);
            border: 2px dashed var(--gray);
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .file-label:hover {
            background-color: #ddd;
            border-color: var(--primary);
        }
        
        .file-label i {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 10px;
        }
        
        .file-label span {
            display: block;
            font-weight: 600;
            color: var(--dark);
        }
        
        .file-label small {
            display: block;
            color: var(--gray);
            font-size: 0.9rem;
        }
        
        /* Account Deletion Styles */
        .danger-zone {
            border: 2px solid rgba(230, 57, 70, 0.3);
            background-color: rgba(230, 57, 70, 0.05);
            border-radius: 15px;
        }
        
        .danger-zone .section-header h2 {
            color: var(--danger);
        }
        
        .danger-zone .section-header h2 i {
            color: var(--danger);
        }
        
        .danger-warning {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 15px;
            background-color: rgba(230, 57, 70, 0.1);
            border-radius: 10px;
            margin-bottom: 15px;
        }
        
        .danger-warning i {
            font-size: 1.3rem;
            color: var(--danger);
            margin-top: 2px;
        }
        
        .danger-warning-content h3 {
            color: var(--danger);
            margin-bottom: 3px;
            font-size: 1rem;
        }
        
        .danger-warning-content p {
            color: var(--dark);
            font-size: 0.9rem;
            line-height: 1.5;
        }
        
        .confirmation-text {
            font-family: monospace;
            background-color: var(--light-gray);
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid #ddd;
            margin: 8px 0;
            font-weight: 600;
            color: var(--danger);
            text-align: center;
            font-size: 0.9rem;
        }
        
        .password-strength {
            margin-top: 2px;
            font-size: 0.8rem;
        }
        
        .strength-weak {
            color: var(--danger);
        }
        
        .strength-medium {
            color: var(--warning);
        }
        
        .strength-strong {
            color: var(--success);
        }
        
        /* Password strength meter */
        .strength-meter {
            height: 4px;
            border-radius: 2px;
            margin-top: 5px;
            margin-bottom: 5px;
            overflow: hidden;
            background-color: var(--light-gray);
        }
        
        .strength-meter-fill {
            height: 100%;
            width: 0%;
            transition: width 0.3s ease;
        }
        
        .strength-meter-fill.weak {
            background-color: var(--danger);
            width: 33%;
        }
        
        .strength-meter-fill.medium {
            background-color: var(--warning);
            width: 66%;
        }
        
        .strength-meter-fill.strong {
            background-color: var(--success);
            width: 100%;
        }
        
        /* Deletion Modal */
        .deletion-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 4000;
            display: flex;
            justify-content: center;
            align-items: center;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        
        .deletion-modal.show {
            opacity: 1;
            visibility: visible;
        }
        
        .deletion-content {
            background-color: white;
            width: 90%;
            max-width: 500px;
            border-radius: 20px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            transform: translateY(30px);
            transition: transform 0.3s ease;
        }
        
        .deletion-modal.show .deletion-content {
            transform: translateY(0);
        }
        
        .deletion-header {
            background-color: var(--danger);
            color: white;
            padding: 25px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .deletion-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .deletion-header h2 i {
            font-size: 1.3rem;
        }
        
        .close-deletion {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
        }
        
        .close-deletion:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }
        
        .deletion-body {
            padding: 30px;
        }
        
        .deletion-icon {
            text-align: center;
            margin-bottom: 25px;
        }
        
        .deletion-icon i {
            font-size: 4rem;
            color: var(--danger);
        }
        
        .deletion-warning {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .deletion-warning h3 {
            font-size: 1.3rem;
            color: var(--danger);
            margin-bottom: 10px;
        }
        
        .deletion-warning p {
            color: var(--dark);
            line-height: 1.7;
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
        
        /* Animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Responsive Design */
        @media (max-width: 1200px) {
            .footer-content {
                grid-template-columns: repeat(2, 1fr);
                gap: 30px;
            }
        }
        
        @media (max-width: 992px) {
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
            
            .profile-header h1 {
                font-size: 2rem;
            }
        }
        
        @media (max-width: 768px) {
            .profile-section {
                padding: 15px 20px;
            }
            
            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .upload-content {
                width: 95%;
            }
            
            .deletion-content {
                width: 95%;
            }
        }
        
        @media (max-width: 576px) {
            .container {
                width: 95%;
                padding: 0 15px;
            }
            
            .footer-content {
                grid-template-columns: 1fr;
                gap: 30px;
            }
            
            .avatar-circle {
                width: 120px;
                height: 120px;
                font-size: 2.5rem;
            }
            
            .form-control {
                padding: 10px 40px 10px 12px;
            }
        }
    </style>
</head>
<body>
<header>
    <div class="container">
        <div class="nav-container">
            <a href="seller-homepage.php" class="logo">
                <i class="fas fa-store"></i>
                LutongBahay Seller
            </a>
            
            <div class="nav-links">
                <a href="seller-homepage.php">Dashboard</a>
                <a href="manage-meals.php">Manage Meals</a>
                <a href="seller-orders.php">Orders</a>
                <a href="seller-sales.php">Sales Report</a>
            </div>
            
            <div class="user-actions">
                <!-- Notification badge for pending orders -->
                <div class="notification-badge" id="notificationToggle">
                    <div class="notification-icon-bell">
                        <i class="fas fa-bell"></i>
                        <?php if ($pending_count > 0): ?>
                            <span class="badge" id="pendingBadge"><?php echo $pending_count; ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Profile dropdown - UPDATED -->
                <div class="profile-dropdown">
                    <div class="user-profile" id="profileToggle">
                        <?php if (!empty($seller_image)): ?>
                            <img src="<?php echo htmlspecialchars($seller_image); ?>" alt="<?php echo htmlspecialchars($seller_name); ?>">
                        <?php else: ?>
                            <?php echo $initial; ?>
                        <?php endif; ?>
                    </div>
                    <div class="dropdown-menu" id="dropdownMenu">
                        <div class="dropdown-header">
                            <div class="user-info">
                                <div class="user-initial">
                                    <?php if (!empty($seller_image)): ?>
                                        <img src="<?php echo htmlspecialchars($seller_image); ?>" alt="<?php echo htmlspecialchars($seller_name); ?>">
                                    <?php else: ?>
                                        <?php echo $initial; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="user-details">
                                    <div class="user-name"><?php echo htmlspecialchars($seller_name); ?></div>
                                    <div class="user-email"><?php echo htmlspecialchars($seller_email); ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a href="seller-profile.php" class="dropdown-item">
                            <i class="fas fa-user"></i> Seller Profile
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

<!-- Notification Modal -->
<div class="notification-modal" id="notificationModal">
    <div class="notification-content">
        <div class="notification-header">
            <h2><i class="fas fa-bell"></i> Pending Orders (<?php echo $pending_count; ?>)</h2>
            <button class="close-notification" id="closeNotification">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="notification-body">
            <div class="notification-empty">
                <i class="fas fa-bell-slash"></i>
                <h3>No Pending Orders</h3>
                <p>You're all caught up! There are no pending orders at the moment.</p>
            </div>
        </div>
    </div>
</div>

<!-- Profile Content -->
<div class="container profile-container">
    <div class="profile-header">
        <h1>Seller Profile</h1>
        <p>Manage your personal information and account settings</p>
    </div>
    
    <?php if ($update_success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            Your profile has been updated successfully!
        </div>
    <?php endif; ?>
    
    <?php if ($update_error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($update_error); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($upload_success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            Profile picture updated successfully!
        </div>
    <?php endif; ?>
    
    <?php if ($upload_error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($upload_error); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($password_success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            Password changed successfully! You can now use your new password.
        </div>
    <?php endif; ?>
    
    <?php if ($password_error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($password_error); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($delete_error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($delete_error); ?>
        </div>
    <?php endif; ?>
    
    <div class="profile-content">
        <!-- Profile Sidebar -->
        <div class="profile-sidebar">
            <div class="profile-avatar">
                <div class="avatar-circle" id="avatarCircle">
                    <?php if (!empty($seller_image)): ?>
                        <img src="<?php echo htmlspecialchars($seller_image); ?>" alt="<?php echo htmlspecialchars($seller_name); ?>">
                        <div class="initial" style="display: none;"><?php echo $initial; ?></div>
                    <?php else: ?>
                        <div class="initial"><?php echo $initial; ?></div>
                    <?php endif; ?>
                    <div class="avatar-upload">
                        <i class="fas fa-camera"></i> Click to Change
                    </div>
                </div>
                <h3 style="text-align: center; margin-bottom: 10px; color: var(--dark);"><?php echo htmlspecialchars($seller_name); ?></h3>
                <p style="text-align: center; color: var(--gray); font-size: 0.9rem;">@<?php echo htmlspecialchars($seller_username); ?></p>
                <div class="avatar-actions" style="margin-top: 20px;">
                    <button type="button" class="btn-avatar btn-avatar-primary" onclick="showUploadModal()">
                        <i class="fas fa-camera"></i> Change Profile Picture
                    </button>
                    <?php if ($seller_image): ?>
                    <form method="POST" style="width: 100%;" onsubmit="return confirm('Are you sure you want to remove your profile picture?');">
                        <button type="submit" name="remove_picture" class="btn-avatar btn-avatar-secondary" style="width: 100%;">
                            <i class="fas fa-trash"></i> Remove Picture
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="profile-stats">
                <h3>Account Information</h3>
                <div class="stat-item">
                    <span class="stat-label">Seller ID</span>
                    <span class="stat-value">#<?php echo str_pad($seller_id, 6, '0', STR_PAD_LEFT); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Member Since</span>
                    <span class="stat-value"><?php echo date('M d, Y', strtotime($seller_created)); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Username</span>
                    <span class="stat-value">@<?php echo htmlspecialchars($seller_username); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Account Status</span>
                    <span class="stat-value" style="color: var(--success);">
                        <i class="fas fa-check-circle"></i> Active
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Profile Main Content -->
        <div class="profile-main">
            <!-- Personal Information -->
            <div class="profile-section">
                <div class="section-header">
                    <h2><i class="fas fa-user-circle"></i> Personal Information</h2>
                    <button class="btn-edit" id="editPersonalInfo">
                        <i class="fas fa-edit"></i> Edit Information
                    </button>
                </div>
                
                <div id="personalInfoView">
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Full Name</label>
                            <div class="value"><?php echo htmlspecialchars($seller_name); ?></div>
                        </div>
                        <div class="info-item">
                            <label>Email Address</label>
                            <div class="value"><?php echo htmlspecialchars($seller_email); ?></div>
                        </div>
                        <div class="info-item">
                            <label>Phone Number</label>
                            <div class="value"><?php echo htmlspecialchars($seller_phone); ?></div>
                        </div>
                        <div class="info-item full-width">
                            <label>Address</label>
                            <div class="value"><?php echo htmlspecialchars($seller_address); ?></div>
                        </div>
                    </div>
                </div>
                
                <form id="personalInfoForm" method="POST" style="display: none;">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="fullname"><i class="fas fa-user"></i> Full Name <span class="required">*</span></label>
                            <input type="text" id="fullname" name="fullname" class="form-control" 
                                   value="<?php echo htmlspecialchars($seller_name); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="email"><i class="fas fa-envelope"></i> Email Address <span class="required">*</span></label>
                            <input type="email" id="email" name="email" class="form-control" 
                                   value="<?php echo htmlspecialchars($seller_email); ?>" required>
                            <div class="form-text">We'll never share your email with anyone else.</div>
                        </div>
                        <div class="form-group">
                            <label for="phone"><i class="fas fa-phone"></i> Phone Number</label>
                            <input type="tel" id="phone" name="phone" class="form-control" 
                                   value="<?php echo htmlspecialchars($seller_phone); ?>">
                            <div class="form-text">Include country code (e.g., +63)</div>
                        </div>
                        <div class="form-group full-width">
                            <label for="address"><i class="fas fa-home"></i> Address</label>
                            <textarea id="address" name="address" class="form-control" 
                                      rows="3"><?php echo htmlspecialchars($seller_address); ?></textarea>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" id="cancelEdit">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Change Password - SINGLE COLUMN -->
            <div class="profile-section">
                <div class="section-header">
                    <h2><i class="fas fa-key"></i> Change Password</h2>
                </div>
                
                <form method="POST" id="changePasswordForm">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="current_password"><i class="fas fa-lock"></i> Current Password <span class="required">*</span></label>
                            <div class="password-input-group">
                                <input type="password" id="current_password" name="current_password" class="form-control" required>
                                <button type="button" class="toggle-password" data-target="current_password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="new_password"><i class="fas fa-key"></i> New Password <span class="required">*</span></label>
                            <div class="password-input-group">
                                <input type="password" id="new_password" name="new_password" class="form-control" required 
                                       pattern=".{8,}" oninput="checkPasswordStrength(this.value)">
                                <button type="button" class="toggle-password" data-target="new_password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="strength-meter">
                                <div class="strength-meter-fill" id="strengthMeter"></div>
                            </div>
                            <div class="password-strength" id="passwordStrength">
                                Password strength: <span id="strengthText">None</span>
                            </div>
                            <div class="form-text">Must be at least 8 characters long</div>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password"><i class="fas fa-key"></i> Confirm New Password <span class="required">*</span></label>
                            <div class="password-input-group">
                                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                                <button type="button" class="toggle-password" data-target="confirm_password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="form-text">Re-enter your new password</div>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="change_password" class="btn btn-primary">
                            <i class="fas fa-key"></i> Change Password
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Account Deletion - Danger Zone -->
            <div class="profile-section danger-zone">
                <div class="section-header">
                    <h2><i class="fas fa-exclamation-triangle"></i> Danger Zone</h2>
                </div>
                
                <div class="danger-warning">
                    <i class="fas fa-exclamation-circle"></i>
                    <div class="danger-warning-content">
                        <h3>Permanent Account Deletion</h3>
                        <p>
                            Once you delete your account, there is no going back. All your data including 
                            profile information, meal listings, order history, and sales records will be 
                            permanently deleted. This action cannot be undone.
                        </p>
                    </div>
                </div>
                
                <form method="POST" id="deleteAccountForm" onsubmit="return confirmDeletion()">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="confirm_delete_password"><i class="fas fa-lock"></i> Confirm Your Password <span class="required">*</span></label>
                            <div class="password-input-group">
                                <input type="password" id="confirm_delete_password" name="confirm_delete_password" class="form-control" required>
                                <button type="button" class="toggle-password" data-target="confirm_delete_password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="form-group full-width">
                            <label>Type the following to confirm: <span class="required">DELETE MY ACCOUNT</span></label>
                            <input type="text" id="confirmation_text" name="confirmation_text" class="form-control" 
                                   placeholder="Type DELETE MY ACCOUNT here" required>
                            <div class="form-text">You must type exactly as shown above to confirm account deletion</div>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="delete_account" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Delete My Account
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Upload Profile Picture Modal -->
<div class="upload-modal" id="uploadModal">
    <div class="upload-content">
        <div class="upload-header">
            <h2><i class="fas fa-camera"></i> Update Profile Picture</h2>
            <button class="close-upload" id="closeUpload">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="uploadForm" method="POST" enctype="multipart/form-data">
            <div class="upload-body">
                <div class="preview-container">
                    <div class="image-preview" id="imagePreview">
                        <?php if (!empty($seller_image)): ?>
                            <img src="<?php echo htmlspecialchars($seller_image); ?>" alt="Current Profile Picture" id="previewImage">
                        <?php else: ?>
                            <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: var(--light-gray); color: var(--gray); font-size: 4rem; font-weight: 700;">
                                <?php echo $initial; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <p style="color: var(--gray); font-size: 0.9rem;">Preview of your new profile picture</p>
                </div>
                
                <div class="upload-actions">
                    <div class="file-input">
                        <input type="file" id="profile_image" name="profile_image" accept="image/*" onchange="previewImage(event)">
                        <label for="profile_image" class="file-label">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <span>Choose a new photo</span>
                            <small>JPG, PNG or GIF (Max 5MB)</small>
                        </label>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeUploadModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload"></i> Upload Photo
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Footer -->
<footer>
    <div class="container">
        <div class="footer-content">
            <div>
                <div class="footer-logo">
                    <i class="fas fa-store"></i>
                    LutongBahay Seller
                </div>
                <p>Empowering Filipino home cooks and small food entrepreneurs to grow their businesses online since 2024.</p>
            </div>
            
            <div class="footer-links">
                <h3>Seller Dashboard</h3>
                <ul>
                    <li><a href="seller-homepage.php">Dashboard</a></li>
                    <li><a href="manage-meals.php">Manage Meals</a></li>
                    <li><a href="seller-orders.php">Orders</a></li>
                    <li><a href="seller-sales.php">Sales Report</a></li>
                </ul>
            </div>
            
            <div class="footer-links">
                <h3>Seller Resources</h3>
                <ul>
                    <li><a href="seller-guide.php">Seller Guide</a></li>
                    <li><a href="pricing.php">Pricing</a></li>
                    <li><a href="seller-support.php">Support Center</a></li>
                    <li><a href="seller-faq.php">FAQs</a></li>
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
            &copy; 2026 LutongBahay Seller Portal. Polytechnic University of the Philippines - Parañaque City Campus. All rights reserved.
        </div>
    </div>
</footer>

<script>
    // DOM Elements
    const profileToggle = document.getElementById('profileToggle');
    const dropdownMenu = document.getElementById('dropdownMenu');
    const logoutLink = document.getElementById('logoutLink');
    const notificationToggle = document.getElementById('notificationToggle');
    const notificationModal = document.getElementById('notificationModal');
    const closeNotification = document.getElementById('closeNotification');
    const editPersonalInfoBtn = document.getElementById('editPersonalInfo');
    const cancelEditBtn = document.getElementById('cancelEdit');
    const personalInfoView = document.getElementById('personalInfoView');
    const personalInfoForm = document.getElementById('personalInfoForm');
    const uploadModal = document.getElementById('uploadModal');
    const closeUpload = document.getElementById('closeUpload');
    const uploadForm = document.getElementById('uploadForm');
    const avatarCircle = document.getElementById('avatarCircle');
    const changePasswordForm = document.getElementById('changePasswordForm');

    // Toggle profile dropdown
    if (profileToggle && dropdownMenu) {
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
    }

    // Toggle notification modal
    if (notificationToggle && notificationModal) {
        notificationToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            notificationModal.classList.add('show');
            document.body.style.overflow = 'hidden';
        });

        // Close notification modal
        if (closeNotification) {
            closeNotification.addEventListener('click', function() {
                notificationModal.classList.remove('show');
                document.body.style.overflow = 'auto';
            });
        }

        // Close notification modal when clicking outside
        notificationModal.addEventListener('click', function(e) {
            if (e.target === notificationModal) {
                notificationModal.classList.remove('show');
                document.body.style.overflow = 'auto';
            }
        });
    }

    // Avatar click to show upload modal
    if (avatarCircle) {
        avatarCircle.addEventListener('click', function(e) {
            if (!e.target.closest('.btn-avatar')) {
                showUploadModal();
            }
        });
    }

    // Toggle personal info edit mode
    if (editPersonalInfoBtn) {
        editPersonalInfoBtn.addEventListener('click', function() {
            personalInfoView.style.display = 'none';
            personalInfoForm.style.display = 'block';
            editPersonalInfoBtn.style.display = 'none';
        });
    }

    // Cancel edit mode
    if (cancelEditBtn) {
        cancelEditBtn.addEventListener('click', function() {
            personalInfoForm.style.display = 'none';
            personalInfoView.style.display = 'block';
            editPersonalInfoBtn.style.display = 'flex';
        });
    }

    // Upload modal functionality
    function showUploadModal() {
        if (uploadModal) {
            uploadModal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }
    }

    function closeUploadModal() {
        if (uploadModal) {
            uploadModal.classList.remove('show');
            document.body.style.overflow = 'auto';
        }
    }

    if (closeUpload) {
        closeUpload.addEventListener('click', closeUploadModal);
    }

    // Close upload modal when clicking outside
    if (uploadModal) {
        uploadModal.addEventListener('click', function(e) {
            if (e.target === uploadModal) {
                closeUploadModal();
            }
        });
    }

    // Account deletion confirmation
    function confirmDeletion() {
        const password = document.getElementById('confirm_delete_password').value;
        const confirmation = document.getElementById('confirmation_text').value;
        
        if (!password) {
            showNotification('Please enter your password', 'error');
            return false;
        }
        
        if (confirmation !== "DELETE MY ACCOUNT") {
            showNotification('Please type DELETE MY ACCOUNT exactly as shown', 'error');
            return false;
        }
        
        return confirm("Are you ABSOLUTELY sure? This action cannot be undone! All your data will be permanently deleted.");
    }

    // Image preview functionality
    function previewImage(event) {
        const input = event.target;
        const preview = document.getElementById('previewImage');
        const previewContainer = document.getElementById('imagePreview');
        
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                if (!preview) {
                    // Create new image element if it doesn't exist
                    const img = document.createElement('img');
                    img.id = 'previewImage';
                    img.src = e.target.result;
                    img.alt = 'Preview';
                    previewContainer.innerHTML = '';
                    previewContainer.appendChild(img);
                } else {
                    preview.src = e.target.result;
                }
            };
            
            reader.readAsDataURL(input.files[0]);
        }
    }

    // Password strength checker
    function checkPasswordStrength(password) {
        const strengthText = document.getElementById('strengthText');
        const strengthDiv = document.getElementById('passwordStrength');
        const strengthMeter = document.getElementById('strengthMeter');
        
        // Reset classes
        strengthDiv.className = 'password-strength';
        strengthMeter.className = 'strength-meter-fill';
        
        if (!password) {
            strengthText.textContent = 'None';
            return;
        }
        
        let strength = 0;
        
        // Length check
        if (password.length >= 8) strength++;
        if (password.length >= 12) strength++;
        
        // Complexity checks
        if (/[A-Z]/.test(password)) strength++;
        if (/[a-z]/.test(password)) strength++;
        if (/[0-9]/.test(password)) strength++;
        if (/[^A-Za-z0-9]/.test(password)) strength++;
        
        // Determine strength level
        if (strength <= 2) {
            strengthText.textContent = 'Weak';
            strengthDiv.classList.add('strength-weak');
            strengthMeter.classList.add('weak');
        } else if (strength <= 4) {
            strengthText.textContent = 'Medium';
            strengthDiv.classList.add('strength-medium');
            strengthMeter.classList.add('medium');
        } else {
            strengthText.textContent = 'Strong';
            strengthDiv.classList.add('strength-strong');
            strengthMeter.classList.add('strong');
        }
    }

    // Toggle password visibility
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('toggle-password') || 
            e.target.parentElement.classList.contains('toggle-password')) {
            
            const button = e.target.classList.contains('toggle-password') ? 
                          e.target : e.target.parentElement;
            const targetId = button.getAttribute('data-target');
            const input = document.getElementById(targetId);
            const icon = button.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    });

    // Password change form validation
    if (changePasswordForm) {
        changePasswordForm.addEventListener('submit', function(e) {
            const currentPassword = document.getElementById('current_password').value;
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (!currentPassword || !newPassword || !confirmPassword) {
                e.preventDefault();
                showNotification('Please fill in all password fields', 'error');
                return false;
            }
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                showNotification('New password and confirmation do not match', 'error');
                document.getElementById('confirm_password').focus();
                return false;
            }
            
            if (newPassword.length < 8) {
                e.preventDefault();
                showNotification('New password must be at least 8 characters long', 'error');
                return false;
            }
            
            // Check if new password is same as current
            if (newPassword === currentPassword) {
                e.preventDefault();
                showNotification('New password must be different from current password', 'error');
                return false;
            }
            
            return true;
        });
    }

    // Show notification
    function showNotification(message, type = 'success') {
        const notification = document.createElement('div');
        notification.textContent = message;
        
        let bgColor, icon;
        switch(type) {
            case 'success':
                bgColor = 'var(--success)';
                icon = 'fas fa-check-circle';
                break;
            case 'error':
                bgColor = 'var(--danger)';
                icon = 'fas fa-exclamation-circle';
                break;
            case 'warning':
                bgColor = 'var(--warning)';
                icon = 'fas fa-exclamation-triangle';
                break;
            case 'info':
                bgColor = 'var(--primary)';
                icon = 'fas fa-info-circle';
                break;
            default:
                bgColor = 'var(--primary)';
                icon = 'fas fa-info-circle';
        }
        
        notification.innerHTML = `
            <i class="${icon}" style="margin-right: 10px;"></i>
            ${message}
        `;
        
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
            display: flex;
            align-items: center;
        `;
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.animation = 'fadeIn 0.3s ease reverse';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }, 3000);
    }

    // Logout confirmation
    if (logoutLink) {
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
                        Are you sure you want to logout?
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
            if (dropdownMenu) {
                dropdownMenu.classList.remove('show');
            }
            
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
    }

    // Form validation for file upload
    if (uploadForm) {
        uploadForm.addEventListener('submit', function(e) {
            const fileInput = document.getElementById('profile_image');
            if (fileInput.files.length === 0) {
                e.preventDefault();
                showNotification('Please select a photo to upload', 'error');
                return false;
            }
            
            const file = fileInput.files[0];
            const maxSize = 5 * 1024 * 1024; // 5MB
            
            if (file.size > maxSize) {
                e.preventDefault();
                showNotification('File size must be less than 5MB', 'error');
                return false;
            }
            
            const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            if (!validTypes.includes(file.type)) {
                e.preventDefault();
                showNotification('Only JPG, PNG, and GIF files are allowed', 'error');
                return false;
            }
            
            return true;
        });
    }

    // Initialize password strength check on page load
    const newPasswordInput = document.getElementById('new_password');
    if (newPasswordInput) {
        newPasswordInput.addEventListener('input', function() {
            checkPasswordStrength(this.value);
        });
    }
    
    // Auto-hide alerts after 5 seconds
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.5s ease';
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.parentNode.removeChild(alert);
                }
            }, 500);
        });
    }, 5000);
</script>

</body>
</html>