<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: user-login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$booking_id = 0;

if (isset($_GET['booking_id'])) {
    $booking_id = (int)$_GET['booking_id'];
} elseif (isset($_GET['m_payment_id'])) {
    $booking_id = (int)$_GET['m_payment_id'];
}

if ($booking_id == 0) {
    die('No booking ID provided. Please go to your dashboard.');
}

$stmt = $pdo->prepare("
    SELECT b.*, biz.name as business_name 
    FROM bookings b
    LEFT JOIN businesses biz ON b.business_id = biz.id
    WHERE b.id = ? AND b.user_id = ?
");
$stmt->execute([$booking_id, $user_id]);
$booking = $stmt->fetch();
// Calculate booking fee 
function calculateBookingFee($price) {
    if ($price < 100) {
        return 15;
    } else {
        return $price * 0.15;
    }
}

$service_price = $booking['total_amount'];
$booking_fee = calculateBookingFee($service_price);
$total_paid = $service_price + $booking_fee;

// Store fee breakdown 
$_SESSION['last_booking_fee'] = $booking_fee;
$_SESSION['last_service_price'] = $service_price;

if (!$booking) {
    die('Booking not found.');
}

$update = $pdo->prepare("UPDATE bookings SET payment_status = 'paid' WHERE id = ?");
$update->execute([$booking_id]);

$pay_stmt = $pdo->prepare("
    INSERT INTO payments (booking_id, user_id, amount, payment_method, status, created_at, service_price, booking_fee) 
    VALUES (?, ?, ?, 'payfast', 'completed', NOW(), ?, ?)
");
$pay_stmt->execute([$booking_id, $user_id, $total_paid, $service_price, $booking_fee]);

if ($booking['status'] == 'pending') {
    $status_update = $pdo->prepare("UPDATE bookings SET status = 'confirmed' WHERE id = ?");
    $status_update->execute([$booking_id]);
    $booking['status'] = 'confirmed';
}


$email_subject = "Payment Confirmed – No Q";
$email_body = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 500px; margin: 0 auto; padding: 20px; background: #f9f9f9; border-radius: 10px; }
            .header { background: #1e3c72; color: white; padding: 15px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { padding: 20px; background: white; border-radius: 0 0 10px 10px; }
            .button { display: inline-block; background: #ff9800; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 15px 0; }
            .footer { text-align: center; font-size: 12px; color: #666; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Payment Confirmed! ✅</h2>
            </div>
            <div class='content'>
                <p>Dear <strong>" . htmlspecialchars($_SESSION['user_name']) . "</strong>,</p>
                <p>Your payment has been successfully received.</p>
                <p><strong>Booking Code:</strong> {$booking['booking_code']}<br>
                <strong>Business:</strong> {$booking['business_name']}<br>
                <strong>Date:</strong> " . date('d M Y', strtotime($booking['booking_date'])) . "<br>
                <strong>Time:</strong> {$booking['time_slot']}<br>
                <strong>Amount Paid:</strong> R " . number_format($total_paid, 2) . "
                <p>Your booking is now confirmed. Please show your QR code at the car wash.</p>
                <div style='text-align: center;'>
                    <a href='https://carwashes.africa/user-dashboard.php' class='button'>View My Bookings</a>
                </div>
                <p>Thank you for choosing No Q!</p>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . "No Q. All Rights Reserved.</p>
            </div>
        </div>
    </body>
    </html>
";

sendEmail($_SESSION['user_email'], $email_subject, $email_body);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(145deg, #f6f9fc 0%, #e9f1f8 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(10px);
            border-radius: 40px;
            padding: 40px;
            max-width: 500px;
            width: 90%;
            margin: 20px;
            text-align: center;
            box-shadow: 0 20px 40px -12px rgba(0,20,40,0.2);
        }
        .check-icon { font-size: 70px; color: #4caf50; margin-bottom: 20px; }
        h1 { font-size: 28px; margin-bottom: 15px; color: #1e3c72; }
        p { color: #2c3e50; margin-bottom: 10px; }
        .booking-code { background: #f0f4f8; padding: 12px; border-radius: 30px; margin: 20px 0; font-weight: 600; font-size: 18px; color: #1e3c72; word-break: break-all; }
        .btn { display: inline-block; background: #1e3c72; color: white; padding: 12px 30px; text-decoration: none; border-radius: 40px; font-weight: 600; margin-top: 10px; }
        .btn:hover { background: #2a5298; }
        @media (max-width: 480px) {
            .container { padding: 25px 20px; }
            h1 { font-size: 22px; }
            .check-icon { font-size: 50px; }
            .booking-code { font-size: 14px; }
            .btn { padding: 10px 20px; font-size: 14px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="check-icon"><i class="fa-solid fa-circle-check"></i></div>
        <h1>Payment Successful!</h1>
        <p>Your payment of <strong>R <?= number_format($total_paid, 2) ?></strong> has been received.</p>
        <div class="booking-code">Booking Code: <?= htmlspecialchars($booking['booking_code']) ?></div>
        <p>A confirmation email has been sent to your email address.</p>
        <a href="user-dashboard.php" class="btn">Go to Dashboard</a>
    </div>
</body>
</html>