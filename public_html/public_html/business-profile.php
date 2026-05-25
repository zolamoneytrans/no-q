<?php
session_start();
require_once 'db_connect.php';

$business_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($business_id == 0) {
    die('Invalid business.');
}

// Fetch business details
$stmt = $pdo->prepare("
    SELECT b.*, 
           (SELECT COUNT(*) FROM user_favorites WHERE business_id = b.id) as favorite_count
    FROM businesses b
    WHERE b.id = ? AND b.is_approved = 1 AND b.is_active = 1 AND b.is_test = 0 AND b.is_hidden = 0
");
$stmt->execute([$business_id]);
$business = $stmt->fetch();

if (!$business) {
    die('Business not found.');
}

// Fetch services
$stmt = $pdo->prepare("SELECT * FROM services WHERE business_id = ? ORDER BY price");
$stmt->execute([$business_id]);
$services = $stmt->fetchAll();

// Fetch reviews
$stmt = $pdo->prepare("
    SELECT r.*, u.name as user_name 
    FROM ratings r 
    JOIN users u ON r.user_id = u.id 
    WHERE r.business_id = ? 
    ORDER BY r.created_at DESC 
    LIMIT 10
");
$stmt->execute([$business_id]);
$reviews = $stmt->fetchAll();

// Fetch images
$stmt = $pdo->prepare("SELECT * FROM business_images WHERE business_id = ? ORDER BY created_at DESC");
$stmt->execute([$business_id]);
$images = $stmt->fetchAll();

// Check if user has favorited this business
$is_favorited = false;
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT id FROM user_favorites WHERE user_id = ? AND business_id = ?");
    $stmt->execute([$_SESSION['user_id'], $business_id]);
    $is_favorited = $stmt->fetch() ? true : false;
}

// Get queue stats
$queue_stats = getBusinessQueueStats($pdo, $business_id);
$current_queue = $queue_stats['queue'];
$base_wait_minutes = $queue_stats['wait_minutes'];

// Calculate capacity percentage
$total_slots = 8;
$used_slots = $queue_stats['queue'];
$capacity_percentage = min(100, round(($used_slots / $total_slots) * 100));

// Determine color based on capacity
if ($capacity_percentage < 50) {
    $capacity_color = '#4caf50';
    $capacity_text = 'Low congestion';
} elseif ($capacity_percentage < 80) {
    $capacity_color = '#ff9800';
    $capacity_text = 'Moderate congestion';
} else {
    $capacity_color = '#f44336';
    $capacity_text = 'High congestion';
}

// Determine today's operating hours
$day_of_week = strtolower(date('l'));
$open_field = $day_of_week . '_open';
$close_field = $day_of_week . '_close';

$today_open = $business[$open_field] ?? null;
$today_close = $business[$close_field] ?? null;

if ($today_open && $today_close) {
    $hours_display = date('g:i a', strtotime($today_open)) . ' – ' . date('g:i a', strtotime($today_close));
} else {
    $hours_display = 'Closed';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= htmlspecialchars($business['name']) ?> · No Q</title>
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
        
        .business-header {
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(10px);
            border-radius: 40px;
            padding: 2rem;
            margin-bottom: 2rem;
            text-align: center;
            border: 1px solid rgba(106,27,154,0.1);
        }
        .business-logo {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 1rem;
            display: block;
            border: 4px solid white;
            box-shadow: 0 5px 15px rgba(106,27,154,0.2);
        }
        .business-name { font-size: 2.5rem; margin-bottom: 0.5rem; color: var(--purple-primary); font-weight: 700; }
        .business-address { color: #2c3e50; margin-bottom: 1rem; }
        .rating {
            color: #f8b84a;
            font-size: 1.2rem;
            margin-bottom: 1rem;
        }
        .hours {
            color: #2c3e50;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
        .capacity-bar {
            background: #e0e0e0;
            border-radius: 10px;
            height: 10px;
            width: 100%;
            margin: 10px 0;
            overflow: hidden;
        }
        .capacity-fill {
            background: <?= $capacity_color ?>;
            width: <?= $capacity_percentage ?>%;
            height: 100%;
            transition: width 0.3s ease;
        }
        .capacity-text { font-size: 0.8rem; color: #2c3e50; margin-bottom: 1rem; }
        .favorite-btn {
            background: transparent;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--orange-primary);
            margin-top: 0.5rem;
            transition: transform 0.2s;
        }
        .favorite-btn:hover { transform: scale(1.1); }
        .closed-message {
            background: #f44336;
            color: white;
            padding: 10px;
            border-radius: 10px;
            margin: 10px 0;
            text-align: center;
        }
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }
        .card {
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(8px);
            border-radius: 30px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(106,27,154,0.1);
        }
        .card h3 {
            font-size: 1.3rem;
            margin-bottom: 1rem;
            background: linear-gradient(145deg, var(--purple-primary), var(--orange-primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .service-item {
            display: flex;
            justify-content: space-between;
            padding: 0.8rem 0;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        .service-item:last-child { border-bottom: none; }
        .service-name { font-weight: 500; }
        .service-price { font-weight: 700; color: var(--purple-primary); }
        .review-item {
            padding: 1rem 0;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        .review-item:last-child { border-bottom: none; }
        .review-user { font-weight: 600; margin-bottom: 0.3rem; color: var(--purple-primary); }
        .review-comment { color: #2c3e50; font-size: 0.9rem; margin-top: 0.3rem; }
        .image-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        .image-gallery img {
            width: 100%;
            height: 120px;
            object-fit: cover;
            border-radius: 15px;
            transition: transform 0.2s;
            cursor: pointer;
        }
        .image-gallery img:hover { transform: scale(1.02); }
        .btn {
            display: inline-block;
            background: linear-gradient(135deg, var(--purple-primary), var(--purple-dark));
            color: white;
            padding: 0.8rem 1.5rem;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            margin-top: 1rem;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(106,27,154,0.3); }
        .btn-book { background: linear-gradient(135deg, var(--orange-primary), var(--orange-dark)); margin-right: 1rem; }
        .btn-book:hover { background: var(--orange-dark); }
        .btn-disabled {
            background: #ccc;
            color: #666;
            cursor: not-allowed;
            display: inline-block;
            padding: 0.8rem 1.5rem;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            margin-top: 1rem;
        }
        .estimated-wait {
            margin: 1rem 0;
            padding: 0.8rem;
            background: #f0f4f8;
            border-radius: 20px;
            text-align: center;
            font-size: 0.95rem;
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
            .grid-2 { grid-template-columns: 1fr; gap: 1rem; }
            .business-name { font-size: 1.8rem; }
            .business-logo { width: 80px; height: 80px; }
            .business-header { padding: 1.2rem; }
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
            <a href="index.php"><i class="fa-solid fa-house"></i> Home</a>
            <a href="search.php"><i class="fa-solid fa-magnifying-glass"></i> Search</a>
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
        <div class="business-header">
            <!-- Business Logo/Profile Image -->
            <?php if (!empty($business['logo_url']) && file_exists($business['logo_url'])): ?>
                <img src="<?= htmlspecialchars($business['logo_url']) ?>" class="business-logo" alt="<?= htmlspecialchars($business['name']) ?>">
            <?php else: ?>
                <div class="business-logo" style="background: rgba(106,27,154,0.1); display: flex; align-items: center; justify-content: center;">
                    <i class="fa-solid fa-building" style="font-size: 3rem; color: var(--purple-primary);"></i>
                </div>
            <?php endif; ?>
            
            <h1 class="business-name"><?= htmlspecialchars($business['name']) ?></h1>
            <p class="business-address"><i class="fa-regular fa-location-dot"></i> <?= htmlspecialchars($business['address']) ?></p>

            <!-- Contact Details -->
            <div style="display: flex; justify-content: center; gap: 1.5rem; flex-wrap: wrap; margin: 1rem 0;">
                <?php 
                $display_phone = !empty($business['public_phone']) ? $business['public_phone'] : $business['phone'];
                if (!empty($display_phone)): 
                ?>
                    <a href="tel:<?= htmlspecialchars($display_phone) ?>" style="color: var(--purple-primary); text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem;">
                        <i class="fa-solid fa-phone"></i> <?= htmlspecialchars($display_phone) ?>
                    </a>
                <?php endif; ?>
                
                <?php 
                $display_email = !empty($business['public_email']) ? $business['public_email'] : $business['email'];
                if (!empty($display_email)): 
                ?>
                    <a href="mailto:<?= htmlspecialchars($display_email) ?>" style="color: var(--purple-primary); text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem;">
                        <i class="fa-regular fa-envelope"></i> <?= htmlspecialchars($display_email) ?>
                    </a>
                <?php endif; ?>
            </div>

            <div class="hours"><i class="fa-regular fa-clock"></i> Today: <?= $hours_display ?></div>
            <div class="rating">
                <?php 
                $rating = round($business['rating_avg'] * 2) / 2;
                for ($i = 1; $i <= 5; $i++):
                    if ($i <= floor($rating)) echo '<i class="fa-solid fa-star"></i>';
                    elseif ($i == floor($rating) + 1 && $rating - floor($rating) >= 0.5) echo '<i class="fa-solid fa-star-half-stroke"></i>';
                    else echo '<i class="fa-regular fa-star"></i>';
                endfor;
                echo ' ' . number_format($business['rating_avg'], 1) . ' (' . $business['favorite_count'] . ' favourite(s))';
                ?>
            </div>
            
            <!-- Capacity Bar -->
            <div class="capacity-bar">
                <div class="capacity-fill"></div>
            </div>
            <div class="capacity-text"><?= $capacity_text ?> · <?= $queue_stats['queue'] ?> cars in queue · Est. wait <?= $queue_stats['wait_minutes'] ?> min</div>
            
            <!-- Temporarily Closed Message -->
            <?php if ($business['is_temporarily_closed'] == 1): ?>
                <div class="closed-message">
                    <i class="fa-solid fa-circle-exclamation"></i> This business is temporarily closed.
                </div>
            <?php endif; ?>
            
            <!-- Favorite Button -->
            <?php if (isset($_SESSION['user_id'])): ?>
                <button class="favorite-btn" onclick="toggleFavorite(<?= $business_id ?>)">
                    <i class="fa-<?= $is_favorited ? 'solid' : 'regular' ?> fa-heart"></i>
                </button>
            <?php endif; ?>
            
            <!-- Booking Button - Disabled when temporarily closed -->
            <?php if ($business['is_temporarily_closed'] == 1): ?>
                <div class="btn-disabled">Temporarily Closed</div>
            <?php elseif (!isset($_SESSION['user_id'])): ?>
                <a href="user-login.php?redirect=<?= urlencode('book.php?id=' . $business_id) ?>" class="btn btn-book">Book Now</a>
            <?php else: ?>
                <a href="book.php?id=<?= $business_id ?>" class="btn btn-book">Book Now</a>
            <?php endif; ?>
        </div>

        <div class="grid-2">
            <div>
                <div class="card">
                    <h3><i class="fa-regular fa-broom"></i> Services</h3>
                    <?php if (empty($services)): ?>
                        <p>No services listed.</p>
                    <?php else: ?>
                        <?php foreach ($services as $s): ?>
                            <div class="service-item">
                                <span class="service-name"><?= htmlspecialchars($s['name']) ?></span>
                                <span class="service-price">R <?= number_format($s['price'], 2) ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <?php
                // Fetch active specials for this business
                $stmt = $pdo->prepare("
                    SELECT * FROM specials 
                    WHERE business_id = ? AND is_active = 1 
                    AND start_date <= CURDATE() AND end_date >= CURDATE()
                    ORDER BY created_at DESC
                ");
                $stmt->execute([$business_id]);
                $active_specials = $stmt->fetchAll();
                ?>
                
                <?php if (!empty($active_specials)): ?>
                <div class="card" style="margin-top: 1rem; border-left: 4px solid var(--orange-primary);">
                    <h3><i class="fa-regular fa-tag"></i> Current Specials</h3>
                    <?php foreach ($active_specials as $sp): ?>
                        <div style="background: rgba(255,152,0,0.1); border-radius: 20px; padding: 1rem; margin-top: 0.8rem;">
                            <strong><?= htmlspecialchars($sp['title']) ?></strong>
                            <p style="font-size: 0.9rem; margin-top: 0.3rem;"><?= nl2br(htmlspecialchars($sp['description'])) ?></p>
                            <p style="font-size: 0.8rem; color: var(--orange-dark); margin-top: 0.3rem;">
                                <?= $sp['discount_type'] == 'percentage' ? $sp['discount_value'] . '% OFF' : 'R ' . number_format($sp['discount_value'], 2) . ' OFF' ?>
                                | Valid until <?= date('d M Y', strtotime($sp['end_date'])) ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <div class="card">
                    <h3><i class="fa-regular fa-circle-info"></i> About</h3>
                    <p><?= nl2br(htmlspecialchars($business['description'] ?? 'No description provided.')) ?></p>
                </div>
            </div>
            
            <div>
                <div class="card">
                    <h3><i class="fa-regular fa-star"></i> Customer Reviews</h3>
                    <?php if (empty($reviews)): ?>
                        <p>No reviews yet. Be the first!</p>
                    <?php else: ?>
                        <?php foreach ($reviews as $r): ?>
                            <div class="review-item">
                                <div class="review-user">
                                    <?= htmlspecialchars($r['user_name']) ?>
                                    <span style="color:#f8b84a;">
                                        <?php for($i=1;$i<=5;$i++) echo $i<=$r['rating'] ? '<i class="fa-solid fa-star"></i>' : '<i class="fa-regular fa-star"></i>'; ?>
                                    </span>
                                </div>
                                <div class="review-comment">"<?= htmlspecialchars($r['comment'] ?? 'No comment') ?>"</div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($images)): ?>
                <div class="card">
                    <h3><i class="fa-regular fa-image"></i> Photos</h3>
                    <div class="image-gallery">
                        <?php foreach ($images as $img): ?>
                            <img src="<?= htmlspecialchars($img['image_path']) ?>" alt="Business image">
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <footer class="app-footer">
        <p>&copy; <?= date('Y'); ?> No Q. All rights reserved.</p>
        <p>Powered by <a href="https://www.jaekerna.com/" target="_blank">Jaekerna Investments</a></p>
    </footer>

    <script>
        // Hamburger menu toggle
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
        
        function toggleFavorite(businessId) {
            fetch('toggle-favorite.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'business_id=' + businessId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Please login to save business as favorite.');
                }
            });
        }
    </script>
</body>
</html>