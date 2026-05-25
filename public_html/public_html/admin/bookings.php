<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin-login.php');
    exit;
}

// Get filter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$query = "
    SELECT b.*, 
           u.name as user_name, u.email as user_email,
           biz.name as business_name, biz.email as business_email
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN businesses biz ON b.business_id = biz.id
    WHERE 1=1
";
$params = [];

if ($filter != 'all') {
    $query .= " AND b.status = ?";
    $params[] = $filter;
}

if (!empty($search)) {
    $query .= " AND (b.booking_code LIKE ? OR u.name LIKE ? OR biz.name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$query .= " ORDER BY b.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

// Get counts
$counts = [];
$statuses = ['pending', 'confirmed', 'completed', 'cancelled', 'rescheduled'];
foreach ($statuses as $s) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE status = ?");
    $stmt->execute([$s]);
    $counts[$s] = $stmt->fetchColumn();
}
$counts['all'] = array_sum($counts);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>All Bookings · Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(145deg, #faf5ff 0%, #f3e8ff 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .app-header {
            background: rgba(255,255,255,0.85);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255,255,255,0.5);
            padding: 0.8rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .logo-area { display: flex; align-items: center; gap: 10px; }
        .logo-text { font-weight: 700; font-size: 1.5rem; background: linear-gradient(135deg, #6d28d9, #8b5cf6); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .nav-links { display: flex; gap: 1.2rem; align-items: center; flex-wrap: wrap; }
        .nav-links a { text-decoration: none; font-weight: 500; color: #5b21b6; padding: 0.5rem 0.8rem; border-radius: 40px; transition: 0.2s; }
        .nav-links a:hover { background: rgba(139,92,246,0.1); color: #6d28d9; }
        .menu-toggle {
            display: none;
            font-size: 1.8rem;
            cursor: pointer;
            background: transparent;
            border: none;
            color: #6d28d9;
            padding: 0.5rem;
        }
        
        @media (max-width: 768px) {
            .menu-toggle { display: block; }
            .app-header { padding: 0.8rem 1rem; }
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
                z-index: 200;
            }
            .nav-links.show { display: flex; }
            .nav-links a { width: 100%; text-align: center; padding: 0.8rem; border-radius: 30px; }
        }
        
        .container { max-width: 1400px; margin: 2rem auto; padding: 0 2rem; flex: 1; }
        h1 { font-size: 1.8rem; margin-bottom: 1rem; color: #6d28d9; display: flex; align-items: center; gap: 0.5rem; }
        
        .filter-tabs {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
        }
        .filter-tab {
            padding: 0.5rem 1.2rem;
            border-radius: 40px;
            background: rgba(255,255,255,0.7);
            color: #5b21b6;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.85rem;
            transition: 0.2s;
        }
        .filter-tab.active { background: #8b5cf6; color: white; }
        .filter-tab .count { margin-left: 0.3rem; font-size: 0.7rem; opacity: 0.8; }
        
        .search-box { margin-bottom: 1.5rem; }
        .search-form {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .search-form input {
            flex: 1;
            padding: 0.8rem 1.2rem;
            border: none;
            border-radius: 40px;
            background: #edb088;
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem;
        }
        .search-form button, .search-form .btn-clear {
            background: #8b5cf6;
            color: white;
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: 40px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .search-form .btn-clear { background: #7c3aed; }
        
        .stats-grid {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
        }
        .stat-card {
            background: rgba(255,255,255,0.85);
            backdrop-filter: blur(8px);
            border-radius: 24px;
            padding: 0.8rem 1.2rem;
            flex: 1;
            min-width: 100px;
            text-align: center;
            border: 1px solid rgba(255,255,255,0.6);
        }
        .stat-card .number { font-size: 1.5rem; font-weight: 800; color: #6d28d9; }
        .stat-card .label { font-size: 0.7rem; color: #5b21b6; }
        
        .bookings-grid {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .booking-card {
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(8px);
            border-radius: 24px;
            padding: 1.2rem;
            border: 1px solid rgba(255,255,255,0.6);
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        .booking-code {
            font-family: monospace;
            font-size: 0.9rem;
            font-weight: 700;
            background: rgba(139,92,246,0.1);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            color: #6d28d9;
        }
        .status-badge {
            padding: 0.25rem 1rem;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
        }
        .status-pending { background: #f97316; color: white; }
        .status-confirmed { background: #8b5cf6; color: white; }
        .status-completed { background: #22c55e; color: white; }
        .status-cancelled { background: #ef4444; color: white; }
        .status-rescheduled { background: #a855f7; color: white; }
        
        .card-body { display: flex; flex-direction: column; gap: 0.75rem; }
        .info-row { display: flex; flex-wrap: wrap; align-items: baseline; gap: 0.5rem; }
        .info-label { font-size: 0.7rem; font-weight: 600; text-transform: uppercase; color: #7c3aed; min-width: 85px; }
        .info-value { font-size: 0.9rem; color: #1a2639; }
        .amount { font-weight: 700; color: #6d28d9; }
        
        .desktop-table {
            display: none;
            width: 100%;
            border-collapse: collapse;
            background: rgba(255,255,255,0.7);
            backdrop-filter: blur(8px);
            border-radius: 30px;
            overflow: hidden;
        }
        .desktop-table th, .desktop-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        .desktop-table th { background: rgba(139,92,246,0.1); color: #6d28d9; font-weight: 600; }
        
        @media (min-width: 992px) {
            .bookings-grid { display: none; }
            .desktop-table { display: table; }
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            background: rgba(255,255,255,0.8);
            border-radius: 30px;
            color: #7c3aed;
        }
        .empty-state i { font-size: 3rem; margin-bottom: 1rem; opacity: 0.5; }
        
        .app-footer {
            background: rgba(255,255,255,0.7);
            padding: 1.5rem;
            text-align: center;
            margin-top: 2rem;
            font-size: 0.8rem;
            color: #7c3aed;
        }
        
        @media (max-width: 768px) {
            .container { padding: 0 1rem; margin: 1rem auto; }
            h1 { font-size: 1.4rem; }
            .filter-tab { font-size: 0.75rem; padding: 0.4rem 0.8rem; }
            .search-form { flex-direction: column; }
            .search-form button, .search-form .btn-clear { justify-content: center; }
            .stats-grid { gap: 0.5rem; }
            .stat-card .number { font-size: 1.2rem; }
        }
    </style>
</head>
<body>
    <header class="app-header">
        <div class="logo-area">
            <img src="/NoQ.jpg" alt="No Q" style="height: 85px; width: auto;">
            <div>
                <span class="logo-text">No Q</span>
                <div style="font-size: 0.7rem; color: #5b21b6; letter-spacing: 0.5px;">No more Queues</div>
            </div>
        </div>
        <button class="menu-toggle" id="menuToggle"><i class="fa-solid fa-bars"></i></button>
        <nav class="nav-links" id="navLinks">
            <a href="admin-dashboard.php">Dashboard</a>
            <a href="users.php">Users</a>
            <a href="businesses.php">Businesses</a>
            <a href="bookings.php" style="background:rgba(139,92,246,0.1);">Bookings</a>
            <a href="payments.php">Payments</a>
            <a href="withdrawals.php">Withdrawals</a>
            <a href="verify-banks.php">Verify Banks</a>
            <a href="reports.php">Reports</a>
            <a href="admin-logout.php">Logout</a>
        </nav>
    </header>

    <div class="container">
        <h1><i class="fa-regular fa-calendar-check"></i> All Bookings</h1>

        <div class="stats-grid">
            <div class="stat-card"><div class="number"><?= $counts['all'] ?></div><div class="label">Total</div></div>
            <div class="stat-card"><div class="number"><?= $counts['pending'] ?></div><div class="label">Pending</div></div>
            <div class="stat-card"><div class="number"><?= $counts['confirmed'] ?></div><div class="label">Confirmed</div></div>
            <div class="stat-card"><div class="number"><?= $counts['completed'] ?></div><div class="label">Completed</div></div>
        </div>

        <div class="filter-tabs">
            <a href="?filter=all" class="filter-tab <?= $filter == 'all' ? 'active' : '' ?>">All <span class="count">(<?= $counts['all'] ?>)</span></a>
            <a href="?filter=pending" class="filter-tab <?= $filter == 'pending' ? 'active' : '' ?>">Pending <span class="count">(<?= $counts['pending'] ?>)</span></a>
            <a href="?filter=confirmed" class="filter-tab <?= $filter == 'confirmed' ? 'active' : '' ?>">Confirmed <span class="count">(<?= $counts['confirmed'] ?>)</span></a>
            <a href="?filter=completed" class="filter-tab <?= $filter == 'completed' ? 'active' : '' ?>">Completed <span class="count">(<?= $counts['completed'] ?>)</span></a>
            <a href="?filter=cancelled" class="filter-tab <?= $filter == 'cancelled' ? 'active' : '' ?>">Cancelled <span class="count">(<?= $counts['cancelled'] ?>)</span></a>
            <a href="?filter=rescheduled" class="filter-tab <?= $filter == 'rescheduled' ? 'active' : '' ?>">Rescheduled <span class="count">(<?= $counts['rescheduled'] ?>)</span></a>
        </div>

        <div class="search-box">
            <form method="GET" class="search-form">
                <input type="hidden" name="filter" value="<?= $filter ?>">
                <input type="text" name="search" placeholder="Search by booking code, user, or business..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
                <?php if ($search): ?>
                    <a href="?filter=<?= $filter ?>" class="btn-clear"><i class="fa-solid fa-times"></i> Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if (empty($bookings)): ?>
            <div class="empty-state">
                <i class="fa-regular fa-calendar-xmark"></i>
                <p>No bookings found.</p>
            </div>
        <?php else: ?>
            
            <table class="desktop-table">
                <thead>
                    <tr><th>ID</th><th>Code</th><th>User</th><th>Business</th><th>Date</th><th>Time</th><th>Amount</th><th>Status</th><th>Booked</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $b): ?>
                    <tr>
                        <td><?= $b['id'] ?></td>
                        <td><?= htmlspecialchars($b['booking_code']) ?></td>
                        <td><?= htmlspecialchars($b['user_name']) ?><br><small><?= htmlspecialchars($b['user_email']) ?></small></td>
                        <td><?= htmlspecialchars($b['business_name']) ?></td>
                        <td><?= $b['booking_date'] ?></td>
                        <td><?= $b['time_slot'] ?></td>
                        <td>R <?= number_format($b['total_amount'], 2) ?></td>
                        <td><span class="status-badge status-<?= $b['status'] ?>"><?= ucfirst($b['status']) ?></span></td>
                        <td><?= date('d M Y', strtotime($b['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="bookings-grid">
                <?php foreach ($bookings as $b): ?>
                <div class="booking-card">
                    <div class="card-header">
                        <span class="booking-code"><i class="fa-solid fa-qrcode"></i> <?= htmlspecialchars($b['booking_code']) ?></span>
                        <span class="status-badge status-<?= $b['status'] ?>"><?= ucfirst($b['status']) ?></span>
                    </div>
                    <div class="card-body">
                        <div class="info-row"><span class="info-label">User</span><span class="info-value"><strong><?= htmlspecialchars($b['user_name']) ?></strong><br><small><?= htmlspecialchars($b['user_email']) ?></small></span></div>
                        <div class="info-row"><span class="info-label">Business</span><span class="info-value"><?= htmlspecialchars($b['business_name']) ?></span></div>
                        <div class="info-row"><span class="info-label">Date/Time</span><span class="info-value"><?= $b['booking_date'] ?> at <?= $b['time_slot'] ?></span></div>
                        <div class="info-row"><span class="info-label">Amount</span><span class="info-value amount">R <?= number_format($b['total_amount'], 2) ?></span></div>
                        <div class="info-row"><span class="info-label">Booked on</span><span class="info-value"><?= date('d M Y', strtotime($b['created_at'])) ?></span></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
        <?php endif; ?>
    </div>

    <footer class="app-footer">
        <p>&copy; <?= date('Y'); ?> No Q · Admin Panel</p>
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
</body>
</html>