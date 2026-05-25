<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin-login.php');
    exit;
}

// Process status update
if (isset($_GET['action']) && isset($_GET['id'])) {
    $req_id = (int)$_GET['id'];
    $action = $_GET['action'];

    if ($action === 'process') {
        $stmt = $pdo->prepare("UPDATE withdrawal_requests SET status = 'processing', processed_at = NOW() WHERE id = ?");
        $stmt->execute([$req_id]);
        header('Location: withdrawals.php');
        exit;
    } elseif ($action === 'complete') {
        $stmt = $pdo->prepare("UPDATE withdrawal_requests SET status = 'completed', processed_at = NOW() WHERE id = ?");
        $stmt->execute([$req_id]);
        header('Location: withdrawals.php');
        exit;
    } elseif ($action === 'reject') {
        // Refund the amount to business wallet
        $req = $pdo->prepare("SELECT business_id, amount FROM withdrawal_requests WHERE id = ?");
        $req->execute([$req_id]);
        $r = $req->fetch();
        if ($r) {
            $pdo->prepare("UPDATE businesses SET wallet_balance = wallet_balance + ? WHERE id = ?")->execute([$r['amount'], $r['business_id']]);
        }
        $stmt = $pdo->prepare("DELETE FROM withdrawal_requests WHERE id = ?");
        $stmt->execute([$req_id]);
        header('Location: withdrawals.php');
        exit;
    }
}

