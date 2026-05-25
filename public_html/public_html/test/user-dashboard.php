<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: user-login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'User';

$stmt = $pdo->prepare("SELECT points FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
$points = $user ? $user['points'] : 0;

$stmt = $pdo->prepare("
    SELECT b.*, biz.name as business_name, s.name as service_name, s.price
    FROM bookings b
    JOIN businesses biz ON b.business_id = biz.id
    LEFT JOIN services s ON b.service_id = s.id
    WHERE b.user_id = ? AND b.status IN ('pending', 'confirmed', 'rescheduled') 
      AND booking_date >= CURDATE()
    ORDER BY booking_date ASC, b.time_slot ASC
");
$stmt->execute([$user_id]);
$upcoming_bookings = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT p.*, booking_date, biz.name as business_name
    FROM payments p
    JOIN bookings bk ON p.booking_id = bk.id
    JOIN businesses biz ON bk.business_id = biz.id
    WHERE p.user_id = ?
    ORDER BY p.created_at DESC
    LIMIT 4
");
$stmt->execute([$user_id]);
$recent_payments = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT * FROM businesses 
    WHERE is_approved = 1 AND is_active = 1 AND rating_avg > 0
    ORDER BY rating_avg DESC 
    LIMIT 3
");
$stmt->execute();
$recommended = $stmt->fetchAll();

// Fetch reviews given by this user
$stmt = $pdo->prepare("
    SELECT r.*, b.name as business_name
    FROM ratings r
    JOIN businesses b ON r.business_id = b.id
    WHERE r.user_id = ?
    ORDER BY r.created_at DESC
");
$stmt->execute([$user_id]);
$my_reviews = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT b.id, b.booking_date, b.time_slot, biz.name as business_name, s.name as service_name
    FROM bookings b
    JOIN businesses biz ON b.business_id = biz.id
    LEFT JOIN services s ON b.service_id = s.id
    LEFT JOIN ratings r ON b.id = r.booking_id
    WHERE b.user_id = ? AND b.status = 'completed' AND r.id IS NULL
    ORDER BY b.booking_date DESC
");
$stmt->execute([$user_id]);
$rateable_bookings = $stmt->fetchAll();

// Fetch favorite businesses for this user
$stmt = $pdo->prepare("
    SELECT b.* FROM businesses b
    INNER JOIN user_favorites f ON b.id = f.business_id
    WHERE f.user_id = ? AND b.is_approved = 1 AND b.is_active = 1
    ORDER BY f.created_at DESC
");
$stmt->execute([$user_id]);
$favorites = $stmt->fetchAll();

// Total spent this month (from completed bookings)
$stmt = $pdo->prepare("
    SELECT SUM(total_amount) FROM bookings
    WHERE user_id = ? AND status = 'completed' AND MONTH(booking_date) = MONTH(CURDATE()) AND YEAR(booking_date) = YEAR(CURDATE())
");
$stmt->execute([$user_id]);
$spent_this_month = round($stmt->fetchColumn() ?: 0, 2);

// Spending per business (all time)
$stmt = $pdo->prepare("
    SELECT biz.name as business_name, SUM(b.total_amount) as total_spent, COUNT(b.id) as washes_count
    FROM bookings b
    JOIN businesses biz ON b.business_id = biz.id
    WHERE b.user_id = ? AND b.status = 'completed'
    GROUP BY b.business_id
    ORDER BY total_spent DESC
");
$stmt->execute([$user_id]);
$spending_by_business = $stmt->fetchAll();

// Monthly spending (last 6 months)
$stmt = $pdo->prepare("
    SELECT DATE_FORMAT(booking_date, '%b %Y') as month, SUM(total_amount) as monthly_spent
    FROM bookings
    WHERE user_id = ? AND status = 'completed' AND booking_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY YEAR(booking_date), MONTH(booking_date)
    ORDER BY booking_date DESC
");
$stmt->execute([$user_id]);
$monthly_spending = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="favicon.svg" />
    <link rel="shortcut icon" href="favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="apple-touch-icon.png" />
    <meta name="apple-mobile-web-app-title" content="No Q" />
    <link rel="manifest" href="site.webmanifest" />
    <title>Dashboard · No Q</title>
    <link rel="manifest" href="/carwash-connect/manifest.json">
    <meta name="theme-color" content="#ff9800">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(145deg, #f6f9fc 0%, #e9f1f8 100%);
            color: #1a2639;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .app-header {
            background: rgba(255,255,255,0.7);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255,255,255,0.5);
            padding: 0.8rem 2rem;
            position: sticky;
            top: 0;
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
        }
        .status.rescheduled { background: #9c27b0; color: white; }
        .logo-area { display: flex; align-items: center; gap: 10px; }
        .logo-icon { background: #1e3c72; color: white; width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        .logo-text { font-weight: 700; font-size: 1.5rem; background: linear-gradient(135deg, #1e3c72, #2a5298); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .nav-links { display: flex; gap: 1.2rem; align-items: center; }
        .nav-links a { text-decoration: none; font-weight: 500; color: #2c3e50; padding: 0.5rem 0.8rem; border-radius: 40px; }
        .nav-links a:hover { background: rgba(42,82,152,0.08); }
        .nav-links .btn-outline { border: 1.5px solid #1e3c72; padding: 0.4rem 1.2rem; border-radius: 40px; background: white; font-weight: 600; }
        .nav-links .btn-outline:hover { background: #1e3c72; color: white; }

        .menu-toggle { display: none; font-size: 1.8rem; cursor: pointer; color: #1e3c72; background: transparent; border: none; padding: 0.5rem; }
        @media (max-width: 768px) {
            .menu-toggle { display: block; }
            .nav-links { display: none; width: 100%; flex-direction: column; align-items: center; gap: 0.5rem; padding: 1rem 0; background: rgba(255,255,255,0.9); backdrop-filter: blur(10px); border-radius: 30px; margin-top: 1rem; }
            .nav-links.show { display: flex; }
            .app-header { padding: 0.8rem 1rem; }
            .nav-links a { width: 100%; text-align: center; padding: 0.8rem; }
            .btn-outline { width: 100%; }
        }

        .dashboard-layout {
            display: flex;
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
            gap: 2rem;
            flex: 1;
        }
        .sidebar {
            width: 280px;
            background: rgba(255,255,255,0.6);
            backdrop-filter: blur(8px);
            border-radius: 40px;
            padding: 2rem 1.5rem;
            border: 1px solid rgba(255,255,255,0.6);
            height: fit-content;
        }
        .user-profile { text-align: center; margin-bottom: 2rem; }
        .avatar {
            width: 80px; height: 80px; background: #1e3c72; border-radius: 50%;
            display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;
            color: white; font-size: 2.5rem;
        }
        .user-profile h3 { font-size: 1.4rem; margin-bottom: 0.25rem; }
        .badge {
            background: #ff9800; color: white; padding: 0.3rem 1rem; border-radius: 30px;
            font-size: 0.8rem; font-weight: 600; display: inline-block; margin-bottom: 1rem;
        }
        .points-box {
            background: rgba(30,60,114,0.1); border-radius: 20px; padding: 1rem; margin-bottom: 2rem; text-align: center;
        }
        .points-box .value { font-size: 2rem; font-weight: 700; color: #1e3c72; }
        .sidebar-nav { display: flex; flex-direction: column; gap: 0.5rem; }
        .sidebar-nav a {
            text-decoration: none; color: #2c3e50; padding: 0.8rem 1rem; border-radius: 30px;
            display: flex; align-items: center; gap: 12px; transition: 0.15s;
        }
        .sidebar-nav a i { width: 24px; color: #2a5298; }
        .sidebar-nav a:hover, .sidebar-nav a.active { background: rgba(42,82,152,0.1); color: #1e3c72; }

        .main-content { flex: 1; }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: rgba(255,255,255,0.7); backdrop-filter: blur(8px); border-radius: 24px; padding: 1.2rem;
            border: 1px solid rgba(255,255,255,0.6); display: flex; align-items: center; gap: 1rem;
        }
        .stat-icon {
            width: 48px; height: 48px; background: rgba(30,60,114,0.1); border-radius: 16px;
            display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: #1e3c72;
        }
        .stat-info h4 { font-size: 0.9rem; font-weight: 400; color: #2c3e50; }
        .stat-info .value { font-size: 1.6rem; font-weight: 700; color: #1e3c72; }

        .section-header { display: flex; justify-content: space-between; align-items: center; margin: 2rem 0 1rem; }
        .section-header h2 {
            font-size: 1.4rem; font-weight: 600;
            background: linear-gradient(145deg, #1e3c72, #2a5298); -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .view-all { color: #2a5298; text-decoration: none; font-weight: 500; }

        .appointments-list, .bookings-list {
            background: rgba(255,255,255,0.5); backdrop-filter: blur(4px); border-radius: 30px; padding: 1rem;
        }
        .appointment-item {
            display: flex; justify-content: space-between; align-items: center; padding: 1rem;
            border-bottom: 1px solid rgba(0,0,0,0.05); flex-wrap: wrap; gap: 1rem;
        }
        .appointment-item:last-child { border-bottom: none; }
        .appointment-info h3 { font-size: 1.1rem; margin-bottom: 0.25rem; }
        .appointment-info p { font-size: 0.9rem; color: #2c3e50; }
        .status {
            background: #4caf50; color: white; padding: 0.25rem 1rem; border-radius: 20px;
            font-size: 0.8rem; font-weight: 600;
        }
        .status.orange { background: #ff9800; }

        .btn-small {
            background: #1e3c72; color: white; border: none; padding: 0.4rem 1.2rem; border-radius: 30px;
            font-size: 0.9rem; cursor: pointer; text-decoration: none; display: inline-block;
        }

        .transactions-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 1rem;
        }
        .transaction-card {
            background: white; border-radius: 20px; padding: 1rem; box-shadow: 0 5px 10px rgba(0,0,0,0.02);
        }
        .transaction-card .date { font-size: 0.8rem; color: #666; }
        .transaction-card .service { font-weight: 600; }
        .transaction-card .amount { font-weight: 700; color: #1e3c72; margin-top: 0.5rem; }

        .chart-placeholder {
            background: rgba(255,255,255,0.5); border-radius: 30px; padding: 1.5rem; text-align: center;
            border: 1px dashed #2a5298;
        }

        .app-footer {
            background: rgba(255,255,255,0.6); padding: 2rem; text-align: center; margin-top: auto;
        }

        @media (max-width: 900px) {
            .dashboard-layout { flex-direction: column; }
            .sidebar { width: 100%; }
        }
        @media (max-width: 480px) {
            .appointment-item { flex-direction: column; align-items: flex-start; }
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <header class="app-header">
        <div class="logo-area">
            <img src="logo.jpeg" alt="No Q" style="height: 40px; width: auto;">
            <span class="logo-text">CarWash<span style="font-weight:400;">Connect</span></span>
        </div>
        <button class="menu-toggle" id="menuToggle"><i class="fa-solid fa-bars"></i></button>
        <nav class="nav-links" id="navLinks">
            <a href="index.php">Home</a>
            <a href="user-dashboard.php" style="background:rgba(42,82,152,0.1);">Dashboard</a>
            <?php
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$user_id]);
            $unread_count = $stmt->fetchColumn();
            ?>
            <a href="notifications.php"><i class="fa-regular fa-bell"></i> Notifications<?php if ($unread_count>0): ?><span style="background:#ff9800; color:white; border-radius:20px; padding:2px 8px; margin-left:5px;"><?= $unread_count ?></span><?php endif; ?></a>
            <a href="logout.php"><i class="fa-regular fa-circle-user"></i> Logout</a>
        </nav>
    </header>

    <div class="dashboard-layout">
        <aside class="sidebar">
            <div class="user-profile">
                <div class="avatar"><i class="fa-regular fa-user"></i></div>
                <h3><?= htmlspecialchars($user_name) ?></h3>
            </div>
            <nav class="sidebar-nav">
                <a href="#" class="active"><i class="fa-regular fa-chart-line"></i> Overview</a>
                <a href="https://carwashes.africa/my-bookings.php?filter=completed"><i class="fa-regular fa-star"></i> Rate Car Wash</a>
                <a href="my-bookings.php"><i class="fa-regular fa-calendar-check"></i> My Bookings</a>
                <a href="notifications.php"><i class="fa-regular fa-bell"></i> Notifications</a>
            </nav>
        </aside>

        <main class="main-content">
            <div style="display:flex; justify-content:space-between; margin-bottom:1.5rem;">
                <h1>Good day, <?= htmlspecialchars(explode(' ',$user_name)[0]) ?></h1>
            </div>

            <?php
            $success_message = $_GET['success'] ?? '';
            $error_message = $_GET['error'] ?? '';
            if ($success_message == 'review_added'): ?>
                <div style="background:#e8f5e9; color:#1e3c72; padding:1rem; border-radius:30px; margin-bottom:1rem;">
                    <i class="fa-solid fa-circle-check"></i> Thank you! Your review has been posted.
                </div>
            <?php elseif ($error_message == 'invalid_booking'): ?>
                <div style="background:#ffebee; color:#b71c1c; padding:1rem; border-radius:30px; margin-bottom:1rem;">
                    <i class="fa-solid fa-circle-exclamation"></i> This booking cannot be rated.
                </div>
            <?php endif; ?>
            <?php if ($success_message == 'cancelled'): ?>
                <div style="background:#e8f5e9; color:#1e3c72; padding:1rem; border-radius:30px; margin-bottom:1rem;">
                    <i class="fa-solid fa-circle-check"></i> Booking cancelled successfully.
                </div>
            <?php elseif ($error_message == 'invalid_cancellation'): ?>
                <div style="background:#ffebee; color:#b71c1c; padding:1rem; border-radius:30px; margin-bottom:1rem;">
                    <i class="fa-solid fa-circle-exclamation"></i> This booking cannot be cancelled.
                </div>
            <?php elseif ($error_message == 'cancel_failed'): ?>
                <div style="background:#ffebee; color:#b71c1c; padding:1rem; border-radius:30px; margin-bottom:1rem;">
                    <i class="fa-solid fa-circle-exclamation"></i> Failed to cancel booking.
                </div>
            <?php endif; ?>

            <?php if ($success_message == 'review_deleted'): ?>
                <div style="background:#e8f5e9; color:#1e3c72; padding:1rem; border-radius:30px; margin-bottom:1rem;">
                    <i class="fa-solid fa-circle-check"></i> Your review has been deleted.
                </div>
            <?php elseif ($error_message == 'invalid_review'): ?>
                <div style="background:#ffebee; color:#b71c1c; padding:1rem; border-radius:30px; margin-bottom:1rem;">
                    <i class="fa-solid fa-circle-exclamation"></i> Review not found.
                </div>
            <?php elseif ($error_message == 'delete_failed'): ?>
                <div style="background:#ffebee; color:#b71c1c; padding:1rem; border-radius:30px; margin-bottom:1rem;">
                    <i class="fa-solid fa-circle-exclamation"></i> Failed to delete review.
                </div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fa-regular fa-calendar"></i></div>
                    <div class="stat-info"><h4>Upcoming</h4><div class="value"><?= count($upcoming_bookings) ?></div></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fa-regular fa-clock"></i></div>
                    <div class="stat-info"><h4>Total washes</h4><div class="value"><?php
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = ? AND status = 'completed'");
                        $stmt->execute([$user_id]); echo $stmt->fetchColumn();
                    ?></div></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fa-regular fa-credit-card"></i></div>
                    <div class="stat-info"><h4>Spent this month</h4><div class="value">R <?= number_format($spent_this_month, 2) ?></div></div>
                </div>
            </div>

            <!-- Upcoming appointments -->
            <div class="section-header"><h2>Upcoming appointments</h2></div>
            <div class="appointments-list">
                <?php if (empty($upcoming_bookings)): ?>
                    <p style="padding:1rem;">No upcoming bookings. <a href="search.php">Find a car wash</a></p>
                <?php else: ?>
                    <?php foreach ($upcoming_bookings as $booking):
                        $status_class = '';
                        if ($booking['status'] == 'pending') $status_class = 'orange';
                        elseif ($booking['status'] == 'rescheduled') $status_class = 'rescheduled';
                        elseif ($booking['status'] == 'confirmed') $status_class = ''; 
                    ?>
                    <div class="appointment-item">
                        <div class="appointment-info">
                            <h3><?= htmlspecialchars($booking['business_name']) ?> · <?= htmlspecialchars($booking['service_name'] ?? 'Car wash') ?></h3>
                            <p><i class="fa-regular fa-calendar"></i> <?= date('d M Y', strtotime($booking['booking_date'])) ?>, <?= htmlspecialchars($booking['time_slot']) ?></p>
                            <p style="font-size:0.9rem; color:#1e3c72;"><strong>Code: <?= htmlspecialchars($booking['booking_code']) ?></strong></p>
                            <!-- QR code - only for confirmed bookings -->
                            <?php if ($booking['status'] == 'confirmed'): ?>
                                <img src="qr.php?code=<?= urlencode($booking['booking_code']) ?>" alt="QR Code" style="width: 80px; height: 80px; margin-top: 5px;">
                            <?php endif; ?>
                        </div>
                        <div style="display:flex; gap:0.5rem; align-items:center;">
                            <span class="status <?= $status_class ?>"><?= ucfirst($booking['status']) ?></span>
                            <?php if ($booking['status'] == 'confirmed' && ($booking['payment_status'] ?? 'pending') != 'paid'): ?>
                                <a href="payfast-pay.php?booking_id=<?= $booking['id'] ?>" class="btn-small" style="background:#4caf50;">Pay Now</a>
                            <?php endif; ?>
                            <a href="reschedule.php?id=<?= $booking['id'] ?>" class="btn-small" style="background:#ff9800;">Reschedule</a>
                            <a href="cancel-booking.php?id=<?= $booking['id'] ?>" class="btn-small" style="background:#f44336;" onclick="return confirm('Cancel?')">Cancel</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <?php if (!empty($rateable_bookings)): ?>
            <div class="section-header"><h2>Rate your recent washes</h2></div>
            <div class="bookings-list">
                <?php foreach ($rateable_bookings as $booking): ?>
                <div class="appointment-item">
                    <div class="appointment-info">
                        <h3><?= htmlspecialchars($booking['business_name']) ?> · <?= htmlspecialchars($booking['service_name'] ?? 'Car wash') ?></h3>
                        <p><?= date('d M Y', strtotime($booking['booking_date'])) ?>, <?= htmlspecialchars($booking['time_slot']) ?></p>
                    </div>
                    <a href="rate-booking.php?id=<?= $booking['id'] ?>" class="btn-small" style="background:#ff9800;">Rate this wash</a>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Recent transactions -->
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:2rem; margin-top:2rem;">
                <div>
                    <div class="section-header"><h2>Recent transactions</h2><a href="transactions.php" class="view-all">View all</a></div>
                    <div class="transactions-grid">
                        <?php if (empty($recent_payments)): ?>
                            <p>No recent transactions.</p>
                        <?php else: ?>
                            <?php foreach ($recent_payments as $pay): ?>
                            <div class="transaction-card">
                                <div class="date"><?= date('d M Y', strtotime($pay['created_at'])) ?></div>
                                <div class="service"><?= htmlspecialchars($pay['business_name']) ?></div>
                                <div class="amount">- R <?= number_format($pay['amount']) ?></div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Spending Breakdown -->
            <div class="section-header">
                <h2>Spending Breakdown</h2>
            </div>
            <div class="bookings-list">
                <?php if (empty($spending_by_business)): ?>
                    <p>No completed bookings yet.</p>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: rgba(30,60,114,0.1);">
                                    <th style="padding: 0.8rem; text-align: left;">Car Wash</th>
                                    <th style="padding: 0.8rem; text-align: left;">Washes</th>
                                    <th style="padding: 0.8rem; text-align: left;">Total Spent</th>
                                 </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($spending_by_business as $biz): ?>
                                 <tr>
                                    <td style="padding: 0.8rem;"><?= htmlspecialchars($biz['business_name']) ?></td>
                                    <td style="padding: 0.8rem;"><?= $biz['washes_count'] ?></td>
                                    <td style="padding: 0.8rem; font-weight: 600; color: #1e3c72;">R <?= number_format($biz['total_spent'], 2) ?></td>
                                 </tr>
                                <?php endforeach; ?>
                            </tbody>
                          </table>
                    </div>

                    <?php if (!empty($monthly_spending)): ?>
                    <div style="margin-top: 1.5rem;">
                        <h4 style="margin: 1rem 0 0.5rem;">Monthly Spending (last 6 months)</h4>
                        <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                            <?php foreach ($monthly_spending as $month): ?>
                            <div style="background: rgba(255,255,255,0.5); border-radius: 20px; padding: 0.5rem 1rem;">
                                <strong><?= $month['month'] ?></strong><br>
                                R <?= number_format($month['monthly_spent'], 2) ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <?php if (!empty($favorites)): ?>
            <div class="section-header">
                <h2>My Favorites</h2>
                <a href="my-favorites.php" class="view-all">View all</a>
            </div>
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px,1fr)); gap:1rem;">
                <?php foreach ($favorites as $biz): ?>
                <div style="background:white; border-radius:24px; padding:1.2rem;">
                    <h3 style="font-size:1.1rem;"><?= htmlspecialchars($biz['name']) ?></h3>
                    <p style="font-size:0.9rem; color:#555;">⭐ <?= number_format($biz['rating_avg'],1) ?> · <?= htmlspecialchars(explode(',',$biz['address'])[0]) ?></p>
                    <button class="btn-small" style="margin-top:0.5rem; width:100%;" onclick="window.location.href='book.php?id=<?= $biz['id'] ?>'">Book</button>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($recommended)): ?>
            <div class="section-header"><h2>Recommended for you</h2><a href="search.php" class="view-all">View all</a></div>
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px,1fr)); gap:1rem;">
                <?php foreach ($recommended as $biz): ?>
                <div style="background:white; border-radius:24px; padding:1.2rem;">
                    <h3 style="font-size:1.1rem;"><?= htmlspecialchars($biz['name']) ?></h3>
                    <p style="font-size:0.9rem; color:#555;">⭐ <?= number_format($biz['rating_avg'],1) ?> · <?= htmlspecialchars(explode(',',$biz['address'])[0]) ?></p>
                    <button class="btn-small" style="margin-top:0.5rem; width:100%;" onclick="window.location.href='book.php?id=<?= $biz['id'] ?>'">Book</button>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- My Reviews -->
            <div class="section-header">
                <h2>My Reviews</h2>
            </div>
            <div class="bookings-list">
                <?php if (empty($my_reviews)): ?>
                    <p>You haven't left any reviews yet.</p>
                <?php else: ?>
                    <?php foreach ($my_reviews as $review): ?>
                    <div class="appointment-item">
                        <div class="appointment-info">
                            <h3><?= htmlspecialchars($review['business_name']) ?></h3>
                            <div class="rating">
                                <?php for ($i=1;$i<=5;$i++): ?>
                                    <?= $i <= $review['rating'] ? '<i class="fa-solid fa-star"></i>' : '<i class="fa-regular fa-star"></i>' ?>
                                <?php endfor; ?>
                            </div>
                            <p><strong>Customer service:</strong> <?= $review['rating_customer_service'] ?> ⭐</p>
                            <p><strong>Time taken:</strong> <?= $review['rating_time_taken'] ?> ⭐</p>
                            <p><strong>Quality:</strong> <?= $review['rating_quality'] ?> ⭐</p>
                            <p><strong>Environment:</strong> <?= $review['rating_environment'] ?> ⭐</p>
                            <p><strong>Cost/value:</strong> <?= $review['rating_cost'] ?> ⭐</p>
                            <p><em>"<?= htmlspecialchars($review['comment'] ?? 'No comment') ?>"</em></p>
                            <p style="font-size:0.8rem;">Posted on <?= date('d M Y', strtotime($review['created_at'])) ?></p>
                        </div>
                        <div>
                            <a href="delete-review.php?id=<?= $review['id'] ?>" class="btn-small" style="background:#f44336;" onclick="return confirm('Delete this review?')">Delete</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <footer class="app-footer">
        <p>&copy; <?= date('Y'); ?> No Q. All rights reserved.</p>
        <p>Powered by <a href="https://www.jaekerna.com/" target="_blank" style="color:#1e3c72;">Jaekerna Investments</a></p>
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

    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('/carwash-connect/sw.js').then(function(registration) {
                    console.log('ServiceWorker registration successful with scope: ', registration.scope);
                }, function(err) {
                    console.log('ServiceWorker registration failed: ', err);
                });
            });
        }
    </script>
</body>
</html>
