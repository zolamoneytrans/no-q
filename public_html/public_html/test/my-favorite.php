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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .logo-area { display: flex; align-items: center; gap: 10px; }
        .logo-icon { background: #1e3c72; color: white; width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        .logo-text { font-weight: 700; font-size: 1.5rem; background: linear-gradient(135deg, #1e3c72, #2a5298); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .nav-links { display: flex; gap: 1.2rem; }
        .nav-links a { text-decoration: none; color: #2c3e50; }
        .container { max-width: 1200px; margin: 2rem auto; padding: 0 2rem; flex: 1; }
        h1 { font-size: 2rem; margin-bottom: 1.5rem; background: linear-gradient(145deg, #1e3c72, #2a5298); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .favorites-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 2rem;
        }
        .favorite-card {
            background: rgba(255,255,255,0.8);
            backdrop-filter: blur(10px);
            border-radius: 32px;
            padding: 1.5rem;
            border: 1px solid rgba(255,255,255,0.6);
            transition: 0.25s;
        }
        .favorite-card:hover {
            transform: translateY(-5px);
            background: white;
            box-shadow: 0 20px 40px -12px rgba(0,20,40,0.2);
        }
        .favorite-card h3 {
            font-size: 1.4rem;
            margin-bottom: 0.5rem;
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
            background: #1e3c72;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 30px;
            font-size: 0.9rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-top: 0.5rem;
        }
        .btn-remove {
            background: #f44336;
            margin-left: 0.5rem;
        }
        .empty {
            text-align: center;
            padding: 3rem;
            color: #2c3e50;
        }
        .empty i {
            font-size: 3rem;
            color: #2a5298;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        .app-footer {
            background: rgba(255,255,255,0.6);
            padding: 2rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <header class="app-header">
        <div class="logo-area">
            <div class="logo-icon"><i class="fas fa-car-wash"></i></div>
            <span class="logo-text">CarWash<span style="font-weight:400;">Connect</span></span>
        </div>
        <nav class="nav-links">
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
                <a href="search.php" class="btn-small" style="background:#ff9800;">Browse Car Washes</a>
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
        <p>&copy; <?= date('Y'); ?> No Q</p>
    </footer>
</body>
</html>
