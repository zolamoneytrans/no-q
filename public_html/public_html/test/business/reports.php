<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['business_id'])) {
    header('Location: business-login.php');
    exit;
}

$business_id = $_SESSION['business_id'];

// Fetch business name
$stmt = $pdo->prepare("SELECT name FROM businesses WHERE id = ?");
$stmt->execute([$business_id]);
$business_name = $stmt->fetchColumn();

// --- Monthly Revenue (last 12 months) ---
$monthly_revenue = [];
$month_labels = [];
for ($i = 0; $i < 12; $i++) {
    $month = date('Y-m', strtotime("-$i months"));
    $month_labels[] = date('M Y', strtotime($month));
    $stmt = $pdo->prepare("
        SELECT SUM(total_amount) FROM bookings 
        WHERE business_id = ? AND status = 'completed' 
        AND DATE_FORMAT(created_at, '%Y-%m') = ?
    ");
    $stmt->execute([$business_id, $month]);
    $monthly_revenue[] = round($stmt->fetchColumn() ?: 0, 2);
}
$monthly_revenue = array_reverse($monthly_revenue);
$month_labels = array_reverse($month_labels);

// --- Weekly bookings (last 4 weeks) ---
$weekly_bookings = [];
$week_labels = [];
for ($i = 3; $i >= 0; $i--) {
    $week_start = date('Y-m-d', strtotime("-$i weeks"));
    $week_end = date('Y-m-d', strtotime("-$i weeks +6 days"));
    $week_labels[] = "Week " . (4 - $i) . " ($week_start - $week_end)";
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM bookings 
        WHERE business_id = ? AND status = 'completed' 
        AND booking_date BETWEEN ? AND ?
    ");
    $stmt->execute([$business_id, $week_start, $week_end]);
    $weekly_bookings[] = $stmt->fetchColumn();
}

// --- Revenue by service (top 5) ---
$stmt = $pdo->prepare("
    SELECT s.name, SUM(b.total_amount) as total
    FROM bookings b
    JOIN services s ON b.service_id = s.id
    WHERE b.business_id = ? AND b.status = 'completed'
    GROUP BY s.id
    ORDER BY total DESC
    LIMIT 5
");
$stmt->execute([$business_id]);
$service_data = $stmt->fetchAll();
$service_labels = array_column($service_data, 'name');
$service_revenue = array_column($service_data, 'total');

// --- Today's revenue & booking count ---
$stmt = $pdo->prepare("SELECT COUNT(*), SUM(total_amount) FROM bookings WHERE business_id = ? AND booking_date = CURDATE() AND status = 'completed'");
$stmt->execute([$business_id]);
list($today_bookings, $today_revenue) = $stmt->fetch();
$today_revenue = round($today_revenue ?: 0, 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="manifest" href="../site.webmanifest" />
    <meta name="theme-color" content="#ff9800">
    <link rel="icon" type="image/png" href="../favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="../favicon.svg" />
    <link rel="shortcut icon" href="../favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="apple-touch-icon.png" />
    <meta name="apple-mobile-web-app-title" content="No Q" />
    <link rel="manifest" href="../site.webmanifest" />
    <title>Reports · <?= htmlspecialchars($business_name) ?></title>
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
            <span class="logo-text">CarWash<span style="font-weight:400;">Connect</span></span>
        </div>
        <button class="menu-toggle" id="menuToggle"><i class="fa-solid fa-bars"></i></button>
        <nav class="nav-links" id="navLinks">
            <a href="../index.php">Home</a>
            <a href="business-dashboard.php">Dashboard</a>
            <a href="bookings.php">Bookings</a>
            <a href="services.php">Services</a>
            <a href="images.php">Images</a>
            <a href="status.php">Status</a>
            <a href="business-settings.php">Settings</a>
            <a href="reports.php" style="background:rgba(42,82,152,0.1);">Reports</a>
            <a href="business-logout.php">Logout</a>
        </nav>
    </header>

    <div class="container">
        <h1>Business Reports</h1>

        <div class="stats-grid">
            <div class="stat-card"><div class="value">R <?= number_format($today_revenue, 2) ?></div><div class="label">Today's Revenue</div></div>
            <div class="stat-card"><div class="value"><?= $today_bookings ?></div><div class="label">Today's Bookings</div></div>
            <div class="stat-card"><div class="value">R <?= number_format(array_sum($monthly_revenue), 2) ?></div><div class="label">Total Revenue (12 months)</div></div>
        </div>

        <div class="chart-container">
            <h3 style="margin-bottom:1rem;">Monthly Revenue (Last 12 Months)</h3>
            <canvas id="revenueChart"></canvas>
        </div>

        <div class="chart-container">
            <h3 style="margin-bottom:1rem;">Weekly Bookings (Last 4 Weeks)</h3>
            <canvas id="bookingsChart"></canvas>
        </div>

        <div class="chart-container">
            <h3 style="margin-bottom:1rem;">Revenue by Service (Top 5)</h3>
            <canvas id="serviceChart"></canvas>
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

        // Revenue Chart
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
            },
            options: { responsive: true, maintainAspectRatio: true }
        });

        // Bookings Chart
        new Chart(document.getElementById('bookingsChart'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($week_labels) ?>,
                datasets: [{
                    label: 'Completed Bookings',
                    data: <?= json_encode($weekly_bookings) ?>,
                    backgroundColor: '#ff9800',
                    borderRadius: 8
                }]
            }
        });

        // Service Chart
        new Chart(document.getElementById('serviceChart'), {
            type: 'pie',
            data: {
                labels: <?= json_encode($service_labels) ?>,
                datasets: [{
                    data: <?= json_encode($service_revenue) ?>,
                    backgroundColor: ['#1e3c72', '#2a5298', '#3a6ea5', '#ff9800', '#4caf50']
                }]
            }
        });
    </script>
</body>
</html>
