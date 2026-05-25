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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard · No Q</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(145deg, #f6f9fc 0%, #e9f1f8 100%);
            min-height: 100vh;
        }
        .app-header {
            background: rgba(255,255,255,0.7);
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
        .logo-icon { background: #1e3c72; color: white; width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        .logo-text { font-weight: 700; font-size: 1.5rem; background: linear-gradient(135deg, #1e3c72, #2a5298); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .nav-links { display: flex; gap: 1.2rem; align-items: center; flex-wrap: wrap; }
        .nav-links a { text-decoration: none; font-weight: 500; color: #2c3e50; padding: 0.5rem 0.8rem; border-radius: 40px; }
        .nav-links a:hover { background: rgba(42,82,152,0.08); }
        .menu-toggle {
            display: none;
            font-size: 1.8rem;
            cursor: pointer;
            background: transparent;
            border: none;
            color: #1e3c72;
            padding: 0.5rem;
        }
        @media (max-width: 768px) {
            .menu-toggle { display: block; }
            .nav-links { display: none; width: 100%; flex-direction: column; background: rgba(255,255,255,0.95); border-radius: 30px; padding: 1rem; margin-top: 1rem; }
            .nav-links.show { display: flex; }
            .app-header { padding: 0.8rem 1rem; }
        }
        .container { max-width: 1400px; margin: 2rem auto; padding: 0 2rem; }
        .welcome { margin-bottom: 2rem; }
        .welcome h1 { font-size: 2rem; color: #1e3c72; }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: rgba(255,255,255,0.7);
            backdrop-filter: blur(8px);
            border-radius: 24px;
            padding: 1.2rem;
            text-align: center;
        }
        .stat-card .value { font-size: 1.8rem; font-weight: 700; color: #1e3c72; }
        .stat-card .label { font-size: 0.8rem; color: #2c3e50; }
        .chart-container {
            background: rgba(255,255,255,0.5);
            backdrop-filter: blur(4px);
            border-radius: 30px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        canvas { max-height: 300px; width: 100%; }
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 2rem 0 1rem;
        }
        .section-header h2 {
            font-size: 1.3rem;
            font-weight: 600;
            color: #1e3c72;
        }
        .card-table {
            background: rgba(255,255,255,0.5);
            backdrop-filter: blur(4px);
            border-radius: 30px;
            padding: 1rem;
            overflow-x: auto;
        }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 0.8rem; text-align: left; border-bottom: 1px solid rgba(0,0,0,0.05); }
        th { color: #1e3c72; font-weight: 600; }
        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
        }
        .status-pending { background: #ff9800; color: white; }
        .btn-approve { background: #4caf50; color: white; padding: 0.3rem 0.8rem; border-radius: 30px; text-decoration: none; font-size: 0.8rem; display: inline-block; }
        .btn-reject { background: #f44336; color: white; padding: 0.3rem 0.8rem; border-radius: 30px; text-decoration: none; font-size: 0.8rem; }
        .view-all { color: #1e3c72; text-decoration: none; font-size: 0.9rem; }
        .app-footer { background: rgba(255,255,255,0.6); padding: 2rem; text-align: center; margin-top: 2rem; }
        .notification-badge {
            background: #f44336;
            color: white;
            border-radius: 20px;
            padding: 2px 8px;
            font-size: 0.7rem;
            margin-left: 5px;
        }
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            table, thead, tbody, th, td, tr { display: block; }
            th { display: none; }
            tr { margin-bottom: 1rem; border: 1px solid rgba(0,0,0,0.1); border-radius: 20px; padding: 0.5rem; }
            td { display: flex; justify-content: space-between; padding: 0.5rem; border: none; }
            td:before { content: attr(data-label); font-weight: bold; color: #1e3c72; }
        }
    </style>
</head>
<body>
    <header class="app-header">
        <div class="logo-area">
            <img src="/logo.jpeg" alt="No Q" style="height: 40px; width: auto;">
            <span class="logo-text">Admin<span style="font-weight:400;">Panel</span></span>
        </div>
        <button class="menu-toggle" id="menuToggle"><i class="fa-solid fa-bars"></i></button>
        <nav class="nav-links" id="navLinks">
            <a href="admin-dashboard.php" style="background:rgba(42,82,152,0.1);">Dashboard</a>
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
            <p style="color:#2c3e50;">Platform overview</p>
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
        </div>

        <div class="chart-container">
            <h3 style="margin-bottom:1rem;">Monthly Revenue (Last 12 Months)</h3>
            <canvas id="revenueChart"></canvas>
        </div>

        <div class="chart-container">
            <h3 style="margin-bottom:1rem;">Monthly Bookings (Last 12 Months)</h3>
            <canvas id="bookingsChart"></canvas>
        </div>

        <div class="section-header">
            <h2>Pending Business Approvals</h2>
            <a href="businesses.php?filter=pending" class="view-all">View all →</a>
        </div>
        <div class="card-table">
            <?php if (empty($pending_list)): ?>
                <p>No pending businesses.</p>
            <?php else: ?>
                <table>
                    <thead><tr><th>Name</th><th>Email</th><th>Registered</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php foreach ($pending_list as $biz): ?>
                        <tr>
                            <td data-label="Name"><?= htmlspecialchars($biz['name']) ?></td>
                            <td data-label="Email"><?= htmlspecialchars($biz['email']) ?></td>
                            <td data-label="Registered"><?= date('d M Y', strtotime($biz['created_at'])) ?></td>
                            <td data-label="Action">
                                <a href="admin-approve.php?id=<?= $biz['id'] ?>&action=approve" class="btn-approve" onclick="return confirm('Approve?')">Approve</a>
                                <a href="admin-approve.php?id=<?= $biz['id'] ?>&action=reject" class="btn-reject" onclick="return confirm('Reject?')">Reject</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="section-header">
            <h2>Recent Bookings</h2>
            <a href="bookings.php" class="view-all">View all →</a>
        </div>
        <div class="card-table">
            <?php if (empty($recent_bookings)): ?>
                <p>No recent bookings.</p>
            <?php else: ?>
                <table>
                    <thead><tr><th>Code</th><th>User</th><th>Business</th><th>Date</th><th>Amount</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($recent_bookings as $b): ?>
                        <tr>
                            <td data-label="Code"><?= htmlspecialchars($b['booking_code']) ?></td>
                            <td data-label="User"><?= htmlspecialchars($b['user_name']) ?></td>
                            <td data-label="Business"><?= htmlspecialchars($b['business_name']) ?></td>
                            <td data-label="Date"><?= $b['booking_date'] ?></td>
                            <td data-label="Amount">R <?= number_format($b['total_amount'], 2) ?></td>
                            <td data-label="Status"><span class="status-badge status-<?= $b['status'] ?>"><?= ucfirst($b['status']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="section-header">
            <h2>Recent Payments</h2>
            <a href="payments.php" class="view-all">View all →</a>
        </div>
        <div class="card-table">
            <?php if (empty($recent_payments)): ?>
                <p>No recent payments.</p>
            <?php else: ?>
                <table>
                    <thead><tr><th>User</th><th>Business</th><th>Amount</th><th>Method</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php foreach ($recent_payments as $p): ?>
                        <tr>
                            <td data-label="User"><?= htmlspecialchars($p['user_name']) ?></td>
                            <td data-label="Business"><?= htmlspecialchars($p['business_name']) ?></td>
                            <td data-label="Amount">R <?= number_format($p['amount'], 2) ?></td>
                            <td data-label="Method">PayFast</td>
                            <td data-label="Date"><?= date('d M Y', strtotime($p['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="section-header">
            <h2>Recent Withdrawals</h2>
            <a href="withdrawals.php" class="view-all">View all →</a>
        </div>
        <div class="card-table">
            <?php if (empty($recent_withdrawals)): ?>
                <p>No recent withdrawals.</p>
            <?php else: ?>
                <table>
                    <thead><tr><th>Business</th><th>Amount</th><th>Status</th><th>Requested</th></tr></thead>
                    <tbody>
                        <?php foreach ($recent_withdrawals as $w): ?>
                        <tr>
                            <td data-label="Business"><?= htmlspecialchars($w['business_name']) ?></td>
                            <td data-label="Amount">R <?= number_format($w['amount'], 2) ?></td>
                            <td data-label="Status"><span class="status-badge status-<?= $w['status'] ?>"><?= ucfirst($w['status']) ?></span></td>
                            <td data-label="Requested"><?= date('d M Y', strtotime($w['requested_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
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

        new Chart(document.getElementById('revenueChart'), {
            type: 'line',
            data: {
                labels: <?= json_encode($month_labels) ?>,
                datasets: [{
                    label: 'Revenue (R)',
                    data: <?= json_encode($monthly_revenue) ?>,
                    borderColor: '#1e3c72',
                    backgroundColor: 'rgba(30,60,114,0.1)',
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
                    backgroundColor: '#ff9800',
                    borderRadius: 8
                }]
            }
        });
    </script>
</body>
</html>
