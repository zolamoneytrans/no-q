<?php
session_set_cookie_params(['path' => '/']);
session_start();

require_once 'db_connect.php';

// Fetch approved businesses for "Popular near you"
$stmt = $pdo->query("
    SELECT id, name, address, rating_avg, logo_url, latitude, longitude
    FROM businesses 
    WHERE is_approved = 1 AND is_active = 1
    ORDER BY rating_avg DESC
");
$businesses = $stmt->fetchAll();

$stmt = $pdo->query("
    SELECT r.rating, r.comment, r.created_at, u.name as user_name, b.name as business_name
    FROM ratings r
    JOIN users u ON r.user_id = u.id
    JOIN businesses b ON r.business_id = b.id
    WHERE r.comment IS NOT NULL AND r.comment != ''
    ORDER BY r.created_at DESC
    LIMIT 3
");
$testimonials = $stmt->fetchAll();

$totalWashes = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'completed'")->fetchColumn();
$totalBusinesses = $pdo->query("SELECT COUNT(*) FROM businesses WHERE is_approved = 1 AND is_active = 1")->fetchColumn();
$avgRating = $pdo->query("SELECT AVG(rating_avg) FROM businesses WHERE is_approved = 1 AND is_active = 1")->fetchColumn();
$avgRating = number_format($avgRating ?: 0, 1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>No Q · your premium car wash app</title>
    <link rel="icon" type="image/png" href="favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="favicon.svg" />
    <link rel="shortcut icon" href="favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="apple-touch-icon.png" />
    <meta name="apple-mobile-web-app-title" content="No Q" />
    <link rel="manifest" href="site.webmanifest" />
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
            line-height: 1.5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .app-header {
            background: rgba(255,255,255,0.7);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255,255,255,0.5);
            padding: 0.8rem 2rem;
            position: sticky;
            top: 0;
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
        }
        .logo-area {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .logo-icon {
            background: #1e3c72;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        .logo-text {
            font-weight: 700;
            font-size: 1.5rem;
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .nav-links {
            display: flex;
            gap: 1.2rem;
            align-items: center;
        }
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
        }
        .nav-links a i { font-size: 1rem; color: #2a5298; }
        .nav-links a:hover { background: rgba(42,82,152,0.08); color: #1e3c72; }
        .nav-links .btn-outline {
            border: 1.5px solid #1e3c72;
            padding: 0.4rem 1.2rem;
            border-radius: 40px;
            background: white;
            font-weight: 600;
        }
        .nav-links .btn-outline:hover { background: #1e3c72; color: white; }

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
            .btn-outline {
                width: 100%;
                text-align: center;
            }
        }

        .hero {
            position: relative;
            overflow: hidden;
            padding: 6rem 2rem;
            text-align: center;
            margin: 1rem 2rem 0;
            border-radius: 48px;
        }
        .hero img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: -2;
        }
        .hero .overlay {
            position: absolute;
            top:0; left:0; width:100%; height:100%;
            background: rgba(0,0,0,0.4);
            z-index: -1;
            animation: float 6s ease-in-out infinite;
        }
        .hero h2 {
            color: white;
            font-size: clamp(2rem, 8vw, 3.5rem);
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .hero p {
            color: white;
            font-size: 1.2rem;
            max-width: 600px;
            margin: 1rem auto;
            opacity: 0.9;
        }
        .hero .btn {
            background: #ff9800;
            color: white;
            padding: 1rem 2rem;
            border-radius: 60px;
            font-size: 1.2rem;
            font-weight: 600;
            display: inline-block;
            text-decoration: none;
            box-shadow: 0 10px 20px rgba(255,152,0,0.3);
            transition: all 0.3s ease;
        }
        .hero .btn:hover {
            transform: scale(1.05);
            box-shadow: 0 15px 30px rgba(255,152,0,0.4);
        }

        .stats-bar {
            display: flex;
            justify-content: center;
            gap: 3rem;
            flex-wrap: wrap;
            margin: 2rem auto;
            max-width: 800px;
            background: rgba(255,255,255,0.5);
            backdrop-filter: blur(4px);
            padding: 1.5rem;
            border-radius: 60px;
        }
        .stat-item { text-align: center; }
        .stat-item .number {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(145deg, #1e3c72, #2a5298);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .stat-item .label { color: #2c3e50; font-size: 0.9rem; }

        .section-title {
            text-align: center;
            font-size: 2rem;
            font-weight: 600;
            margin: 3rem 0 1rem;
            background: linear-gradient(145deg, #1e3c72, #2a5298);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .cards-grid {
            display: flex;
            justify-content: center;
            gap: 2rem;
            flex-wrap: wrap;
            padding: 1rem 2rem 3rem;
            max-width: 1300px;
            margin: 0 auto;
        }
        .card {
            background: rgba(255,255,255,0.75);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255,255,255,0.6);
            border-radius: 32px;
            padding: 2rem 1.5rem;
            width: 280px;
            box-shadow: 0 25px 40px -12px rgba(0,20,40,0.2);
            transition: all 0.25s ease;
            text-align: center;
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.8s ease, transform 0.8s ease, box-shadow 0.25s ease;
        }
        .card.reveal {
            opacity: 1;
            transform: translateY(0);
        }
        .card:hover {
            transform: translateY(-8px);
            background: white;
            box-shadow: 0 30px 50px -15px #1e3c7240;
        }
        .card-icon {
            background: #eef3fc;
            width: 64px;
            height: 64px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
            color: #1e3c72;
        }
        .card h3 { font-size: 1.4rem; margin-bottom: 0.6rem; }
        .card p { color: #2c3e50; margin-bottom: 1rem; }
        .rating { color: #f8b84a; font-weight: 600; margin: 0.8rem 0; }
        .card .btn {
            background: #1e3c72;
            color: white;
            text-decoration: none;
            padding: 0.7rem 1.5rem;
            border-radius: 40px;
            font-weight: 500;
            display: inline-block;
            margin-top: 0.8rem;
        }
        .card .btn:hover { background: #2a5298; }

        .how-card { background: linear-gradient(145deg, #ffffff, #f8fcff); }
        .testimonial-card { text-align: left; padding: 2rem; }
        .testimonial-header { display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem; }
        .testimonial-avatar {
            background: #eef3fc;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: #1e3c72;
        }
        .testimonial-name h4 { margin: 0; font-size: 1.1rem; }
        .testimonial-name .stars { color: #f8b84a; font-size: 0.9rem; }
        .testimonial-text { font-style: italic; color: #2c3e50; }

        #locate-btn {
            background: #ff9800;
            border: none;
            padding: 0.7rem 2rem;
            border-radius: 40px;
            color: white;
            font-weight: 600;
            cursor: pointer;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(255,152,0,0.7); }
            50% { transform: scale(1.05); box-shadow: 0 0 0 10px rgba(255,152,0,0); }
            100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(255,152,0,0); }
        }
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }

        /* Expandable grid */
        .expandable-grid {
            max-height: 700px;
            overflow: hidden;
            transition: max-height 0.5s ease;
        }
        .expandable-grid.expanded {
            max-height: 5000px;
        }

        .app-footer {
            background: rgba(255,255,255,0.6);
            backdrop-filter: blur(8px);
            border-top: 1px solid white;
            padding: 2rem;
            text-align: center;
            margin-top: auto;
            color: #1e3c72;
        }

        @media (max-width: 768px) {
            table { display: block; overflow-x: auto; white-space: nowrap; }
        }
        @media (max-width: 480px) {
            .cards-grid { grid-template-columns: 1fr; gap: 1rem; }
            .card { width: 100%; }
            .hero { padding: 3rem 1rem; }
            .hero h2 { font-size: 1.8rem; }
            .hero p { font-size: 1rem; }
            .stats-bar { flex-direction: column; gap: 1rem; }
        }

        /* NEW ANIMATIONS AND COLORFUL STYLES */
        .gradient-overlay {
            background: linear-gradient(135deg, rgba(30,60,114,0.75) 0%, rgba(42,82,152,0.5) 50%, rgba(255,0,127,0.4) 100%) !important;
            mix-blend-mode: multiply;
        }

        .animate-drop {
            animation: dropIn 0.8s cubic-bezier(0.25, 1, 0.5, 1) forwards;
            opacity: 0;
            transform: translateY(-50px);
            text-shadow: 0 4px 15px rgba(0,0,0,0.6);
        }

        .animate-fade-in-up {
            animation: fadeInUp 0.8s ease-out forwards;
            opacity: 0;
            transform: translateY(30px);
        }

        .btn-giant {
            padding: 1.2rem 3rem !important;
            font-size: 1.4rem !important;
            border-radius: 60px !important;
            text-transform: uppercase;
            letter-spacing: 1px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .glow-btn {
            background: linear-gradient(45deg, #ff9800, #ff007f);
            box-shadow: 0 0 20px rgba(255,152,0,0.5), 0 0 40px rgba(255,0,127,0.3);
            border: 2px solid rgba(255,255,255,0.2) !important;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) !important;
        }

        .glow-btn:hover {
            transform: scale(1.1) translateY(-5px) !important;
            box-shadow: 0 0 30px rgba(255,152,0,0.8), 0 0 60px rgba(255,0,127,0.6) !important;
            background: linear-gradient(45deg, #ff007f, #ff9800) !important;
        }

        .pulse-container {
            display: inline-block;
            animation: containerPulse 2.5s infinite;
        }

        /* Card Enhancements */
        .image-card {
            padding: 0 !important;
            overflow: hidden !important;
            background: white !important;
            border: none !important;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1) !important;
        }

        .card-img-wrapper {
            width: 100%;
            height: 200px;
            overflow: hidden;
            border-radius: 32px 32px 0 0;
            position: relative;
        }

        .card-img-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.6s ease;
        }

        .image-card:hover .card-img-wrapper img {
            transform: scale(1.15);
        }

        .card-content {
            padding: 2rem 1.5rem;
            position: relative;
        }

        .card-icon.gradient-bg {
            background: linear-gradient(135deg, #7928ca, #ff007f) !important;
            color: white !important;
            box-shadow: 0 10px 20px rgba(255,0,127,0.3) !important;
            margin-top: -4rem !important;
            border: 4px solid white !important;
            position: relative;
            z-index: 2;
        }

        .text-white { color: white !important; }

        .jelly-hover {
            transition: transform 0.5s cubic-bezier(0.25, 0.8, 0.25, 1), box-shadow 0.5s ease, opacity 0.8s ease !important;
        }
        .jelly-hover:hover {
            transform: translateY(-15px) scale(1.02) !important;
            box-shadow: 0 30px 60px rgba(121, 40, 202, 0.2) !important;
        }

        /* Fun animations */
        @keyframes containerPulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.02); }
            100% { transform: scale(1); }
        }

        @keyframes dropIn {
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes fadeInUp {
            to { opacity: 1; transform: translateY(0); }
        }
        
        .section-title {
            animation: gradientShine 5s infinite;
            background-size: 200% auto !important;
        }
        @keyframes gradientShine {
            0% { background-position: 0% center; }
            50% { background-position: 100% center; }
            100% { background-position: 0% center; }
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
            <a href="#about" onclick="smoothScrollToSection('about')">About</a>
            <a href="#mission" onclick="smoothScrollToSection('mission')">Mission</a>
            <a href="#vision" onclick="smoothScrollToSection('vision')">Vision</a>
            <a href="contact.php">Contact</a>
            <?php if (isset($_SESSION['business_id'])): ?>
                <a href="business/business-dashboard.php"><i class="fa-regular fa-building"></i> Dashboard</a>
                <a href="business/business-logout.php" class="btn-outline"><i class="fa-regular fa-sign-out"></i> Logout</a>
            <?php elseif (isset($_SESSION['user_id'])): ?>
                <a href="user-dashboard.php"><i class="fa-regular fa-user"></i> Dashboard</a>
                <a href="logout.php?t=<?= time() ?>" class="btn-outline"><i class="fa-regular fa-sign-out"></i> Logout</a>
            <?php else: ?>
                <a href="user-login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>"><i class="fa-regular fa-user"></i> Sign In</a>
            <?php endif; ?>
            <a href="business/business-signup.php" class="btn-outline"><i class="fa-regular fa-building"></i> List Business</a>
        </nav>
    </header>

    <section class="hero">
        <!-- New generated premium car wash image -->
        <img src="premium_hero.png" alt="Premium Car Wash">
        <div class="overlay gradient-overlay"></div>
        <div style="position: relative; z-index: 1;" class="hero-content">
            <h2 class="animate-drop">Find & Book the Best Car Wash Near You.</h2>
            <p class="animate-fade-in-up" style="animation-delay: 0.3s; font-size: 1.3rem;">Instant booking. No queues. Premium car wash near you.</p>
            <div class="pulse-container" style="margin-top: 2.5rem; animation-delay: 0.6s;">
                <a href="search.php" class="btn btn-giant glow-btn">Find a Car Wash <i class="fa-solid fa-arrow-right" style="margin-left:8px;"></i></a>
            </div>
            <p class="animate-fade-in-up" style="margin-top:1.5rem; color:#fff; font-weight:600; font-size: 1.1rem; text-shadow: 0 2px 4px rgba(0,0,0,0.5); animation-delay: 0.9s;">
                Join thousands of happy customers
            </p>
        </div>
    </section>

    <div class="stats-bar">
        <div class="stat-item"><div class="number"><?= number_format($totalWashes) ?></div><div class="label">Washes completed</div></div>
        <div class="stat-item"><div class="number"><?= $totalBusinesses ?></div><div class="label">Registered Car washes</div></div>
        <div class="stat-item"><div class="number"><?= $avgRating ?></div><div class="label">Average rating</div></div>
    </div>

    <h2 class="section-title">How it works</h2>
    <div class="cards-grid">
        <div class="card how-card"><div class="card-icon"><i class="fa-solid fa-magnifying-glass"></i></div><h3>1. Search</h3><p>Find top rated car washes near you.</p></div>
        <div class="card how-card"><div class="card-icon"><i class="fa-regular fa-calendar-check"></i></div><h3>2. Book</h3><p>Choose a time slot that fits your schedule.</p></div>
        <div class="card how-card"><div class="card-icon"><i class="fa-solid fa-droplet"></i></div><h3>3. Wash</h3><p>Arrive dirty and leave clean.</p></div>
    </div>

    <h2 class="section-title" style="background: linear-gradient(90deg, #ff007f, #7928ca); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Why choose us</h2>
    <div class="cards-grid presentation-grid">
        <div class="card image-card jelly-hover">
            <div class="card-img-wrapper"><img src="eco_wash.png" alt="Eco-friendly wash"></div>
            <div class="card-content">
                <div class="card-icon gradient-bg"><i class="fa-solid fa-leaf text-white"></i></div>
                <h3>Eco‑friendly</h3>
                <p>Water‑smart partners, biodegradable soaps ensuring a green future for all.</p>
            </div>
        </div>
        <div class="card image-card jelly-hover">
            <div class="card-img-wrapper"><img src="fast_booking.png" alt="Fast booking app"></div>
            <div class="card-content">
                <div class="card-icon gradient-bg"><i class="fa-solid fa-clock text-white"></i></div>
                <h3>Save time</h3>
                <p>Book instantly in under 60 seconds through our ultra-smooth interface.</p>
            </div>
        </div>
        <div class="card image-card jelly-hover">
            <div class="card-img-wrapper"><img src="3.jpg" alt="Quality guaranteed"></div>
            <div class="card-content">
                <div class="card-icon gradient-bg"><i class="fa-solid fa-medal text-white"></i></div>
                <h3>Quality guaranteed</h3>
                <p>Top‑rated specialists and inspected facilities ensuring a spotless shine.</p>
            </div>
        </div>
    </div>

    <h2 class="section-title">Everything you need</h2>
    <div class="cards-grid">
        <div class="card how-card"><div class="card-icon"><i class="fa-solid fa-magnifying-glass"></i></div><h3>Find & Filter</h3><p>Search by location, rating, or name. Use your location to see nearby car washes.</p></div>
        <div class="card how-card"><div class="card-icon"><i class="fa-regular fa-calendar-check"></i></div><h3>Book Instantly</h3><p>Real‑time availability, unique booking codes, and instant confirmation emails.</p></div>
        <div class="card how-card"><div class="card-icon"><i class="fa-regular fa-star"></i></div><h3>Rate & Review</h3><p>Share your experience, leave ratings, and earn loyalty points for every review.</p></div>
        <div class="card how-card"><div class="card-icon"><i class="fa-regular fa-heart"></i></div><h3>Favorites</h3><p>Save your favourite car washes and quickly book them again.</p></div>
        <div class="card how-card"><div class="card-icon"><i class="fa-regular fa-building"></i></div><h3>Business Tools</h3><p>Full dashboard for car washes: manage bookings, services, images, and real‑time status.</p></div>
        <div class="card how-card"><div class="card-icon"><i class="fa-regular fa-credit-card"></i></div><h3>Secure Payments</h3><p>E‑wallet for businesses, withdrawal requests, and seamless payment flow.</p></div>
    </div>

    <h2 class="section-title">Happy customers</h2>
    <div class="cards-grid">
        <?php if (empty($testimonials)): ?>
            <p style="color:#2c3e50;">No reviews yet. Be the first!</p>
        <?php else: ?>
            <?php foreach ($testimonials as $t): ?>
            <div class="card testimonial-card">
                <div class="testimonial-header">
                    <div class="testimonial-avatar"><i class="fa-regular fa-user"></i></div>
                    <div class="testimonial-name">
                        <h4><?= htmlspecialchars($t['user_name']) ?></h4>
                        <div class="stars">
                            <?php for ($i=1;$i<=5;$i++): ?>
                                <?= $i <= $t['rating'] ? '<i class="fa-solid fa-star"></i>' : '<i class="fa-regular fa-star"></i>' ?>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
                <p class="testimonial-text">"<?= htmlspecialchars($t['comment']) ?>"</p>
                <p style="margin-top:1rem; font-size:0.8rem;">– <?= htmlspecialchars($t['business_name']) ?></p>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <h2 class="section-title">Popular near you</h2>
    <div style="text-align: center; margin-bottom: 1rem;">
        <button id="locate-btn"><i class="fa-solid fa-location-dot"></i> Use my location</button>
        <span id="location-status" style="margin-left:1rem; color:#2c3e50;"></span>
    </div>

    <div class="expandable-grid" id="expandableGrid">
        <div class="cards-grid" id="popular-grid">
            <?php if (empty($businesses)): ?>
                <p style="color:#2c3e50;">No car washes available.</p>
            <?php else: ?>
                <?php foreach ($businesses as $biz): ?>
                <div class="card">
                    <div class="card-icon"><i class="fa-solid fa-sparkles"></i></div>
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
                    <div style="display: flex; gap: 0.5rem; justify-content: center;">
                       <?php if (isset($_SESSION['user_id'])): ?>
    <a href="business-profile.php?id=<?= $biz['id'] ?>" class="btn">
        <i class="fa-regular fa-building"></i> View Details
    </a>
<?php else: ?>
    <a href="user-login.php?redirect=<?= urlencode('business-profile.php?id=' . $biz['id']) ?>" class="btn">
        <i class="fa-regular fa-building"></i> Login to View
    </a>
<?php endif; ?>
                       <?php if (isset($_SESSION['user_id'])): ?>
    <a href="book.php?id=<?= $biz['id'] ?>" class="btn" style="background:#ff9800;">
        <i class="fa-regular fa-calendar-check"></i> Book Now
    </a>
<?php else: ?>
    <a href="user-login.php?redirect=<?= urlencode('book.php?id=' . $biz['id']) ?>" class="btn" style="background:#ff9800;">
        <i class="fa-regular fa-calendar-check"></i> Book Now
    </a>
<?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <script>var businesses = <?= json_encode($businesses) ?>;</script>
            <?php endif; ?>
        </div>
    </div>

  <?php if (count($businesses) > 3): ?>
<div style="text-align: center; margin-top: 1rem;">
    <button id="showMoreBtn" class="btn" style="background: #1e3c72; padding: 0.5rem 2rem;">Show more <i class="fa-solid fa-chevron-down"></i></button>
</div>
<?php endif; ?>

    <section id="about" style="scroll-margin-top: 80px;">
        <h2 class="section-title">About No Q</h2>
        <div class="cards-grid" style="max-width:900px;">
            <div class="card how-card" style="width:100%; max-width:600px; margin:0 auto; text-align:left;">
                <p style="font-size:1.1rem; line-height:1.6;">We started in 2026 to make car care effortless. No more waiting in line. Just tap, book, and shine. We want to connect drivers with the best local car washes across KwaZulu‑Natal.</p>
                <div style="display:flex; gap:2rem; justify-content:center; flex-wrap:wrap; margin-top:2rem;">
                    <div style="text-align:center;"><i class="fa-solid fa-leaf" style="font-size:2rem; color:#1e3c72;"></i><h4>Eco‑friendly</h4><p>Water‑smart partners</p></div>
                    <div style="text-align:center;"><i class="fa-solid fa-clock" style="font-size:2rem; color:#1e3c72;"></i><h4>Fast booking</h4><p>Under 60 seconds</p></div>
                    <div style="text-align:center;"><i class="fa-solid fa-star" style="font-size:2rem; color:#1e3c72;"></i><h4>Top rated</h4><p>Only 4.5+ stars</p></div>
                </div>
            </div>
        </div>
    </section>

    <section id="mission" style="scroll-margin-top: 80px;"> 
        <h2 class="section-title">Our Mission</h2>
        <div class="cards-grid" style="max-width: 900px;">
            <div class="card how-card" style="width: 100%; max-width: 600px; margin: 0 auto; text-align: left;">
                <p style="font-size: 1.1rem; line-height: 1.6;">To revolutionize the car wash experience by providing a seamless, tech‑driven platform that connects customers with top‑quality car washes, saving time and ensuring satisfaction.</p>
            </div>
        </div>
    </section>

    <section id="vision" style="scroll-margin-top: 80px;"> 
        <h2 class="section-title">Our Vision</h2>
        <div class="cards-grid" style="max-width: 900px;">
            <div class="card how-card" style="width: 100%; max-width: 600px; margin: 0 auto; text-align: left;">
                <p style="font-size: 1.1rem; line-height: 1.6;">To become the leading car wash booking platform across South Africa, empowering local businesses and delivering convenience to every driver.</p>
            </div>
        </div>
    </section>

    <footer class="app-footer">
        <p>&copy; <?= date('Y'); ?> No Q. All rights reserved</p>
        <p>Powered by <a href="https://www.jaekerna.com/" target="_blank" style="color:#1e3c72;">Jaekerna Investments</a></p>
    </footer>

    <script>
    // Smooth scroll
    function smoothScrollToSection(sectionId) {
        const section = document.getElementById(sectionId);
        if (!section) return;
        const target = section.getBoundingClientRect().top + window.pageYOffset;
        const start = window.pageYOffset;
        const distance = target - start;
        const duration = 700;
        let startTime = null;
        function animate(current) {
            if (startTime === null) startTime = current;
            const elapsed = current - startTime;
            const progress = Math.min(elapsed / duration, 1);
            const ease = progress < 0.5 ? 2 * progress * progress : 1 - Math.pow(-2 * progress + 2, 2) / 2;
            window.scrollTo(0, start + distance * ease);
            if (elapsed < duration) requestAnimationFrame(animate);
        }
        requestAnimationFrame(animate);
    }

    // Staggered card reveal
    document.addEventListener('DOMContentLoaded', function() {
        const cards = document.querySelectorAll('.card');
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('reveal');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.2, rootMargin: '0px 0px -50px 0px' });
        cards.forEach((card, i) => {
            card.style.transitionDelay = i * 0.1 + 's';
            observer.observe(card);
        });
    });

    // Hamburger menu
    document.getElementById('menuToggle').addEventListener('click', function() {
        document.getElementById('navLinks').classList.toggle('show');
    });
    document.querySelectorAll('.nav-links a').forEach(link => {
        link.addEventListener('click', () => {
            document.getElementById('navLinks').classList.remove('show');
        });
    });

    // Expandable grid toggle
    document.getElementById('showMoreBtn').addEventListener('click', function() {
        var grid = document.getElementById('expandableGrid');
        var btn = this;
        if (grid.classList.contains('expanded')) {
            grid.classList.remove('expanded');
            btn.innerHTML = 'Show more <i class="fa-solid fa-chevron-down"></i>';
        } else {
            grid.classList.add('expanded');
            btn.innerHTML = 'Show less <i class="fa-solid fa-chevron-up"></i>';
        }
    });

    // Geolocation
    var loggedIn = <?= isset($_SESSION['user_id']) ? 'true' : 'false' ?>;
    var businesses = <?= json_encode($businesses ?: []) ?>;

    document.getElementById('locate-btn').addEventListener('click', function() {
        var status = document.getElementById('location-status');
        status.innerHTML = 'Getting location...';
        if (!navigator.geolocation) {
            status.innerHTML = 'Geolocation not supported.';
            return;
        }
        navigator.geolocation.getCurrentPosition(function(position) {
            status.innerHTML = 'Location found! Sorting...';
            var userLat = position.coords.latitude;
            var userLng = position.coords.longitude;
            function getDistance(lat1, lon1, lat2, lon2) {
                if (!lat1 || !lon1 || !lat2 || !lon2) return Infinity;
                var R = 6371;
                var dLat = (lat2 - lat1) * Math.PI / 180;
                var dLon = (lon2 - lon1) * Math.PI / 180;
                var a = Math.sin(dLat/2) * Math.sin(dLat/2) + Math.cos(lat1 * Math.PI/180) * Math.cos(lat2 * Math.PI/180) * Math.sin(dLon/2) * Math.sin(dLon/2);
                var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
                return R * c;
            }
            businesses.forEach(b => b.distance = getDistance(userLat, userLng, parseFloat(b.latitude), parseFloat(b.longitude)));
            businesses.sort((a,b) => a.distance - b.distance);
            var grid = document.getElementById('popular-grid');
            grid.innerHTML = '';
            businesses.forEach(b => {
                if (b.distance === Infinity) return;
                var rating = Math.round(b.rating_avg * 2) / 2;
                var stars = '';
                for (var i=1; i<=5; i++) {
                    if (i <= Math.floor(rating)) stars += '<i class="fa-solid fa-star"></i>';
                    else if (i === Math.floor(rating)+1 && rating - Math.floor(rating) >= 0.5) stars += '<i class="fa-solid fa-star-half-stroke"></i>';
                    else stars += '<i class="fa-regular fa-star"></i>';
                }
                stars += ' ' + b.rating_avg.toFixed(1);
                var dist = b.distance < 1 ? (b.distance*1000).toFixed(0)+' m' : b.distance.toFixed(1)+' km';
                var card = document.createElement('div');
                card.className = 'card';
                card.innerHTML = `
                    <div class="card-icon"><i class="fa-solid fa-sparkles"></i></div>
                    <h3>${escapeHtml(b.name)}</h3>
                    <p><i class="fa-solid fa-location-dot"></i> ${escapeHtml(b.address)}</p>
                    <div style="font-size:0.9rem; color:#666;">📍 ${dist}</div>
                    <div class="rating">${stars}</div>
                    <div style="display: flex; gap: 0.5rem; justify-content: center;">
                        <a href="business-profile.php?id=${b.id}" class="btn"><i class="fa-regular fa-building"></i> ${loggedIn ? 'View Details' : 'Login to View'}</a>
                        <a href="book.php?id=${b.id}" class="btn" style="background:#ff9800;"><i class="fa-regular fa-calendar-check"></i> Book Now</a>
                    </div>
                `;
                grid.appendChild(card);
            });
            status.innerHTML = 'Sorted by distance.';
        }, function(error) {
            status.innerHTML = 'Unable to get location: ' + error.message;
        });
    });

    function escapeHtml(unsafe) {
        return unsafe.replace(/[&<>"']/g, m => {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            if (m === '"') return '&quot;';
            return '&#039;';
        });
    }

    // Service worker registration
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/carwash-connect/sw.js')
            .then(reg => console.log('Service Worker registered'))
            .catch(err => console.error('SW registration failed', err));
    }

    // PWA INSTALL BUTTON 
    let deferredPrompt;
    let installButton = null;

    function createInstallButton(manual = false) {
        if (installButton) return;
        if (window.matchMedia('(display-mode: standalone)').matches) return;
        installButton = document.createElement('button');
        installButton.textContent = 'Install App';
        installButton.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 9999;
            padding: 12px 20px;
            background: #ff9800;
            color: white;
            border: none;
            border-radius: 40px;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        `;
        if (manual) {
            installButton.onclick = () => {
                alert('To install, open the browser menu and select "Add to Home Screen" or "Install App".');
            };
        } else {
            installButton.onclick = async () => {
                if (!deferredPrompt) return;
                deferredPrompt.prompt();
                const { outcome } = await deferredPrompt.userChoice;
                console.log(`User response: ${outcome}`);
                deferredPrompt = null;
                installButton.remove();
                installButton = null;
            };
        }
        document.body.appendChild(installButton);
    }

    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        createInstallButton(false);
    });

    window.addEventListener('appinstalled', () => {
        if (installButton) installButton.remove();
        deferredPrompt = null;
    });

    setTimeout(() => {
        if (!deferredPrompt && !installButton && !window.matchMedia('(display-mode: standalone)').matches) {
            createInstallButton(true);
        }
    }, 5000);
    </script>
</body>
</html>
