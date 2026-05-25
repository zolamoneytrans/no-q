<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: user-login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'upcoming';

// Fetch all bookings for this user
$query = "
    SELECT b.*, biz.name as business_name, biz.address, s.name as service_name, s.price
    FROM bookings b
    JOIN businesses biz ON b.business_id = biz.id
    LEFT JOIN services s ON b.service_id = s.id
    WHERE b.user_id = ?
";

// Apply filter
if ($filter == 'upcoming') {
    $query .= " AND b.booking_date >= CURDATE() AND b.status IN ('pending', 'confirmed', 'rescheduled')";
} elseif ($filter == 'pending') {
    $query .= " AND b.status = 'pending'";
} elseif ($filter == 'confirmed') {
    $query .= " AND b.status = 'confirmed'";
} elseif ($filter == 'rescheduled') {
    $query .= " AND b.status = 'rescheduled'";
} elseif ($filter == 'completed') {
    $query .= " AND b.status = 'completed'";
} elseif ($filter == 'cancelled') {
    $query .= " AND b.status = 'cancelled'";
} elseif ($filter == 'past') {
    $query .= " AND (b.booking_date < CURDATE() OR b.status IN ('completed', 'cancelled'))";
}

$query .= " ORDER BY b.booking_date DESC, b.time_slot DESC";

$stmt = $pdo->prepare($query);
$stmt->execute([$user_id]);
$bookings = $stmt->fetchAll();

// Get counts for each status
$status_counts = [
    'upcoming' => 0,
    'pending' => 0,
    'confirmed' => 0,
    'rescheduled' => 0,
    'completed' => 0,
    'cancelled' => 0
];

$count_stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM bookings WHERE user_id = ? GROUP BY status");
$count_stmt->execute([$user_id]);
$counts = $count_stmt->fetchAll();

foreach ($counts as $c) {
    $status_counts[$c['status']] = $c['count'];
}

