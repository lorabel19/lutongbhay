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

// Handle actions (add, update, remove)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $meal_id = isset($_POST['meal_id']) ? intval($_POST['meal_id']) : 0;
        
        switch ($_POST['action']) {
            case 'update_quantity':
                $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
                if ($quantity > 0) {
                    // Check if item exists in cart
                    $check_sql = "SELECT * FROM Cart WHERE CustomerID = ? AND MealID = ?";
                    $check_stmt = $conn->prepare($check_sql);
                    $check_stmt->bind_param("ii", $user_id, $meal_id);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    
                    if ($check_result->num_rows > 0) {
                        // Update quantity
                        $update_sql = "UPDATE Cart SET Quantity = ? WHERE CustomerID = ? AND MealID = ?";
                        $update_stmt = $conn->prepare($update_sql);
                        $update_stmt->bind_param("iii", $quantity, $user_id, $meal_id);
                        $update_stmt->execute();
                        $update_stmt->close();
                    }
                    $check_stmt->close();
                }
                break;
                
            case 'remove_item':
                $remove_sql = "DELETE FROM Cart WHERE CustomerID = ? AND MealID = ?";
                $remove_stmt = $conn->prepare($remove_sql);
                $remove_stmt->bind_param("ii", $user_id, $meal_id);
                $remove_stmt->execute();
                $remove_stmt->close();
                break;
                
            case 'clear_cart':
                $clear_sql = "DELETE FROM Cart WHERE CustomerID = ?";
                $clear_stmt = $conn->prepare($clear_sql);
                $clear_stmt->bind_param("i", $user_id);
                $clear_stmt->execute();
                $clear_stmt->close();
                break;
                
            case 'place_order':
                // Get cart items grouped by seller
                $cart_sql = "SELECT 
                                c.CartID, 
                                c.MealID, 
                                c.Quantity, 
                                m.Title, 
                                m.Price,
                                m.SellerID,
                                s.FullName as SellerName,
                                s.ContactNo as SellerContact,
                                s.ImagePath as SellerImage
                            FROM Cart c
                            JOIN Meal m ON c.MealID = m.MealID
                            JOIN Seller s ON m.SellerID = s.SellerID
                            WHERE c.CustomerID = ?
                            ORDER BY m.SellerID";
                $cart_stmt = $conn->prepare($cart_sql);
                $cart_stmt->bind_param("i", $user_id);
                $cart_stmt->execute();
                $cart_result = $cart_stmt->get_result();
                
                $cart_items_by_seller = [];
                $subtotal = 0;
                $total_items = 0;
                
                while ($item = $cart_result->fetch_assoc()) {
                    $seller_id = $item['SellerID'];
                    $item['Subtotal'] = $item['Price'] * $item['Quantity'];
                    $subtotal += $item['Subtotal'];
                    $total_items += $item['Quantity'];
                    
                    if (!isset($cart_items_by_seller[$seller_id])) {
                        $cart_items_by_seller[$seller_id] = [
                            'seller_info' => [
                                'SellerID' => $seller_id,
                                'SellerName' => $item['SellerName'],
                                'SellerContact' => $item['SellerContact'],
                                'SellerImage' => $item['SellerImage']
                            ],
                            'items' => []
                        ];
                    }
                    
                    $cart_items_by_seller[$seller_id]['items'][] = $item;
                }
                $cart_stmt->close();
                
                if (count($cart_items_by_seller) > 0) {
                    // Calculate delivery fee (fixed per seller)
                    $delivery_fee_per_seller = 30.00; // ₱30 per seller
                    
                    // Get delivery details from POST
                    $delivery_address = isset($_POST['delivery_address']) ? $_POST['delivery_address'] : $user['Address'];
                    $contact_no = isset($_POST['contact_no']) ? $_POST['contact_no'] : $user['ContactNo'];
                    $notes = isset($_POST['notes']) ? $_POST['notes'] : '';
                    
                    // Start transaction
                    $conn->begin_transaction();
                    
                    try {
                        $order_ids = []; // To store all created order IDs
                        
                        foreach ($cart_items_by_seller as $seller_id => $seller_data) {
                            // Calculate subtotal for this seller
                            $seller_subtotal = 0;
                            foreach ($seller_data['items'] as $item) {
                                $seller_subtotal += $item['Subtotal'];
                            }
                            
                            // Calculate total for this seller (subtotal + delivery fee)
                            $seller_total_amount = $seller_subtotal + $delivery_fee_per_seller;
                            
                            // Create separate order for this seller WITHOUT SellerID column
                            $order_sql = "INSERT INTO `Order` (CustomerID, TotalAmount, DeliveryAddress, ContactNo, Notes) 
                                         VALUES (?, ?, ?, ?, ?)";
                            $order_stmt = $conn->prepare($order_sql);
                            if ($order_stmt === false) {
                                throw new Exception("Prepare failed: " . $conn->error);
                            }
                            $order_stmt->bind_param("idsss", $user_id, $seller_total_amount, $delivery_address, $contact_no, $notes);
                            $order_stmt->execute();
                            $order_id = $order_stmt->insert_id;
                            $order_stmt->close();
                            
                            // Store order ID with seller info
                            $order_ids[$seller_id] = [
                                'order_id' => $order_id,
                                'seller_name' => $seller_data['seller_info']['SellerName']
                            ];
                            
                            // Create order details for this seller's order
                            $order_details_sql = "INSERT INTO OrderDetails (OrderID, MealID, Quantity, Subtotal) 
                                                 VALUES (?, ?, ?, ?)";
                            $order_details_stmt = $conn->prepare($order_details_sql);
                            if ($order_details_stmt === false) {
                                throw new Exception("Prepare failed: " . $conn->error);
                            }
                            
                            foreach ($seller_data['items'] as $item) {
                                $order_details_stmt->bind_param("iiid", $order_id, $item['MealID'], $item['Quantity'], $item['Subtotal']);
                                $order_details_stmt->execute();
                            }
                            $order_details_stmt->close();
                        }
                        
                        // Clear cart after all orders are created
                        $clear_cart_sql = "DELETE FROM Cart WHERE CustomerID = ?";
                        $clear_cart_stmt = $conn->prepare($clear_cart_sql);
                        $clear_cart_stmt->bind_param("i", $user_id);
                        $clear_cart_stmt->execute();
                        $clear_cart_stmt->close();
                        
                        // Commit transaction
                        $conn->commit();
                        
                        // Store order IDs in session
                        $_SESSION['order_success'] = true;
                        $_SESSION['order_ids'] = $order_ids; // Array of order IDs by seller
                        $_SESSION['order_count'] = count($order_ids);
                        $_SESSION['order_total'] = $subtotal + (count($order_ids) * $delivery_fee_per_seller);
                        
                        header('Location: orders.php');
                        exit();
                        
                    } catch (Exception $e) {
                        $conn->rollback();
                        $error = "Error placing order: " . $e->getMessage();
                    }
                } else {
                    $error = "Your cart is empty!";
                }
                break;
        }
        
        // Redirect to avoid form resubmission (except for place_order which redirects differently)
        if ($_POST['action'] !== 'place_order') {
            header('Location: cart.php');
            exit();
        }
    }
}

