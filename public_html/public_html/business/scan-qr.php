<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['business_id'])) {
    header('Location: business-login.php');
    exit;
}

$business_id = $_SESSION['business_id'];
$business_name = $_SESSION['business_name'] ?? 'Business';
$message = '';
$error = '';

if (isset($_GET['code'])) {
    $booking_code = trim($_GET['code']);
    
    $stmt = $pdo->prepare("
        SELECT b.*, u.name as user_name, u.email as user_email
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        WHERE (b.booking_code = ? OR b.id = ?) AND b.business_id = ? AND b.status IN ('pending', 'confirmed')
    ");
    $stmt->execute([$booking_code, $booking_code, $business_id]);
    $booking = $stmt->fetch();
    
    if ($booking) {
       $update = $pdo->prepare("UPDATE bookings SET status = 'completed', completed_at = NOW() WHERE id = ?");
if ($update->execute([$booking['id']])) {
    $message = "✅ Booking has been marked as COMPLETED!";
    
    // Calculate how much goes to business
    $service_price = $booking['total_amount'];
    
    // Calculate booking fee 
    if ($service_price < 100) {
        $booking_fee = 15;
    } else {
        $booking_fee = $service_price * 0.15;
    }
    
    // Business gets: 100% of service price + 26% of booking fee
    $business_portion = $service_price + ($booking_fee * 0.26);
    
    $wallet_update = $pdo->prepare("UPDATE businesses SET wallet_balance = wallet_balance + ? WHERE id = ?");
    $wallet_update->execute([$business_portion, $business_id]);
    
    // Fetch business details for limit check
    $biz_stmt = $pdo->prepare("SELECT * FROM businesses WHERE id = ?");
    $biz_stmt->execute([$business_id]);
    $business = $biz_stmt->fetch();

	// Check monthly
$current_count = getBusinessMonthlyBookingCount($pdo, $business_id);
$limit = getBusinessBookingLimit($business['subscription_plan']);

if ($current_count >= $limit && $business['upgrade_warning_sent'] == 0) {
    // Send warning email
    $warning_subject = "⚠️ Booking Limit Reached – Upgrade Required";
    $warning_body = "
    <html>
    <body>
        <h2>You've reached your monthly booking limit</h2>
        <p>Dear {$business['name']},</p>
        <p>Your {$business['subscription_plan']} plan allows {$limit} bookings per month. You have now reached this limit.</p>
        <p><strong>Please upgrade your plan to continue accepting new bookings.</strong></p>
        <p>You have 7 days to upgrade. After that, your business profile will be hidden from customers until you upgrade.</p>
        <p><a href='https://carwashes.africa/business/business-settings.php'>Upgrade Now</a></p>
        <p>No Q Team</p>
    </body>
    </html>
    ";
    sendEmail($business['email'], $warning_subject, $warning_body);
    
    // Update warning sent and set grace deadline (7 days from now)
    $update_warning = $pdo->prepare("UPDATE businesses SET upgrade_warning_sent = 1, upgrade_grace_deadline = DATE_ADD(CURDATE(), INTERVAL 7 DAY) WHERE id = ?");
    $update_warning->execute([$business_id]);
}
    
    $points = $pdo->prepare("UPDATE users SET points = points + 10 WHERE id = ?");
    $points->execute([$booking['user_id']]);
}
        } else {
            $error = "Failed to update booking.";
        }
    } else {
        $error = "Invalid booking code.";
    }
}

$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM bookings 
    WHERE business_id = ? AND status = 'completed' AND DATE(completed_at) = CURDATE()
");
$stmt->execute([$business_id]);
$today_completed = $stmt->fetchColumn();

$rev_stmt = $pdo->prepare("
    SELECT SUM(total_amount) FROM bookings 
    WHERE business_id = ? AND DATE(completed_at) = CURDATE() AND status = 'completed'
");
$rev_stmt->execute([$business_id]);
$today_revenue = round($rev_stmt->fetchColumn() ?: 0, 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Scan QR Code</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --purple-primary: #6a1b9a;
            --purple-dark: #4a0072;
            --purple-light: #9c4dcc;
            --orange-primary: #ff9800;
            --orange-dark: #f57c00;
            --white: #ffffff;
            --bg-gradient: linear-gradient(145deg, #faf5ff 0%, #f3e5f5 100%);
        }
        body { font-family: 'Inter', sans-serif; background: var(--bg-gradient); }
        .container { max-width: 600px; margin: 2rem auto; padding: 0 1rem; }
        .card { background: rgba(255,255,255,0.9); border-radius: 32px; padding: 2rem; margin-bottom: 1.5rem; border: 1px solid rgba(106,27,154,0.1); box-shadow: 0 20px 40px -12px rgba(106,27,154,0.2); }
        h2 { text-align: center; background: linear-gradient(145deg, var(--purple-primary), var(--orange-primary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        #reader { width: 100%; max-width: 400px; margin: 0 auto; border: 3px solid var(--purple-primary); border-radius: 20px; }
        .btn { background: linear-gradient(135deg, var(--purple-primary), var(--purple-dark)); color: white; padding: 12px 24px; border-radius: 40px; cursor: pointer; border: none; transition: all 0.2s; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 3px 10px rgba(106,27,154,0.3); }
        .message { background: #e8f5e9; color: var(--purple-primary); padding: 1rem; border-radius: 30px; text-align: center; }
        .error { background: #ffebee; color: #b71c1c; padding: 1rem; border-radius: 30px; text-align: center; }
        .stats { text-align:center; padding:1rem; background: rgba(106,27,154,0.1); border-radius:30px; }
        .stats .number { font-size:2rem; font-weight:700; color: var(--purple-primary); }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h2><i class="fa-solid fa-qrcode"></i> Scan QR Code</h2>
            <?php if ($message): ?><div class="message"><?= htmlspecialchars($message) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <div id="reader"></div>
            <form method="get" class="manual-input" style="text-align:center; margin-top:1rem;">
                <input type="text" name="code" placeholder="Enter booking code" style="padding:10px; border-radius:30px; border:none;">
                <button type="submit" class="btn" style="margin-top:10px;">Mark Complete</button>
            </form>
        </div>
        <div class="stats">
            <div class="number"><?= $today_completed ?></div>
            <div>Bookings completed today</div>
            <div style="margin-top:0.5rem;">Revenue today: <strong>R <?= number_format($today_revenue, 2) ?></strong></div>
        </div>
    </div>
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script>
        const html5QrCode = new Html5Qrcode("reader");
        html5QrCode.start({ facingMode: "environment" }, { fps: 10, qrbox: 250 }, (decodedText) => {
            html5QrCode.stop();
            window.location.href = "?code=" + encodeURIComponent(decodedText);
        }, (err) => {});
    </script>
</body>
</html>