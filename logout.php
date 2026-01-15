<?php
// logout.php
session_start();

// Store user info for message if needed
$username = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'User';

// Unset all of the session variables
$_SESSION = array();

// If it's desired to kill the session, also delete the session cookie.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finally, destroy the session
session_destroy();

// Redirect to login page with success message
header('Location: index.php?logout=success&user=' . urlencode($username));
exit();
?>