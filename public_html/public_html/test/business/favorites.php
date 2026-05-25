<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['business_id'])) {
    header('Location: business-login.php');
    exit;
}

$business_id = $_SESSION['business_id'];

// Fetch users who favorited this business
$stmt = $pdo->prepare("
    SELECT u.id, u.name, u.email, f.created_at as favorited_at
    FROM user_favorites f
    JOIN users u ON f.user_id = u.id
    WHERE f.business_id = ?
    ORDER BY f.created_at DESC
");
$stmt->execute([$business_id]);
$favorited_users = $stmt->fetchAll();

// For each user, count completed bookings at this business
foreach ($favorited_users as &$user) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = ? AND business_id = ? AND status = 'completed'");
    $stmt->execute([$user['id'], $business_id]);
    $user['booking_count'] = $stmt->fetchColumn();
}
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
    <link rel="apple-touch-icon" sizes="180x180" href="../apple-touch-icon.png" />
    <meta name="apple-mobile-web-app-title" content="No Q" />
    <link rel="manifest" href="../site.webmanifest" />
    <title>Who Favorited Me · No Q</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
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
        .app-header {
            background: rgba(255,255,255,0.7);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255,255,255,0.5);
            padding: 0.8rem 2rem;
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
            .menu-toggle {
                display: block;
            }
            .nav-links {
                display: none;
                width: 100%;
                flex-direction: column;
                align-items: center;
                gap: 0.5rem;
                padding: 1rem 0;
                background: rgba(255,255,255,0.9);
                backdrop-filter: blur(10px);
                border-radius: 30px;
                margin-top: 1rem;
            }
            .nav-links.show {
                display: flex;
            }
            .app-header {
                padding: 0.8rem 1rem;
            }
            .nav-links a {
                width: 100%;
                text-align: center;
                padding: 0.8rem;
            }
        }

        .container {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 0 2rem;
            flex: 1;
        }
        h1 {
            font-size: 2rem;
            margin-bottom: 1.5rem;
            background: linear-gradient(145deg, #1e3c72, #2a5298);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: rgba(255,255,255,0.7);
            backdrop-filter: blur(8px);
            border-radius: 30px;
            overflow: hidden;
        }
        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        th {
            background: rgba(30,60,114,0.1);
            color: #1e3c72;
        }
        .app-footer {
            background: rgba(255,255,255,0.6);
            padding: 2rem;
            text-align: center;
            margin-top: auto;
        }
        .empty-state {
            text-align: center;
            padding: 3rem;
            background: rgba(255,255,255,0.5);
            border-radius: 30px;
        }
    </style>
</head>
<body>
    <header class="app-header">
        <div class="logo-area">
            <img src="/logo.jpeg" alt="No Q" style="height: 40px; width: auto;">
            <span class="logo-text">CarWash<span style="font-weight:400;">Connect</span></span>
        </div>
        <button class="menu-toggle" id="menuToggle" aria-label="Menu">
            <i class="fa-solid fa-bars"></i>
        </button>
        <nav class="nav-links" id="navLinks">
            <a href="../index.php">Home</a>
            <a href="business-dashboard.php">Dashboard</a>
            <a href="business-logout.php">Logout</a>
        </nav>
    </header>

    <div class="container">
        <h1>Customers Who Favorited Your Business</h1>

        <?php if (empty($favorited_users)): ?>
            <div class="empty-state">
                <i class="fa-regular fa-heart" style="font-size: 3rem; color: #2a5298; margin-bottom: 1rem;"></i>
                <p>No customers have added your business to their favorites yet.</p>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Favorited On</th>
                        <th>Completed Bookings</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($favorited_users as $user): ?>
                    <tr>
                        <td><?= htmlspecialchars($user['name']) ?></td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td><?= date('d M Y', strtotime($user['favorited_at'])) ?></td>
                        <td><?= $user['booking_count'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <footer class="app-footer">
        <p>&copy; <?= date('Y'); ?> No Q</p>
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