// Get cart items
$cart_sql = "SELECT 
                c.CartID, 
                c.MealID, 
                c.Quantity, 
                m.Title, 
                m.Description, 
                m.Price, 
                m.ImagePath,
                m.Availability,
                m.SellerID,
                s.FullName as SellerName,
                s.ImagePath as SellerImage
            FROM Cart c
            JOIN Meal m ON c.MealID = m.MealID
            JOIN Seller s ON m.SellerID = s.SellerID
            WHERE c.CustomerID = ?
            ORDER BY m.SellerID, c.AddedAt DESC";
$cart_stmt = $conn->prepare($cart_sql);
$cart_stmt->bind_param("i", $user_id);
$cart_stmt->execute();
$cart_result = $cart_stmt->get_result();

$cart_items = [];
$subtotal = 0;
$total_items = 0;
$unique_sellers = [];

while ($item = $cart_result->fetch_assoc()) {
    $item['Subtotal'] = $item['Price'] * $item['Quantity'];
    $subtotal += $item['Subtotal'];
    $total_items += $item['Quantity'];
    $cart_items[] = $item;
    
    if (!in_array($item['SellerID'], $unique_sellers)) {
        $unique_sellers[] = $item['SellerID'];
    }
}

$cart_stmt->close();

// Calculate fees - separate delivery fee per seller
$seller_count = count($unique_sellers);
$delivery_fee_per_seller = 30.00; // Fixed delivery fee per seller
$total_delivery_fee = $delivery_fee_per_seller * $seller_count;
$total_amount = $subtotal + $total_delivery_fee;

