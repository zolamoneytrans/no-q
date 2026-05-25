<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: user-login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$notification_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch notification, ensure it belongs to the user
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE id = ? AND user_id = ?");
$stmt->execute([$notification_id, $user_id]);
$notification = $stmt->fetch();

if (!$notification) {
    header('Location: notifications.php');
    exit;
}

// Mark as read when viewed
if (!$notification['is_read']) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
    $stmt->execute([$notification_id]);
    $notification['is_read'] = 1;
}


$pageTitle = 'Notification Details';
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
    <title>Notification · No Q</title>
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
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
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
        .nav-links { display: flex; gap: 1.2rem; align-items: center; }
        .nav-links a { 
            text-decoration: none; 
            font-weight: 500; 
            color: #2c3e50; 
            transition: 0.2s; 
            font-size: 0.95rem; 
            display: flex; 
            align-items: center; 
            gap: 6px; 
            padding: 0.5rem 0.8rem; 
            border-radius: 40px;
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
        .nav-links a i { font-size: 1rem; color: var(--purple-primary); }
        .nav-links a:hover { background: rgba(106,27,154,0.08); color: var(--purple-primary); }
        .nav-links .btn-outline {
            border: 1.5px solid var(--purple-primary);
            padding: 0.4rem 1.2rem;
            border-radius: 40px;
            background: white;
            font-weight: 600;
        }
        .nav-links .btn-outline:hover { background: var(--purple-primary); color: white; }
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
            .btn-outline { width: 100%; text-align: center; }
        }

        .container {
            max-width: 800px;
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
        h1 {
            font-size: 2rem;
            margin-bottom: 1rem;
            background: linear-gradient(145deg, var(--purple-primary), var(--orange-primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .message-box {
            background: rgba(106,27,154,0.05);
            border-radius: 30px;
            padding: 1.5rem;
            margin: 1.5rem 0;
            font-size: 1.1rem;
            line-height: 1.6;
        }
        .meta {
            color: #2c3e50;
            margin-bottom: 1rem;
        }
        .btn {
            background: linear-gradient(135deg, var(--purple-primary), var(--purple-dark));
            color: white;
            text-decoration: none;
            padding: 0.8rem 2rem;
            border-radius: 40px;
            font-weight: 500;
            display: inline-block;
            margin-top: 1rem;
            transition: all 0.2s ease;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(106,27,154,0.3);
        }
        .back-link {
            color: var(--purple-primary);
            text-decoration: none;
            transition: color 0.2s;
        }
        .back-link:hover {
            color: var(--orange-primary);
            text-decoration: underline;
        }
        .app-footer {
            background: rgba(255,255,255,0.6);
            backdrop-filter: blur(8px);
            border-top: 1px solid white;
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
            .card { padding: 1.5rem; }
            h1 { font-size: 1.5rem; }
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
            <a href="index.php">Home</a>
            <a href="user-dashboard.php">Dashboard</a>
            <a href="notifications.php">Notifications</a>
            <a href="logout.php">Logout</a>
        </nav>
    </header>

    <div class="container">
        <div class="card">
            <h1>Notification Details</h1>
            <div class="meta">
                <i class="fa-regular fa-clock"></i> <?= date('l, d F Y H:i', strtotime($notification['created_at'])) ?>
                <?php if ($notification['is_read']): ?>
                    <span style="margin-left:1rem; background:#4caf50; color:white; padding:0.2rem 1rem; border-radius:30px; font-size:0.8rem;">Read</span>
                <?php else: ?>
                    <span style="margin-left:1rem; background:var(--orange-primary); color:white; padding:0.2rem 1rem; border-radius:30px; font-size:0.8rem;">New</span>
                <?php endif; ?>
            </div>
            <div class="message-box">
                <?= nl2br(htmlspecialchars($notification['message'])) ?>
            </div>
            <?php if ($notification['link']): ?>
                <a href="<?= htmlspecialchars($notification['link']) ?>" class="btn"><i class="fa-regular fa-arrow-right"></i> Go to Dashboard</a>
            <?php endif; ?>
            <div style="margin-top:2rem;">
                <a href="notifications.php" class="back-link">← Back to notifications</a>
            </div>
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
    </script>
</body>
</html>
