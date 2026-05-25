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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Booking Details · No Q</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Purple, Orange & White Theme */
        :root {
            --purple-primary: #6a1b9a;
            --purple-dark: #4a0072;
            --purple-light: #9c4dcc;
            --orange-primary: #ff9800;
            --orange-dark: #f57c00;
            --white: #ffffff;
            --bg-gradient: linear-gradient(145deg, #faf5ff 0%, #f3e5f5 100%);
        }

        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-gradient);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .app-header {
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(106,27,154,0.1);
            padding: 0.8rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .logo-area { display: flex; align-items: center; gap: 10px; transition: transform 0.3s ease; }
        .logo-area:hover { transform: scale(1.02); }
        .logo-text { 
            font-weight: 700; 
            font-size: 1.5rem; 
            background: linear-gradient(135deg, var(--purple-primary), var(--orange-primary)); 
            -webkit-background-clip: text; 
            -webkit-text-fill-color: transparent; 
        }
        .nav-links { display: flex; gap: 1.2rem; align-items: center; flex-wrap: wrap; }
        .nav-links a { 
            text-decoration: none; 
            font-weight: 500; 
            color: #2c3e50; 
            padding: 0.5rem 0.8rem; 
            border-radius: 40px;
            transition: 0.2s;
            position: relative;
        }
        .nav-links a::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 2px;
            background: var(--orange-primary);
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }
        .nav-links a:hover::after { width: 30px; }
        .nav-links a:hover { background: rgba(106,27,154,0.08); color: var(--purple-primary); }
        
        .menu-toggle {
            display: none;
            font-size: 1.8rem;
            cursor: pointer;
            background: transparent;
            border: none;
            color: var(--purple-primary);
            padding: 0.5rem;
            transition: transform 0.2s;
        }
        .menu-toggle:hover { transform: scale(1.1); }
        
        @media (max-width: 768px) {
            .menu-toggle { display: block; }
            .app-header { 
                padding: 0.8rem 1rem; 
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                z-index: 1000;
                background: rgba(255,255,255,0.95);
            }
            body { padding-top: 85px; }
            .nav-links { 
                display: none; 
                width: 100%; 
                flex-direction: column; 
                align-items: stretch;
                gap: 0.5rem; 
                padding: 1rem; 
                background: rgba(255,255,255,0.98);
                backdrop-filter: blur(10px);
                border-radius: 24px;
                margin-top: 1rem;
                box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                z-index: 200;
            }
            .nav-links.show { display: flex; }
            .nav-links a { width: 100%; text-align: center; padding: 0.8rem; border-radius: 30px; }
        }
        
        .container { max-width: 800px; margin: 2rem auto; padding: 0 2rem; flex: 1; }
        .card {
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(10px);
            border-radius: 40px;
            padding: 2rem;
            border: 1px solid rgba(106,27,154,0.1);
            box-shadow: 0 20px 40px -12px rgba(106,27,154,0.2);
        }
        h1 { 
            font-size: 2rem; 
            margin-bottom: 1rem; 
            background: linear-gradient(145deg, var(--purple-primary), var(--orange-primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .detail-row { 
            display: flex; 
            padding: 0.8rem 0; 
            border-bottom: 1px solid rgba(0,0,0,0.05);
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .detail-label { 
            font-weight: 600; 
            width: 140px; 
            color: var(--purple-primary); 
        }
        .detail-value { flex: 1; color: #2c3e50; }
        .status { 
            display: inline-block; 
            padding: 0.25rem 1rem; 
            border-radius: 30px; 
            font-size: 0.9rem; 
            font-weight: 600; 
        }
        .status.pending { background: var(--orange-primary); color: white; }
        .status.confirmed { background: #4caf50; color: white; }
        .status.completed { background: #2196f3; color: white; }
        .status.cancelled { background: #f44336; color: white; }
        .back-link { 
            display: inline-block; 
            margin-top: 2rem; 
            color: var(--purple-primary); 
            text-decoration: none;
            transition: all 0.2s;
        }
        .back-link:hover {
            color: var(--orange-primary);
            text-decoration: underline;
        }
        .app-footer { 
            background: rgba(255,255,255,0.6); 
            padding: 2rem; 
            text-align: center; 
            margin-top: auto;
            color: var(--purple-primary);
            font-size: 0.85rem;
        }
        .app-footer a { color: var(--purple-primary); text-decoration: none; }
        .app-footer a:hover { color: var(--orange-primary); text-decoration: underline; }
        
        @media (max-width: 768px) {
            .container { padding: 0 1rem; margin: 1rem auto; }
            .card { padding: 1.2rem; }
            h1 { font-size: 1.5rem; }
            .detail-label { width: 110px; font-size: 0.85rem; }
            .detail-value { font-size: 0.85rem; }
        }
    </style>
</head>
<body>
    <header class="app-header">
        <div class="logo-area">
            <img src="/NoQ.jpg" alt="No Q" style="height: 85px; width: auto;">
            <div>
                <span class="logo-text">No Q</span>
                <div style="font-size: 0.7rem; color: var(--purple-primary); letter-spacing: 0.5px;">No more Queues</div>
            </div>
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
        <p>&copy; <?= date('Y'); ?> No Q. All rights reserved.</p>
        <p>Powered by <a href="https://www.jaekerna.com/" target="_blank">Jaekerna Investments</a></p>
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