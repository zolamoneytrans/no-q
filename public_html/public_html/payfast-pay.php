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


// Calculate booking fee
$service_price = $booking['total_amount'];

function calculateBookingFee($price) {
    if ($price < 100) {
        return 15;
    } else {
        return $price * 0.15;
    }
}

$booking_fee = calculateBookingFee($service_price);
$total_with_fee = $service_price + $booking_fee;
$amount = number_format($total_with_fee, 2, '.', '');

// Store fee details in session for later use
$_SESSION['booking_fee'] = $booking_fee;
$_SESSION['service_price'] = $service_price;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
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
        .payment-wrapper {
            max-width: 500px;
            width: 100%;
            margin: 0 auto;
        }
        .payment-container {
            width: 100%;
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
        .cancel-btn {
            display: inline-block;
            padding: 10px 20px;
            background: #dc3545;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
            margin-top: 15px;
        }
        .cancel-btn:hover {
            background: #c82333;
        }
        @media (max-width: 480px) {
            body {
                padding: 15px;
            }
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
    <div class="payment-wrapper">
        <div class="payment-container">
            <h2>Pay for Your Car Wash</h2>
            <div class="amount-display">R <?= $amount ?></div>
            <div class="booking-code">Booking: <?= htmlspecialchars($booking['booking_code']) ?></div>
            
            <form action="https://www.payfast.co.za/eng/process" method="post">
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

       
            <a href="my-bookings.php" class="cancel-btn">Cancel</a>
            
            <div class="note">Secure payment powered by PayFast</div>
        </div>
    </div>
</body>
</html>