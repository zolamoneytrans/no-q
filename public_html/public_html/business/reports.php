<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['business_id'])) {
    header('Location: business-login.php');
    exit;
}

$business_id = $_SESSION['business_id'];
$business_name = $_SESSION['business_name'] ?? 'Business';

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Reports · <?= htmlspecialchars($business_name) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            position: sticky;
            top: 0;
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: space-between;
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

        .container { max-width: 1200px; margin: 2rem auto; padding: 0 2rem; flex:1; }
        h1 { font-size: 2rem; margin-bottom: 1rem; background: linear-gradient(145deg, var(--purple-primary), var(--orange-primary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: rgba(255,255,255,0.8);
            backdrop-filter: blur(8px);
            border-radius: 24px;
            padding: 1.2rem;
            border: 1px solid rgba(106,27,154,0.1);
            text-align: center;
            transition: all 0.2s;
        }
        .stat-card:hover { transform: translateY(-3px); background: white; }
        .stat-card .value { font-size: 2rem; font-weight: 700; color: var(--purple-primary); }
        .stat-card .label { color: #2c3e50; font-size: 0.9rem; }
        .chart-container {
            background: rgba(255,255,255,0.8);
            backdrop-filter: blur(4px);
            border-radius: 30px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(106,27,154,0.1);
        }
        canvas { max-height: 300px; width: 100%; }
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
            .stats-grid { gap: 1rem; }
            .stat-card .value { font-size: 1.5rem; }
        }
    </style>
</head>
<body>
    <header class="app-header">
        <div class="logo-area">
            <img src="/NoQ.jpg" alt="No Q" style="height: 70px; width: auto;">
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
            <a href="services.php">Services</a>
            <a href="images.php">Images</a>
            <a href="status.php">Status</a>
            <a href="business-settings.php">Settings</a>
            <a href="reports.php" style="background:rgba(106,27,154,0.1);">Reports</a>
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
            <h3 style="margin-bottom:1rem; color: var(--purple-primary);">Monthly Revenue (Last 12 Months)</h3>
            <?php if (empty(array_filter($monthly_revenue))): ?>
                <div style="text-align:center; padding: 2rem; color: #888;">
                    <i class="fa-solid fa-chart-line" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                    <p>No revenue data available for the last 12 months.</p>
                </div>
            <?php else: ?>
                <canvas id="revenueChart"></canvas>
            <?php endif; ?>
        </div>

        <div class="chart-container">
            <h3 style="margin-bottom:1rem; color: var(--purple-primary);">Weekly Bookings (Last 4 Weeks)</h3>
            <?php if (empty(array_filter($weekly_bookings))): ?>
                <div style="text-align:center; padding: 2rem; color: #888;">
                    <i class="fa-regular fa-calendar" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                    <p>No bookings data available for the last 4 weeks.</p>
                </div>
            <?php else: ?>
                <canvas id="bookingsChart"></canvas>
            <?php endif; ?>
        </div>

        <div class="chart-container">
            <h3 style="margin-bottom:1rem; color: var(--purple-primary);">Revenue by Service (Top 5)</h3>
            <?php if (empty($service_revenue)): ?>
                <div style="text-align:center; padding: 2rem; color: #888;">
                    <i class="fa-solid fa-car-side" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                    <p>No service revenue data available.</p>
                </div>
            <?php else: ?>
                <canvas id="serviceChart"></canvas>
            <?php endif; ?>
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

        new Chart(document.getElementById('revenueChart'), {
            type: 'line',
            data: {
                labels: <?= json_encode($month_labels) ?>,
                datasets: [{
                    label: 'Revenue (R)',
                    data: <?= json_encode($monthly_revenue) ?>,
                    borderColor: '#6a1b9a',
                    backgroundColor: 'rgba(106,27,154,0.1)',
                    tension: 0.3,
                    fill: true
                }]
            },
            options: { responsive: true, maintainAspectRatio: true }
        });

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
new Chart(document.getElementById('serviceChart'), {
    type: 'pie',
    data: {
        labels: <?= json_encode($service_labels) ?>,
        datasets: [{
            data: <?= json_encode($service_revenue) ?>,
            backgroundColor: ['#6a1b9a', '#ff9800', '#9c4dcc', '#4caf50', '#2196f3'],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    boxWidth: 12,
                    padding: 10
                }
            }
        }
    }
});
    </script>
</body>
</html>