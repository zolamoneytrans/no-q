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
    WHERE b.user_id = ? AND b.status IN ('pending', 'confirmed') 
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

// Get current hour for greeting
$current_hour = date('H');
if ($current_hour < 12) {
    $greeting = "Good morning";
} elseif ($current_hour < 17) {
    $greeting = "Good afternoon";
} else {
    $greeting = "Good evening";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <link rel="icon" type="image/png" href="favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="favicon.svg" />
    <link rel="shortcut icon" href="favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="apple-touch-icon.png" />
    <meta name="apple-mobile-web-app-title" content="No Q" />
    <link rel="manifest" href="site.webmanifest" />
    <title>Dashboard · No Q</title>
    <link rel="manifest" href="/carwash-connect/manifest.json">
    <meta name="theme-color" content="#6a1b9a">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
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
            color: #1a2639;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        /* Animations */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes fadeInLeft {
            from { opacity: 0; transform: translateX(-30px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        @keyframes fadeInRight {
            from { opacity: 0; transform: translateX(30px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-5px); }
            100% { transform: translateY(0px); }
        }
        
        .stat-card, .sidebar, .appointments-list, .bookings-list, .card, .transaction-card {
            animation: fadeInUp 0.6s ease-out;
            animation-fill-mode: both;
        }
        
        .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stat-card:nth-child(2) { animation-delay: 0.2s; }
        .stat-card:nth-child(3) { animation-delay: 0.3s; }
        
        .app-header {
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(106,27,154,0.1);
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
        .logo-area { display: flex; align-items: center; gap: 10px; transition: transform 0.3s ease; }
        .logo-area:hover { transform: scale(1.02); }
        .logo-text { font-weight: 700; font-size: 1.5rem; background: linear-gradient(135deg, var(--purple-primary), var(--orange-primary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .nav-links { display: flex; gap: 1.2rem; align-items: center; flex-wrap: wrap; }
        .nav-links a { text-decoration: none; font-weight: 500; color: #2c3e50; padding: 0.5rem 0.8rem; border-radius: 40px; transition: all 0.3s ease; position: relative; }
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
        .nav-links .btn-outline { border: 1.5px solid var(--purple-primary); padding: 0.4rem 1.2rem; border-radius: 40px; background: white; font-weight: 600; }
        .nav-links .btn-outline:hover { background: var(--purple-primary); color: white; }
        .nav-links .btn-outline::after { display: none; }

        .menu-toggle { display: none; font-size: 1.8rem; cursor: pointer; color: var(--purple-primary); background: transparent; border: none; padding: 0.5rem; transition: transform 0.2s; }
        .menu-toggle:hover { transform: scale(1.1); }
        
        @media (max-width: 768px) {
            .menu-toggle { display: block; }
            .app-header { padding: 0.8rem 1rem; position: relative; }
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
            .btn-outline { width: 100%; text-align: center; }
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
            transition: all 0.3s ease;
        }
        .sidebar:hover { transform: translateY(-5px); background: rgba(255,255,255,0.8); }
        .user-profile { text-align: center; margin-bottom: 2rem; }
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
        .user-profile h3 { font-size: 1.4rem; margin-bottom: 0.25rem; color: var(--purple-primary); }
        .badge {
            background: var(--orange-primary);
            color: white;
            padding: 0.3rem 1rem;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 1rem;
        }
        .points-box {
            background: rgba(106,27,154,0.1);
            border-radius: 20px;
            padding: 1rem;
            margin-bottom: 2rem;
            text-align: center;
            transition: all 0.3s ease;
        }
        .points-box:hover { transform: scale(1.02); background: rgba(106,27,154,0.15); }
        .points-box .value { font-size: 2rem; font-weight: 700; color: var(--purple-primary); }
        .sidebar-nav { display: flex; flex-direction: column; gap: 0.5rem; }
        .sidebar-nav a {
            text-decoration: none; color: #2c3e50; padding: 0.8rem 1rem; border-radius: 30px;
            display: flex; align-items: center; gap: 12px; transition: all 0.3s ease;
        }
        .sidebar-nav a i { width: 24px; color: var(--purple-primary); transition: transform 0.2s; }
        .sidebar-nav a:hover, .sidebar-nav a.active { background: rgba(106,27,154,0.1); color: var(--purple-primary); transform: translateX(5px); }
        .sidebar-nav a:hover i { transform: translateX(3px); }

        .main-content { flex: 1; }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
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
            cursor: pointer;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            background: rgba(255,255,255,0.9);
            box-shadow: 0 10px 25px -12px rgba(106,27,154,0.2);
        }
        .stat-icon {
            width: 48px;
            height: 48px;
            background: rgba(106,27,154,0.1);
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

        .section-header { display: flex; justify-content: space-between; align-items: center; margin: 2rem 0 1rem; flex-wrap: wrap; gap: 0.5rem; }
        .section-header h2 {
            font-size: 1.4rem;
            font-weight: 600;
            background: linear-gradient(145deg, var(--purple-primary), var(--orange-primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .view-all { color: var(--purple-primary); text-decoration: none; font-weight: 500; transition: all 0.2s; }
        .view-all:hover { color: var(--orange-primary); transform: translateX(3px); display: inline-block; }

        .appointments-list, .bookings-list {
            background: rgba(255,255,255,0.5);
            backdrop-filter: blur(4px);
            border-radius: 30px;
            padding: 1rem;
            transition: all 0.3s ease;
        }
        .appointments-list:hover, .bookings-list:hover { background: rgba(255,255,255,0.6); }
        .appointment-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            flex-wrap: wrap;
            gap: 1rem;
            transition: all 0.2s ease;
        }
        .appointment-item:hover { background: rgba(106,27,154,0.05); border-radius: 20px; transform: translateX(5px); }
        .appointment-item:last-child { border-bottom: none; }
        .appointment-info { flex: 1; }
        .appointment-info h3 { font-size: 1.1rem; margin-bottom: 0.25rem; color: var(--purple-primary); }
        .appointment-info p { font-size: 0.9rem; color: #2c3e50; }
        .status {
            background: #4caf50;
            color: white;
            padding: 0.25rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
        }
        .status.orange { background: var(--orange-primary); }
        .status.rescheduled { background: #9c27b0; }

        .btn-small {
            background: var(--purple-primary);
            color: white;
            border: none;
            padding: 0.4rem 1.2rem;
            border-radius: 30px;
            font-size: 0.9rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: all 0.2s ease;
        }
        .btn-small:hover { transform: translateY(-2px); opacity: 0.9; }
        .btn-small.orange { background: var(--orange-primary); }
        .btn-small.red { background: #f44336; }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .transactions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        .transaction-card {
            background: white;
            border-radius: 20px;
            padding: 1rem;
            box-shadow: 0 5px 10px rgba(0,0,0,0.02);
            transition: all 0.3s ease;
            border: 1px solid rgba(106,27,154,0.05);
        }
        .transaction-card:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(106,27,154,0.1); }
        .transaction-card .date { font-size: 0.8rem; color: #666; }
        .transaction-card .service { font-weight: 600; color: var(--purple-primary); }
        .transaction-card .amount { font-weight: 700; color: var(--purple-primary); margin-top: 0.5rem; }

        .app-footer {
            background: rgba(255,255,255,0.6);
            padding: 2rem;
            text-align: center;
            margin-top: auto;
        }

        .welcome-banner {
            background: linear-gradient(135deg, var(--purple-primary), var(--purple-dark));
            border-radius: 30px;
            padding: 1.5rem 2rem;
            margin-bottom: 2rem;
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
            animation: fadeInLeft 0.6s ease;
        }
        .welcome-banner h1 {
            font-size: 1.8rem;
            margin: 0;
            background: none;
            -webkit-text-fill-color: white;
        }
        .welcome-banner p { opacity: 0.9; margin-top: 0.3rem; }
        .welcome-icon { font-size: 3rem; animation: float 3s ease-in-out infinite; }

        @media (max-width: 900px) {
            .dashboard-layout { flex-direction: column; }
            .sidebar { width: 100%; }
        }
        
        @media (max-width: 768px) {
            .appointment-item { flex-direction: column; align-items: flex-start; }
            .action-buttons { width: 100%; justify-content: flex-start; }
            .btn-small { flex: 1; text-align: center; }
            .stats-grid { grid-template-columns: 1fr; }
            .welcome-banner { flex-direction: column; text-align: center; }
            .welcome-icon { font-size: 2.5rem; }
        }
        
        @media (max-width: 480px) {
            .appointment-item { padding: 0.8rem; }
            .appointment-info h3 { font-size: 1rem; }
            .action-buttons { flex-direction: column; width: 100%; }
            .btn-small { width: 100%; }
        }
        
        .qr-code-img {
            width: 80px;
            height: 80px;
            margin-top: 5px;
            border-radius: 10px;
            transition: transform 0.2s;
        }
        .qr-code-img:hover { transform: scale(1.05); }
        
        @media (max-width: 480px) { .qr-code-img { width: 60px; height: 60px; } }
		/* Mobile Navbar Fix */
@media (max-width: 768px) {
    .app-header {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 1000;
        background: rgba(255,255,255,0.95);
    }
    
    body {
        padding-top: 85px;
    }
}
    </style>
</head>
<body>
    <header class="app-header">
        <div class="logo-area">
            <img src="NoQ.jpg" alt="No Q" style="height: 85px; width: auto;">
            <div>
                <span class="logo-text">No Q</span>
                <div style="font-size: 0.7rem; color: var(--purple-primary); letter-spacing: 0.5px;">No more Queues</div>
            </div>
        </div>
        <button class="menu-toggle" id="menuToggle"><i class="fa-solid fa-bars"></i></button>
        <nav class="nav-links" id="navLinks">
            <a href="index.php">Home</a>
            <a href="contact.php">Contact</a>
            <a href="user-dashboard.php" style="background:rgba(106,27,154,0.1);">Dashboard</a>
            <?php
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$user_id]);
            $unread_count = $stmt->fetchColumn();
            ?>
            <a href="search.php">Book a wash</a>
            <a href="notifications.php"><i class="fa-regular fa-bell"></i> Notifications<?php if ($unread_count>0): ?><span style="background:var(--orange-primary); color:white; border-radius:20px; padding:2px 8px; margin-left:5px;"><?= $unread_count ?></span><?php endif; ?></a>
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
            <div style="margin-top: 2rem; text-align: center;">
                <a href="search.php" style="display: block; background: linear-gradient(135deg, var(--orange-primary), var(--orange-dark)); color: white; padding: 1rem; border-radius: 30px; text-decoration: none; font-weight: bold; box-shadow: 0 5px 15px rgba(255,152,0,0.3); transition: transform 0.2s;"><i class="fa-solid fa-plus-circle"></i> Book a Car Wash</a>
            </div>
        </aside>

        <main class="main-content">
            <div class="welcome-banner">
                <div>
                    <h1><?= $greeting ?>, <?= htmlspecialchars(explode(' ',$user_name)[0]) ?>! 👋</h1>
                    <p>Ready to give your car some shine today? Let's make it sparkle ✨</p>
                    <a href="search.php" class="btn-small orange" style="margin-top: 1rem; padding: 0.6rem 1.5rem; font-size: 1rem;"><i class="fa-solid fa-magnifying-glass"></i> Book a Car Wash</a>
                </div>
                <div class="welcome-icon"><i class="fa-solid fa-car"></i></div>
            </div>

            <?php
            $success_message = $_GET['success'] ?? '';
            $error_message = $_GET['error'] ?? '';
            if ($success_message == 'review_added'): ?>
                <div style="background:#e8f5e9; color:var(--purple-primary); padding:1rem; border-radius:30px; margin-bottom:1rem; animation:fadeInUp 0.5s ease;">
                    <i class="fa-solid fa-circle-check"></i> Thank you! Your review has been posted.
                </div>
            <?php elseif ($error_message == 'invalid_booking'): ?>
                <div style="background:#ffebee; color:#b71c1c; padding:1rem; border-radius:30px; margin-bottom:1rem; animation:fadeInUp 0.5s ease;">
                    <i class="fa-solid fa-circle-exclamation"></i> This booking cannot be rated.
                </div>
            <?php endif; ?>
            <?php if ($success_message == 'cancelled'): ?>
                <div style="background:#e8f5e9; color:var(--purple-primary); padding:1rem; border-radius:30px; margin-bottom:1rem; animation:fadeInUp 0.5s ease;">
                    <i class="fa-solid fa-circle-check"></i> Booking cancelled successfully.
                </div>
            <?php elseif ($error_message == 'invalid_cancellation'): ?>
                <div style="background:#ffebee; color:#b71c1c; padding:1rem; border-radius:30px; margin-bottom:1rem; animation:fadeInUp 0.5s ease;">
                    <i class="fa-solid fa-circle-exclamation"></i> This booking cannot be cancelled.
                </div>
            <?php elseif ($error_message == 'cancel_failed'): ?>
                <div style="background:#ffebee; color:#b71c1c; padding:1rem; border-radius:30px; margin-bottom:1rem; animation:fadeInUp 0.5s ease;">
                    <i class="fa-solid fa-circle-exclamation"></i> Failed to cancel booking.
                </div>
            <?php endif; ?>

            <?php if ($success_message == 'review_deleted'): ?>
                <div style="background:#e8f5e9; color:var(--purple-primary); padding:1rem; border-radius:30px; margin-bottom:1rem; animation:fadeInUp 0.5s ease;">
                    <i class="fa-solid fa-circle-check"></i> Your review has been deleted.
                </div>
            <?php elseif ($error_message == 'invalid_review'): ?>
                <div style="background:#ffebee; color:#b71c1c; padding:1rem; border-radius:30px; margin-bottom:1rem; animation:fadeInUp 0.5s ease;">
                    <i class="fa-solid fa-circle-exclamation"></i> Review not found.
                </div>
            <?php elseif ($error_message == 'delete_failed'): ?>
                <div style="background:#ffebee; color:#b71c1c; padding:1rem; border-radius:30px; margin-bottom:1rem; animation:fadeInUp 0.5s ease;">
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
                            <p style="font-size:0.9rem;"><strong>Code: <?= htmlspecialchars($booking['booking_code']) ?></strong></p>
                            <?php if ($booking['status'] == 'confirmed'): ?>
                                <img src="qr.php?code=<?= urlencode($booking['booking_code']) ?>" alt="QR Code" class="qr-code-img">
                            <?php endif; ?>
                        </div>
                        <div class="action-buttons">
                            <span class="status <?= $status_class ?>"><?= ucfirst($booking['status']) ?></span>
                            <?php if ($booking['status'] == 'confirmed' && ($booking['payment_status'] ?? 'pending') != 'paid'): ?>
                                <a href="payfast-pay.php?booking_id=<?= $booking['id'] ?>" class="btn-small" style="background:#4caf50;">Pay Now</a>
                            <?php endif; ?>
                            <?php if ($booking['status'] == 'pending' || $booking['status'] == 'confirmed'): ?>
                                <a href="reschedule.php?id=<?= $booking['id'] ?>" class="btn-small orange">Reschedule</a>
                                <a href="cancel-booking.php?id=<?= $booking['id'] ?>" class="btn-small red" onclick="return confirm('Are you sure you want to cancel this booking?')">Cancel</a>
                            <?php endif; ?>
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
                    <div class="action-buttons">
                        <a href="rate-booking.php?id=<?= $booking['id'] ?>" class="btn-small orange">Rate this wash</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Recent transactions -->
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:2rem; margin-top:2rem;">
                <div>
                    <div class="section-header"><h2>Recent transactions</h2><a href="transactions.php" class="view-all">View all →</a></div>
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
                                <tr style="background: rgba(106,27,154,0.1);">
                                    <th style="padding: 0.8rem; text-align: left; color: var(--purple-primary);">Car Wash</th>
                                    <th style="padding: 0.8rem; text-align: left; color: var(--purple-primary);">Washes</th>
                                    <th style="padding: 0.8rem; text-align: left; color: var(--purple-primary);">Total Spent</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($spending_by_business as $biz): ?>
                                <tr>
                                    <td style="padding: 0.8rem;"><?= htmlspecialchars($biz['business_name']) ?></td>
                                    <td style="padding: 0.8rem;"><?= $biz['washes_count'] ?></td>
                                    <td style="padding: 0.8rem; font-weight: 600; color: var(--purple-primary);">R <?= number_format($biz['total_spent'], 2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if (!empty($monthly_spending)): ?>
                    <div style="margin-top: 1.5rem;">
                        <h4 style="margin: 1rem 0 0.5rem; color: var(--purple-primary);">Monthly Spending (last 6 months)</h4>
                        <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                            <?php foreach ($monthly_spending as $month): ?>
                            <div style="background: rgba(106,27,154,0.05); border-radius: 20px; padding: 0.5rem 1rem; transition: all 0.2s;">
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
                <h2>My Favourites</h2>
                <a href="my-favorites.php" class="view-all">View all →</a>
            </div>
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px,1fr)); gap:1rem;">
                <?php foreach ($favorites as $biz): ?>
                <div style="background:white; border-radius:24px; padding:1.2rem; transition: all 0.3s ease; cursor: pointer; border: 1px solid rgba(106,27,154,0.05);" onmouseenter="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 10px 20px rgba(106,27,154,0.1)'" onmouseleave="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                    <h3 style="font-size:1.1rem; color: var(--purple-primary);"><?= htmlspecialchars($biz['name']) ?></h3>
                    <p style="font-size:0.9rem; color:#555;">⭐ <?= number_format($biz['rating_avg'],1) ?> · <?= htmlspecialchars(explode(',',$biz['address'])[0]) ?></p>
                    <button class="btn-small" style="margin-top:0.5rem; width:100%;" onclick="window.location.href='book.php?id=<?= $biz['id'] ?>'">Book</button>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($recommended)): ?>
            <div class="section-header"><h2>Recommended for you</h2><a href="search.php" class="view-all">View all →</a></div>
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px,1fr)); gap:1rem;">
                <?php foreach ($recommended as $biz): ?>
                <div style="background:white; border-radius:24px; padding:1.2rem; transition: all 0.3s ease; cursor: pointer; border: 1px solid rgba(106,27,154,0.05);" onmouseenter="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 10px 20px rgba(106,27,154,0.1)'" onmouseleave="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                    <h3 style="font-size:1.1rem; color: var(--purple-primary);"><?= htmlspecialchars($biz['name']) ?></h3>
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
                        <div class="action-buttons">
                            <a href="delete-review.php?id=<?= $review['id'] ?>" class="btn-small red" onclick="return confirm('Delete this review?')">Delete</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <footer class="app-footer">
        <p>&copy; <?= date('Y'); ?> No Q. All rights reserved.</p>
        <p>Powered by <a href="https://www.jaekerna.com/" target="_blank" style="color:var(--purple-primary);">Jaekerna Investments</a></p>
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
        
        document.querySelectorAll('.stat-card').forEach(card => {
            card.addEventListener('click', function() {
                this.style.transform = 'scale(0.95)';
                setTimeout(() => { this.style.transform = ''; }, 200);
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
