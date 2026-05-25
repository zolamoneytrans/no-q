<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: user-login.php');
    exit;
}

$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;

if ($booking_id == 0) {
    die('No booking selected');
}

$stmt = $pdo->prepare("
    SELECT b.*, biz.name as business_name
    FROM bookings b
    JOIN businesses biz ON b.business_id = biz.id
    WHERE b.id = ? AND b.user_id = ? AND b.status = 'confirmed'
");
$stmt->execute([$booking_id, $_SESSION['user_id']]);
$booking = $stmt->fetch();

if (!$booking) {
    die('Invalid booking');
}

// Ensure amount is at least R5.00
$amount = max($booking['total_amount'], 5);
$amount = number_format($amount, 2, '.', '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Car Wash Payment</title>
    <style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body {
        font-family: Arial, sans-serif;
        background: #f4f6f8;
        margin: 0;
        padding: 20px;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .payment-container {
        max-width: 500px;
        width: 100%;
        margin: 0 auto;
        background: #fff;
        padding: 30px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        text-align: center;
    }
    h2 {
        color: #333;
        font-size: 24px;
    }
    .amount-display {
        font-size: 48px;
        font-weight: bold;
        color: #1e3c72;
        margin: 20px 0;
    }
    .booking-code {
        color: #666;
        margin-bottom: 20px;
        word-break: break-all;
    }
    .pay-button {
        margin-top: 25px;
    }
    .pay-button input[type="image"] {
        max-width: 100%;
        height: auto;
    }
    .note {
        font-size: 12px;
        color: #999;
        margin-top: 20px;
    }
    @media (max-width: 480px) {
        .payment-container {
            padding: 20px 15px;
        }
        .amount-display {
            font-size: 36px;
        }
        h2 {
            font-size: 20px;
        }
    }
</style>
</head>
<body>
    <div class="payment-container">
        <h2>Pay for Your Car Wash</h2>
        <div class="amount-display">R <?= $amount ?></div>
        <div class="booking-code">Booking: <?= htmlspecialchars($booking['booking_code']) ?></div>
        
        <form action="https:/payment.payfast.io/eng/process" method="post">
            <input type="hidden" name="cmd" value="_paynow">
            <input type="hidden" name="receiver" value="13376932">
            <input type="hidden" name="return_url" value="https://carwashes.africa/payment-success.php?booking_id=<?= $booking_id ?>">
            <input type="hidden" name="cancel_url" value="https://carwashes.africa/payment-cancel.php?booking_id=<?= $booking_id ?>">
            <input type="hidden" name="notify_url" value="https://carwashes.africa/payfast-notify.php">
            <input type="hidden" name="item_name" value="Car Wash">
            <input type="hidden" name="item_description" value="Booking at <?= htmlspecialchars($booking['business_name']) ?>">
            <input type="hidden" name="amount" value="<?= $amount ?>">
            
            <div class="pay-button">
                <input type="image" src="https://my.payfast.io/images/buttons/PayNow/Primary-Small-PayNow.png" alt="Pay Now">
            </div>
        </form>
        <div class="note">Secure payment powered by PayFast</div>
    </div>
</body>
</html>