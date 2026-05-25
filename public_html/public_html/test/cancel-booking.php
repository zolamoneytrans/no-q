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

$update = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?");
if ($update->execute([$booking_id])) {
    // Send notification to business
    sendBusinessBookingNotification($pdo, $booking_id, 'cancelled_by_customer');
    
    header('Location: user-dashboard.php?success=cancelled');
    exit;
} else {
    header('Location: user-dashboard.php?error=cancel_failed');
    exit;
}
?>