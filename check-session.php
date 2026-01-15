<?php
session_start();

$response = [
    'logged_in' => isset($_SESSION['user_id']) && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'seller'
];

echo json_encode($response);
?>