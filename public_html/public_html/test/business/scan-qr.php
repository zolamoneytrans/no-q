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
        // ONLY CHANGE: added completed_at = NOW()
        $update = $pdo->prepare("UPDATE bookings SET status = 'completed', completed_at = NOW() WHERE id = ?");
        if ($update->execute([$booking['id']])) {
            $message = "✅ Booking has been marked as COMPLETED!";
            
            $wallet_update = $pdo->prepare("UPDATE businesses SET wallet_balance = wallet_balance + ? WHERE id = ?");
            $wallet_update->execute([$booking['total_amount'], $business_id]);
            
            $points = $pdo->prepare("UPDATE users SET points = points + 10 WHERE id = ?");
            $points->execute([$booking['user_id']]);
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan QR Code</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background: linear-gradient(145deg, #f6f9fc 0%, #e9f1f8 100%); }
        .container { max-width: 600px; margin: 2rem auto; padding: 0 1rem; }
        .card { background: rgba(255,255,255,0.8); border-radius: 32px; padding: 2rem; margin-bottom: 1.5rem; }
        h2 { text-align: center; color: #1e3c72; }
        #reader { width: 100%; max-width: 400px; margin: 0 auto; border: 3px solid #1e3c72; border-radius: 20px; }
        .btn { background: #1e3c72; color: white; padding: 12px 24px; border-radius: 40px; cursor: pointer; border: none; }
        .message { background: #e8f5e9; color: #1e3c72; padding: 1rem; border-radius: 30px; text-align: center; }
        .error { background: #ffebee; color: #b71c1c; padding: 1rem; border-radius: 30px; text-align: center; }
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
        <div class="stats" style="text-align:center; padding:1rem; background:rgba(30,60,114,0.1); border-radius:30px;">
            <div class="number" style="font-size:2rem; font-weight:700;"><?= $today_completed ?></div>
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