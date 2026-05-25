<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: user-login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Mark a single notification as read if requested
if (isset($_GET['mark_read'])) {
    $notif_id = (int)$_GET['mark_read'];
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$notif_id, $user_id]);
    header('Location: notifications.php');
    exit;
}

// Mark all as read
if (isset($_GET['mark_all_read'])) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$user_id]);
    header('Location: notifications.php');
    exit;
}

// Fetch all notifications for this user, newest first
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll();
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
    <title>Notifications · No Q</title>
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
            max-width: 900px;
            margin: 2rem auto;
            padding: 0 2rem;
            flex: 1;
        }
        .card {
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(10px);
            border-radius: 40px;
            padding: 2rem;
            border: 1px solid rgba(106,27,154,0.1);
            box-shadow: 0 20px 40px -12px rgba(106,27,154,0.2);
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid rgba(106,27,154,0.1);
        }
        .card-header h2 {
            font-size: 1.8rem;
            background: linear-gradient(145deg, var(--purple-primary), var(--orange-primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        /* Stats Summary */
        .stats-summary {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        .stat-badge {
            background: rgba(106,27,154,0.1);
            padding: 0.5rem 1rem;
            border-radius: 30px;
            font-size: 0.85rem;
            color: var(--purple-primary);
        }
        .stat-badge strong {
            font-size: 1.2rem;
            margin-right: 0.3rem;
        }
        
        /* Notification Items */
        .notifications-list {
            display: flex;
            flex-direction: column;
            gap: 0.8rem;
        }
        .notification-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.2rem;
            border-radius: 30px;
            transition: all 0.2s ease;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .notification-item.unread {
            background: rgba(255,152,0,0.1);
            border-left: 4px solid var(--orange-primary);
        }
        .notification-item.read {
            background: rgba(255,255,255,0.5);
        }
        .notification-item:hover {
            transform: translateX(5px);
        }
        .notification-content {
            flex: 1;
        }
        .notification-message {
            font-size: 1rem;
            color: #2c3e50;
            margin-bottom: 0.3rem;
            line-height: 1.4;
        }
        .notification-time {
            font-size: 0.75rem;
            color: #888;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        .notification-time i {
            font-size: 0.7rem;
        }
        .notification-link {
            color: var(--purple-primary);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            margin-top: 0.5rem;
            transition: color 0.2s;
        }
        .notification-link:hover {
            color: var(--orange-primary);
        }
        .notification-actions {
            display: flex;
            gap: 0.5rem;
        }
        .btn-small {
            background: linear-gradient(135deg, var(--purple-primary), var(--purple-dark));
            color: white;
            border: none;
            padding: 0.4rem 1rem;
            border-radius: 30px;
            font-size: 0.8rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s ease;
        }
        .btn-small:hover {
            transform: translateY(-2px);
            opacity: 0.9;
        }
        .btn-small.mark-read {
            background: #777;
        }
        .btn-small.mark-all {
            background: var(--orange-primary);
        }
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #2c3e50;
        }
        .empty-state i {
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
        
        @media (max-width: 768px) {
            .container { padding: 0 1rem; margin: 1rem auto; }
            .card { padding: 1.2rem; }
            .card-header h2 { font-size: 1.4rem; }
            .notification-item { flex-direction: column; align-items: flex-start; }
            .notification-actions { width: 100%; justify-content: flex-end; }
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
        <button class="menu-toggle" id="menuToggle" aria-label="Menu">
            <i class="fa-solid fa-bars"></i>
        </button>
        <nav class="nav-links" id="navLinks">
            <a href="index.php">Home</a>
            <a href="user-dashboard.php">Dashboard</a>
            <a href="logout.php">Logout</a>
        </nav>
    </header>

    <div class="container">
        <div class="card">
            <div class="card-header">
                <h2><i class="fa-regular fa-bell"></i> Notifications</h2>
                <?php if (count($notifications) > 0): ?>
                    <a href="?mark_all_read=1" class="btn-small mark-all"><i class="fa-regular fa-check-double"></i> Mark all as read</a>
                <?php endif; ?>
            </div>

            <?php 
            $unread_count = 0;
            foreach ($notifications as $n) {
                if (!$n['is_read']) $unread_count++;
            }
            ?>
            
            <?php if (count($notifications) > 0): ?>
                <div class="stats-summary">
                    <div class="stat-badge">
                        <strong><?= count($notifications) ?></strong> Total
                    </div>
                    <div class="stat-badge">
                        <strong><?= $unread_count ?></strong> Unread
                    </div>
                </div>
            <?php endif; ?>

            <?php if (empty($notifications)): ?>
                <div class="empty-state">
                    <i class="fa-regular fa-bell-slash"></i>
                    <h3>No notifications</h3>
                    <p>You're all caught up! When you receive notifications, they'll appear here.</p>
                </div>
            <?php else: ?>
                <div class="notifications-list">
                    <?php foreach ($notifications as $n): ?>
                    <div class="notification-item <?= $n['is_read'] ? 'read' : 'unread' ?>">
                        <div class="notification-content">
                            <div class="notification-message">
                                <?= htmlspecialchars($n['message']) ?>
                            </div>
                            <div class="notification-time">
                                <i class="fa-regular fa-clock"></i> <?= date('d M Y H:i', strtotime($n['created_at'])) ?>
                            </div>
                            <?php if ($n['link']): ?>
                                <a href="notification-details.php?id=<?= $n['id'] ?>" class="notification-link">
                                    <i class="fa-regular fa-arrow-right"></i> View details
                                </a>
                            <?php endif; ?>
                        </div>
                        <div class="notification-actions">
                            <?php if (!$n['is_read']): ?>
                                <a href="?mark_read=<?= $n['id'] ?>" class="btn-small mark-read">
                                    <i class="fa-regular fa-check"></i> Mark read
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
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
