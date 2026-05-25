<?php
require_once 'db_connect.php';

// Find bookings within 30-90 minutes that need a reminder
$stmt = $pdo->prepare("
    SELECT b.*, u.name as user_name, u.email as user_email, biz.name as business_name
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN businesses biz ON b.business_id = biz.id
    WHERE b.status IN ('confirmed', 'pending')
    AND b.booking_date = CURDATE()
    AND TIME(b.time_slot) BETWEEN DATE_ADD(CURTIME(), INTERVAL 30 MINUTE) AND DATE_ADD(CURTIME(), INTERVAL 90 MINUTE)
    AND b.reminder_sent = 0
");
$stmt->execute();
$bookings = $stmt->fetchAll();

foreach ($bookings as $booking) {

    $update = $pdo->prepare("UPDATE bookings SET reminder_sent = 1 WHERE id = ?");
    $update->execute([$booking['id']]);
    
    // Send email
    $subject = "⏰ Reminder: Your car wash is in 1 hour!";
    $body = "
    <html>
    <body style='font-family: Arial;'>
        <h2>Your car wash is coming up! 🚗</h2>
        <p>Hi <strong>{$booking['user_name']}</strong>,</p>
        <p>This is a reminder that your car wash is scheduled in <strong>1 hour</strong>.</p>
        <p><strong>Business:</strong> {$booking['business_name']}<br>
        <strong>Date:</strong> " . date('d M Y', strtotime($booking['booking_date'])) . "<br>
        <strong>Time:</strong> {$booking['time_slot']}<br>
        <strong>Booking Code:</strong> {$booking['booking_code']}</p>
        <p><a href='https://carwashes.africa/my-bookings.php'>View your booking</a></p>
        <p>No Q Team</p>
    </body>
    </html>
    ";
    sendEmail($booking['user_email'], $subject, $body);
}

echo "Reminders sent for " . count($bookings) . " bookings.";
?>