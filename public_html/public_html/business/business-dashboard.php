<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['business_id'])) {
    header('Location: business-login.php');
    exit;
}

$business_id = $_SESSION['business_id'];
$business_name = $_SESSION['business_name'];

$stmt = $pdo->prepare("SELECT * FROM businesses WHERE id = ?");
$stmt->execute([$business_id]);
$business = $stmt->fetch();

$wallet_balance = $business['wallet_balance'] ?? 0;

$stmt = $pdo->prepare("SELECT AVG(rating) FROM ratings WHERE business_id = ?");
$stmt->execute([$business_id]);
$avg_rating = $stmt->fetchColumn() ?: 0;

$stmt = $pdo->prepare("
    SELECT b.*, u.name as user_name, u.phone, s.name as service_name
    FROM bookings b
    LEFT JOIN users u ON b.user_id = u.id
    LEFT JOIN services s ON b.service_id = s.id
    WHERE b.business_id = ? AND b.booking_date = CURDATE()
    ORDER BY b.time_slot ASC
");
$stmt->execute([$business_id]);
$today_bookings = $stmt->fetchAll();
$today_count = count($today_bookings);

$stmt = $pdo->prepare("
    SELECT SUM(total_amount) FROM bookings
    WHERE business_id = ? 
    AND DATE(completed_at) = CURDATE() 
    AND status = 'completed'
");
$stmt->execute([$business_id]);
$today_revenue = round($stmt->fetchColumn() ?: 0, 2);

$stmt = $pdo->prepare("
    SELECT b.*, u.name as user_name, u.phone, s.name as service_name
    FROM bookings b
    LEFT JOIN users u ON b.user_id = u.id
    LEFT JOIN services s ON b.service_id = s.id
    WHERE b.business_id = ? AND b.booking_date > CURDATE() AND b.status IN ('pending','confirmed')
    ORDER BY b.booking_date ASC, b.time_slot ASC
");
$stmt->execute([$business_id]);
$upcoming_bookings = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT b.*, u.name as user_name, r.rating, r.comment
    FROM bookings b
    LEFT JOIN users u ON b.user_id = u.id
    LEFT JOIN ratings r ON b.id = r.booking_id
    WHERE b.business_id = ? AND b.status = 'completed'
    ORDER BY b.booking_date DESC
    LIMIT 5
");
$stmt->execute([$business_id]);
$recent_completed = $stmt->fetchAll();

// Leaderboard data
$rank = null;
$total_in_region = null;
$competitors = [];

if (!empty($business['region'])) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) + 1 as rank FROM businesses 
        WHERE region = ? AND rating_avg > (SELECT rating_avg FROM businesses WHERE id = ?)
    ");
    $stmt->execute([$business['region'], $business_id]);
    $rank = $stmt->fetchColumn() ?: 1;

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM businesses WHERE region = ?");
    $stmt->execute([$business['region']]);
    $total_in_region = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT name, rating_avg, id FROM businesses 
        WHERE region = ? AND id != ? 
        ORDER BY rating_avg DESC, id ASC 
        LIMIT 5
    ");
    $stmt->execute([$business['region'], $business_id]);
    $competitors = $stmt->fetchAll();
}
	// Check if business is hidden and show warning
if ($business['is_hidden'] == 1) {
    $hidden_warning = "<div class='alert alert-danger' style='background:#ffebee; color:#b71c1c; padding:1rem; border-radius:30px; margin-bottom:1rem;'>
            <i class='fa-solid fa-circle-exclamation'></i> 
            <strong>Your profile is hidden from customers!</strong> You exceeded your booking limit and did not upgrade. 
            <a href='business-settings.php' style='color:#6a1b9a;'>Upgrade now</a> to restore visibility.
          </div>";
} else {
    $hidden_warning = '';
}

// Show warning if close to limit (80% or more)
$warning_message = '';
$current_count = getBusinessMonthlyBookingCount($pdo, $business_id);
$limit = getBusinessBookingLimit($business['subscription_plan']);
$percentage = ($limit > 0 && $limit != 999999) ? ($current_count / $limit) * 100 : 0;

if ($percentage >= 80 && $limit != 999999 && $business['is_hidden'] == 0) {
    $remaining = $limit - $current_count;
    $warning_message = "<div class='alert alert-warning' style='background:#fff3e0; color:#e65100; padding:1rem; border-radius:30px; margin-bottom:1rem;'>
            <i class='fa-solid fa-clock'></i> 
            <strong>You've used {$current_count} of {$limit} bookings this month.</strong> 
            {$remaining} bookings remaining. <a href='business-settings.php' style='color:#6a1b9a;'>Upgrade</a> to increase your limit.
          </div>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Business Dashboard · No Q</title>
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
            position: sticky;
            top: 0;
            z-index: 100;
            flex-wrap: wrap;
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
        .menu-toggle { display: none; font-size: 1.8rem; cursor: pointer; background: transparent; border: none; padding: 0.5rem; color: var(--purple-primary); transition: transform 0.2s; }
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
                background: rgba(255,255,255,0.85);
                backdrop-filter: blur(15px);
                border-radius: 0 0 24px 24px;
                margin-top: 0;
                box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
                position: fixed;
                top: 85px;
                bottom: 0;
                left: 0;
                right: 0;
                z-index: 200;
                overflow-y: auto;
            }
            .nav-links.show { display: flex; }
            .nav-links a { width: 100%; text-align: center; padding: 0.8rem; border-radius: 30px; }
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
            border: 1px solid rgba(106,27,154,0.1);
            height: fit-content;
        }
        .business-profile { text-align: center; margin-bottom: 2rem; }
        .avatar {
            width: 80px; height: 80px; background: var(--purple-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: white;
            font-size: 2.5rem;
            transition: all 0.3s ease;
        }
        .avatar:hover { transform: scale(1.05) rotate(5deg); background: var(--orange-primary); }
        .business-profile h3 { font-size: 1.4rem; margin-bottom: 0.25rem; color: var(--purple-primary); }
        .subscription-box {
            background: rgba(106,27,154,0.1);
            border-radius: 20px;
            padding: 1rem;
            margin-bottom: 2rem;
            text-align: center;
        }
        .subscription-box .plan { font-size: 1.2rem; font-weight: 700; color: var(--purple-primary); }
        .sidebar-nav { display: flex; flex-direction: column; gap: 0.5rem; }
        .sidebar-nav a {
            text-decoration: none;
            color: #2c3e50;
            padding: 0.8rem 1rem;
            border-radius: 30px;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s ease;
        }
        .sidebar-nav a i { width: 24px; color: var(--purple-primary); }
        .sidebar-nav a:hover, .sidebar-nav a.active { background: rgba(106,27,154,0.1); color: var(--purple-primary); transform: translateX(5px); }
        .main-content { flex: 1; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: rgba(255,255,255,0.7);
            backdrop-filter: blur(8px);
            border-radius: 24px;
            padding: 1.2rem;
            border: 1px solid rgba(106,27,154,0.1);
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            background: rgba(255,255,255,0.9);
            box-shadow: 0 10px 25px -12px rgba(106,27,154,0.2);
        }
        .stat-icon {
            width: 48px; height: 48px; background: rgba(106,27,154,0.1);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--purple-primary);
            transition: all 0.3s ease;
        }
        .stat-card:hover .stat-icon { background: var(--orange-primary); color: white; transform: scale(1.1); }
        .stat-info h4 { font-size: 0.9rem; font-weight: 400; color: #2c3e50; }
        .stat-info .value { font-size: 1.6rem; font-weight: 700; color: var(--purple-primary); }
        
        .leaderboard-card {
            background: linear-gradient(135deg, var(--purple-primary), var(--purple-dark));
            color: white;
            border-radius: 30px;
            padding: 1.5rem;
            margin: 1rem 0 2rem 0;
        }
        .competitor-card {
            background: rgba(255,255,255,0.7);
            backdrop-filter: blur(8px);
            border-radius: 30px;
            padding: 1.5rem;
            margin: 0 0 2rem 0;
            border: 1px solid rgba(106,27,154,0.1);
        }
        .competitor-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.8rem 0;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        .competitor-item:last-child { border-bottom: none; }
        .badge-ahead { background: #f44336; color: white; padding: 2px 8px; border-radius: 15px; font-size: 0.7rem; margin-left: 8px; }
        .badge-behind { background: #4caf50; color: white; padding: 2px 8px; border-radius: 15px; font-size: 0.7rem; margin-left: 8px; }
        .badge-tied { background: var(--orange-primary); color: white; padding: 2px 8px; border-radius: 15px; font-size: 0.7rem; margin-left: 8px; }
        
        .section-header { margin: 2rem 0 1rem; }
        .section-header h2 { 
            font-size: 1.4rem; 
            font-weight: 600; 
            background: linear-gradient(145deg, var(--purple-primary), var(--orange-primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .bookings-list {
            background: rgba(255,255,255,0.5);
            backdrop-filter: blur(4px);
            border-radius: 30px;
            padding: 1rem;
        }
        .booking-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            flex-wrap: wrap;
            gap: 1rem;
        }
        .booking-item:last-child { border-bottom: none; }
        .booking-info h3 { font-size: 1.1rem; margin-bottom: 0.25rem; color: var(--purple-primary); }
        .booking-info p { font-size: 0.9rem; color: #2c3e50; }
        .status {
            padding: 0.25rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .status.pending { background: var(--orange-primary); color: white; }
        .status.confirmed { background: #4caf50; color: white; }
        .status.completed { background: #2196f3; color: white; }
        .status.cancelled { background: #f44336; color: white; }
        
        .btn-small {
            background: linear-gradient(135deg, var(--purple-primary), var(--purple-dark));
            color: white;
            padding: 0.4rem 1.2rem;
            border-radius: 30px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s ease;
        }
        .btn-small:hover {
            transform: translateY(-2px);
            opacity: 0.9;
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
        
        @media (max-width: 900px) {
            .dashboard-layout { flex-direction: column; }
            .sidebar { width: 100%; }
        }
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 0.8rem; }
            .stat-card { padding: 0.8rem; }
            .stat-info .value { font-size: 1.2rem; }
            .stat-icon { width: 36px; height: 36px; font-size: 1rem; }
            .section-header h2 { font-size: 1.2rem; }
        }
    </style>
</head>
<body>
    <header class="app-header">
        <div class="logo-area">
            <img src="../NoQ.jpg" alt="No Q" style="height: 85px; width: auto;">
            <div>
                <span class="logo-text">No Q</span>
                <div style="font-size: 0.7rem; color: var(--purple-primary); letter-spacing: 0.5px;">No more Queues</div>
            </div>
        </div>
        <button class="menu-toggle" id="menuToggle"><i class="fa-solid fa-bars"></i></button>
        <nav class="nav-links" id="navLinks">
            <a href="/index.php">Home</a>
            <a href="/contact.php">Contact</a>
            <a href="business-logout.php">Logout</a>
        </nav>
    </header>
	<?= $hidden_warning ?>
    <?= $warning_message ?>

    <div class="dashboard-layout">
        <aside class="sidebar">
            <div class="business-profile">
                <?php if (!empty($business['logo_url']) && file_exists('../' . $business['logo_url'])): ?>
                    <img src="../<?= htmlspecialchars($business['logo_url']) ?>" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; margin: 0 auto 1rem; display: block; border: 3px solid white;">
                <?php else: ?>
                    <div class="avatar"><i class="fa-regular fa-building"></i></div>
                <?php endif; ?>
                <h3><?= htmlspecialchars($business_name) ?></h3>
                <div class="subscription-box">
                    <div class="plan">⭐ <?= number_format($avg_rating,1) ?> stars</div>
                    <div><?= $today_count ?> bookings today</div>
                </div>
                <div class="wallet-box" style="background: var(--purple-primary); color: white; border-radius: 30px; padding: 1rem; text-align: center; margin: 1rem 0;">
                    <div style="font-size: 1.2rem;">E‑wallet balance</div>
                    <div style="font-size: 2rem; font-weight: 700;">R <?= number_format($wallet_balance, 2) ?></div>
                    <a href="withdraw.php" class="btn-small" style="background: var(--orange-primary); margin-top: 0.5rem; display: inline-block;">Request Withdrawal</a>
                </div>
            </div>
           <!-- Quick Action Tiles (Management section) -->
<div style="margin-bottom: 2rem;">
    <h4 style="color: var(--purple-primary); margin-bottom: 1rem; font-size: 1rem;">Quick Actions</h4>
    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.8rem;">
        <a href="bookings.php" style="background: white; padding: 1rem; border-radius: 20px; text-decoration: none; text-align: center; transition: all 0.3s ease; border: 1px solid rgba(106,27,154,0.1);">
            <i class="fa-regular fa-calendar-check" style="font-size: 1.5rem; color: var(--purple-primary); display: block; margin-bottom: 0.5rem;"></i>
            <span style="font-size: 0.8rem; font-weight: 600; color: #2c3e50;">Manage Bookings</span>
        </a>
        <a href="services.php" style="background: white; padding: 1rem; border-radius: 20px; text-decoration: none; text-align: center; transition: all 0.3s ease; border: 1px solid rgba(106,27,154,0.1);">
            <i class="fa-regular fa-pen-to-square" style="font-size: 1.5rem; color: var(--purple-primary); display: block; margin-bottom: 0.5rem;"></i>
            <span style="font-size: 0.8rem; font-weight: 600; color: #2c3e50;">Manage Services</span>
        </a>
    </div>
</div>

<!-- Regular sidebar navigation for other items -->
<nav class="sidebar-nav">
    <a href="reports.php"><i class="fa-regular fa-chart-line"></i> Reports</a>
    <a href="specials.php"><i class="fa-regular fa-tag"></i> Specials</a>
    <a href="images.php"><i class="fa-regular fa-image"></i> Manage Images</a>
    <a href="scan-qr.php"><i class="fa-solid fa-qrcode"></i> Scan QR Code</a>
    <a href="bank-details.php"><i class="fa-regular fa-bank"></i> Bank Details</a>
    <a href="favorites.php"><i class="fa-regular fa-heart"></i> Who Favourited Me</a>
    <a href="send-notification.php"><i class="fa-regular fa-bell"></i> Send Notification</a>
    <a href="toggle-status.php" style="display: flex; align-items: center; gap: 12px; padding: 0.8rem 1rem; background: <?= ($business['is_temporarily_closed'] ?? 0) ? '#f44336' : '#4caf50' ?>; color: white; border-radius: 30px; text-decoration: none; margin-top: 1rem;">
        <i class="fa-regular fa-<?= ($business['is_temporarily_closed'] ?? 0) ? 'clock' : 'sun' ?>"></i>
        <?= ($business['is_temporarily_closed'] ?? 0) ? 'Currently Closed - Click to Open' : 'Currently Open - Click to Close' ?>
    </a>
    <a href="help.php"><i class="fa-regular fa-circle-question"></i> Help & FAQ</a>
    <a href="business-settings.php"><i class="fa-regular fa-gear"></i> Settings</a>
</nav>
        </aside>

        <main class="main-content">
            <div style="margin-bottom:1.5rem;">
                <h1 style="color: var(--purple-primary);">Good day, <?= htmlspecialchars($business_name) ?></h1>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fa-regular fa-calendar"></i></div>
                    <div class="stat-info"><h4>Today's bookings</h4><div class="value"><?= $today_count ?></div></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fa-regular fa-clock"></i></div>
                    <div class="stat-info"><h4>Revenue today</h4><div class="value">R <?= number_format($today_revenue, 2) ?></div></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fa-regular fa-star"></i></div>
                    <div class="stat-info"><h4>Average rating</h4><div class="value"><?= number_format($avg_rating,1) ?></div></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fa-regular fa-calendar-check"></i></div>
                    <div class="stat-info"><h4>Upcoming</h4><div class="value"><?= count($upcoming_bookings) ?></div></div>
                </div>
            </div>

            <?php if (!empty($business['region'])): ?>
                <div class="leaderboard-card">
                    <h3 style="color: white; margin-bottom: 1rem;"><i class="fa-regular fa-trophy"></i> Your Ranking in <?= htmlspecialchars($business['region']) ?></h3>
                    <div style="font-size: 2.5rem; font-weight: 700; margin-bottom: 0.5rem;">#<?= $rank ?> out of <?= $total_in_region ?></div>
                    <p>car washes in your region</p>
                </div>

                <?php if (!empty($competitors)): ?>
                    <div class="competitor-card">
                        <h3><i class="fa-regular fa-chart-line"></i> Top Competitors in <?= htmlspecialchars($business['region']) ?></h3>
                        <div style="margin-top: 1rem;">
                            <?php foreach ($competitors as $c): ?>
                                <div class="competitor-item">
                                    <div>
                                        <strong><?= htmlspecialchars($c['name']) ?></strong>
                                        <?php if ($c['rating_avg'] > $avg_rating): ?>
                                            <span class="badge-ahead">Ahead of you</span>
                                        <?php elseif ($c['rating_avg'] < $avg_rating): ?>
                                            <span class="badge-behind">Behind you</span>
                                        <?php else: ?>
                                            <span class="badge-tied">Tied</span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="color: #f8b84a;">⭐ <?= number_format($c['rating_avg'], 1) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div style="margin-top: 1rem; padding-top: 0.5rem; text-align: center; font-size: 0.8rem; color: #666;">
                            <i class="fa-regular fa-lightbulb"></i> Improve your rating to climb the leaderboard!
                        </div>
                    </div>
                <?php else: ?>
                    <div class="competitor-card">
                        <h3><i class="fa-regular fa-chart-line"></i> No Competitors Yet</h3>
                        <p>You are currently the only car wash registered in <?= htmlspecialchars($business['region']) ?>.</p>
                        <p>Once other car washes join your region, they will appear here.</p>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="competitor-card">
                    <h3><i class="fa-regular fa-map"></i> Region Not Set</h3>
                    <p>Please update your business profile with your region to see how you rank against competitors.</p>
                    <a href="business-settings.php" class="btn-small" style="background: var(--orange-primary); margin-top: 0.5rem; display: inline-block;">Update Region</a>
                </div>
            <?php endif; ?>

            <div class="section-header"><h2>Today's bookings</h2></div>
            <div class="bookings-list">
                <?php if (empty($today_bookings)): ?>
                    <p>No bookings for today.</p>
                <?php else: ?>
                    <?php foreach ($today_bookings as $b): ?>
                    <div class="booking-item">
                        <div class="booking-info">
                            <h3><?= htmlspecialchars($b['user_name']??'Guest') ?> · <?= htmlspecialchars($b['service_name']??'Car wash') ?></h3>
                            <p><?= htmlspecialchars($b['time_slot']) ?> · R <?= number_format($b['total_amount']) ?></p>
                        </div>
                        <span class="status <?= $b['status'] ?>"><?= ucfirst($b['status']) ?></span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="section-header"><h2>Upcoming bookings</h2></div>
            <div class="bookings-list">
                <?php if (empty($upcoming_bookings)): ?>
                    <p>No upcoming bookings.</p>
                <?php else: ?>
                    <?php foreach ($upcoming_bookings as $b): ?>
                    <div class="booking-item">
                        <div class="booking-info">
                            <h3><?= htmlspecialchars($b['user_name']??'Guest') ?> · <?= htmlspecialchars($b['service_name']??'Car wash') ?></h3>
                            <p><?= date('d M', strtotime($b['booking_date'])) ?>, <?= htmlspecialchars($b['time_slot']) ?> · R <?= number_format($b['total_amount']) ?></p>
                            <p><strong>Code: <?= htmlspecialchars($b['booking_code']) ?></strong></p>
                        </div>
                        <div style="display:flex; gap:0.5rem;">
                            <span class="status <?= $b['status'] ?>"><?= ucfirst($b['status']) ?></span>
                            <?php if ($b['status'] != 'completed'): ?>
                                <a href="complete-booking.php?id=<?= $b['id'] ?>" class="btn-small" style="background:#4caf50;" onclick="return confirm('Mark completed?')">✓ Done</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="section-header"><h2>Recent feedback</h2></div>
            <div class="bookings-list">
                <?php if (empty($recent_completed)): ?>
                    <p>No reviews yet.</p>
                <?php else: ?>
                    <?php foreach ($recent_completed as $b): ?>
                        <?php if ($b['rating']): ?>
                        <div class="booking-item">
                            <div class="booking-info">
                                <h3><?= htmlspecialchars($b['user_name']??'Guest') ?> 
                                    <span style="color:#f8b84a;">
                                    <?php for($i=1;$i<=5;$i++) echo $i<=$b['rating'] ? '<i class="fa-solid fa-star"></i>' : '<i class="fa-regular fa-star"></i>'; ?>
                                    </span>
                                </h3>
                                <p>"<?= htmlspecialchars($b['comment'] ?? 'No comment') ?>"</p>
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <footer class="app-footer">
        <p>&copy; <?= date('Y'); ?> No Q · Business Dashboard</p>
        <p>Powered by <a href="https://www.jaekerna.com/" target="_blank">Jaekerna Investments</a></p>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const menuToggle = document.getElementById('menuToggle');
            const navLinks = document.getElementById('navLinks');
            if (menuToggle) {
                menuToggle.addEventListener('click', function() {
                    navLinks.classList.toggle('show');
                });
            }
            document.querySelectorAll('.nav-links a').forEach(link => {
                link.addEventListener('click', () => {
                    navLinks.classList.remove('show');
                });
            });
        });
    </script>

    <audio id="notificationSound" loop>
        <source src="https://actions.google.com/sounds/v1/alarms/digital_watch_alarm_long.ogg" type="audio/ogg">
    </audio>
    <script>
        let notifInterval;
        let isRinging = false;
        function checkPendingBookings() {
            fetch('check-pending.php')
                .then(r => r.json())
                .then(data => {
                    const audio = document.getElementById('notificationSound');
                    if (data.pending) {
                        if (!isRinging) {
                            audio.play().catch(e => console.log('Audio play blocked:', e));
                            isRinging = true;
                        }
                    } else {
                        audio.pause();
                        audio.currentTime = 0;
                        isRinging = false;
                    }
                })
                .catch(e => console.error(e));
        }
        notifInterval = setInterval(checkPendingBookings, 10000);
        setTimeout(checkPendingBookings, 2000);
    </script>
</body>
</html>