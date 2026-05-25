<?php
// cron-auto-cancel.php
// Run this via cron every 5 minutes (e.g. */5 * * * * php /path/to/cron-auto-cancel.php)
require_once __DIR__ . '/db_connect.php';

try {
    // Find pending bookings older than 30 minutes
    $stmt = $pdo->prepare("
        SELECT * FROM bookings 
        WHERE status = 'pending' 
        AND created_at <= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
    ");
    $stmt->execute();
    $expired_bookings = $stmt->fetchAll();

    if (empty($expired_bookings)) {
        echo "No expired pending bookings found.\n";
        exit;
    }

    $cancelStmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?");
    $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
    $updateCount = 0;

    foreach ($expired_bookings as $booking) {
        // Cancel the booking
        $cancelStmt->execute([$booking['id']]);
        
        // Notify the user that it was auto-cancelled
        if (!empty($booking['user_id'])) {
            $msg = "Your booking for " . date('d M Y', strtotime($booking['booking_date'])) . " at " . $booking['time_slot'] . " was auto-cancelled because the business did not confirm it in time.";
            $notifStmt->execute([$booking['user_id'], 'Booking Auto-Cancelled', $msg]);
        }

        $updateCount++;
    }

    echo "Successfully auto-cancelled {$updateCount} expired bookings.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
