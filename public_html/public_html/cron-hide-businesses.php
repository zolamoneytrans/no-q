<?php
require_once 'db_connect.php';

// Find businesses where grace period has passed and not already blocked
$stmt = $pdo->prepare("
    SELECT id, name, email, subscription_plan 
    FROM businesses 
    WHERE upgrade_warning_sent = 1 
    AND upgrade_grace_deadline < CURDATE() 
    AND bookings_blocked = 0
");
$stmt->execute();
$businesses = $stmt->fetchAll();

foreach ($businesses as $business) {
    // Hide the business from customers
    $update = $pdo->prepare("UPDATE businesses SET is_hidden = 1, bookings_blocked = 1 WHERE id = ?");
    $update->execute([$business['id']]);
    
    // Send final notification
    $subject = "⚠️ Your Business Profile Has Been Hidden";
    $body = "
    <html>
    <body>
        <h2>Your business profile is now hidden</h2>
        <p>Dear {$business['name']},</p>
        <p>You did not upgrade your plan within the 7-day grace period. Your business profile has been hidden from customers.</p>
        <p><strong>To restore your profile, please upgrade your plan now.</strong></p>
        <p><a href='https://carwashes.africa/business/business-settings.php'>Upgrade Now</a></p>
        <p>No Q Team</p>
    </body>
    </html>
    ";
    sendEmail($business['email'], $subject, $body);
}

echo count($businesses) . " businesses hidden.";
?>