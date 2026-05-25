<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

$business_id = isset($_GET['business_id']) ? (int)$_GET['business_id'] : 0;
$booking_date = isset($_GET['date']) ? trim($_GET['date']) : '';

if ($business_id == 0 || empty($booking_date)) {
    echo json_encode([]);
    exit;
}

$slots = getAvailableSlots($pdo, $business_id, $booking_date);

$result = [];
foreach ($slots as $time => $available) {
    $result[] = ['time' => $time, 'available' => $available];
}

echo json_encode($result);
?>