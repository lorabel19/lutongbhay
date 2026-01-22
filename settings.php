<?php
// settings.php - COMPLETE VERSION WITH DELETE ACCOUNT
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

// Get user info
if ($user_type === 'customer') {
    $user_sql = "SELECT * FROM Customer WHERE CustomerID = ?";
    $table_name = "Customer";
    $id_column = "CustomerID";
} else {
    $user_sql = "SELECT * FROM Seller WHERE SellerID = ?";
    $table_name = "Seller";
    $id_column = "SellerID";
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

// Check if user exists
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
    if ($cart_result) {
        $cart_data = $cart_result->fetch_assoc();
        $cart_count = $cart_data['cart_count'] ?: 0;
    }
    $cart_stmt->close();
}

$message = '';
$message_type = '';

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Verify current password
        if (!password_verify($current_password, $user['Password'])) {
            $message = "Current password is incorrect";
            $message_type = "error";
        } elseif ($new_password !== $confirm_password) {
            $message = "New passwords do not match";
            $message_type = "error";
        } elseif (strlen($new_password) < 8) {
            $message = "New password must be at least 8 characters long";
            $message_type = "error";
        } else {
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_sql = "UPDATE $table_name SET Password = ? WHERE $id_column = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($update_stmt->execute()) {
                $message = "Password changed successfully!";
                $message_type = "success";
            } else {
                $message = "Error changing password: " . $conn->error;
                $message_type = "error";
            }
            $update_stmt->close();
        }
    }
    
    // Handle account deletion
    if (isset($_POST['delete_account'])) {
        $confirm_text = $_POST['delete_confirm'] ?? '';
        $password = $_POST['delete_password'] ?? '';
        
        if (empty($password)) {
            $message = "Please enter your password to confirm deletion";
            $message_type = "error";
        } elseif (!password_verify($password, $user['Password'])) {
            $message = "Password is incorrect";
            $message_type = "error";
        } elseif (strtoupper($confirm_text) !== 'DELETE') {
            $message = "Please type 'DELETE' in all caps to confirm";
            $message_type = "error";
        } else {
            // Start transaction
            $conn->begin_transaction();
            
            try {
                if ($user_type === 'customer') {
                    // Delete customer-related data
                    $delete_sql = "DELETE FROM Customer WHERE CustomerID = ?";
                    
                    // Delete cart items first (if exists)
                    $delete_cart_sql = "DELETE FROM Cart WHERE CustomerID = ?";
                    $stmt_cart = $conn->prepare($delete_cart_sql);
                    $stmt_cart->bind_param("i", $user_id);
                    $stmt_cart->execute();
                    $stmt_cart->close();
                    
                } else {
                    // Delete seller-related data
                    $delete_sql = "DELETE FROM Seller WHERE SellerID = ?";
                    
                    // You might want to handle seller's products, orders, etc.
                    // For now, we'll just delete the seller
                }
                
                // Prepare and execute delete statement
                $stmt_delete = $conn->prepare($delete_sql);
                $stmt_delete->bind_param("i", $user_id);
                
                if ($stmt_delete->execute()) {
                    $conn->commit();
                    
                    // Logout and redirect
                    session_destroy();
                    header('Location: index.php?message=account_deleted');
                    exit();
                } else {
                    throw new Exception("Failed to delete account: " . $conn->error);
                }
                
                $stmt_delete->close();
                
            } catch (Exception $e) {
                $conn->rollback();
                $message = "Error deleting account: " . $e->getMessage();
                $message_type = "error";
            }
        }
    }
    
    // Handle privacy settings
    if (isset($_POST['update_privacy'])) {
        $profile_visible = isset($_POST['profile_visibility']) ? 1 : 0;
        $order_history = isset($_POST['order_history']) ? 1 : 0;
        $personalized_ads = isset($_POST['personalized_ads']) ? 1 : 0;
        $data_retention = $_POST['data_retention'];
        
        if ($user_type === 'customer') {
            $show_contact = 1;
            $show_address = 1;
        } else {
            $show_contact = isset($_POST['show_contact']) ? 1 : 0;
            $show_address = isset($_POST['show_address']) ? 1 : 0;
        }
        
        // Store in session for demo
        $_SESSION['privacy_settings'] = [
            'profile_visible' => $profile_visible,
            'order_history' => $order_history,
            'personalized_ads' => $personalized_ads,
            'data_retention' => $data_retention,
            'show_contact' => $show_contact,
            'show_address' => $show_address
        ];
        
        $message = "Privacy settings updated!";
        $message_type = "success";
    }
}

