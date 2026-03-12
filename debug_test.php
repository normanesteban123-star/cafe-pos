<?php
session_start();
// Simulate logged in admin for testing
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['full_name'] = 'Test';
$_SESSION['username'] = 'admin';
$_GET['action'] = 'get_products';
$_GET['category_id'] = '0';
ob_start();
require __DIR__ . '/api.php';
$output = ob_get_clean();
$data = json_decode($output, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo "JSON ERROR: " . json_last_error_msg() . "\n";
    echo "Raw output (first 500 chars):\n";
    echo htmlspecialchars(substr($output, 0, 500));
} else {
    echo "Success: " . ($data['success'] ? 'true' : 'false') . "\n";
    if (!$data['success']) {
        echo "Message: " . $data['message'] . "\n";
    } else {
        echo "Product count: " . count($data['data']) . "\n";
        if (!empty($data['data'])) {
            $p = $data['data'][0];
            echo "First product: " . $p['name'] . "\n";
            echo "has_variants: " . ($p['has_variants'] ? 'true' : 'false') . "\n";
            echo "variants count: " . count($p['variants']) . "\n";
        }
    }
}
