<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['business_id'])) {
    header('Location: business-login.php');
    exit;
}

$business_id = $_SESSION['business_id'];
$booking_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("
    SELECT b.*, u.name as user_name, u.email, u.phone, u.points,
           s.name as service_name, s.description as service_description, s.duration as service_duration,
           biz.name as business_name, biz.address, biz.phone as business_phone, biz.email as business_email
    FROM bookings b
    LEFT JOIN users u ON b.user_id = u.id
    LEFT JOIN services s ON b.service_id = s.id
    JOIN businesses biz ON b.business_id = biz.id
    WHERE b.id = ? AND b.business_id = ?
");
$stmt->execute([$booking_id, $business_id]);
$booking = $stmt->fetch();
if (!$booking) die("Booking not found.");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="../favicon.svg" />
    <link rel="shortcut icon" href="../favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="../apple-touch-icon.png" />
    <meta name="apple-mobile-web-app-title" content="No Q" />
    <link rel="manifest" href="../site.webmanifest" />
    <title>Booking Details · No Q</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Inter', sans-serif; background: linear-gradient(145deg,#f6f9fc 0%,#e9f1f8 100%); min-height:100vh; display:flex; flex-direction:column; }
        .app-header {
            background: rgba(255,255,255,0.7); backdrop-filter:blur(12px); border-bottom:1px solid rgba(255,255,255,0.5);
            padding:0.8rem 2rem; display:flex; align-items:center; justify-content:space-between;
        }
        .logo-area { display:flex; align-items:center; gap:10px; }
        .logo-icon { background:#1e3c72; color:white; width:40px; height:40px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:1.5rem; }
        .logo-text { font-weight:700; font-size:1.5rem; background:linear-gradient(135deg,#1e3c72,#2a5298); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
        .nav-links { display:flex; gap:1.2rem; }
        .nav-links a { text-decoration:none; color:#2c3e50; }
        .menu-toggle { display:none; font-size:1.8rem; cursor:pointer; color:#1e3c72; background:transparent; border:none; padding:0.5rem; }
        @media (max-width:768px) {
            .menu-toggle { display:block; }
            .nav-links { display:none; width:100%; flex-direction:column; align-items:center; gap:0.5rem; padding:1rem 0; background:rgba(255,255,255,0.9); backdrop-filter:blur(10px); border-radius:30px; margin-top:1rem; }
            .nav-links.show { display:flex; }
            .app-header { padding:0.8rem 1rem; }
            .nav-links a { width:100%; text-align:center; padding:0.8rem; }
        }
        .container { max-width:800px; margin:2rem auto; padding:2rem; flex:1; }
        .card {
            background:rgba(255,255,255,0.8); backdrop-filter:blur(10px); border-radius:40px; padding:2rem;
            border:1px solid rgba(255,255,255,0.6);
        }
        h1 { font-size:2rem; margin-bottom:1rem; background:linear-gradient(145deg,#1e3c72,#2a5298); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
        .detail-row { display:flex; padding:0.8rem 0; border-bottom:1px solid rgba(0,0,0,0.05); }
        .detail-label { font-weight:600; width:150px; color:#1e3c72; }
        .detail-value { flex:1; color:#2c3e50; }
        .status { display:inline-block; padding:0.25rem 1rem; border-radius:30px; font-size:0.9rem; font-weight:600; }
        .status.pending { background:#ff9800; color:white; }
        .status.confirmed { background:#4caf50; color:white; }
        .status.completed { background:#2196f3; color:white; }
        .status.cancelled { background:#f44336; color:white; }
        .back-link { display:inline-block; margin-top:2rem; color:#1e3c72; }
        .app-footer { background:rgba(255,255,255,0.6); padding:2rem; text-align:center; }
    </style>
</head>
<body>
    <header class="app-header">
        <div class="logo-area">
            <img src="/logo.jpeg" alt="No Q" style="height: 40px; width: auto;">
            <span class="logo-text">CarWash<span style="font-weight:400;">Connect</span></span>
        </div>
        <button class="menu-toggle" id="menuToggle"><i class="fa-solid fa-bars"></i></button>
        <nav class="nav-links" id="navLinks">
            <a href="../index.php">Home</a>
            <a href="business-dashboard.php">Dashboard</a>
            <a href="bookings.php">Bookings</a>
            <a href="business-logout.php">Logout</a>
        </nav>
    </header>

    <div class="container">
        <div class="card">
            <h1>Booking Details</h1>
            <div class="detail-row"><div class="detail-label">Code</div><div class="detail-value"><strong><?= htmlspecialchars($booking['booking_code']) ?></strong></div></div>
            <div class="detail-row"><div class="detail-label">Status</div><div class="detail-value"><span class="status <?= $booking['status'] ?>"><?= ucfirst($booking['status']) ?></span></div></div>
            <div class="detail-row"><div class="detail-label">Customer</div><div class="detail-value"><?= htmlspecialchars($booking['user_name']??'Guest') ?></div></div>
            <div class="detail-row"><div class="detail-label">Email</div><div class="detail-value"><?= htmlspecialchars($booking['email']??'—') ?></div></div>
            <div class="detail-row"><div class="detail-label">Phone</div><div class="detail-value"><?= htmlspecialchars($booking['phone']??'—') ?></div></div>
            <div class="detail-row"><div class="detail-label">Service</div><div class="detail-value"><?= htmlspecialchars($booking['service_name']??'—') ?></div></div>
            <?php if (!empty($booking['service_description'])): ?><div class="detail-row"><div class="detail-label">Description</div><div class="detail-value"><?= nl2br(htmlspecialchars($booking['service_description'])) ?></div></div><?php endif; ?>
            <?php if ($booking['service_duration']): ?><div class="detail-row"><div class="detail-label">Duration</div><div class="detail-value"><?= $booking['service_duration'] ?> min</div></div><?php endif; ?>
            <div class="detail-row"><div class="detail-label">Date</div><div class="detail-value"><?= date('l, d F Y', strtotime($booking['booking_date'])) ?></div></div>
            <div class="detail-row"><div class="detail-label">Time</div><div class="detail-value"><?= htmlspecialchars($booking['time_slot']) ?></div></div>
            <div class="detail-row"><div class="detail-label">Amount</div><div class="detail-value">R <?= number_format($booking['total_amount'],2) ?></div></div>
            <div class="detail-row"><div class="detail-label">Booked on</div><div class="detail-value"><?= date('d M Y H:i', strtotime($booking['created_at'])) ?></div></div>
            <a href="bookings.php" class="back-link"><i class="fa-regular fa-arrow-left"></i> Back to Bookings</a>
        </div>
    </div>

    <footer class="app-footer">
        <p>&copy; <?= date('Y'); ?> No Q</p>
    </footer>

    <script>
        document.getElementById('menuToggle').addEventListener('click', function() {
            document.getElementById('navLinks').classList.toggle('show');
        });
        document.querySelectorAll('.nav-links a').forEach(link => {
            link.addEventListener('click', () => {
                document.getElementById('navLinks').classList.remove('show');
            });
        });
    </script>
</body>
</html>
