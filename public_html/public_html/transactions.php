<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: user-login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get filter values
$search_business = isset($_GET['business']) ? trim($_GET['business']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build query with filters
$sql = "
    SELECT p.*, bk.booking_date, biz.name as business_name, bk.booking_code
    FROM payments p
    JOIN bookings bk ON p.booking_id = bk.id
    JOIN businesses biz ON bk.business_id = biz.id
    WHERE p.user_id = ?
";
$params = [$user_id];

if (!empty($search_business)) {
    $sql .= " AND biz.name LIKE ?";
    $params[] = "%$search_business%";
}
if (!empty($date_from)) {
    $sql .= " AND DATE(p.created_at) >= ?";
    $params[] = $date_from;
}
if (!empty($date_to)) {
    $sql .= " AND DATE(p.created_at) <= ?";
    $params[] = $date_to;
}

$sql .= " ORDER BY p.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$all_payments = $stmt->fetchAll();

// Get total spent
$total_spent = array_sum(array_column($all_payments, 'amount'));
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
    <title>My Transactions · No Q</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
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
            flex-wrap: wrap;
            position: sticky;
            top: 0;
            z-index: 100;
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
        .nav-links a i { margin-right: 6px; color: var(--purple-primary); }
        .nav-links a:hover { background: rgba(106,27,154,0.08); color: var(--purple-primary); }
        .nav-links .btn-outline { 
            border: 1.5px solid var(--purple-primary); 
            padding: 0.4rem 1.2rem; 
            border-radius: 40px; 
            background: white; 
            font-weight: 600; 
        }
        .nav-links .btn-outline:hover { background: var(--purple-primary); color: white; }
        .nav-links .btn-outline:hover i { color: white; }
        .nav-links .btn-outline::after { display: none; }

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
            .btn-outline { width: 100%; text-align: center; }
        }
        
        .container { max-width: 1000px; margin: 2rem auto; padding: 0 1rem; flex: 1; }
        .card { 
            background: rgba(255,255,255,0.9); 
            backdrop-filter: blur(10px); 
            border-radius: 32px; 
            padding: 2rem;
            border: 1px solid rgba(106,27,154,0.1);
            box-shadow: 0 20px 40px -12px rgba(106,27,154,0.2);
        }
        h1 { 
            margin-bottom: 0.5rem; 
            background: linear-gradient(145deg, var(--purple-primary), var(--orange-primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .subtitle { color: #666; margin-bottom: 1.5rem; }
        .filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 2rem;
            padding: 1rem;
            background: rgba(106,27,154,0.1);
            border-radius: 30px;
            align-items: flex-end;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
        }
        .filter-group label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--purple-primary);
        }
        .filter-group input {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 30px;
            background: white;
            font-family: 'Inter', sans-serif;
            transition: all 0.2s;
        }
        .filter-group input:focus {
            outline: none;
            box-shadow: 0 0 0 2px rgba(106,27,154,0.2);
        }
        .btn-filter {
            background: linear-gradient(135deg, var(--purple-primary), var(--purple-dark));
            color: white;
            border: none;
            padding: 0.5rem 1.5rem;
            border-radius: 30px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s;
        }
        .btn-filter:hover {
            transform: translateY(-2px);
            box-shadow: 0 3px 10px rgba(106,27,154,0.3);
        }
        .btn-reset {
            background: var(--orange-primary);
            color: white;
            border: none;
            padding: 0.5rem 1.5rem;
            border-radius: 30px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-weight: 600;
            transition: all 0.2s;
        }
        .btn-reset:hover {
            transform: translateY(-2px);
            background: var(--orange-dark);
        }
        .total-box {
            background: rgba(106,27,154,0.1);
            border-radius: 20px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            text-align: right;
        }
        .total-box .label { font-size: 0.9rem; color: #2c3e50; }
        .total-box .value { font-size: 1.8rem; font-weight: 700; color: var(--purple-primary); }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            text-align: left;
            padding: 1rem 0.5rem;
            background: rgba(106,27,154,0.1);
            border-radius: 20px;
            color: var(--purple-primary);
        }
        td {
            padding: 1rem 0.5rem;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        .no-data {
            text-align: center;
            padding: 2rem;
            color: #666;
        }
        .no-data i {
            color: var(--purple-primary);
            opacity: 0.5;
        }
        .btn-back {
            background: linear-gradient(135deg, var(--purple-primary), var(--purple-dark));
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 30px;
            text-decoration: none;
            display: inline-block;
            margin-top: 1.5rem;
            transition: all 0.2s;
        }
        .btn-back:hover {
            transform: translateY(-2px);
            box-shadow: 0 3px 10px rgba(106,27,154,0.3);
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
        
        @media (max-width: 768px) {
            .filter-bar { flex-direction: column; align-items: stretch; }
            .filter-group input { width: 100%; }
            .container { margin: 1rem auto; }
            .card { padding: 1.2rem; }
            h1 { font-size: 1.5rem; }
            table, thead, tbody, th, td, tr { display: block; }
            th { display: none; }
            td { display: block; padding: 0.8rem; border-bottom: 1px solid #ddd; }
            td:before { content: attr(data-label); font-weight: 600; display: inline-block; width: 120px; color: var(--purple-primary); }
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
            <a href="index.php"><i class="fa-solid fa-house"></i> Home</a>
            <a href="user-dashboard.php"><i class="fa-regular fa-user"></i> Dashboard</a>
            <a href="my-bookings.php"><i class="fa-regular fa-calendar-check"></i> My Bookings</a>
            <a href="logout.php" class="btn-outline"><i class="fa-regular fa-sign-out"></i> Logout</a>
        </nav>
    </header>

    <div class="container">
        <div class="card">
            <h1><i class="fa-regular fa-credit-card"></i> My Transactions</h1>
            <p class="subtitle">View all your payments and search by business or date</p>

            <!-- Filter Form -->
            <form method="get" class="filter-bar">
                <div class="filter-group">
                    <label><i class="fa-regular fa-building"></i> Business Name</label>
                    <input type="text" name="business" placeholder="Search business..." value="<?= htmlspecialchars($search_business) ?>">
                </div>
                <div class="filter-group">
                    <label><i class="fa-regular fa-calendar"></i> From Date</label>
                    <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
                </div>
                <div class="filter-group">
                    <label><i class="fa-regular fa-calendar"></i> To Date</label>
                    <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
                </div>
                <div class="filter-group">
                    <button type="submit" class="btn-filter"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
                </div>
                <div class="filter-group">
                    <a href="transactions.php" class="btn-reset"><i class="fa-solid fa-rotate-left"></i> Reset</a>
                </div>
            </form>

            <!-- Total Spent -->
            <div class="total-box">
                <div class="label">Total spent (filtered)</div>
                <div class="value">R <?= number_format($total_spent, 2) ?></div>
            </div>

            <!-- Transactions Table -->
            <?php if (empty($all_payments)): ?>
                <div class="no-data">
                    <i class="fa-regular fa-receipt"></i> No transactions found.
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Business</th>
                                <th>Booking Code</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_payments as $pay): ?>
                            <tr>
                                <td data-label="Date"><?= date('d M Y, H:i', strtotime($pay['created_at'])) ?></td>
                                <td data-label="Business"><strong><?= htmlspecialchars($pay['business_name']) ?></strong></td>
                                <td data-label="Booking Code"><code><?= htmlspecialchars($pay['booking_code']) ?></code></td>
                                <td data-label="Amount" style="font-weight:700; color:var(--purple-primary);">R <?= number_format($pay['amount'], 2) ?></td>
                                <td data-label="Status"><span style="background:#4caf50; color:white; padding:2px 12px; border-radius:20px;">Paid</span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <a href="user-dashboard.php" class="btn-back"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>
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
