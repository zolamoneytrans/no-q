<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['has_upcoming' => false]);
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT TIMESTAMPDIFF(MINUTE, NOW(), CONCAT(booking_date, ' ', time_slot)) as minutes_until
    FROM bookings
    WHERE user_id = ? AND status IN ('confirmed', 'pending')
    AND booking_date = CURDATE()
    AND CONCAT(booking_date, ' ', time_slot) BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 2 HOUR)
    LIMIT 1
");
$stmt->execute([$user_id]);
$booking = $stmt->fetch();

if ($booking && $booking['minutes_until'] > 0) {
    echo json_encode(['has_upcoming' => true, 'minutes' => $booking['minutes_until']]);
} else {
    echo json_encode(['has_upcoming' => false]);
}
?>