// Fetch all requests
$stmt = $pdo->query("
    SELECT w.*, b.name as business_name, b.email as business_email
    FROM withdrawal_requests w
    JOIN businesses b ON w.business_id = b.id
    ORDER BY w.requested_at DESC
");
$requests = $stmt->fetchAll();

// Get pending count for badge
$pending_count = $pdo->query("SELECT COUNT(*) FROM withdrawal_requests WHERE status = 'pending'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Withdrawal Requests · Admin</title>
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
        
        /* Hamburger */
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
            .menu-toggle {
                display: block;
            }
            .app-header {
                padding: 0.8rem 1rem;
               
            }
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
            .nav-links.show {
                display: flex;
            }
            .nav-links a {
                width: 100%;
                text-align: center;
                padding: 0.8rem;
                border-radius: 30px;
            }
        }
        
        /* Container */
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
            flex: 1;
        }
        h1 {
            font-size: 1.8rem;
            margin-bottom: 1.5rem;
            background: linear-gradient(145deg, #1e3c72, #2a5298);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        /* Stats Cards */
        .stats-grid {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: rgba(255,255,255,0.7);
            backdrop-filter: blur(8px);
            border-radius: 24px;
            padding: 1rem 1.5rem;
            flex: 1;
            min-width: 120px;
            text-align: center;
            border: 1px solid rgba(255,255,255,0.6);
        }
        .stat-card .number {
            font-size: 2rem;
            font-weight: 800;
            color: #1e3c72;
        }
        .stat-card .label {
            font-size: 0.8rem;
            color: #2c3e50;
        }
        
        /* Table Wrapper - Mobile Friendly */
        .requests-grid {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
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
        .desktop-table th {
            background: rgba(30,60,114,0.1);
            color: #1e3c72;
            font-weight: 600;
        }
        .desktop-table tr:last-child td {
            border-bottom: none;
        }
        
        /* Mobile Cards */
        .request-card {
            background: rgba(255,255,255,0.85);
            backdrop-filter: blur(8px);
            border-radius: 24px;
            padding: 1.2rem;
            border: 1px solid rgba(255,255,255,0.6);
            transition: all 0.2s ease;
        }
        .request-card:hover {
            background: rgba(255,255,255,0.95);
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        .request-id {
            font-family: monospace;
            font-size: 0.9rem;
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
        .status-pending { background: #ff9800; color: white; }
        .status-processing { background: #2196f3; color: white; }
        .status-completed { background: #4caf50; color: white; }
        
        .card-body {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }
        .info-row {
            display: flex;
            flex-wrap: wrap;
            align-items: baseline;
            gap: 0.5rem;
        }
        .info-label {
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6c7a8a;
            min-width: 90px;
        }
        .info-value {
            font-size: 0.9rem;
            color: #1a2639;
            font-weight: 500;
        }
        .business-name {
            font-weight: 700;
            color: #1e3c72;
        }
        .business-email {
            font-size: 0.75rem;
            color: #6c7a8a;
            word-break: break-all;
        }
        .amount {
            font-size: 1.2rem;
            font-weight: 800;
            color: #1e3c72;
        }
        
        .card-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.5rem;
            padding-top: 0.75rem;
            border-top: 1px solid rgba(0,0,0,0.05);
        }
        
        /* Buttons */
        .btn-small {
            background: #1e3c72;
            color: white;
            border: none;
            padding: 0.4rem 1rem;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            transition: 0.2s;
        }
        .btn-small.orange { background: #ff9800; }
        .btn-small.green { background: #4caf50; }
        .btn-small.red { background: #f44336; }
        .btn-small:hover { transform: translateY(-1px); }
        
        /* Notification Badge */
        .notification-badge {
            background: #f44336;
            color: white;
            border-radius: 20px;
            padding: 2px 8px;
            font-size: 0.7rem;
            margin-left: 5px;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            background: rgba(255,255,255,0.6);
            border-radius: 30px;
            color: #6c7a8a;
        }
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .app-footer {
            background: rgba(255,255,255,0.6);
            padding: 1.5rem;
            text-align: center;
            margin-top: auto;
            font-size: 0.8rem;
            color: #6c7a8a;
        }
        
        /* Desktop breakpoint */
        @media (min-width: 992px) {
            .requests-grid {
                display: none;
            }
            .desktop-table {
                display: table;
            }
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
                margin: 1rem auto;
            }
            h1 {
                font-size: 1.4rem;
            }
            .stats-grid {
                gap: 0.8rem;
            }
            .stat-card {
                padding: 0.8rem;
            }
            .stat-card .number {
                font-size: 1.5rem;
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                flex-direction: column;
            }
            .stat-card {
                text-align: left;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .stat-card .number {
                font-size: 1.8rem;
            }
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
            <a href="payments.php">Payments</a>
            <a href="withdrawals.php" style="background:rgba(42,82,152,0.1);">Withdrawals <?php if ($pending_count > 0): ?><span class="notification-badge"><?= $pending_count ?></span><?php endif; ?></a>
            <a href="verify-banks.php">Verify Banks</a>
            <a href="reports.php">Reports</a>
            <a href="admin-logout.php">Logout</a>
        </nav>
    </header>

    <div class="container">
        <h1><i class="fa-regular fa-arrow-right-from-bracket"></i> Withdrawal Requests</h1>
        
        <!-- Stats Summary -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="number"><?= count($requests) ?></div>
                <div class="label">Total Requests</div>
            </div>
            <div class="stat-card">
                <div class="number"><?= $pending_count ?></div>
                <div class="label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="number"><?= count(array_filter($requests, fn($r) => $r['status'] == 'processing')) ?></div>
                <div class="label">Processing</div>
            </div>
            <div class="stat-card">
                <div class="number"><?= count(array_filter($requests, fn($r) => $r['status'] == 'completed')) ?></div>
                <div class="label">Completed</div>
            </div>
        </div>
        
        <?php if (empty($requests)): ?>
            <div class="empty-state">
                <i class="fa-regular fa-receipt"></i>
                <p>No withdrawal requests yet.</p>
                <p style="font-size:0.85rem; margin-top:0.5rem;">When businesses request withdrawals, they'll appear here.</p>
            </div>
        <?php else: ?>
            
            <!-- Desktop Table -->
            <table class="desktop-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Business</th>
                        <th>Amount</th>
                        <th>Requested</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $r): ?>
                    <tr>
                        <td><?= $r['id'] ?></td>
                        <td>
                            <strong><?= htmlspecialchars($r['business_name']) ?></strong><br>
                            <small><?= htmlspecialchars($r['business_email']) ?></small>
                        </td>
                        <td><strong>R <?= number_format($r['amount'], 2) ?></strong></td>
                        <td><?= date('d M Y H:i', strtotime($r['requested_at'])) ?></td>
                        <td><span class="status-badge status-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span></td>
                        <td>
                            <?php if ($r['status'] == 'pending'): ?>
                                <a href="?action=process&id=<?= $r['id'] ?>" class="btn-small orange" onclick="return confirm('Mark as processing?')">Process</a>
                            <?php elseif ($r['status'] == 'processing'): ?>
                                <a href="?action=complete&id=<?= $r['id'] ?>" class="btn-small green" onclick="return confirm('Mark as completed?')">Complete</a>
                            <?php endif; ?>
                            <?php if ($r['status'] != 'completed'): ?>
                                <a href="?action=reject&id=<?= $r['id'] ?>" class="btn-small red" onclick="return confirm('Reject this request? This will refund the amount to the business wallet.')">Reject</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Mobile Cards -->
            <div class="requests-grid">
                <?php foreach ($requests as $r): ?>
                <div class="request-card">
                    <div class="card-header">
                        <span class="request-id"><i class="fa-regular fa-hashtag"></i> #<?= $r['id'] ?></span>
                        <span class="status-badge status-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span>
                    </div>
                    <div class="card-body">
                        <div class="info-row">
                            <span class="info-label"><i class="fa-regular fa-building"></i> Business</span>
                            <span class="info-value">
                                <span class="business-name"><?= htmlspecialchars($r['business_name']) ?></span>
                                <br><span class="business-email"><?= htmlspecialchars($r['business_email']) ?></span>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label"><i class="fa-regular fa-money-bill-1"></i> Amount</span>
                            <span class="info-value amount">R <?= number_format($r['amount'], 2) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label"><i class="fa-regular fa-calendar"></i> Requested</span>
                            <span class="info-value"><?= date('d M Y H:i', strtotime($r['requested_at'])) ?></span>
                        </div>
                    </div>
                    <div class="card-actions">
                        <?php if ($r['status'] == 'pending'): ?>
                            <a href="?action=process&id=<?= $r['id'] ?>" class="btn-small orange" onclick="return confirm('Mark as processing?')"><i class="fa-solid fa-clock"></i> Process</a>
                        <?php elseif ($r['status'] == 'processing'): ?>
                            <a href="?action=complete&id=<?= $r['id'] ?>" class="btn-small green" onclick="return confirm('Mark as completed?')"><i class="fa-solid fa-check"></i> Complete</a>
                        <?php endif; ?>
                        <?php if ($r['status'] != 'completed'): ?>
                            <a href="?action=reject&id=<?= $r['id'] ?>" class="btn-small red" onclick="return confirm('Reject this request? This will refund the amount to the business wallet.')"><i class="fa-solid fa-xmark"></i> Reject</a>
                        <?php endif; ?>
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
