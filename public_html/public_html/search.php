<?php
session_start();
require_once 'db_connect.php';

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$rating_filter = isset($_GET['rating']) ? (int)$_GET['rating'] : 0;

// Build query
$query = "
    SELECT *, 
           (SELECT COUNT(*) FROM user_favorites WHERE business_id = b.id) as favorite_count
    FROM businesses b
    WHERE b.is_approved = 1 AND b.is_active = 1 AND b.is_test = 0 AND b.is_hidden = 0
";

$params = [];

if (!empty($search)) {
    $query .= " AND (b.name LIKE ? OR b.address LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($rating_filter > 0) {
    $query .= " AND b.rating_avg >= ?";
    $params[] = $rating_filter;
}

$query .= " ORDER BY b.rating_avg DESC, b.name ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$businesses = $stmt->fetchAll();

// Determine today's operating hours for each business (for display)
$day_of_week = strtolower(date('l'));
$open_field = $day_of_week . '_open';
$close_field = $day_of_week . '_close';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Search Car Washes · No Q</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
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
        
        /* Header */
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
            font-weight: 800; 
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
            .btn-outline { width: 100%; text-align: center; }
        }
        
        .container { max-width: 1200px; margin: 2rem auto; padding: 0 2rem; flex: 1; }
        
        h1 { 
            font-size: 2rem; 
            margin-bottom: 1rem; 
            background: linear-gradient(145deg, var(--purple-primary), var(--orange-primary)); 
            -webkit-background-clip: text; 
            -webkit-text-fill-color: transparent;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .search-bar {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            background: rgba(255,255,255,0.5);
            padding: 1rem;
            border-radius: 60px;
        }
        .search-bar input {
            flex: 1;
            padding: 1rem 1.2rem;
            border: none;
            border-radius: 40px;
            background: white;
            font-size: 1rem;
            font-family: 'Inter', sans-serif;
        }
        .search-bar select {
            padding: 1rem 1.5rem;
            border: none;
            border-radius: 40px;
            background: white;
            color: #2c3e50;
            cursor: pointer;
            font-weight: 500;
            font-family: 'Inter', sans-serif;
        }
        .search-bar button {
            padding: 1rem 2rem;
            border: none;
            border-radius: 40px;
            background: linear-gradient(135deg, var(--purple-primary), var(--purple-dark));
            color: white;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s;
        }
        .search-bar button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(106,27,154,0.3);
        }
        
        .results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }
        .business-card {
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(8px);
            border-radius: 30px;
            padding: 1.5rem;
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
            display: block;
            border: 1px solid rgba(106,27,154,0.1);
        }
        .business-card:hover {
            transform: translateY(-5px);
            background: white;
            box-shadow: 0 20px 35px -12px rgba(106,27,154,0.2);
        }
        .card-name {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--purple-primary);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
        }
        .closed-badge {
            background: #f44336;
            color: white;
            padding: 2px 8px;
            border-radius: 15px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .card-hours {
            font-size: 0.75rem;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        .card-rating {
            color: #f8b84a;
            margin-bottom: 0.5rem;
        }
        .card-address {
            color: #2c3e50;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        .card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .price {
            font-weight: 700;
            color: var(--purple-primary);
            background: rgba(106,27,154,0.1);
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
        }
        .btn-small {
            background: linear-gradient(135deg, var(--orange-primary), var(--orange-dark));
            color: white;
            padding: 0.5rem 1.2rem;
            border-radius: 30px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.2s;
        }
        .btn-small:hover {
            transform: translateY(-2px);
            box-shadow: 0 3px 10px rgba(255,152,0,0.3);
        }
        .no-results {
            text-align: center;
            padding: 3rem;
            color: #2c3e50;
            background: rgba(255,255,255,0.6);
            border-radius: 30px;
        }
        .no-results i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
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
        
        @media (max-width: 768px) {
            .container { padding: 0 1rem; margin: 1rem auto; }
            .results-grid { grid-template-columns: 1fr; }
            .search-bar { flex-direction: column; border-radius: 30px; padding: 1rem; }
            .search-bar input, .search-bar select, .search-bar button { width: 100%; }
            h1 { font-size: 1.5rem; }
        }
    </style>
</head>
<body>
    <header class="app-header">
        <div class="logo-area">
            <img src="NoQ.jpg" alt="No Q" style="height: 70px; width: auto;">
            <div>
                <span class="logo-text">No Q</span>
                <div style="font-size: 0.7rem; color: var(--purple-primary); letter-spacing: 0.5px;">No more Queues</div>
            </div>
        </div>
        <button class="menu-toggle" id="menuToggle"><i class="fa-solid fa-bars"></i></button>
        <nav class="nav-links" id="navLinks">
            <a href="index.php"><i class="fa-solid fa-house"></i> Home</a>
            <a href="search.php" style="background:rgba(106,27,154,0.1);"><i class="fa-solid fa-magnifying-glass"></i> Search</a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="user-dashboard.php"><i class="fa-regular fa-user"></i> Dashboard</a>
                <a href="logout.php" class="btn-outline"><i class="fa-regular fa-sign-out"></i> Logout</a>
            <?php else: ?>
                <a href="user-login.php"><i class="fa-regular fa-user"></i> Sign In</a>
            <?php endif; ?>
            <a href="business/business-signup.php" class="btn-outline"><i class="fa-regular fa-building"></i> List Business</a>
        </nav>
    </header>

    <div class="container">
        <h1><i class="fa-solid fa-magnifying-glass"></i> Find a Car Wash</h1>
        
        <form method="get" class="search-bar">
            <input type="text" name="search" placeholder="Search by name or location..." value="<?= htmlspecialchars($search) ?>">
            <select name="rating">
                <option value="0">⭐ All ratings</option>
                <option value="4" <?= $rating_filter == 4 ? 'selected' : '' ?>>⭐⭐⭐⭐ 4+ stars</option>
                <option value="3" <?= $rating_filter == 3 ? 'selected' : '' ?>>⭐⭐⭐ 3+ stars</option>
                <option value="2" <?= $rating_filter == 2 ? 'selected' : '' ?>>⭐⭐ 2+ stars</option>
            </select>
            <button type="submit"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
        </form>

        <div class="results-grid">
            <?php if (empty($businesses)): ?>
                <div class="no-results">
                    <i class="fa-regular fa-circle-xmark"></i>
                    <h3>No car washes found</h3>
                    <p>Try adjusting your search criteria</p>
                </div>
            <?php else: ?>
                <?php foreach ($businesses as $biz): 
                    $biz_open = $biz[$open_field] ?? null;
                    $biz_close = $biz[$close_field] ?? null;
                    if ($biz_open && $biz_close) {
                        $hours_display = date('g:i a', strtotime($biz_open)) . ' – ' . date('g:i a', strtotime($biz_close));
                    } else {
                        $hours_display = 'Closed';
                    }
                ?>
                    <a href="business-profile.php?id=<?= $biz['id'] ?>" class="business-card">
                        <div class="card-name">
                            <?= htmlspecialchars($biz['name']) ?>
                            <?php if ($biz['is_temporarily_closed'] == 1): ?>
                                <span class="closed-badge"><i class="fa-regular fa-clock"></i> Closed</span>
                            <?php endif; ?>
                        </div>
                        <div class="card-hours"><i class="fa-regular fa-clock"></i> Today: <?= $hours_display ?></div>
                        <div class="card-rating">
                            <?php 
                            $rating = round($biz['rating_avg'] * 2) / 2;
                            for ($i = 1; $i <= 5; $i++):
                                if ($i <= floor($rating)) echo '<i class="fa-solid fa-star"></i>';
                                elseif ($i == floor($rating)+1 && $rating - floor($rating) >= 0.5) echo '<i class="fa-solid fa-star-half-stroke"></i>';
                                else echo '<i class="fa-regular fa-star"></i>';
                            endfor;
                            echo ' ' . number_format($biz['rating_avg'], 1);
                            ?>
                        </div>
                        <div class="card-address">
                            <i class="fa-regular fa-location-dot"></i> <?= htmlspecialchars(substr($biz['address'], 0, 60)) . (strlen($biz['address']) > 60 ? '...' : '') ?>
                        </div>
                        <div class="card-footer">
                            <span class="price">⭐ <?= number_format($biz['rating_avg'], 1) ?> (<?= $biz['favorite_count'] ?> favs)</span>
                            <span class="btn-small">View Details <i class="fa-solid fa-arrow-right"></i></span>
                        </div>
                    </a>
                <?php endforeach; ?>
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