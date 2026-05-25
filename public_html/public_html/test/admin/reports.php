<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin-login.php');
    exit;
}

$admin_name = $_SESSION['admin_name'];

// --- Overall stats ---
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_businesses = $pdo->query("SELECT COUNT(*) FROM businesses")->fetchColumn();
$total_bookings = $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
$total_revenue = $pdo->query("SELECT SUM(total_amount) FROM bookings WHERE status = 'completed'")->fetchColumn() ?: 0;

// --- Monthly bookings (last 12 months) ---
$monthly_bookings = [];
$month_labels = [];
for ($i = 0; $i < 12; $i++) {
    $month = date('Y-m', strtotime("-$i months"));
    $month_labels[] = date('M Y', strtotime($month));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE status = 'completed' AND DATE_FORMAT(created_at, '%Y-%m') = ?");
    $stmt->execute([$month]);
    $monthly_bookings[] = $stmt->fetchColumn();
}
$monthly_bookings = array_reverse($monthly_bookings);
$month_labels = array_reverse($month_labels);

// --- Monthly revenue (last 12 months) ---
$monthly_revenue = [];
for ($i = 0; $i < 12; $i++) {
    $month = date('Y-m', strtotime("-$i months"));
    $stmt = $pdo->prepare("SELECT SUM(total_amount) FROM bookings WHERE status = 'completed' AND DATE_FORMAT(created_at, '%Y-%m') = ?");
    $stmt->execute([$month]);
    $monthly_revenue[] = round($stmt->fetchColumn() ?: 0, 2);
}
$monthly_revenue = array_reverse($monthly_revenue);

// --- Top businesses by revenue (last 3 months) ---
$stmt = $pdo->prepare("
    SELECT b.name, SUM(bk.total_amount) as revenue
    FROM bookings bk
    JOIN businesses b ON bk.business_id = b.id
    WHERE bk.status = 'completed' AND bk.created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
    GROUP BY b.id
    ORDER BY revenue DESC
    LIMIT 5
");
$stmt->execute();
$top_businesses = $stmt->fetchAll();
$top_business_names = array_column($top_businesses, 'name');
$top_business_revenue = array_column($top_businesses, 'revenue');
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
    <title>Admin Reports · No Q</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .logo-area { display: flex; align-items: center; gap: 10px; }
        .logo-icon { background: #1e3c72; color: white; width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        .logo-text { font-weight: 700; font-size: 1.5rem; background: linear-gradient(135deg, #1e3c72, #2a5298); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .nav-links { display: flex; gap: 1.2rem; align-items: center; }
        .nav-links a { text-decoration: none; font-weight: 500; color: #2c3e50; padding: 0.5rem 0.8rem; border-radius: 40px; }
        .nav-links a:hover { background: rgba(42,82,152,0.08); }

        .menu-toggle {
            display: none;
            font-size: 1.8rem;
            cursor: pointer;
            color: #1e3c72;
            background: transparent;
            border: none;
            padding: 0.5rem;
        }
        @media (max-width: 768px) {
            .menu-toggle { display: block; }
            .nav-links { display: none; width:100%; flex-direction:column; align-items:center; gap:0.5rem; padding:1rem 0; background:rgba(255,255,255,0.9); backdrop-filter:blur(10px); border-radius:30px; margin-top:1rem; }
            .nav-links.show { display: flex; }
            .app-header { padding: 0.8rem 1rem; }
            .nav-links a { width:100%; text-align:center; padding:0.8rem; }
        }

        .container { max-width: 1200px; margin: 2rem auto; padding: 0 2rem; flex:1; }
        h1 { font-size: 2rem; margin-bottom: 1rem; background: linear-gradient(145deg, #1e3c72, #2a5298); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: rgba(255,255,255,0.7);
            backdrop-filter: blur(8px);
            border-radius: 24px;
            padding: 1.2rem;
            border: 1px solid rgba(255,255,255,0.6);
            text-align: center;
        }
        .stat-card .value { font-size: 2rem; font-weight: 700; color: #1e3c72; }
        .stat-card .label { color: #2c3e50; font-size: 0.9rem; }
        .chart-container {
            background: rgba(255,255,255,0.5);
            backdrop-filter: blur(4px);
            border-radius: 30px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        canvas { max-height: 300px; width: 100%; }
        .app-footer {
            background: rgba(255,255,255,0.6);
            padding: 2rem;
            text-align: center;
            margin-top: auto;
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
            <a href="admin-dashboard.php">Dashboard</a>
            <a href="users.php">Users</a>
            <a href="businesses.php">Businesses</a>
            <a href="withdrawals.php">Withdrawals</a>
            <a href="reports.php" style="background:rgba(42,82,152,0.1);">Reports</a>
            <a href="admin-logout.php">Logout</a>
        </nav>
    </header>

    <div class="container">
        <h1>Platform Reports</h1>

        <div class="stats-grid">
            <div class="stat-card"><div class="value"><?= $total_users ?></div><div class="label">Total Users</div></div>
            <div class="stat-card"><div class="value"><?= $total_businesses ?></div><div class="label">Total Businesses</div></div>
            <div class="stat-card"><div class="value"><?= $total_bookings ?></div><div class="label">Total Bookings</div></div>
            <div class="stat-card"><div class="value">R <?= number_format($total_revenue, 2) ?></div><div class="label">Total Revenue</div></div>
        </div>

        <div class="chart-container">
            <h3 style="margin-bottom:1rem;">Monthly Bookings (Last 12 Months)</h3>
            <canvas id="bookingsChart"></canvas>
        </div>

        <div class="chart-container">
            <h3 style="margin-bottom:1rem;">Monthly Revenue (Last 12 Months)</h3>
            <canvas id="revenueChart"></canvas>
        </div>

        <div class="chart-container">
            <h3 style="margin-bottom:1rem;">Top 5 Businesses by Revenue (Last 3 Months)</h3>
            <canvas id="topBusinessesChart"></canvas>
        </div>
    </div>

    <footer class="app-footer">
        <p>&copy; <?= date('Y'); ?> No Q · Admin</p>
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

        new Chart(document.getElementById('bookingsChart'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($month_labels) ?>,
                datasets: [{
                    label: 'Completed Bookings',
                    data: <?= json_encode($monthly_bookings) ?>,
                    backgroundColor: '#ff9800',
                    borderRadius: 8
                }]
            }
        });

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

        new Chart(document.getElementById('topBusinessesChart'), {
            type: 'pie',
            data: {
                labels: <?= json_encode($top_business_names) ?>,
                datasets: [{
                    data: <?= json_encode($top_business_revenue) ?>,
                    backgroundColor: ['#1e3c72', '#2a5298', '#3a6ea5', '#ff9800', '#4caf50']
                }]
            }
        });
    </script>
</body>
</html>