$conn->close();

// Get privacy settings from session
$privacy_settings = isset($_SESSION['privacy_settings']) ? $_SESSION['privacy_settings'] : [
    'profile_visible' => 1,
    'order_history' => 1,
    'personalized_ads' => 0,
    'data_retention' => 90,
    'show_contact' => 1,
    'show_address' => 1
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings | LutongBahay</title>
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
            --danger: #dc3545;
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
        
        /* Settings Content */
        .settings-container {
            padding: 40px 0;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .settings-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .settings-header h1 {
            font-size: 2.5rem;
            color: var(--dark);
            margin-bottom: 10px;
        }
        
        .settings-header p {
            color: var(--gray);
            font-size: 1.1rem;
        }
        
        .settings-card {
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        
        .settings-card-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .settings-card-header h2 {
            font-size: 1.8rem;
            margin-bottom: 5px;
        }
        
        .settings-card-header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .settings-card-body {
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
        
        .checkbox-group {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .checkbox-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--primary);
        }
        
        .checkbox-item label {
            margin-bottom: 0;
            font-weight: 500;
            cursor: pointer;
        }
        
        .password-strength {
            margin-top: 8px;
            height: 4px;
            background-color: var(--light-gray);
            border-radius: 2px;
            overflow: hidden;
        }
        
        .strength-meter {
            height: 100%;
            width: 0%;
            transition: width 0.3s ease;
        }
        
        .strength-meter.weak {
            background-color: #e63946;
            width: 33%;
        }
        
        .strength-meter.medium {
            background-color: #f4a261;
            width: 66%;
        }
        
        .strength-meter.strong {
            background-color: #2a9d8f;
            width: 100%;
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
        
        .btn-danger {
            background-color: transparent;
            color: var(--danger);
            border: 2px solid var(--danger);
        }
        
        .btn-danger:hover {
            background-color: rgba(220, 53, 69, 0.1);
        }
        
        .btn-danger-full {
            background-color: var(--danger);
            color: white;
            border: 2px solid var(--danger);
        }
        
        .btn-danger-full:hover {
            background-color: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
        }
        
        .section-header {
            font-size: 1.5rem;
            color: var(--dark);
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--light-gray);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-header i {
            color: var(--primary);
        }
        
        /* Danger Zone */
        .danger-zone {
            background-color: rgba(220, 53, 69, 0.05);
            border: 2px solid rgba(220, 53, 69, 0.2);
            margin-top: 40px;
            border-radius: 15px;
            padding: 25px;
        }
        
        .danger-zone h3 {
            color: var(--danger);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.3rem;
        }
        
        .danger-zone h3 i {
            color: var(--danger);
        }
        
        .danger-zone p {
            color: var(--gray);
            margin-bottom: 20px;
        }
        
        /* Delete Account Modal */
        .delete-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 3000;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s ease;
        }
        
        .delete-modal.show {
            display: flex;
        }
        
        .delete-modal-content {
            background-color: white;
            padding: 40px;
            border-radius: 20px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }
        
        .delete-modal-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .delete-icon {
            font-size: 3.5rem;
            color: var(--danger);
            margin-bottom: 15px;
        }
        
        .delete-modal-header h3 {
            font-size: 1.8rem;
            color: var(--dark);
            margin-bottom: 10px;
        }
        
        .delete-modal-header p {
            color: var(--gray);
            font-size: 1rem;
        }
        
        .warning-box {
            background-color: rgba(220, 53, 69, 0.1);
            border-left: 4px solid var(--danger);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
        }
        
        .warning-box p {
            color: var(--danger);
            font-size: 0.9rem;
            margin: 0;
            font-weight: 500;
        }
        
        .modal-buttons {
            display: flex;
            gap: 15px;
            margin-top: 25px;
        }
        
        .modal-buttons .btn {
            flex: 1;
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
            transition: opacity 0.5s ease, transform 0.5s ease;
        }
        
        .alert-success {
            background-color: rgba(42, 157, 143, 0.1);
            color: var(--success);
            border-left: 4px solid var(--success);
        }
        
        .alert-error {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--danger);
            border-left: 4px solid var(--danger);
        }
        
        .alert i {
            font-size: 1.2rem;
        }
        
        /* Demo notice */
        .demo-notice {
            background-color: rgba(233, 196, 106, 0.1);
            border-left: 4px solid var(--warning);
            color: #856404;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-size: 0.9rem;
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
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
        
        .shake {
            animation: shake 0.5s ease-in-out;
        }
        
        /* Responsive Design */
        @media (max-width: 992px) {
            .footer-content {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .form-row {
                flex-direction: column;
                gap: 0;
            }
        }
        
        @media (max-width: 768px) {
            .settings-card-body {
                padding: 25px;
            }
            
            .settings-header h1 {
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
            
            .modal-buttons {
                flex-direction: column;
            }
        }
        
        @media (max-width: 576px) {
            .container {
                width: 95%;
                padding: 0 15px;
            }
            
            .settings-card-header {
                padding: 20px;
            }
            
            .settings-header h1 {
                font-size: 1.8rem;
            }
            
            .danger-zone {
                padding: 20px;
            }
            
            .delete-modal-content {
                padding: 25px;
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
                            <a href="profile.php" class="dropdown-item">
                                <i class="fas fa-user"></i> My Profile
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="settings.php" class="dropdown-item active">
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
        <div class="settings-container">
            <div class="settings-header">
                <h1>Account Settings</h1>
                <p>Manage your account security and privacy</p>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'error'; ?>">
                    <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <div class="settings-card">
                <div class="settings-card-header">
                    <h2>Security & Privacy</h2>
                    <p>Protect your account and control your data</p>
                </div>
                
                <div class="settings-card-body">
                    <!-- Security Section -->
                    <div class="section-header">
                        <i class="fas fa-lock"></i> Security Settings
                    </div>
                    
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" class="form-control" required>
                            <div class="password-strength">
                                <div class="strength-meter" id="passwordStrength"></div>
                            </div>
                            <small style="color: var(--gray); margin-top: 5px; display: block;">
                                Password must be at least 8 characters long
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                            <small id="passwordMatch" style="margin-top: 5px; display: block;"></small>
                        </div>
                        
                        <button type="submit" name="change_password" class="btn btn-primary">
                            <i class="fas fa-key"></i> Change Password
                        </button>
                    </form>
                    
                    <!-- Privacy Section -->
                    <div class="section-header" style="margin-top: 40px;">
                        <i class="fas fa-shield-alt"></i> Privacy Settings
                    </div>
                    
                    <form method="POST" action="">
                        <div class="checkbox-group">
                            <?php if ($user_type === 'customer'): ?>
                            <div class="checkbox-item">
                                <input type="checkbox" id="profile_visibility" name="profile_visibility" <?php echo $privacy_settings['profile_visible'] ? 'checked' : ''; ?>>
                                <label for="profile_visibility">Make my profile visible to sellers</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="checkbox" id="order_history" name="order_history" <?php echo $privacy_settings['order_history'] ? 'checked' : ''; ?>>
                                <label for="order_history">Allow sellers to see my order history</label>
                            </div>
                            <?php else: ?>
                            <div class="checkbox-item">
                                <input type="checkbox" id="show_contact" name="show_contact" <?php echo $privacy_settings['show_contact'] ? 'checked' : ''; ?>>
                                <label for="show_contact">Show contact information to customers</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="checkbox" id="show_address" name="show_address" <?php echo $privacy_settings['show_address'] ? 'checked' : ''; ?>>
                                <label for="show_address">Show business address to customers</label>
                            </div>
                            <?php endif; ?>
                            <div class="checkbox-item">
                                <input type="checkbox" id="personalized_ads" name="personalized_ads" <?php echo $privacy_settings['personalized_ads'] ? 'checked' : ''; ?>>
                                <label for="personalized_ads">Personalized ads based on my activity</label>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="data_retention">Data Retention Period</label>
                            <select id="data_retention" name="data_retention" class="form-control">
                                <option value="30" <?php echo $privacy_settings['data_retention'] == 30 ? 'selected' : ''; ?>>30 days</option>
                                <option value="90" <?php echo $privacy_settings['data_retention'] == 90 ? 'selected' : ''; ?>>90 days</option>
                                <option value="365" <?php echo $privacy_settings['data_retention'] == 365 ? 'selected' : ''; ?>>1 year</option>
                                <option value="forever" <?php echo $privacy_settings['data_retention'] == 'forever' ? 'selected' : ''; ?>>Indefinitely</option>
                            </select>
                            <small style="color: var(--gray); margin-top: 5px; display: block;">
                                How long we keep your activity data
                            </small>
                        </div>
                        
                        <button type="submit" name="update_privacy" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Privacy Settings
                        </button>
                    </form>
                    
                    <!-- Danger Zone -->
                    <div class="danger-zone">
                        <h3><i class="fas fa-exclamation-triangle"></i> Danger Zone</h3>
                        <p>These actions are irreversible. Please proceed with caution.</p>
                        
                        <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                            <button type="button" class="btn btn-danger-full" id="deleteAccountBtn">
                                <i class="fas fa-trash"></i> Delete Account
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Delete Account Modal -->
    <div class="delete-modal" id="deleteModal">
        <div class="delete-modal-content">
            <form method="POST" action="" id="deleteForm">
                <div class="delete-modal-header">
                    <div class="delete-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h3>Delete Your Account</h3>
                    <p>This action cannot be undone. All your data will be permanently removed.</p>
                </div>
                
                <div class="warning-box">
                    <p><i class="fas fa-exclamation-circle"></i> Warning: Deleting your account will remove all your data including orders, preferences, and history.</p>
                </div>
                
                <div class="form-group">
                    <label for="delete_password">Enter your password to confirm:</label>
                    <input type="password" id="delete_password" name="delete_password" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="delete_confirm">Type "DELETE" in all caps to confirm:</label>
                    <input type="text" id="delete_confirm" name="delete_confirm" class="form-control" required>
                    <small style="color: var(--gray); margin-top: 5px; display: block;">
                        This is to ensure you understand this action is permanent.
                    </small>
                </div>
                
                <div class="modal-buttons">
                    <button type="button" class="btn btn-secondary" id="cancelDelete">Cancel</button>
                    <button type="submit" name="delete_account" class="btn btn-danger-full">
                        <i class="fas fa-trash"></i> Delete My Account
                    </button>
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
    const deleteModal = document.getElementById('deleteModal');
    const deleteAccountBtn = document.getElementById('deleteAccountBtn');
    const cancelDelete = document.getElementById('cancelDelete');
    const deleteForm = document.getElementById('deleteForm');
    
    // Auto-hide alert messages after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
        const alert = document.querySelector('.alert');
        if (alert) {
            setTimeout(() => {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                alert.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                
                // Remove from DOM after animation completes
                setTimeout(() => {
                    alert.style.display = 'none';
                }, 500);
            }, 5000);
        }
    });
    
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

    // Password strength meter
    const newPasswordInput = document.getElementById('new_password');
    const passwordStrength = document.getElementById('passwordStrength');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const passwordMatch = document.getElementById('passwordMatch');
    
    if (newPasswordInput) {
        newPasswordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 'weak';
            
            if (password.length >= 12) {
                strength = 'strong';
            } else if (password.length >= 8) {
                strength = 'medium';
            }
            
            passwordStrength.className = 'strength-meter ' + strength;
        });
    }
    
    // Password match validation
    if (confirmPasswordInput) {
        confirmPasswordInput.addEventListener('input', function() {
            if (newPasswordInput && newPasswordInput.value && this.value) {
                if (newPasswordInput.value === this.value) {
                    passwordMatch.textContent = 'Passwords match ✓';
                    passwordMatch.style.color = 'var(--success)';
                } else {
                    passwordMatch.textContent = 'Passwords do not match ✗';
                    passwordMatch.style.color = 'var(--primary)';
                }
            } else {
                passwordMatch.textContent = '';
            }
        });
    }
    
    // Delete Account Modal
    deleteAccountBtn.addEventListener('click', function() {
        deleteModal.classList.add('show');
        document.body.style.overflow = 'hidden'; // Prevent scrolling
    });
    
    cancelDelete.addEventListener('click', function() {
        deleteModal.classList.remove('show');
        document.body.style.overflow = 'auto';
    });
    
    // Close modal when clicking outside
    deleteModal.addEventListener('click', function(e) {
        if (e.target === deleteModal) {
            deleteModal.classList.remove('show');
            document.body.style.overflow = 'auto';
        }
    });
    
    // Validate delete form
    deleteForm.addEventListener('submit', function(e) {
        const password = document.getElementById('delete_password').value;
        const confirmText = document.getElementById('delete_confirm').value;
        
        if (confirmText !== 'DELETE') {
            e.preventDefault();
            document.getElementById('delete_confirm').classList.add('shake');
            
            setTimeout(() => {
                document.getElementById('delete_confirm').classList.remove('shake');
            }, 500);
            
            alert('Please type "DELETE" in all caps to confirm.');
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

    // Danger zone functions
    function exportData() {
        alert('Data export feature would be implemented here. In a real application, this would generate a downloadable file with all your personal data.');
    }
    
    // Update cart count with animation
    function updateCartCount() {
        // Animate the cart count
        if (cartCountElement) {
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
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                deleteModal.classList.remove('show');
                document.body.style.overflow = 'auto';
            }
        });
    });
</script>
</body>
</html>