<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin-login.php');
    exit;
}

$admin_name = $_SESSION['admin_name'];

$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_businesses = $pdo->query("SELECT COUNT(*) FROM businesses")->fetchColumn();
$pending_businesses = $pdo->query("SELECT COUNT(*) FROM businesses WHERE is_approved = 0")->fetchColumn();
$total_bookings = $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
$total_revenue = $pdo->query("SELECT SUM(amount) FROM payments WHERE status = 'completed'")->fetchColumn() ?: 0;
$total_payments = $pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'completed'")->fetchColumn();
$pending_withdrawals = $pdo->query("SELECT COUNT(*) FROM withdrawal_requests WHERE status = 'pending'")->fetchColumn();
$pending_withdrawals_amount = $pdo->query("SELECT SUM(amount) FROM withdrawal_requests WHERE status = 'pending'")->fetchColumn() ?: 0;

// Subscription stats
$low_plan_count = $pdo->query("SELECT COUNT(*) FROM businesses WHERE subscription_plan = 'low'")->fetchColumn();
$medium_plan_count = $pdo->query("SELECT COUNT(*) FROM businesses WHERE subscription_plan = 'medium'")->fetchColumn();
$high_plan_count = $pdo->query("SELECT COUNT(*) FROM businesses WHERE subscription_plan = 'high'")->fetchColumn();

// Total booking fees collected (app's 34% share)
$stmt = $pdo->query("SELECT SUM(booking_fee) FROM payments WHERE status = 'completed'");
$total_booking_fees = $stmt->fetchColumn() ?: 0;
$app_share = $total_booking_fees * 0.34;

// Monthly revenue (last 12 months)
$monthly_revenue = [];
$month_labels = [];
for ($i = 0; $i < 12; $i++) {
    $month = date('Y-m', strtotime("-$i months"));
    $month_labels[] = date('M Y', strtotime($month));
    $stmt = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE status = 'completed' AND DATE_FORMAT(created_at, '%Y-%m') = ?");
    $stmt->execute([$month]);
    $monthly_revenue[] = round($stmt->fetchColumn() ?: 0, 2);
}
$monthly_revenue = array_reverse($monthly_revenue);
$month_labels = array_reverse($month_labels);

// Monthly bookings (last 12 months)
$monthly_bookings = [];
for ($i = 0; $i < 12; $i++) {
    $month = date('Y-m', strtotime("-$i months"));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE DATE_FORMAT(created_at, '%Y-%m') = ?");
    $stmt->execute([$month]);
    $monthly_bookings[] = $stmt->fetchColumn();
}
$monthly_bookings = array_reverse($monthly_bookings);

