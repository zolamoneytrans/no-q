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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Who Favorited Me · No Q</title>
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
        
        .container { max-width: 1000px; margin: 2rem auto; padding: 0 2rem; flex: 1; }
        h1 { 
            font-size: 2rem; 
            margin-bottom: 1.5rem; 
            background: linear-gradient(145deg, var(--purple-primary), var(--orange-primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        /* Desktop Table */
        .desktop-table {
            width: 100%;
            border-collapse: collapse;
            background: rgba(255,255,255,0.7);
            backdrop-filter: blur(8px);
            border-radius: 30px;
            overflow: hidden;
        }
        .desktop-table th, .desktop-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        .desktop-table th { background: rgba(106,27,154,0.1); color: var(--purple-primary); font-weight: 600; }
        
        /* Mobile Cards */
        .users-grid {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .user-card {
            background: rgba(255,255,255,0.85);
            backdrop-filter: blur(8px);
            border-radius: 24px;
            padding: 1.2rem;
            border: 1px solid rgba(106,27,154,0.1);
            transition: all 0.2s ease;
        }
        .user-card:hover {
            background: rgba(255,255,255,0.95);
            transform: translateY(-2px);
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
        .user-name { font-weight: 700; font-size: 1.1rem; color: var(--purple-primary); }
        .booking-count {
            background: var(--orange-primary);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .card-body { display: flex; flex-direction: column; gap: 0.75rem; }
        .info-row { display: flex; flex-wrap: wrap; align-items: baseline; gap: 0.5rem; }
        .info-label { font-size: 0.7rem; font-weight: 600; text-transform: uppercase; color: #6c7a8a; min-width: 85px; }
        .info-value { font-size: 0.9rem; color: #1a2639; }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            background: rgba(255,255,255,0.6);
            border-radius: 30px;
        }
        .empty-state i {
            color: var(--purple-primary);
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
        
        @media (min-width: 769px) {
            .users-grid { display: none; }
        }
        @media (max-width: 768px) {
            .desktop-table { display: none; }
            .container { padding: 0 1rem; margin: 1rem auto; }
            h1 { font-size: 1.5rem; }
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
            <a href="business-logout.php">Logout</a>
        </nav>
    </header>

    <div class="container">
        <h1>Customers Who Favourited Your Business</h1>

        <?php if (empty($favorited_users)): ?>
            <div class="empty-state">
                <i class="fa-regular fa-heart" style="font-size: 3rem; margin-bottom: 1rem; display: block;"></i>
                <p>No customers have added your business to their favourites yet.</p>
            </div>
        <?php else: ?>
            <!-- Desktop Table -->
            <table class="desktop-table">
                <thead>
                    <tr><th>Name</th><th>Email</th><th>Favourited On</th><th>Completed Bookings</th></tr>
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
            
            <!-- Mobile Cards -->
            <div class="users-grid">
                <?php foreach ($favorited_users as $user): ?>
                <div class="user-card">
                    <div class="card-header">
                        <span class="user-name"><?= htmlspecialchars($user['name']) ?></span>
                        <span class="booking-count"><?= $user['booking_count'] ?> bookings</span>
                    </div>
                    <div class="card-body">
                        <div class="info-row"><span class="info-label">Email</span><span class="info-value"><?= htmlspecialchars($user['email']) ?></span></div>
                        <div class="info-row"><span class="info-label">Favourited</span><span class="info-value"><?= date('d M Y', strtotime($user['favorited_at'])) ?></span></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
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
    </script>
</body>
</html>