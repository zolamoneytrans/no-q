<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: user-login.php');
    exit;
}

$booking_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ? AND user_id = ? AND status IN ('pending', 'confirmed')");
$stmt->execute([$booking_id, $user_id]);
$booking = $stmt->fetch();

if (!$booking) {
    header('Location: user-dashboard.php?error=invalid_cancellation');
    exit;
}

$slot_start = explode(' - ', $booking['time_slot'])[0];
$slot_time = strtotime($booking['booking_date'] . ' ' . $slot_start);
$current_time = time();

$fee_amount = 0;
$refund_amount = $booking['total_amount'];

if ($current_time >= $slot_time) {
    // 20% cancellation fee
    $fee_amount = $booking['total_amount'] * 0.2;
    $refund_amount = $booking['total_amount'] - $fee_amount;
}

try {
    $pdo->exec("ALTER TABLE bookings ADD COLUMN cancellation_fee DECIMAL(10,2) DEFAULT 0");
    $pdo->exec("ALTER TABLE bookings ADD COLUMN refund_amount DECIMAL(10,2) DEFAULT 0");
} catch(PDOException $e) {}

$update = $pdo->prepare("UPDATE bookings SET status = 'cancelled', cancellation_fee = ?, refund_amount = ? WHERE id = ?");
if ($update->execute([$fee_amount, $refund_amount, $booking_id])) {
    // Send notification to business
    sendBusinessBookingNotification($pdo, $booking_id, 'cancelled_by_customer');
    
    header('Location: user-dashboard.php?success=cancelled');
    exit;
} else {
    header('Location: user-dashboard.php?error=cancel_failed');
    exit;
}
?>