// Get cart count for header
$cart_count_sql = "SELECT COUNT(*) as cart_count FROM Cart WHERE CustomerID = ?";
$cart_count_stmt = $conn->prepare($cart_count_sql);
$cart_count_stmt->bind_param("i", $user_id);
$cart_count_stmt->execute();
$cart_count_result = $cart_count_stmt->get_result();
$cart_count_data = $cart_count_result->fetch_assoc();
$cart_count = $cart_count_data['cart_count'] ?: 0;
$cart_count_stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Cart | LutongBahay</title>
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
        
        /* HERO SECTION */
        .hero-section {
            background: var(--primary);
            color: white;
            padding: 60px 0 30px; 
            text-align: center;
        }
        
        .hero-content {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .hero-title {
            font-size: 2.8rem; 
            font-weight: 800;
            margin-bottom: 15px;
            line-height: 1.2;
            color: white;
        }
        
        .hero-subtitle {
            font-size: 1.2rem; 
            opacity: 0.9;
            line-height: 1.6;
            color: rgba(255, 255, 255, 0.9);
        }
        
        /* Cart Layout */
        .cart-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 40px;
            margin: 40px auto 60px;
        }
        
        /* Cart Items Section */
        .cart-items-section {
            background-color: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--shadow);
        }
        
        .cart-header {
            padding: 25px 30px;
            border-bottom: 1px solid var(--light-gray);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .cart-header h2 {
            font-size: 1.8rem;
            color: var(--dark);
        }
        
        .clear-cart-btn {
            background-color: transparent;
            color: var(--primary);
            border: 1px solid var(--primary);
            padding: 8px 20px;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
        }
        
        .clear-cart-btn:hover {
            background-color: rgba(230, 57, 70, 0.1);
        }
        
        .cart-items {
            padding: 20px 0;
        }
        
        .seller-group-cart {
            padding: 15px 30px;
            background-color: #f8f9fa;
            margin-bottom: 10px;
            border-left: 4px solid var(--primary);
        }
        
        .seller-header-cart {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 10px;
        }
        
        .seller-avatar-cart {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            overflow: hidden;
            background-color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1rem;
        }
        
        .seller-avatar-cart img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .seller-name-cart {
            font-weight: 600;
            color: var(--dark);
            font-size: 1.1rem;
        }
        
        .cart-item {
            display: flex;
            padding: 20px 30px;
            border-bottom: 1px solid var(--light-gray);
            align-items: center;
        }
        
        .cart-item:hover {
            background-color: rgba(233, 236, 239, 0.3);
        }
        
        .cart-item-image {
            width: 120px;
            height: 100px;
            border-radius: 10px;
            overflow: hidden;
            flex-shrink: 0;
            margin-right: 20px;
        }
        
        .cart-item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .cart-item-details {
            flex: 1;
            margin-right: 20px;
        }
        
        .cart-item-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 5px;
        }
        
        .cart-item-seller {
            color: var(--gray);
            font-size: 1rem;
            margin-bottom: 8px;
        }
        
        .cart-item-price {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--primary);
        }
        
        .cart-item-quantity {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-right: 30px;
        }
        
        .quantity-control {
            display: flex;
            align-items: center;
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            overflow: hidden;
        }
        
        .quantity-btn {
            background-color: white;
            border: none;
            width: 40px;
            height: 40px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
        }
        
        .quantity-btn:hover {
            background-color: var(--light-gray);
        }
        
        .quantity-input {
            width: 50px;
            height: 40px;
            border: none;
            text-align: center;
            font-size: 1.1rem;
            font-weight: 600;
            outline: none;
        }
        
        .cart-item-subtotal {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--dark);
            width: 120px;
            text-align: right;
        }
        
        .cart-item-remove {
            background-color: transparent;
            border: none;
            color: var(--gray);
            cursor: pointer;
            font-size: 1.2rem;
            padding: 8px;
            border-radius: 50%;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .cart-item-remove:hover {
            background-color: rgba(230, 57, 70, 0.1);
            color: var(--primary);
        }
        
        .empty-cart {
            text-align: center;
            padding: 60px 30px;
        }
        
        .empty-cart-icon {
            font-size: 5rem;
            color: var(--light-gray);
            margin-bottom: 20px;
        }
        
        .empty-cart h3 {
            font-size: 1.8rem;
            color: var(--gray);
            margin-bottom: 15px;
        }
        
        .empty-cart p {
            color: var(--gray);
            margin-bottom: 30px;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .btn-shop {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 14px 40px;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 1.1rem;
            text-decoration: none;
        }
        
        .btn-shop:hover {
            background-color: var(--primary-dark);
        }
        
        /* Order Summary Section */
        .order-summary {
            background-color: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: var(--shadow);
            height: fit-content;
            position: sticky;
            top: 100px;
        }
        
        .order-summary h2 {
            font-size: 1.8rem;
            color: var(--dark);
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-size: 1.1rem;
        }
        
        .summary-row.subtotal {
            font-weight: 600;
            margin-top: 10px;
            padding-top: 15px;
            border-top: 1px solid var(--light-gray);
        }
        
        .summary-row.total {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary);
            margin-top: 10px;
            padding-top: 15px;
            border-top: 2px solid var(--light-gray);
        }
        
        .summary-label {
            color: var(--gray);
        }
        
        .summary-value {
            font-weight: 600;
        }
        
        .summary-info {
            margin-top: 15px;
            padding: 12px 15px;
            background-color: #f8f9fa;
            border-radius: 8px;
            font-size: 0.9rem;
            color: var(--gray);
            border-left: 3px solid var(--primary);
        }
        
        .btn-checkout {
            background-color: var(--primary);
            color: white;
            border: none;
            width: 100%;
            padding: 18px;
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            font-size: 1.2rem;
            margin-top: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-checkout:hover {
            background-color: var(--primary-dark);
            transform: translateY(-3px);
            box-shadow: 0 7px 15px rgba(230, 57, 70, 0.2);
        }
        
        .btn-checkout:disabled {
            background-color: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
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
        
        /* Notification */
        .notification {
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
            max-width: 300px;
            display: none;
        }
        
        .notification.show {
            display: block;
        }
        
        .notification.error {
            background-color: var(--primary);
        }
        
        /* Checkout Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            display: none;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .modal-overlay.show {
            display: flex;
        }
        
        .checkout-modal {
            background-color: white;
            border-radius: 15px;
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow);
            animation: fadeIn 0.3s ease;
        }
        
        .modal-header {
            padding: 25px 30px;
            border-bottom: 1px solid var(--light-gray);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            font-size: 1.5rem;
            color: var(--dark);
            font-weight: 700;
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--gray);
            cursor: pointer;
            transition: var(--transition);
            padding: 5px;
            border-radius: 50%;
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .close-modal:hover {
            background-color: var(--light-gray);
            color: var(--primary);
        }
        
        .modal-body {
            padding: 25px 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
            font-size: 1rem;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            font-size: 1rem;
            transition: var(--transition);
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(230, 57, 70, 0.1);
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .modal-footer {
            padding: 20px 30px;
            border-top: 1px solid var(--light-gray);
            display: flex;
            justify-content: flex-end;
            gap: 15px;
        }
        
        .modal-btn {
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            font-size: 1rem;
            border: none;
        }
        
        .modal-btn.cancel {
            background-color: var(--light-gray);
            color: var(--dark);
        }
        
        .modal-btn.cancel:hover {
            background-color: #dee2e6;
        }
        
        .modal-btn.confirm {
            background-color: var(--primary);
            color: white;
        }
        
        .modal-btn.confirm:hover {
            background-color: var(--primary-dark);
        }
        
        .order-summary-modal {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .order-summary-modal h4 {
            font-size: 1.2rem;
            color: var(--dark);
            margin-bottom: 15px;
            font-weight: 700;
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
        
        /* Responsive Design */
        @media (max-width: 1200px) {
            .cart-container {
                grid-template-columns: 1fr;
                gap: 30px;
            }
            
            .order-summary {
                position: static;
            }
            
            .footer-content {
                grid-template-columns: repeat(2, 1fr);
                gap: 30px;
            }
            
            .hero-title {
                font-size: 2.5rem;
            }
        }
        
        @media (max-width: 768px) {
            .cart-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .cart-item-image {
                width: 100%;
                height: 200px;
                margin-right: 0;
            }
            
            .cart-item-details {
                margin-right: 0;
                width: 100%;
            }
            
            .cart-item-quantity {
                margin-right: 0;
                justify-content: space-between;
                width: 100%;
            }
            
            .cart-item-subtotal {
                width: 100%;
                text-align: left;
            }
            
            .cart-item-remove {
                position: absolute;
                top: 15px;
                right: 15px;
            }
            
            .cart-item {
                position: relative;
            }
            
            .footer-content {
                grid-template-columns: 1fr;
                gap: 30px;
            }
            
            .hero-title {
                font-size: 2.2rem;
            }
            
            .hero-subtitle {
                font-size: 1rem;
            }
            
            .cart-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
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
            
            /* Modal adjustments */
            .modal-overlay {
                padding: 10px;
            }
            
            .checkout-modal {
                max-height: 95vh;
            }
            
            .modal-header,
            .modal-body,
            .modal-footer {
                padding: 20px;
            }
            
            .seller-group-cart {
                padding: 15px 20px;
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
            
            .quantity-control {
                width: 100%;
                justify-content: space-between;
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
            
            .modal-footer {
                flex-direction: column;
            }
            
            .modal-btn {
                width: 100%;
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
    
    <!-- HERO SECTION -->
    <section class="hero-section">
        <div class="container">
            <div class="hero-content">
                <h1 class="hero-title">My Cart</h1>
                <p class="hero-subtitle">Review your items and proceed to checkout</p>
            </div>
        </div>
    </section>

    <!-- Notification -->
    <div class="notification" id="notification"></div>

    <!-- Checkout Modal -->
    <div class="modal-overlay" id="checkoutModal">
        <div class="checkout-modal">
            <div class="modal-header">
                <h3>Complete Your Order</h3>
                <button type="button" class="close-modal" id="closeCheckoutModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" action="cart.php" id="checkoutForm">
                <div class="modal-body">
                    <div class="order-summary-modal" id="modalOrderSummary">
                        <!-- Dynamic content will be loaded by JavaScript -->
                    </div>
                    
                    <div class="form-group">
                        <label for="delivery_address">Delivery Address</label>
                        <input type="text" 
                               id="delivery_address" 
                               name="delivery_address" 
                               value="<?php echo htmlspecialchars($user['Address'] ? $user['Address'] : ''); ?>" 
                               required 
                               placeholder="Enter your delivery address">
                    </div>
                    
                    <div class="form-group">
                        <label for="contact_no">Contact Number</label>
                        <input type="text" 
                               id="contact_no" 
                               name="contact_no" 
                               value="<?php echo htmlspecialchars($user['ContactNo'] ? $user['ContactNo'] : ''); ?>" 
                               required 
                               placeholder="Enter your contact number">
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Additional Notes (Optional)</label>
                        <textarea id="notes" 
                                  name="notes" 
                                  placeholder="Any special instructions for delivery or preparation"></textarea>
                    </div>
                    
                    <input type="hidden" name="action" value="place_order">
                </div>
                <div class="modal-footer">
                    <button type="button" class="modal-btn cancel" id="cancelCheckout">Cancel</button>
                    <button type="submit" class="modal-btn confirm">Place Order</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Cart Content -->
    <div class="container">
        <div class="cart-container">
            <!-- Cart Items Section -->
            <div class="cart-items-section">
                <div class="cart-header">
                    <h2>Cart Items (<?php echo $total_items; ?>)</h2>
                    <?php if (count($cart_items) > 0): ?>
                        <form method="POST" action="cart.php" onsubmit="return confirmClearCart();">
                            <input type="hidden" name="action" value="clear_cart">
                            <button type="submit" class="clear-cart-btn">
                                <i class="fas fa-trash"></i> Clear Cart
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
                
                <div class="cart-items">
                    <?php if (count($cart_items) > 0): ?>
                        <?php 
                        $current_seller = null;
                        foreach ($cart_items as $index => $item): 
                            // Start new seller group
                            if ($current_seller != $item['SellerID']):
                                $current_seller = $item['SellerID'];
                        ?>
                        <div class="seller-group-cart" data-seller-id="<?php echo $current_seller; ?>">
                            <div class="seller-header-cart">
                                <div class="seller-avatar-cart">
                                    <?php if (!empty($item['SellerImage'])): ?>
                                        <img src="<?php echo htmlspecialchars($item['SellerImage']); ?>" alt="<?php echo htmlspecialchars($item['SellerName']); ?>">
                                    <?php else: ?>
                                        <?php echo strtoupper(substr($item['SellerName'], 0, 1)); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="seller-name-cart"><?php echo htmlspecialchars($item['SellerName']); ?></div>
                            </div>
                            <div style="font-size: 0.9rem; color: var(--gray);">
                                <i class="fas fa-truck"></i> Delivery Fee: ₱30.00
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="cart-item" id="cart-item-<?php echo $item['MealID']; ?>" data-seller-id="<?php echo $item['SellerID']; ?>">
                            <div class="cart-item-image">
                                <img src="<?php echo htmlspecialchars($item['ImagePath'] ? $item['ImagePath'] : 'https://images.unsplash.com/photo-1565958011703-44f9829ba187?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80'); ?>" alt="<?php echo htmlspecialchars($item['Title']); ?>">
                            </div>
                            
                            <div class="cart-item-details">
                                <h3 class="cart-item-title"><?php echo htmlspecialchars($item['Title']); ?></h3>
                                <p class="cart-item-seller">Sold by: <?php echo htmlspecialchars($item['SellerName']); ?></p>
                                <div class="cart-item-price">₱<?php echo number_format($item['Price'], 2); ?></div>
                            </div>
                            
                            <div class="cart-item-quantity">
                                <div class="quantity-control">
                                    <button type="button" class="quantity-btn minus" onclick="updateQuantity(<?php echo $item['MealID']; ?>, <?php echo $item['Quantity'] - 1; ?>)">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                    <input type="number" 
                                           class="quantity-input" 
                                           value="<?php echo $item['Quantity']; ?>" 
                                           min="1" 
                                           max="10"
                                           onchange="updateQuantity(<?php echo $item['MealID']; ?>, this.value)"
                                           data-meal-id="<?php echo $item['MealID']; ?>">
                                    <button type="button" class="quantity-btn plus" onclick="updateQuantity(<?php echo $item['MealID']; ?>, <?php echo $item['Quantity'] + 1; ?>)">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="cart-item-subtotal" id="subtotal-<?php echo $item['MealID']; ?>">
                                ₱<?php echo number_format($item['Subtotal'], 2); ?>
                            </div>
                            
                            <form method="POST" action="cart.php" class="remove-form">
                                <input type="hidden" name="action" value="remove_item">
                                <input type="hidden" name="meal_id" value="<?php echo $item['MealID']; ?>">
                                <button type="submit" class="cart-item-remove" onclick="return confirmRemoveItem('<?php echo htmlspecialchars(addslashes($item['Title'])); ?>')">
                                    <i class="fas fa-times"></i>
                                </button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-cart">
                            <div class="empty-cart-icon">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                            <h3>Your cart is empty</h3>
                            <p>Looks like you haven't added any meals to your cart yet. Start browsing our delicious homemade meals!</p>
                            <a href="browse-meals.php" class="btn-shop">
                                <i class="fas fa-utensils"></i> Browse Meals
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Order Summary Section -->
            <div class="order-summary">
                <h2>Order Summary</h2>
                
                <div class="summary-row">
                    <span class="summary-label">Items (<?php echo $total_items; ?>):</span>
                    <span class="summary-value" id="summary-subtotal">₱<?php echo number_format($subtotal, 2); ?></span>
                </div>
                
                <div class="summary-row">
                    <span class="summary-label">Delivery Fee (<?php echo $seller_count; ?> seller<?php echo $seller_count > 1 ? 's' : ''; ?>):</span>
                    <span class="summary-value" id="summary-delivery">₱<?php echo number_format($total_delivery_fee, 2); ?></span>
                </div>
                
                <div class="summary-row" style="font-size: 0.9rem; color: var(--gray);">
                    <span class="summary-label">Delivery Fee per Seller:</span>
                    <span class="summary-value">₱<?php echo number_format($delivery_fee_per_seller, 2); ?></span>
                </div>
                
                <div class="summary-row total">
                    <span class="summary-label">Total:</span>
                    <span class="summary-value" id="summary-total">₱<?php echo number_format($total_amount, 2); ?></span>
                </div>
                
                <div class="summary-info" id="summaryInfo">
                    <i class="fas fa-info-circle" style="color: var(--primary); margin-right: 5px;"></i>
                    You will have <?php echo $seller_count; ?> separate order<?php echo $seller_count > 1 ? 's' : ''; ?> - one for each seller.
                </div>
                
                <button type="button" class="btn-checkout" id="checkoutBtn" <?php echo count($cart_items) == 0 ? 'disabled' : ''; ?> onclick="openCheckoutModal()">
                    <i class="fas fa-credit-card"></i> Proceed to Checkout
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
        const profileToggle = document.getElementById('profileToggle');
        const dropdownMenu = document.getElementById('dropdownMenu');
        const logoutLink = document.getElementById('logoutLink');
        const notification = document.getElementById('notification');
        const checkoutBtn = document.getElementById('checkoutBtn');
        const cartCountElement = document.getElementById('cartCount');
        
        // Checkout Modal Elements
        const checkoutModal = document.getElementById('checkoutModal');
        const closeCheckoutModal = document.getElementById('closeCheckoutModal');
        const cancelCheckout = document.getElementById('cancelCheckout');
        const checkoutForm = document.getElementById('checkoutForm');
        const modalOrderSummary = document.getElementById('modalOrderSummary');

        // Profile dropdown functionality
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

        // Show notification function
        function showNotification(message, type = 'success') {
            notification.textContent = message;
            notification.className = 'notification';
            notification.classList.add(type, 'show');
            
            setTimeout(() => {
                notification.classList.remove('show');
            }, 3000);
        }

        // Update cart count
        function updateCartCount() {
            cartCountElement.style.animation = 'bounce 0.5s ease';
            
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

        // Update quantity function
        function updateQuantity(mealId, quantity) {
            // If quantity is 0 or less, remove the item
            if (quantity < 1) {
                if (confirm('Remove this item from cart?')) {
                    // Submit the remove form
                    const removeForm = document.querySelector(`#cart-item-${mealId} .remove-form`);
                    if (removeForm) {
                        removeForm.submit();
                    }
                }
                return;
            }
            
            // Limit max quantity to 10
            if (quantity > 10) {
                showNotification('Maximum quantity is 10', 'error');
                quantity = 10;
            }
            
            fetch('update-cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    meal_id: mealId,
                    quantity: quantity,
                    action: 'update_quantity'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update the input value
                    const input = document.querySelector(`input[data-meal-id="${mealId}"]`);
                    if (input) input.value = quantity;
                    
                    // Update subtotal
                    const subtotalElement = document.getElementById(`subtotal-${mealId}`);
                    if (subtotalElement) {
                        const price = data.item_price || parseFloat(subtotalElement.textContent.replace('₱', '').replace(',', '')) / quantity;
                        const newSubtotal = price * quantity;
                        subtotalElement.textContent = `₱${newSubtotal.toFixed(2)}`;
                    }
                    
                    // Update summary
                    updateOrderSummary();
                    updateCartCount();
                    
                    // Show message
                    showNotification('Quantity updated!');
                } else {
                    showNotification(data.message || 'Error updating quantity', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error updating quantity', 'error');
            });
        }

        // Update order summary
        function updateOrderSummary() {
            fetch('get-cart-total.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update all summary elements
                        document.getElementById('summary-subtotal').textContent = `₱${data.subtotal.toFixed(2)}`;
                        document.getElementById('summary-total').textContent = `₱${data.total.toFixed(2)}`;
                        
                        // Update delivery fee display
                        const deliveryFeeElement = document.getElementById('summary-delivery');
                        if (deliveryFeeElement) {
                            deliveryFeeElement.textContent = `₱${data.total_delivery_fee.toFixed(2)}`;
                        }
                        
                        // Update seller count in delivery label
                        const deliveryLabel = document.querySelector('.summary-row:nth-child(2) .summary-label');
                        if (deliveryLabel) {
                            deliveryLabel.textContent = `Delivery Fee (${data.seller_count} seller${data.seller_count > 1 ? 's' : ''}):`;
                        }
                        
                        // Update info message
                        const infoMessage = document.getElementById('summaryInfo');
                        if (infoMessage) {
                            infoMessage.innerHTML = `
                                <i class="fas fa-info-circle" style="color: var(--primary); margin-right: 5px;"></i>
                                You will have ${data.seller_count} separate order${data.seller_count > 1 ? 's' : ''} - one for each seller.
                            `;
                        }
                        
                        // Update item count in summary
                        const summaryRows = document.querySelectorAll('.summary-row');
                        if (summaryRows.length > 0) {
                            summaryRows[0].querySelector('.summary-label').textContent = `Items (${data.total_items}):`;
                        }
                        
                        // Update cart header
                        const cartHeader = document.querySelector('.cart-header h2');
                        if (cartHeader) {
                            cartHeader.textContent = `Cart Items (${data.total_items})`;
                        }
                        
                        // Enable/disable checkout button
                        if (checkoutBtn) {
                            checkoutBtn.disabled = data.total_items === 0;
                        }
                        
                        // Update modal summary if modal is open
                        updateModalOrderSummary(data);
                    }
                })
                .catch(error => {
                    console.error('Error fetching cart total:', error);
                });
        }

        // Update modal order summary
        function updateModalOrderSummary(data) {
            if (checkoutModal.classList.contains('show')) {
                modalOrderSummary.innerHTML = `
                    <h4>Order Summary</h4>
                    <div class="summary-row">
                        <span class="summary-label">Items (${data.total_items}):</span>
                        <span class="summary-value">₱${data.subtotal.toFixed(2)}</span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">Delivery Fee (${data.seller_count} seller${data.seller_count > 1 ? 's' : ''}):</span>
                        <span class="summary-value">₱${data.total_delivery_fee.toFixed(2)}</span>
                    </div>
                    <div class="summary-row total">
                        <span class="summary-label">Total:</span>
                        <span class="summary-value">₱${data.total.toFixed(2)}</span>
                    </div>
                    <div style="margin-top: 10px; font-size: 0.9rem; color: var(--gray);">
                        <i class="fas fa-info-circle"></i> You will receive ${data.seller_count} separate order${data.seller_count > 1 ? 's' : ''} from ${data.seller_count} seller${data.seller_count > 1 ? 's' : ''}.
                    </div>
                `;
            }
        }

        // Load modal order summary
        function loadModalOrderSummary() {
            fetch('get-cart-total.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        modalOrderSummary.innerHTML = `
                            <h4>Order Summary</h4>
                            <div class="summary-row">
                                <span class="summary-label">Items (${data.total_items}):</span>
                                <span class="summary-value">₱${data.subtotal.toFixed(2)}</span>
                            </div>
                            <div class="summary-row">
                                <span class="summary-label">Delivery Fee (${data.seller_count} seller${data.seller_count > 1 ? 's' : ''}):</span>
                                <span class="summary-value">₱${data.total_delivery_fee.toFixed(2)}</span>
                            </div>
                            <div class="summary-row total">
                                <span class="summary-label">Total:</span>
                                <span class="summary-value">₱${data.total.toFixed(2)}</span>
                            </div>
                            <div style="margin-top: 10px; font-size: 0.9rem; color: var(--gray);">
                                <i class="fas fa-info-circle"></i> You will receive ${data.seller_count} separate order${data.seller_count > 1 ? 's' : ''} from ${data.seller_count} seller${data.seller_count > 1 ? 's' : ''}.
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    modalOrderSummary.innerHTML = `
                        <h4>Order Summary</h4>
                        <div style="color: var(--gray); text-align: center; padding: 20px;">
                            Error loading order summary
                        </div>
                    `;
                });
        }

        // Confirm remove item
        function confirmRemoveItem(itemTitle) {
            return confirm(`Are you sure you want to remove "${itemTitle}" from your cart?`);
        }

        // Confirm clear cart
        function confirmClearCart() {
            return confirm('Are you sure you want to clear your entire cart? This action cannot be undone.');
        }

        // Open checkout modal
        function openCheckoutModal() {
            // Check if cart is empty
            if (parseInt(cartCountElement.textContent) === 0) {
                showNotification('Your cart is empty!', 'error');
                return;
            }
            
            // Load order summary in modal
            loadModalOrderSummary();
            
            checkoutModal.classList.add('show');
            document.body.style.overflow = 'hidden'; // Prevent scrolling
        }

        // Close checkout modal
        function closeCheckoutModalFunc() {
            checkoutModal.classList.remove('show');
            document.body.style.overflow = ''; // Restore scrolling
        }

        // Close modal when clicking outside
        checkoutModal.addEventListener('click', function(e) {
            if (e.target === checkoutModal) {
                closeCheckoutModalFunc();
            }
        });

        // Close modal with close button
        closeCheckoutModal.addEventListener('click', closeCheckoutModalFunc);
        cancelCheckout.addEventListener('click', closeCheckoutModalFunc);

        // Form submission
        checkoutForm.addEventListener('submit', function(e) {
            // You can add form validation here if needed
            const deliveryAddress = document.getElementById('delivery_address').value;
            const contactNo = document.getElementById('contact_no').value;
            
            if (!deliveryAddress.trim()) {
                e.preventDefault();
                showNotification('Please enter a delivery address', 'error');
                return;
            }
            
            if (!contactNo.trim()) {
                e.preventDefault();
                showNotification('Please enter a contact number', 'error');
                return;
            }
        });

        // Initialize cart count display
        document.addEventListener('DOMContentLoaded', function() {
            if (parseInt(cartCountElement.textContent) > 0) {
                cartCountElement.style.display = 'flex';
            } else {
                cartCountElement.style.display = 'none';
            }
            
            // Add event listeners to quantity inputs
            const quantityInputs = document.querySelectorAll('.quantity-input');
            quantityInputs.forEach(input => {
                input.addEventListener('change', function() {
                    const mealId = this.getAttribute('data-meal-id');
                    const quantity = parseInt(this.value);
                    if (!isNaN(quantity)) {
                        updateQuantity(mealId, quantity);
                    }
                });
            });
            
            // Show error message if exists
            <?php if (isset($error)): ?>
                showNotification('<?php echo addslashes($error); ?>', 'error');
            <?php endif; ?>
        });
    </script>
</body>
</html>