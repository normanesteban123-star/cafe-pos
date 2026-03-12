<?php
// ============================================
// DATABASE CONFIGURATION
// ============================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'cafe_pos');
define('DB_PORT', '3306');

define('TAX_RATE', 0.12); // 12% VAT
define('CURRENCY', '₱');
define('CAFE_NAME', 'Brewed & Co.');
define('CAFE_ADDRESS', '123 Coffee Lane, Manila');
define('CAFE_PHONE', '+63 912 345 6789');

function getDB() {
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        if ($conn->connect_error) {
            die(json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]));
        }
        $conn->set_charset('utf8mb4');
    }
    return $conn;
}

function sanitize($data) {
    $db = getDB();
    return $db->real_escape_string(trim($data));
}

function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function generateOrderNumber() {
    return 'ORD-' . strtoupper(date('ymd')) . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
}