// Upcoming count = pending + confirmed + rescheduled with future dates
$upcoming_stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = ? AND booking_date >= CURDATE() AND status IN ('pending', 'confirmed', 'rescheduled')");
$upcoming_stmt->execute([$user_id]);
$status_counts['upcoming'] = $upcoming_stmt->fetchColumn();
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
    <title>My Bookings · No Q</title>
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
        .nav-links { display: flex; gap: 1.2rem; align-items: center; }
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
            color: var(--purple-primary);
            background: transparent;
            border: none;
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
                align-items: center;
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

        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
            flex: 1;
        }
        h1 {
            font-size: 2rem;
            margin-bottom: 1.5rem;
            background: linear-gradient(145deg, var(--purple-primary), var(--orange-primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        /* Filter Tabs */
        .filter-tabs {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-bottom: 2rem;
        }
        .filter-tab {
            padding: 0.6rem 1.5rem;
            border-radius: 40px;
            background: rgba(255,255,255,0.5);
            color: #2c3e50;
            text-decoration: none;
            font-weight: 500;
            transition: 0.2s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .filter-tab:hover {
            background: rgba(255,255,255,0.8);
        }
        .filter-tab.active {
            background: var(--purple-primary);
            color: white;
        }
        .filter-tab .count {
            background: rgba(0,0,0,0.1);
            padding: 0.2rem 0.6rem;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        .filter-tab.active .count {
            background: rgba(255,255,255,0.2);
        }

        /* Bookings List */
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
            padding: 1.5rem;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            flex-wrap: wrap;
            gap: 1rem;
        }
        .booking-item:last-child {
            border-bottom: none;
        }
        .booking-info h3 {
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
            color: var(--purple-primary);
        }
        .booking-info p {
            margin-bottom: 0.3rem;
            color: #2c3e50;
            font-size: 0.95rem;
        }
        .booking-info i {
            width: 20px;
            color: var(--purple-primary);
        }
        .booking-code {
            font-weight: 600;
            color: var(--purple-primary);
            background: rgba(106,27,154,0.1);
            padding: 0.2rem 0.8rem;
            border-radius: 20px;
            display: inline-block;
            margin-top: 0.3rem;
        }
        .booking-status {
            display: inline-block;
            padding: 0.3rem 1rem;
            border-radius: 30px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .status-pending { background: var(--orange-primary); color: white; }
        .status-confirmed { background: #4caf50; color: white; }
        .status-completed { background: #2196f3; color: white; }
        .status-rescheduled { background: #9c27b0; color: white; }
        .status-cancelled { background: #f44336; color: white; }
        
        .booking-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .btn-small {
            background: linear-gradient(135deg, var(--purple-primary), var(--purple-dark));
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 30px;
            font-size: 0.9rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s ease;
        }
        .btn-small:hover {
            transform: translateY(-2px);
            opacity: 0.9;
        }
        .btn-small.orange { background: var(--orange-primary); }
        .btn-small.red { background: #f44336; }
        .btn-small.green { background: #4caf50; }
        .no-bookings {
            text-align: center;
            padding: 3rem;
            color: #2c3e50;
        }
        .no-bookings i {
            font-size: 3rem;
            color: var(--purple-primary);
            margin-bottom: 1rem;
            opacity: 0.5;
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
        .qr-code {
            margin-top: 0.5rem;
            display: inline-block;
        }
        .qr-code img {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            background: white;
            padding: 4px;
        }
        @media (max-width: 768px) {
            .booking-item {
                flex-direction: column;
                align-items: flex-start;
            }
            .booking-actions {
                width: 100%;
                justify-content: flex-start;
            }
            .container {
                margin: 1rem auto;
                padding: 0 1rem;
            }
            h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <header class="app-header">
        <div class="logo-area">
            <img src="NoQ.jpg" alt="No Q" style="height: 70px; width: auto;">
            <div>
                <span class="logo-text">No Q</span>
                <div style="font-size: 0.7rem; color: var(--purple-primary); letter-spacing: 0.5px;">No more Queues</div>
            </div>
        </div>
        <button class="menu-toggle" id="menuToggle" aria-label="Menu">
            <i class="fa-solid fa-bars"></i>
        </button>
        <nav class="nav-links" id="navLinks">
            <a href="index.php">Home</a>
            <a href="user-dashboard.php">Dashboard</a>
            <a href="my-bookings.php" style="background:rgba(106,27,154,0.1);">My Bookings</a>
            <a href="logout.php">Logout</a>
        </nav>
    </header>

    <div class="container">
        <h1>My Bookings</h1>

        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <a href="?filter=upcoming" class="filter-tab <?= $filter == 'upcoming' ? 'active' : '' ?>">
                Upcoming <span class="count"><?= $status_counts['upcoming'] ?></span>
            </a>
            <a href="?filter=pending" class="filter-tab <?= $filter == 'pending' ? 'active' : '' ?>">
                Pending <span class="count"><?= $status_counts['pending'] ?></span>
            </a>
            <a href="?filter=confirmed" class="filter-tab <?= $filter == 'confirmed' ? 'active' : '' ?>">
                Confirmed <span class="count"><?= $status_counts['confirmed'] ?></span>
            </a>
            <a href="?filter=rescheduled" class="filter-tab <?= $filter == 'rescheduled' ? 'active' : '' ?>">
                Rescheduled <span class="count"><?= $status_counts['rescheduled'] ?></span>
            </a>
            <a href="?filter=completed" class="filter-tab <?= $filter == 'completed' ? 'active' : '' ?>">
                Completed <span class="count"><?= $status_counts['completed'] ?></span>
            </a>
            <a href="?filter=cancelled" class="filter-tab <?= $filter == 'cancelled' ? 'active' : '' ?>">
                Cancelled <span class="count"><?= $status_counts['cancelled'] ?></span>
            </a>
            <a href="?filter=past" class="filter-tab <?= $filter == 'past' ? 'active' : '' ?>">
                Past Bookings
            </a>
        </div>

        <!-- Bookings List -->
        <div class="bookings-list">
            <?php if (empty($bookings)): ?>
                <div class="no-bookings">
                    <i class="fa-regular fa-calendar-xmark"></i>
                    <h3>No bookings found</h3>
                    <p>Try a different filter or <a href="search.php" style="color: var(--purple-primary);">book a car wash</a></p>
                </div>
            <?php else: ?>
                <?php foreach ($bookings as $b): ?>
                <div class="booking-item">
                    <div class="booking-info">
                        <h3><?= htmlspecialchars($b['business_name']) ?></h3>
                        <p><i class="fa-regular fa-location-dot"></i> <?= htmlspecialchars($b['address']) ?></p>
                        <p><i class="fa-regular fa-calendar"></i> <?= date('l, d F Y', strtotime($b['booking_date'])) ?></p>
                        <p><i class="fa-regular fa-clock"></i> <?= htmlspecialchars($b['time_slot']) ?></p>
                        <p><i class="fa-regular fa-broom"></i> <?= htmlspecialchars($b['service_name'] ?? 'Car wash') ?> – R <?= number_format($b['total_amount'], 2) ?></p>
                        <div class="booking-code">
                            <i class="fa-regular fa-tag"></i> Code: <?= htmlspecialchars($b['booking_code']) ?>
                        </div>
                        <?php if ($b['status'] == 'confirmed'): ?>
                            <div class="qr-code">
                                <img src="qr.php?code=<?= urlencode($b['booking_code']) ?>" alt="QR Code">
                            </div>
                        <?php endif; ?>
                    </div>
                    <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 0.5rem;">
                        <div class="booking-status status-<?= $b['status'] ?>">
                            <?= ucfirst($b['status']) ?>
                        </div>
                        <div class="booking-actions">
                            <?php if ($b['status'] == 'pending' || $b['status'] == 'confirmed' || $b['status'] == 'rescheduled'): ?>
                                <a href="reschedule.php?id=<?= $b['id'] ?>" class="btn-small orange">Reschedule</a>
                                <a href="cancel-booking.php?id=<?= $b['id'] ?>" class="btn-small red" onclick="return confirm('Cancel this booking?')">Cancel</a>
                            <?php endif; ?>
                            
                            <?php if ($b['status'] == 'confirmed' && ($b['payment_status'] ?? 'pending') != 'paid'): ?>
                                <a href="payfast-pay.php?booking_id=<?= $b['id'] ?>" class="btn-small green">Pay Now</a>
                            <?php endif; ?>
                            
                            <?php if ($b['status'] == 'completed'): ?>
                                <?php
                                // Check if already rated
                                $rated = $pdo->prepare("SELECT id FROM ratings WHERE booking_id = ?");
                                $rated->execute([$b['id']]);
                                if (!$rated->fetch()):
                                ?>
                                <a href="rate-booking.php?id=<?= $b['id'] ?>" class="btn-small orange">Rate</a>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <a href="booking-details.php?id=<?= $b['id'] ?>" class="btn-small">Details</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <footer class="app-footer">
        <p>&copy; <?= date('Y'); ?> No Q. All rights reserved.</p>
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
                    if (navLinks) navLinks.classList.remove('show');
                });
            });
        });
    </script>
</body>
</html>
