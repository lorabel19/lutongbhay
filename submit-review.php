<?php
session_start();

// Simulate loading delay
sleep(1);

// Random success/failure for realism
$random = rand(1, 10); // 90% success rate

if ($random <= 9) {
    echo json_encode([
        'success' => true,
        'message' => '✅ Review submitted successfully! Thank you for your feedback.'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => '⚠️ Something went wrong. Please try again.'
    ]);
}

// Optionally, you can log the review locally (not in database)
if (isset($_POST['order_id']) && isset($_POST['rating'])) {
    $log_data = [
        'timestamp' => date('Y-m-d H:i:s'),
        'order_id' => $_POST['order_id'] ?? '',
        'meal_id' => $_POST['meal_id'] ?? '',
        'rating' => $_POST['rating'] ?? '',
        'comment' => substr($_POST['comment'] ?? '', 0, 100) // Truncate comment
    ];
    
    // Save to a text file (optional)
    file_put_contents('review_logs.txt', json_encode($log_data) . PHP_EOL, FILE_APPEND);
}
?>