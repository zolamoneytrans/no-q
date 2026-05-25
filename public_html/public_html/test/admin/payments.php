<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin-login.php');
    exit;
}

// Get all payments with user and business details
$stmt = $pdo->prepare("
    SELECT p.*, 
           u.name as user_name, u.email as user_email,
           b.name as business_name,
           bk.booking_code, bk.booking_date
    FROM payments p
    JOIN users u ON p.user_id = u.id
    JOIN bookings bk ON p.booking_id = bk.id
    JOIN businesses b ON bk.business_id = b.id
    ORDER BY p.created_at DESC
");
$stmt->execute();
$payments = $stmt->fetchAll();

// Get total stats
$total_paid = $pdo->query("SELECT SUM(amount) FROM payments WHERE status = 'completed'")->fetchColumn() ?: 0;
$total_payments = $pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'completed'")->fetchColumn();
$today_payments = $pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'completed' AND DATE(created_at) = CURDATE()")->fetchColumn();
$today_amount = $pdo->query("SELECT SUM(amount) FROM payments WHERE status = 'completed' AND DATE(created_at) = CURDATE()")->fetchColumn() ?: 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Payments · Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(145deg, #f6f9fc 0%, #e9f1f8 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        /* Header */
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
        .logo-text { font-weight: 700; font-size: 1.5rem; background: linear-gradient(135deg, #1e3c72, #2a5298); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .nav-links { display: flex; gap: 1.2rem; align-items: center; flex-wrap: wrap; }
        .nav-links a { text-decoration: none; font-weight: 500; color: #2c3e50; padding: 0.5rem 0.8rem; border-radius: 40px; transition: 0.2s; }
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
        
        /* Mobile Navigation */
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
                right: 0;
                z-index: 200;
            }
            .nav-links.show { display: flex; }
            .nav-links a { width: 100%; text-align: center; padding: 0.8rem; border-radius: 30px; }
        }
        
        .container { max-width: 1400px; margin: 2rem auto; padding: 0 2rem; flex: 1; }
        h1 { font-size: 1.8rem; margin-bottom: 1.5rem; color: #1e3c72; display: flex; align-items: center; gap: 0.5rem; }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: rgba(255,255,255,0.7);
            backdrop-filter: blur(8px);
            border-radius: 24px;
            padding: 1rem;
            text-align: center;
            border: 1px solid rgba(255,255,255,0.6);
        }
        .stat-card .value { font-size: 1.8rem; font-weight: 800; color: #1e3c72; }
        .stat-card .label { color: #2c3e50; font-size: 0.8rem; margin-top: 0.3rem; }
        
        /* Mobile Cards */
        .payments-grid {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .payment-card {
            background: rgba(255,255,255,0.85);
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
        .payment-id {
            font-family: monospace;
            font-size: 0.85rem;
            font-weight: 700;
            background: rgba(30,60,114,0.1);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            color: #1e3c72;
        }
        .status-badge {
            padding: 0.25rem 1rem;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
        }
        .status-completed { background: #4caf50; color: white; }
        .status-pending { background: #ff9800; color: white; }
        
        .card-body { display: flex; flex-direction: column; gap: 0.75rem; }
        .info-row { display: flex; flex-wrap: wrap; align-items: baseline; gap: 0.5rem; }
        .info-label { font-size: 0.7rem; font-weight: 600; text-transform: uppercase; color: #6c7a8a; min-width: 85px; }
        .info-value { font-size: 0.9rem; color: #1a2639; }
        .amount { font-weight: 800; color: #1e3c72; font-size: 1.1rem; }
        
        /* Desktop Table */
        .desktop-table {
            display: none;
            width: 100%;
            border-collapse: collapse;
            background: rgba(255,255,255,0.5);
            backdrop-filter: blur(8px);
            border-radius: 30px;
            overflow: hidden;
        }
        .desktop-table th, .desktop-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        .desktop-table th { background: rgba(30,60,114,0.1); color: #1e3c72; font-weight: 600; }
        
        @media (min-width: 992px) {
            .payments-grid { display: none; }
            .desktop-table { display: table; }
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            background: rgba(255,255,255,0.6);
            border-radius: 30px;
            color: #6c7a8a;
        }
        .empty-state i { font-size: 3rem; margin-bottom: 1rem; opacity: 0.5; }
        
        .app-footer {
            background: rgba(255,255,255,0.6);
            padding: 1.5rem;
            text-align: center;
            margin-top: 2rem;
            font-size: 0.8rem;
            color: #6c7a8a;
        }
        
        @media (max-width: 768px) {
            .container { padding: 0 1rem; margin: 1rem auto; }
            h1 { font-size: 1.4rem; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 0.8rem; }
            .stat-card .value { font-size: 1.4rem; }
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
            <a href="bookings.php">Bookings</a>
            <a href="payments.php" style="background:rgba(42,82,152,0.1);">Payments</a>
            <a href="withdrawals.php">Withdrawals</a>
            <a href="verify-banks.php">Verify Banks</a>
            <a href="reports.php">Reports</a>
            <a href="admin-logout.php">Logout</a>
        </nav>
    </header>

    <div class="container">
        <h1><i class="fa-regular fa-credit-card"></i> All Payments</h1>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card"><div class="value">R <?= number_format($total_paid, 2) ?></div><div class="label">Total Revenue</div></div>
            <div class="stat-card"><div class="value"><?= $total_payments ?></div><div class="label">Total Payments</div></div>
            <div class="stat-card"><div class="value"><?= $today_payments ?></div><div class="label">Today's Payments</div></div>
            <div class="stat-card"><div class="value">R <?= number_format($today_amount, 2) ?></div><div class="label">Today's Amount</div></div>
        </div>

        <?php if (empty($payments)): ?>
            <div class="empty-state">
                <i class="fa-regular fa-receipt"></i>
                <p>No payments recorded yet.</p>
            </div>
        <?php else: ?>
            
            <!-- Desktop Table -->
            <table class="desktop-table">
                <thead>
                    <tr><th>ID</th><th>User</th><th>Business</th><th>Booking</th><th>Amount</th><th>Status</th><th>Date</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $p): ?>
                    <tr>
                        <td><?= $p['id'] ?></td>
                        <td><?= htmlspecialchars($p['user_name']) ?><br><small><?= htmlspecialchars($p['user_email']) ?></small></td>
                        <td><?= htmlspecialchars($p['business_name']) ?></td>
                        <td><?= $p['booking_code'] ?><br><small><?= $p['booking_date'] ?></small></td>
                        <td><strong>R <?= number_format($p['amount'], 2) ?></strong></td>
                        <td><span class="status-badge status-<?= $p['status'] ?>"><?= ucfirst($p['status']) ?></span></td>
                        <td><?= date('d M Y H:i', strtotime($p['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Mobile Cards -->
            <div class="payments-grid">
                <?php foreach ($payments as $p): ?>
                <div class="payment-card">
                    <div class="card-header">
                        <span class="payment-id"><i class="fa-regular fa-hashtag"></i> #<?= $p['id'] ?></span>
                        <span class="status-badge status-<?= $p['status'] ?>"><?= ucfirst($p['status']) ?></span>
                    </div>
                    <div class="card-body">
                        <div class="info-row"><span class="info-label">User</span><span class="info-value"><strong><?= htmlspecialchars($p['user_name']) ?></strong><br><small><?= htmlspecialchars($p['user_email']) ?></small></span></div>
                        <div class="info-row"><span class="info-label">Business</span><span class="info-value"><?= htmlspecialchars($p['business_name']) ?></span></div>
                        <div class="info-row"><span class="info-label">Booking</span><span class="info-value"><?= $p['booking_code'] ?> (<?= $p['booking_date'] ?>)</span></div>
                        <div class="info-row"><span class="info-label">Amount</span><span class="info-value amount">R <?= number_format($p['amount'], 2) ?></span></div>
                        <div class="info-row"><span class="info-label">Date</span><span class="info-value"><?= date('d M Y H:i', strtotime($p['created_at'])) ?></span></div>
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
