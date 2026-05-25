<?php
require_once 'db_connect.php';

$stmt = $pdo->prepare("
    SELECT b.*, u.email as user_email
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    WHERE b.status = 'confirmed'
    LIMIT 1
");
$stmt->execute();
$booking = $stmt->fetch();

if ($booking) {
    sendEmail($booking['user_email'], "Test Reminder", "This is a test. Your reminder system works!");
    echo "Email sent to " . $booking['user_email'];
} else {
    echo "No confirmed bookings found.";
}
?>