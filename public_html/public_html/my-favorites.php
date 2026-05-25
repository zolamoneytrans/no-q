<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: user-login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch all favorites for this user
$stmt = $pdo->prepare("
    SELECT b.*, f.created_at as favorited_on
    FROM businesses b
    INNER JOIN user_favorites f ON b.id = f.business_id
    WHERE f.user_id = ? AND b.is_approved = 1 AND b.is_active = 1
    ORDER BY f.created_at DESC
");
$stmt->execute([$user_id]);
$favorites = $stmt->fetchAll();

$pageTitle = 'My Favorites';
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
    <title>My Favorites · No Q</title>
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

        .container { max-width: 1200px; margin: 2rem auto; padding: 0 2rem; flex: 1; }
        h1 { 
            font-size: 2rem; 
            margin-bottom: 1.5rem; 
            background: linear-gradient(145deg, var(--purple-primary), var(--orange-primary)); 
            -webkit-background-clip: text; 
            -webkit-text-fill-color: transparent; 
        }
        .favorites-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 2rem;
        }
        .favorite-card {
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(10px);
            border-radius: 32px;
            padding: 1.5rem;
            border: 1px solid rgba(106,27,154,0.1);
            transition: 0.25s;
        }
        .favorite-card:hover {
            transform: translateY(-5px);
            background: white;
            box-shadow: 0 20px 40px -12px rgba(106,27,154,0.2);
        }
        .favorite-card h3 {
            font-size: 1.4rem;
            margin-bottom: 0.5rem;
            color: var(--purple-primary);
        }
        .favorite-card p {
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        .rating {
            color: #f8b84a;
            margin: 0.5rem 0;
        }
        .btn-small {
            background: linear-gradient(135deg, var(--purple-primary), var(--purple-dark));
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 30px;
            font-size: 0.9rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-top: 0.5rem;
            transition: all 0.2s ease;
        }
        .btn-small:hover {
            transform: translateY(-2px);
            opacity: 0.9;
        }
        .btn-remove {
            background: #f44336;
            margin-left: 0.5rem;
        }
        .btn-remove:hover {
            background: #d32f2f;
        }
        .empty {
            text-align: center;
            padding: 3rem;
            color: #2c3e50;
        }
        .empty i {
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
            .container {
                margin: 1rem auto;
                padding: 0 1rem;
            }
            h1 {
                font-size: 1.5rem;
            }
            .favorites-grid {
                gap: 1rem;
            }
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
        <h1>My Favorite Car Washes</h1>
        <?php if (empty($favorites)): ?>
            <div class="empty">
                <i class="fa-regular fa-heart"></i>
                <h3>No favorites yet</h3>
                <p>Browse car washes and click the heart icon to save your favorites.</p>
                <a href="search.php" class="btn-small" style="background: var(--orange-primary);">Browse Car Washes</a>
            </div>
        <?php else: ?>
            <div class="favorites-grid">
                <?php foreach ($favorites as $biz): ?>
                <div class="favorite-card">
                    <h3><?= htmlspecialchars($biz['name']) ?></h3>
                    <p><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($biz['address']) ?></p>
                    <div class="rating">
                        <?php
                        $rating = round($biz['rating_avg'] * 2) / 2;
                        for ($i=1;$i<=5;$i++):
                            if ($i <= floor($rating)) echo '<i class="fa-solid fa-star"></i>';
                            elseif ($i == floor($rating)+1 && $rating - floor($rating) >= 0.5) echo '<i class="fa-solid fa-star-half-stroke"></i>';
                            else echo '<i class="fa-regular fa-star"></i>';
                        endfor;
                        echo ' ' . number_format($biz['rating_avg'],1);
                        ?>
                    </div>
                    <div>
                        <a href="book.php?id=<?= $biz['id'] ?>" class="btn-small">Book Now</a>
                        <a href="toggle-favorite.php?id=<?= $biz['id'] ?>&redirect=my-favorites.php" class="btn-small btn-remove">Remove</a>
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
