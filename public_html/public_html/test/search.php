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
    WHERE b.is_approved = 1 AND b.is_active = 1
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Car Washes · No Q</title>
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
            background: transparent;
            border: none;
            color: #1e3c72;
        }
        @media (max-width: 768px) {
            .menu-toggle { display: block; }
            .nav-links { display: none; width: 100%; flex-direction: column; background: rgba(255,255,255,0.95); border-radius: 30px; padding: 1rem; margin-top: 1rem; }
            .nav-links.show { display: flex; }
            .app-header { padding: 0.8rem 1rem; }
        }
        .container { max-width: 1200px; margin: 2rem auto; padding: 0 2rem; flex: 1; }
        h1 { font-size: 2rem; margin-bottom: 1rem; background: linear-gradient(145deg, #1e3c72, #2a5298); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .search-bar {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        .search-bar input {
            flex: 1;
            padding: 1rem;
            border: none;
            border-radius: 40px;
            background: rgba(255,255,255,0.7);
            font-size: 1rem;
        }
        .search-bar select, .search-bar button {
            padding: 1rem 1.5rem;
            border: none;
            border-radius: 40px;
            background: #1e3c72;
            color: white;
            cursor: pointer;
            font-weight: 600;
        }
        .results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }
        .business-card {
            background: rgba(255,255,255,0.7);
            backdrop-filter: blur(8px);
            border-radius: 30px;
            padding: 1.5rem;
            transition: transform 0.2s;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        .business-card:hover {
            transform: translateY(-5px);
            background: white;
        }
        .card-name {
            font-size: 1.3rem;
            font-weight: 700;
            color: #1e3c72;
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
            font-size: 11px;
            margin-left: 5px;
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
            color: #1e3c72;
        }
        .btn-small {
            background: #ff9800;
            color: white;
            padding: 0.4rem 1rem;
            border-radius: 30px;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 600;
        }
        .no-results {
            text-align: center;
            padding: 3rem;
            color: #2c3e50;
        }
        .app-footer {
            background: rgba(255,255,255,0.6);
            padding: 2rem;
            text-align: center;
            margin-top: auto;
        }
        @media (max-width: 768px) {
            .results-grid { grid-template-columns: 1fr; }
            .search-bar { flex-direction: column; }
        }
    </style>
</head>
<body>
    <header class="app-header">
        <div class="logo-area">
            <img src="logo.jpeg" alt="No Q" style="height: 40px; width: auto;">
            <span class="logo-text">CarWash<span style="font-weight:400;">Connect</span></span>
        </div>
        <button class="menu-toggle" id="menuToggle"><i class="fa-solid fa-bars"></i></button>
        <nav class="nav-links" id="navLinks">
            <a href="index.php">Home</a>
            <a href="search.php" style="background:rgba(42,82,152,0.1);">Search</a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="user-dashboard.php">Dashboard</a>
                <a href="logout.php">Logout</a>
            <?php else: ?>
                <a href="user-login.php">Sign In</a>
            <?php endif; ?>
        </nav>
    </header>

    <div class="container">
        <h1>Find a Car Wash</h1>
        
        <form method="get" class="search-bar">
            <input type="text" name="search" placeholder="Search by name or location..." value="<?= htmlspecialchars($search) ?>">
            <select name="rating">
                <option value="0">All ratings</option>
                <option value="4" <?= $rating_filter == 4 ? 'selected' : '' ?>>4+ stars</option>
                <option value="3" <?= $rating_filter == 3 ? 'selected' : '' ?>>3+ stars</option>
                <option value="2" <?= $rating_filter == 2 ? 'selected' : '' ?>>2+ stars</option>
            </select>
            <button type="submit"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
        </form>

        <div class="results-grid">
            <?php if (empty($businesses)): ?>
                <div class="no-results">
                    <i class="fa-regular fa-circle-xmark" style="font-size: 3rem;"></i>
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
                                <span class="closed-badge">Closed</span>
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
                            <i class="fa-regular fa-location-dot"></i> <?= htmlspecialchars($biz['address']) ?>
                        </div>
                        <div class="card-footer">
                            <span class="price">⭐ <?= number_format($biz['rating_avg'], 1) ?></span>
                            <span class="btn-small">View Details</span>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <footer class="app-footer">
        <p>&copy; <?= date('Y'); ?> No Q</p>
    </footer>

    <script>
        document.getElementById('menuToggle').addEventListener('click', function() {
            document.getElementById('navLinks').classList.toggle('show');
        });
    </script>
</body>
</html>
