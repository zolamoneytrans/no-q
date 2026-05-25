<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['business_id'])) {
    header('Location: business-login.php');
    exit;
}

$booking_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$business_id = $_SESSION['business_id'];

$stmt = $pdo->prepare("SELECT total_amount FROM bookings WHERE id = ? AND business_id = ?");
$stmt->execute([$booking_id, $business_id]);
$booking = $stmt->fetch();

if (!$booking) {
    header('Location: business-dashboard.php');
    exit;
}

// ONLY CHANGE: added completed_at = NOW()
$update = $pdo->prepare("UPDATE bookings SET status = 'completed', completed_at = NOW() WHERE id = ? AND business_id = ?");
if ($update->execute([$booking_id, $business_id])) {
    $wallet = $pdo->prepare("UPDATE businesses SET wallet_balance = wallet_balance + ? WHERE id = ?");
    $wallet->execute([$booking['total_amount'], $business_id]);

    $info = $pdo->prepare("SELECT u.id as user_id, u.email, biz.name, b.id FROM bookings b JOIN users u ON b.user_id = u.id JOIN businesses biz ON b.business_id = biz.id WHERE b.id = ?");
    $info->execute([$booking_id]);
    $row = $info->fetch();
    if ($row) {
        addNotification($pdo, $row['user_id'], "Your car wash at {$row['name']} is complete. Please rate your experience!", "../rate-booking.php?id={$row['id']}");
        $subject = "Booking Completed – Rate Your Experience!";
        $body = "<p>Your car wash at <strong>{$row['name']}</strong> is complete.</p>
                 <p>We'd love to hear your feedback! Please rate your experience: 
                 <a href='https://carwashes.africa/rate-booking.php?id={$row['id']}'>Rate Now</a></p>";
        sendEmail($row['email'], $subject, $body);
    }
}
header('Location: business-dashboard.php');
exit;