// Recent activity
$recent_bookings = $pdo->query("
    SELECT b.*, u.name as user_name, biz.name as business_name 
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN businesses biz ON b.business_id = biz.id
    ORDER BY b.created_at DESC LIMIT 5
")->fetchAll();

$recent_payments = $pdo->query("
    SELECT p.*, u.name as user_name, biz.name as business_name
    FROM payments p
    JOIN users u ON p.user_id = u.id
    JOIN bookings bk ON p.booking_id = bk.id
    JOIN businesses biz ON bk.business_id = biz.id
    ORDER BY p.created_at DESC LIMIT 5
")->fetchAll();

$recent_withdrawals = $pdo->query("
    SELECT w.*, b.name as business_name
    FROM withdrawal_requests w
    JOIN businesses b ON w.business_id = b.id
    ORDER BY w.requested_at DESC LIMIT 5
")->fetchAll();

$stmt = $pdo->query("SELECT * FROM businesses WHERE is_approved = 0 ORDER BY created_at DESC LIMIT 5");
$pending_list = $stmt->fetchAll();

// Business subscription plans data
$stmt = $pdo->query("
    SELECT id, name, email, subscription_plan, wallet_balance, is_active 
    FROM businesses 
    ORDER BY subscription_plan, name
    LIMIT 10
");
$sub_businesses = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Admin Dashboard · No Q</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(145deg, #faf5ff 0%, #f3e8ff 100%);
            min-height: 100vh;
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
        .logo-icon { background: #6d28d9; color: white; width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        .logo-text { font-weight: 700; font-size: 1.5rem; background: linear-gradient(135deg, #6d28d9, #8b5cf6); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .nav-links { display: flex; gap: 1.2rem; align-items: center; flex-wrap: wrap; }
        .nav-links a { text-decoration: none; font-weight: 500; color: #5b21b6; padding: 0.5rem 0.8rem; border-radius: 40px; transition: all 0.2s; }
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
            .nav-links { display: none; width: 100%; flex-direction: column; background: rgba(255,255,255,0.98); border-radius: 30px; padding: 1rem; margin-top: 1rem; }
            .nav-links.show { display: flex; }
            .app-header { padding: 0.8rem 1rem; }
            .nav-links a { width: 100%; text-align: center; padding: 0.8rem; }
        }
        .container { max-width: 1400px; margin: 2rem auto; padding: 0 2rem; }
        .welcome { margin-bottom: 2rem; }
        .welcome h1 { font-size: 2rem; color: #6d28d9; }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: rgba(255,255,255,0.85);
            backdrop-filter: blur(8px);
            border-radius: 24px;
            padding: 1.2rem;
            text-align: center;
            border: 1px solid rgba(255,255,255,0.6);
            transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-3px); }
        .stat-card .value { font-size: 1.8rem; font-weight: 700; color: #6d28d9; }
        .stat-card .label { font-size: 0.8rem; color: #5b21b6; }
        .chart-container {
            background: rgba(255,255,255,0.7);
            backdrop-filter: blur(4px);
            border-radius: 30px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(255,255,255,0.5);
        }
        canvas { max-height: 300px; width: 100%; }
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 2rem 0 1rem;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .section-header h2 {
            font-size: 1.3rem;
            font-weight: 600;
            color: #6d28d9;
        }
        .card-table {
            background: rgba(255,255,255,0.7);
            backdrop-filter: blur(4px);
            border-radius: 30px;
            padding: 1rem;
            overflow-x: auto;
        }
        table { width: 100%; border-collapse: collapse; min-width: 500px; }
        th, td { padding: 0.8rem; text-align: left; border-bottom: 1px solid rgba(0,0,0,0.05); }
        th { color: #6d28d9; font-weight: 600; background: rgba(109,40,217,0.08); }
        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
        }
        .status-pending { background: #f97316; color: white; }
        .status-confirmed { background: #8b5cf6; color: white; }
        .status-completed { background: #22c55e; color: white; }
        .status-cancelled { background: #ef4444; color: white; }
        .btn-approve { background: #22c55e; color: white; padding: 0.3rem 0.8rem; border-radius: 30px; text-decoration: none; font-size: 0.8rem; display: inline-block; }
        .btn-reject { background: #ef4444; color: white; padding: 0.3rem 0.8rem; border-radius: 30px; text-decoration: none; font-size: 0.8rem; }
        .view-all { color: #8b5cf6; text-decoration: none; font-size: 0.9rem; font-weight: 500; }
        .view-all:hover { text-decoration: underline; }
        .app-footer { background: rgba(255,255,255,0.7); padding: 2rem; text-align: center; margin-top: 2rem; color: #7c3aed; }
        .notification-badge {
            background: #f97316;
            color: white;
            border-radius: 20px;
            padding: 2px 8px;
            font-size: 0.7rem;
            margin-left: 5px;
        }
        
        /* ========== MOBILE CARD STYLES - NO TABLES ON MOBILE ========== */
        .mobile-cards-container {
            display: none;
        }
        
        .desktop-table-view {
            display: block;
        }
        
        @media (max-width: 768px) {
            .container { padding: 0 1rem; }
            .welcome h1 { font-size: 1.5rem; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 0.8rem; }
            .stat-card { padding: 0.8rem; }
            .stat-card .value { font-size: 1.3rem; }
            .stat-card .label { font-size: 0.7rem; }
            .section-header h2 { font-size: 1.1rem; }
            
            /* HIDE ALL DESKTOP TABLES ON MOBILE */
            .desktop-table-view {
                display: none !important;
            }
            
            /* SHOW MOBILE CARDS */
            .mobile-cards-container {
                display: flex;
                flex-direction: column;
                gap: 1rem;
                margin-bottom: 1.5rem;
            }
            
            .mobile-data-card {
                background: white;
                border-radius: 24px;
                padding: 1rem;
                box-shadow: 0 4px 12px rgba(0,0,0,0.08);
                border: 1px solid rgba(139,92,246,0.15);
            }
            
            .card-header-row {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 0.8rem;
                padding-bottom: 0.5rem;
                border-bottom: 2px solid rgba(109,40,217,0.1);
            }
            
            .card-title {
                font-weight: 700;
                color: #6d28d9;
                font-size: 1rem;
            }
            
            .card-badge {
                padding: 4px 10px;
                border-radius: 20px;
                font-size: 0.7rem;
                font-weight: 600;
                color: white;
            }
            
            .card-detail-row {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 0.6rem 0;
                border-bottom: 1px solid rgba(0,0,0,0.05);
            }
            
            .card-detail-row:last-child {
                border-bottom: none;
            }
            
            .detail-label {
                font-weight: 600;
                color: #5b21b6;
                font-size: 0.8rem;
                display: flex;
                align-items: center;
                gap: 6px;
            }
            
            .detail-label i {
                width: 20px;
                color: #8b5cf6;
            }
            
            .detail-value {
                color: #1f2937;
                font-weight: 500;
                font-size: 0.85rem;
                text-align: right;
                word-break: break-word;
                max-width: 60%;
            }
            
            .amount-highlight {
                font-weight: 700;
                color: #6d28d9;
            }
            
            .card-action {
                margin-top: 0.8rem;
                padding-top: 0.5rem;
                text-align: right;
                display: flex;
                gap: 0.5rem;
                justify-content: flex-end;
                flex-wrap: wrap;
            }
            
            .card-action a {
                color: #8b5cf6;
                text-decoration: none;
                font-size: 0.75rem;
                font-weight: 600;
                padding: 0.4rem 0.8rem;
                border-radius: 30px;
                background: rgba(139,92,246,0.1);
                display: inline-block;
            }
            
            .card-action a.btn-approve-card {
                background: #22c55e;
                color: white;
            }
            
            .card-action a.btn-reject-card {
                background: #ef4444;
                color: white;
            }
            
            .booking-code {
                font-family: monospace;
                font-weight: 700;
                background: #f3e8ff;
                padding: 2px 8px;
                border-radius: 20px;
                font-size: 0.75rem;
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
            .detail-label { min-width: 90px; }
            .detail-value { font-size: 0.75rem; }
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
            <a href="admin-dashboard.php" style="background:rgba(139,92,246,0.1);">Dashboard</a>
            <a href="users.php">Users</a>
            <a href="businesses.php">Businesses</a>
            <a href="bookings.php">Bookings</a>
            <a href="payments.php">Payments</a>
            <a href="withdrawals.php">Withdrawals <?php if ($pending_withdrawals > 0): ?><span class="notification-badge"><?= $pending_withdrawals ?></span><?php endif; ?></a>
            <a href="verify-banks.php">Verify Banks</a>
            <a href="reports.php">Reports</a>
            <a href="admin-logout.php">Logout</a>
        </nav>
    </header>

    <div class="container">
        <div class="welcome">
            <h1>Welcome, <?= htmlspecialchars($admin_name) ?> 👋</h1>
            <p style="color:#5b21b6;">Platform overview</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card"><div class="value"><?= $total_users ?></div><div class="label">Users</div></div>
            <div class="stat-card"><div class="value"><?= $total_businesses ?></div><div class="label">Businesses</div></div>
            <div class="stat-card"><div class="value"><?= $pending_businesses ?></div><div class="label">Pending Approval</div></div>
            <div class="stat-card"><div class="value"><?= $total_bookings ?></div><div class="label">Bookings</div></div>
            <div class="stat-card"><div class="value">R <?= number_format($total_revenue, 2) ?></div><div class="label">Total Revenue</div></div>
            <div class="stat-card"><div class="value"><?= $total_payments ?></div><div class="label">Payments</div></div>
            <div class="stat-card"><div class="value"><?= $pending_withdrawals ?></div><div class="label">Pending Withdrawals</div></div>
            <div class="stat-card"><div class="value">R <?= number_format($pending_withdrawals_amount, 2) ?></div><div class="label">Pending Amount</div></div>
            <div class="stat-card"><div class="value"><?= $low_plan_count ?> / <?= $medium_plan_count ?> / <?= $high_plan_count ?></div><div class="label">Business Plans (L/M/H)</div></div>
            <div class="stat-card"><div class="value">R <?= number_format($app_share, 2) ?></div><div class="label">App Revenue (34% of fees)</div></div>
        </div>

        <div class="chart-container">
            <h3 style="margin-bottom:1rem; color:#6d28d9;">Monthly Revenue (Last 12 Months)</h3>
            <canvas id="revenueChart"></canvas>
        </div>

        <div class="chart-container">
            <h3 style="margin-bottom:1rem; color:#6d28d9;">Monthly Bookings (Last 12 Months)</h3>
            <canvas id="bookingsChart"></canvas>
        </div>

        <!-- ========== PENDING BUSINESS APPROVALS ========== -->
        <div class="section-header">
            <h2>Pending Business Approvals</h2>
            <a href="businesses.php?filter=pending" class="view-all">View all →</a>
        </div>
        
        <!-- Desktop Table -->
        <div class="card-table desktop-table-view">
            <?php if (empty($pending_list)): ?>
                <p>No pending businesses.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr><th>Name</th><th>Email</th><th>Registered</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_list as $biz): ?>
                        <tr>
                            <td><?= htmlspecialchars($biz['name']) ?></td>
                            <td><?= htmlspecialchars($biz['email']) ?></td>
                            <td><?= date('d M Y', strtotime($biz['created_at'])) ?></td>
                            <td>
                                <a href="admin-approve.php?id=<?= $biz['id'] ?>&action=approve" class="btn-approve" onclick="return confirm('Approve?')">Approve</a>
                                <a href="admin-approve.php?id=<?= $biz['id'] ?>&action=reject" class="btn-reject" onclick="return confirm('Reject?')">Reject</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <!-- Mobile Cards ONLY -->
        <div class="mobile-cards-container">
            <?php if (empty($pending_list)): ?>
                <div class="mobile-data-card" style="text-align:center;">No pending businesses.</div>
            <?php else: ?>
                <?php foreach ($pending_list as $biz): ?>
                    <div class="mobile-data-card">
                        <div class="card-header-row">
                            <span class="card-title"><i class="fa-regular fa-building"></i> <?= htmlspecialchars($biz['name']) ?></span>
                        </div>
                        <div class="card-detail-row">
                            <span class="detail-label"><i class="fa-regular fa-envelope"></i> Email:</span>
                            <span class="detail-value"><?= htmlspecialchars($biz['email']) ?></span>
                        </div>
                        <div class="card-detail-row">
                            <span class="detail-label"><i class="fa-regular fa-calendar"></i> Registered:</span>
                            <span class="detail-value"><?= date('d M Y', strtotime($biz['created_at'])) ?></span>
                        </div>
                        <div class="card-action">
                            <a href="admin-approve.php?id=<?= $biz['id'] ?>&action=approve" class="btn-approve-card" onclick="return confirm('Approve?')">Approve</a>
                            <a href="admin-approve.php?id=<?= $biz['id'] ?>&action=reject" class="btn-reject-card" onclick="return confirm('Reject?')">Reject</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- ========== RECENT BOOKINGS ========== -->
        <div class="section-header">
            <h2>Recent Bookings</h2>
            <a href="bookings.php" class="view-all">View all →</a>
        </div>
        
        <!-- Desktop Table -->
        <div class="card-table desktop-table-view">
            <?php if (empty($recent_bookings)): ?>
                <p>No recent bookings.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr><th>Code</th><th>User</th><th>Business</th><th>Date</th><th>Amount</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_bookings as $b): ?>
                        <tr>
                            <td><?= htmlspecialchars($b['booking_code']) ?></td>
                            <td><?= htmlspecialchars($b['user_name']) ?></td>
                            <td><?= htmlspecialchars($b['business_name']) ?></td>
                            <td><?= $b['booking_date'] ?></td>
                            <td>R <?= number_format($b['total_amount'], 2) ?></td>
                            <td><span class="status-badge status-<?= $b['status'] ?>"><?= ucfirst($b['status']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <!-- Mobile Cards ONLY -->
        <div class="mobile-cards-container">
            <?php if (empty($recent_bookings)): ?>
                <div class="mobile-data-card" style="text-align:center;">No recent bookings.</div>
            <?php else: ?>
                <?php foreach ($recent_bookings as $b): ?>
                    <div class="mobile-data-card">
                        <div class="card-header-row">
                            <span class="card-title"><i class="fa-regular fa-calendar-check"></i> <?= htmlspecialchars($b['business_name']) ?></span>
                            <span class="card-badge status-badge status-<?= $b['status'] ?>"><?= ucfirst($b['status']) ?></span>
                        </div>
                        <div class="card-detail-row">
                            <span class="detail-label"><i class="fa-solid fa-hashtag"></i> Booking Code:</span>
                            <span class="detail-value booking-code"><?= htmlspecialchars($b['booking_code']) ?></span>
                        </div>
                        <div class="card-detail-row">
                            <span class="detail-label"><i class="fa-regular fa-user"></i> User:</span>
                            <span class="detail-value"><?= htmlspecialchars($b['user_name']) ?></span>
                        </div>
                        <div class="card-detail-row">
                            <span class="detail-label"><i class="fa-regular fa-calendar"></i> Date:</span>
                            <span class="detail-value"><?= $b['booking_date'] ?></span>
                        </div>
                        <div class="card-detail-row">
                            <span class="detail-label"><i class="fa-solid fa-currency-sign"></i> Amount:</span>
                            <span class="detail-value amount-highlight">R <?= number_format($b['total_amount'], 2) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- ========== RECENT PAYMENTS ========== -->
        <div class="section-header">
            <h2>Recent Payments</h2>
            <a href="payments.php" class="view-all">View all →</a>
        </div>
        
        <!-- Desktop Table -->
        <div class="card-table desktop-table-view">
            <?php if (empty($recent_payments)): ?>
                <p>No recent payments.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr><th>User</th><th>Business</th><th>Amount</th><th>Method</th><th>Date</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_payments as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['user_name']) ?></td>
                            <td><?= htmlspecialchars($p['business_name']) ?></td>
                            <td>R <?= number_format($p['amount'], 2) ?></td>
                            <td>PayFast</td>
                            <td><?= date('d M Y', strtotime($p['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <!-- Mobile Cards ONLY -->
        <div class="mobile-cards-container">
            <?php if (empty($recent_payments)): ?>
                <div class="mobile-data-card" style="text-align:center;">No recent payments.</div>
            <?php else: ?>
                <?php foreach ($recent_payments as $p): ?>
                    <div class="mobile-data-card">
                        <div class="card-header-row">
                            <span class="card-title"><i class="fa-regular fa-credit-card"></i> <?= htmlspecialchars($p['business_name']) ?></span>
                        </div>
                        <div class="card-detail-row">
                            <span class="detail-label"><i class="fa-regular fa-user"></i> User:</span>
                            <span class="detail-value"><?= htmlspecialchars($p['user_name']) ?></span>
                        </div>
                        <div class="card-detail-row">
                            <span class="detail-label"><i class="fa-solid fa-currency-sign"></i> Amount:</span>
                            <span class="detail-value amount-highlight">R <?= number_format($p['amount'], 2) ?></span>
                        </div>
                        <div class="card-detail-row">
                            <span class="detail-label"><i class="fa-solid fa-globe"></i> Method:</span>
                            <span class="detail-value">PayFast</span>
                        </div>
                        <div class="card-detail-row">
                            <span class="detail-label"><i class="fa-regular fa-calendar"></i> Date:</span>
                            <span class="detail-value"><?= date('d M Y', strtotime($p['created_at'])) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- ========== RECENT WITHDRAWALS ========== -->
        <div class="section-header">
            <h2>Recent Withdrawals</h2>
            <a href="withdrawals.php" class="view-all">View all →</a>
        </div>
        
        <!-- Desktop Table -->
        <div class="card-table desktop-table-view">
            <?php if (empty($recent_withdrawals)): ?>
                <p>No recent withdrawals.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr><th>Business</th><th>Amount</th><th>Status</th><th>Requested</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_withdrawals as $w): ?>
                        <tr>
                            <td><?= htmlspecialchars($w['business_name']) ?></td>
                            <td>R <?= number_format($w['amount'], 2) ?></td>
                            <td><span class="status-badge status-<?= $w['status'] ?>"><?= ucfirst($w['status']) ?></span></td>
                            <td><?= date('d M Y', strtotime($w['requested_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <!-- Mobile Cards ONLY -->
        <div class="mobile-cards-container">
            <?php if (empty($recent_withdrawals)): ?>
                <div class="mobile-data-card" style="text-align:center;">No recent withdrawals.</div>
            <?php else: ?>
                <?php foreach ($recent_withdrawals as $w): ?>
                    <div class="mobile-data-card">
                        <div class="card-header-row">
                            <span class="card-title"><i class="fa-regular fa-building"></i> <?= htmlspecialchars($w['business_name']) ?></span>
                            <span class="card-badge status-badge status-<?= $w['status'] ?>"><?= ucfirst($w['status']) ?></span>
                        </div>
                        <div class="card-detail-row">
                            <span class="detail-label"><i class="fa-solid fa-currency-sign"></i> Amount:</span>
                            <span class="detail-value amount-highlight">R <?= number_format($w['amount'], 2) ?></span>
                        </div>
                        <div class="card-detail-row">
                            <span class="detail-label"><i class="fa-regular fa-calendar"></i> Requested:</span>
                            <span class="detail-value"><?= date('d M Y', strtotime($w['requested_at'])) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- ========== BUSINESS SUBSCRIPTION PLANS ========== -->
        <div class="section-header">
            <h2>Business Subscription Plans</h2>
            <a href="businesses.php" class="view-all">Manage →</a>
        </div>
        
        <!-- Desktop Table -->
        <div class="card-table desktop-table-view">
            <?php if (empty($sub_businesses)): ?>
                <p>No businesses found.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr><th>Business</th><th>Plan</th><th>Wallet Balance</th><th>Status</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sub_businesses as $biz): 
                            $plan_color = $biz['subscription_plan'] == 'low' ? '#22c55e' : ($biz['subscription_plan'] == 'medium' ? '#f97316' : '#ef4444');
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($biz['name']) ?><br><small><?= htmlspecialchars($biz['email']) ?></small></td>
                            <td><span class="status-badge" style="background: <?= $plan_color ?>;"><?= ucfirst($biz['subscription_plan']) ?></span></td>
                            <td>R <?= number_format($biz['wallet_balance'], 2) ?></td>
                            <td><?= $biz['is_active'] ? 'Active' : 'Frozen' ?></td>
                            <td><a href="businesses-view.php?id=<?= $biz['id'] ?>" class="view-all">View</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <!-- Mobile Cards ONLY -->
        <div class="mobile-cards-container">
            <?php if (empty($sub_businesses)): ?>
                <div class="mobile-data-card" style="text-align:center;">No businesses found.</div>
            <?php else: ?>
                <?php foreach ($sub_businesses as $biz): 
                    $plan_color = $biz['subscription_plan'] == 'low' ? '#22c55e' : ($biz['subscription_plan'] == 'medium' ? '#f97316' : '#ef4444');
                ?>
                    <div class="mobile-data-card">
                        <div class="card-header-row">
                            <span class="card-title"><i class="fa-regular fa-building"></i> <?= htmlspecialchars($biz['name']) ?></span>
                            <span class="card-badge" style="background: <?= $plan_color ?>;"><?= ucfirst($biz['subscription_plan']) ?></span>
                        </div>
                        <div class="card-detail-row">
                            <span class="detail-label"><i class="fa-regular fa-envelope"></i> Email:</span>
                            <span class="detail-value"><?= htmlspecialchars($biz['email']) ?></span>
                        </div>
                        <div class="card-detail-row">
                            <span class="detail-label"><i class="fa-solid fa-wallet"></i> Wallet:</span>
                            <span class="detail-value amount-highlight">R <?= number_format($biz['wallet_balance'], 2) ?></span>
                        </div>
                        <div class="card-detail-row">
                            <span class="detail-label"><i class="fa-solid fa-toggle-on"></i> Status:</span>
                            <span class="detail-value"><?= $biz['is_active'] ? '<span style="color:#22c55e;">Active</span>' : '<span style="color:#ef4444;">Frozen</span>' ?></span>
                        </div>
                        <div class="card-action">
                            <a href="businesses-view.php?id=<?= $biz['id'] ?>"><i class="fa-regular fa-eye"></i> View Business</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
    </div>

    <footer class="app-footer">
        <p>&copy; <?= date('Y'); ?> No Q · Admin Dashboard</p>
    </footer>

    <script>
        const menuToggle = document.getElementById('menuToggle');
        const navLinks = document.getElementById('navLinks');
        if (menuToggle) {
            menuToggle.addEventListener('click', () => navLinks.classList.toggle('show'));
        }
        document.querySelectorAll('.nav-links a').forEach(link => {
            link.addEventListener('click', () => {
                navLinks.classList.remove('show');
            });
        });

        new Chart(document.getElementById('revenueChart'), {
            type: 'line',
            data: {
                labels: <?= json_encode($month_labels) ?>,
                datasets: [{
                    label: 'Revenue (R)',
                    data: <?= json_encode($monthly_revenue) ?>,
                    borderColor: '#8b5cf6',
                    backgroundColor: 'rgba(139,92,246,0.1)',
                    tension: 0.3,
                    fill: true
                }]
            }
        });

        new Chart(document.getElementById('bookingsChart'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($month_labels) ?>,
                datasets: [{
                    label: 'Bookings',
                    data: <?= json_encode($monthly_bookings) ?>,
                    backgroundColor: '#f97316',
                    borderRadius: 8
                }]
            }
        });
    </script>
</body>
</html>