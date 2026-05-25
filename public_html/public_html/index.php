<?php
echo '<script>console.log("theme.css loaded");</script>';
session_set_cookie_params(['path' => '/']);
session_start();


require_once 'db_connect.php';

// Leaderboard based on user location
$leaderboard_region = null;
$leaderboard_businesses = [];

// Check if user has allowed location
$force_region = isset($_GET['region']) ? trim($_GET['region']) : '';

if (!empty($force_region)) {

    $leaderboard_region = $force_region;
} elseif (isset($_SESSION['user_lat']) && isset($_SESSION['user_lng'])) {
    // Use stored location from previous visit
    $leaderboard_region = getRegionFromLocation($pdo, $_SESSION['user_lat'], $_SESSION['user_lng']);
}

// If we have a region, fetch top businesses in that region
if (!empty($leaderboard_region)) {
    $stmt = $pdo->prepare("
        SELECT id, name, address, rating_avg, logo_url, latitude, longitude, region
        FROM businesses 
        WHERE is_approved = 1 AND is_active = 1 AND region = ?
        ORDER BY rating_avg DESC
        LIMIT 6
    ");
    $stmt->execute([$leaderboard_region]);
    $leaderboard_businesses = $stmt->fetchAll();
}


// Fetch approved businesses for "Popular near you"
$stmt = $pdo->query("
    SELECT id, name, address, rating_avg, logo_url, latitude, longitude
    FROM businesses 
    WHERE is_approved = 1 AND is_active = 1 AND is_test = 0 AND is_hidden = 0
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
    LIMIT 6
");
$testimonials = $stmt->fetchAll();


$totalWashes = $pdo->query("SELECT COUNT(*) FROM bookings b JOIN businesses biz ON b.business_id = biz.id WHERE b.status = 'completed' AND biz.is_test = 0")->fetchColumn();
$totalBusinesses = $pdo->query("SELECT COUNT(*) FROM businesses WHERE is_approved = 1 AND is_active = 1 AND is_test = 0")->fetchColumn();
$avgRating = $pdo->query("SELECT AVG(rating_avg) FROM businesses WHERE is_approved = 1 AND is_active = 1")->fetchColumn();
$avgRating = number_format($avgRating ?: 0, 1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>No Q · Book.Wash.Go</title>
    <link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
    <link rel="shortcut icon" href="/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />
    <meta name="apple-mobile-web-app-title" content="No Q" />
    <link rel="manifest" href="/site.webmanifest?v=2" />
    <meta name="description" content="Book premium car washes instantly. No queues, secure payments, and top-rated car washes near you.">
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
            line-height: 1.5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #f3e5f5; border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: var(--purple-primary); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--purple-dark); }
        
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
            transition: all 0.3s ease;
        }
        .logo-area {
            display: flex;
            align-items: center;
            gap: 10px;
            transition: transform 0.3s ease;
        }
        .logo-area:hover { transform: scale(1.02); }
        .logo-text {
            font-weight: 800;
            font-size: 1.5rem;
            background: linear-gradient(135deg, var(--purple-primary), var(--orange-primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .nav-links {
            display: flex;
            gap: 1.2rem;
            align-items: center;
            flex-wrap: wrap;
        }
        .nav-links a,
        .nav-links .dropdown .dropbtn {
            text-decoration: none;
            font-weight: 500;
            color: #2c3e50;
            transition: all 0.3s ease;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 0.5rem 0.8rem;
            border-radius: 40px;
            background: transparent;
            border: none;
            cursor: pointer;
            position: relative;
        }
        .nav-links a::after,
        .nav-links .dropdown .dropbtn::after {
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
        .nav-links a:hover::after,
        .nav-links .dropdown .dropbtn:hover::after { width: 30px; }
        .nav-links a i { font-size: 1rem; color: var(--purple-primary); }
        .nav-links a:hover,
        .nav-links .dropdown .dropbtn:hover { background: rgba(106,27,154,0.08); color: var(--purple-primary); }
        .nav-links .btn-outline {
            border: 1.5px solid var(--purple-primary);
            padding: 0.4rem 1.2rem;
            border-radius: 40px;
            background: white;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .nav-links .btn-outline:hover { background: var(--purple-primary); color: white; transform: translateY(-2px); }
        .nav-links .btn-outline:hover i { color: white; }
        .nav-links .btn-outline::after { display: none; }
        
        .nav-links .dropdown { display: inline-block; position: relative; }
        .dropdown-content {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            background: white;
            min-width: 160px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            border-radius: 20px;
            z-index: 100;
            overflow: hidden;
            opacity: 0;
            transform: translateY(-10px);
            transition: all 0.3s ease;
        }
        .dropdown-content a {
            padding: 12px 16px;
            display: block;
            text-align: left;
            color: #2c3e50;
            text-decoration: none;
            font-size: 0.9rem;
            border-radius: 0;
            transition: all 0.2s ease;
        }
        .dropdown-content a:hover { background: rgba(106,27,154,0.1); padding-left: 24px; color: var(--purple-primary); }
        
        @media (min-width: 769px) {
            .dropdown:hover .dropdown-content {
                display: block;
                opacity: 1;
                transform: translateY(0);
            }
            .dropdown:hover .dropbtn i { transform: rotate(180deg); }
        }
        
        .menu-toggle {
            display: none;
            font-size: 1.8rem;
            cursor: pointer;
            color: var(--purple-primary);
            background: transparent;
            border: none;
            padding: 0.5rem;
            transition: transform 0.2s ease;
        }
        .menu-toggle:hover { transform: scale(1.1); }
        
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
            transition: transform 0.5s ease;
        }
        .hero:hover img { transform: scale(1.05); }
        .hero .overlay {
            position: absolute;
            top:0; left:0; width:100%; height:100%;
            background: linear-gradient(135deg, rgba(106,27,154,0.6) 0%, rgba(0,0,0,0.3) 100%);
            z-index: -1;
        }
        .hero h2 {
            color: white;
            font-size: clamp(2rem, 8vw, 3.5rem);
            font-weight: 800;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 10px rgba(0,0,0,0.2);
            min-height: 80px;
        }
        .hero p {
            color: white;
            font-size: 1.2rem;
            max-width: 600px;
            margin: 1rem auto;
            opacity: 0.95;
        }
        .hero .btn {
            background: linear-gradient(135deg, var(--orange-primary), var(--orange-dark));
            color: white;
            padding: 1rem 2rem;
            border-radius: 60px;
            font-size: 1.2rem;
            font-weight: 600;
            display: inline-block;
            text-decoration: none;
            box-shadow: 0 10px 20px rgba(255,152,0,0.3);
            transition: all 0.3s ease;
            animation: softPulse 2s infinite;
        }
        .hero .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(255,152,0,0.4);
            animation: none;
        }
        
        @keyframes softPulse {
            0% { box-shadow: 0 0 0 0 rgba(255,152,0,0.4); }
            70% { box-shadow: 0 0 0 15px rgba(255,152,0,0); }
            100% { box-shadow: 0 0 0 0 rgba(255,152,0,0); }
        }

        .stats-bar {
            display: flex;
            justify-content: center;
            gap: 3rem;
            flex-wrap: wrap;
            margin: 2rem auto;
            max-width: 800px;
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(8px);
            padding: 1.5rem;
            border-radius: 60px;
            box-shadow: 0 20px 40px -12px rgba(106,27,154,0.15);
            position: relative;
            z-index: 10;
        }
        .stat-item { text-align: center; transition: transform 0.3s ease; cursor: pointer; }
        .stat-item:hover { transform: translateY(-5px); }
        .stat-item .number {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(145deg, var(--purple-primary), var(--purple-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            transition: all 0.3s ease;
        }
        .stat-item .label { color: #2c3e50; font-size: 0.9rem; font-weight: 500; }

        .section-title {
            text-align: center;
            font-size: 2rem;
            font-weight: 700;
            margin: 3rem 0 1rem;
            background: linear-gradient(145deg, var(--purple-primary), var(--orange-primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            position: relative;
            display: inline-block;
            width: 100%;
        }
        .section-title::after {
            content: '';
            display: block;
            width: 60px;
            height: 3px;
            background: var(--orange-primary);
            margin: 0.5rem auto 0;
            border-radius: 10px;
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
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(106,27,154,0.1);
            border-radius: 32px;
            padding: 2rem 1.5rem;
            width: 280px;
            box-shadow: 0 20px 35px -12px rgba(106,27,154,0.1);
            transition: all 0.3s ease;
            text-align: center;
            opacity: 0;
            transform: translateY(30px);
            position: relative;
            overflow: hidden;
        }
        .card::before {
            content: '';
            position: absolute;
            top: -20px;
            right: -20px;
            font-size: 3rem;
            opacity: 0;
            transition: all 0.3s ease;
        }
        .card:hover::before {
            opacity: 0.2;
            transform: rotate(15deg);
        }
        .card.reveal {
            opacity: 1;
            transform: translateY(0);
        }
        .card:hover {
            transform: translateY(-8px);
            background: white;
            box-shadow: 0 30px 50px -20px rgba(106,27,154,0.2);
        }
        .card-icon {
            background: linear-gradient(145deg, #f3e5f5, #e1bee7);
            width: 70px;
            height: 70px;
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
            color: var(--purple-primary);
            transition: all 0.3s ease;
        }
        .card:hover .card-icon {
            transform: scale(1.1);
            background: var(--orange-primary);
            color: white;
        }
        .card h3 { font-size: 1.4rem; margin-bottom: 0.6rem; font-weight: 700; color: var(--purple-primary); }
        .card p { color: #2c3e50; margin-bottom: 1rem; line-height: 1.5; }
        .rating { color: #f8b84a; font-weight: 600; margin: 0.8rem 0; }
        .card .btn {
            background: var(--purple-primary);
            color: white;
            text-decoration: none;
            padding: 0.7rem 1.5rem;
            border-radius: 40px;
            font-weight: 500;
            display: inline-block;
            margin-top: 0.8rem;
            transition: all 0.3s ease;
        }
        .card .btn:hover { background: var(--purple-dark); transform: translateY(-2px); }

        .how-card { background: linear-gradient(145deg, #ffffff, #faf5ff); }
        .testimonial-card { text-align: left; padding: 2rem; position: relative; }
        .testimonial-card .quote-icon {
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 2rem;
            color: rgba(106,27,154,0.1);
        }
        .testimonial-header { display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem; }
        .testimonial-avatar {
            background: linear-gradient(145deg, var(--purple-primary), var(--purple-dark));
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }
        .testimonial-name h4 { margin: 0; font-size: 1.1rem; font-weight: 700; color: var(--purple-primary); }
        .testimonial-name .stars { color: #f8b84a; font-size: 0.85rem; margin-top: 4px; }
        .testimonial-text { font-style: italic; color: #2c3e50; line-height: 1.6; }

        #locate-btn {
            background: linear-gradient(135deg, var(--orange-primary), var(--orange-dark));
            border: none;
            padding: 0.7rem 2rem;
            border-radius: 40px;
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        #locate-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255,152,0,0.4);
        }

        .expandable-grid {
            max-height: 700px;
            overflow: hidden;
            transition: max-height 0.5s ease;
        }
        .expandable-grid.expanded { max-height: none; }
        .hidden-card {
            display: none;
        }
        .expandable-grid.expanded .hidden-card {
            display: block;
        }

        .fab {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: linear-gradient(135deg, var(--purple-primary), var(--purple-dark));
            color: white;
            width: 55px;
            height: 55px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(106,27,154,0.3);
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 999;
            border: none;
            animation: softPulse 2s infinite;
        }
        .fab:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(106,27,154,0.4);
            animation: none;
        }
        
        .app-footer {
            background: rgba(255,255,255,0.6);
            backdrop-filter: blur(8px);
            border-top: 1px solid white;
            padding: 2rem;
            text-align: center;
            margin-top: auto;
            color: var(--purple-primary);
        }
        .app-footer a { color: var(--purple-primary); text-decoration: none; transition: color 0.2s; }
        .app-footer a:hover { color: var(--orange-primary); text-decoration: underline; }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .install-btn {
            position: fixed;
            bottom: 90px;
            right: 20px;
            z-index: 999;
            padding: 12px 20px;
            background: linear-gradient(135deg, var(--purple-primary), var(--purple-dark));
            color: white;
            border: none;
            border-radius: 40px;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(106,27,154,0.3);
            transition: all 0.3s ease;
        }
        .install-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(106,27,154,0.4); }
        
        @media (min-width: 769px) { 
            .install-btn { display: none !important; } 
        }
        @media all and (display-mode: standalone) { 
            .install-btn { display: none !important; } 
        }

        @media (max-width: 768px) {
            .menu-toggle { display: block; }
            .nav-links {
                display: none;
                width: 100%;
                flex-direction: column;
                align-items: center;
                gap: 0.5rem;
                padding: 1rem;
                background: rgba(255,255,255,0.85);
                backdrop-filter: blur(15px);
                border-radius: 0 0 30px 30px;
                margin-top: 0;
                position: fixed;
                top: 85px;
                bottom: 0;
                left: 0;
                right: 0;
                z-index: 200;
                overflow-y: auto;
            }
            .nav-links.show { display: flex; }
            .app-header { padding: 0.8rem 1rem; }
            .nav-links a,
            .nav-links .dropdown .dropbtn {
                width: 100%;
                text-align: center;
                justify-content: center;
                padding: 0.8rem;
            }
            .btn-outline { width: 100%; text-align: center; }
            .nav-links .dropdown { width: 100%; display: block; }
            .dropdown-content {
                position: static;
                background: rgba(106,27,154,0.05);
                margin-top: 0.5rem;
                width: 100%;
                box-shadow: none;
                opacity: 1;
                transform: none;
            }
            .dropdown.open .dropdown-content { display: block !important; }
            
            .hero {
                margin: 0.5rem 1rem 0;
                padding: 3rem 1rem;
                border-radius: 32px;
            }
            .hero h2 { min-height: 60px; font-size: 1.6rem; }
            .hero p { font-size: 0.9rem; }
            .hero .btn { padding: 0.8rem 1.5rem; font-size: 1rem; }
            
            .stats-bar {
                margin: 1rem 1rem 1.5rem;
                gap: 1rem;
                padding: 1rem;
            }
            .stat-item .number { font-size: 1.5rem; }
            .stat-item .label { font-size: 0.7rem; }
            
            .section-title { font-size: 1.6rem; margin-top: 2rem; }
            .cards-grid { gap: 1rem; padding: 1rem 1rem 2rem; }
            .card { width: 100%; max-width: 320px; margin: 0 auto; }
            .fab { width: 45px; height: 45px; bottom: 15px; right: 15px; }
            .install-btn { bottom: 75px; right: 15px; padding: 10px 16px; font-size: 12px; }
        }
        
        @media (max-width: 480px) {
            .hero h2 { font-size: 1.4rem; min-height: 50px; }
            .stats-bar { margin: 0.5rem 1rem 1rem; }
            .stat-item .number { font-size: 1.3rem; }
            .stat-item .label { font-size: 0.65rem; }
            .section-title { font-size: 1.4rem; }
            .section-title::after { width: 40px; }
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .confetti-burst {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 9999;
        }
        .confetti {
            position: absolute;
            width: 10px;
            height: 10px;
            background: var(--orange-primary);
            animation: confettiFall 3s ease-out forwards;
        }
        @keyframes confettiFall {
            0% { transform: translateY(-100vh) rotate(0deg); opacity: 1; }
            100% { transform: translateY(100vh) rotate(360deg); opacity: 0; }
        }

        .blur-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(255,255,255,0.3);
            backdrop-filter: blur(5px);
            z-index: 90;
            display: none;
        }
        .blur-overlay.show { display: block; }
    </style>

</head>
<body>
    <div class="blur-overlay" id="blurOverlay"></div>
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
            <div class="dropdown" id="aboutDropdown">
                <a href="#" class="dropbtn" id="dropbtn">About <i class="fa-solid fa-chevron-down"></i></a>
                <div class="dropdown-content" id="dropdownContent">
                    <a href="#about" onclick="smoothScrollToSection('about')">About Us</a>
                    <a href="#mission" onclick="smoothScrollToSection('mission')">Our Mission</a>
                    <a href="#vision" onclick="smoothScrollToSection('vision')">Our Vision</a>
                </div>
            </div>
            <a href="contact.php">Contact</a>
            <?php if (isset($_SESSION['business_id'])): ?>
                <a href="business/business-dashboard.php"><i class="fa-regular fa-building"></i> Dashboard</a>
                <a href="business/business-logout.php" class="btn-outline"><i class="fa-regular fa-sign-out"></i> Logout</a>
            <?php elseif (isset($_SESSION['user_id'])): ?>
                <a href="user-dashboard.php"><i class="fa-regular fa-user"></i> Dashboard</a>
                <a href="logout.php?t=<?= time() ?>" class="btn-outline"><i class="fa-regular fa-sign-out"></i> Logout</a>
                <a href="business/business-signup.php" class="btn-outline"><i class="fa-regular fa-building"></i> List Business</a>
            <?php else: ?>
                <a href="user-login.php"><i class="fa-regular fa-user"></i> Client Login</a>
                <a href="business/business-login.php"><i class="fa-regular fa-building"></i> Business Login</a>
                <a href="business/business-signup.php" class="btn-outline"><i class="fa-regular fa-building"></i> List Business</a>
            <?php endif; ?>
        </nav>
    </header>

    <section class="hero">
        <img src="1.png" alt="Car wash">
        <div class="overlay"></div>
        <div style="position: relative; z-index: 1;">
            <h2 id="typing-headline"></h2>
            <p>Book. Wash. Go </p>
            <div style="margin-top: 2rem;">
                <?php if (isset($_SESSION['business_id'])): ?>
                    <a href="business/business-dashboard.php" class="btn">Go to Business Dashboard →</a>
                <?php elseif (isset($_SESSION['user_id'])): ?>
                    <a href="user-dashboard.php" class="btn">Go to Dashboard →</a>
                <?php else: ?>
                    <div style="display: flex; gap: 1rem; justify-content: center;">
                        <a href="user-login.php" class="btn">Client Login</a>
                        <a href="business/business-login.php" class="btn" style="background:linear-gradient(135deg, var(--purple-primary), var(--purple-dark));">Business Login</a>
                    </div>
                <?php endif; ?>
            </div>
            <p style="margin-top:1.5rem; color:rgba(255,255,255,0.8);">Join thousands of happy customers </p>
        </div>
    </section>

    <div class="stats-bar">
        <div class="stat-item" onclick="celebrate()">
            <div class="number" id="washesCount">0</div>
            <div class="label">Washes completed </div>
        </div>
        <div class="stat-item" onclick="celebrate()">
            <div class="number" id="businessesCount">0</div>
            <div class="label">Car washes registered </div>
        </div>
        <div class="stat-item" onclick="celebrate()">
            <div class="number" id="ratingCount">0</div>
            <div class="label">Average rating </div>
        </div>
    </div>

    <h2 class="section-title">How it works</h2>
    <div class="cards-grid">
        <div class="card how-card"><div class="card-icon"><i class="fa-solid fa-magnifying-glass"></i></div><h3>1. Search</h3><p>Find top rated car washes near you.</p></div>
        <div class="card how-card"><div class="card-icon"><i class="fa-regular fa-calendar-check"></i></div><h3>2. Book</h3><p>Choose a time slot that fits your schedule.</p></div>
        <div class="card how-card"><div class="card-icon"><i class="fa-solid fa-droplet"></i></div><h3>3. Wash</h3><p>Arrive dirty and leave clean </p></div>
    </div>

    <h2 class="section-title">Why choose us</h2>
    <div class="cards-grid">
        <div class="card how-card"><div class="card-icon"><i class="fa-solid fa-leaf"></i></div><h3>Eco‑friendly</h3><p>Water‑smart partners, biodegradable soaps </p></div>
        <div class="card how-card"><div class="card-icon"><i class="fa-solid fa-clock"></i></div><h3>Save time</h3><p>Book in under 60 seconds </p></div>
        <div class="card how-card"><div class="card-icon"><i class="fa-solid fa-medal"></i></div><h3>Quality guaranteed</h3><p>Top‑rated and inspected regularly </p></div>
    </div>

    <h2 class="section-title">Everything you need</h2>
    <div class="cards-grid">
        <div class="card how-card"><div class="card-icon"><i class="fa-solid fa-magnifying-glass"></i></div><h3>Find & Filter</h3><p>Search by location, rating, or name.</p></div>
        <div class="card how-card"><div class="card-icon"><i class="fa-regular fa-calendar-check"></i></div><h3>Book Instantly</h3><p>Real‑time availability, instant confirmation.</p></div>
        <div class="card how-card"><div class="card-icon"><i class="fa-regular fa-star"></i></div><h3>Rate & Review</h3><p>Rate and leave a comment for any car wash</p></div>
        <div class="card how-card"><div class="card-icon"><i class="fa-regular fa-heart"></i></div><h3>Favourites</h3><p>Save your favourite car washes </p></div>
        <div class="card how-card"><div class="card-icon"><i class="fa-regular fa-building"></i></div><h3>Business Tools</h3><p>Full dashboard for car washes.</p></div>
        <div class="card how-card"><div class="card-icon"><i class="fa-regular fa-credit-card"></i></div><h3>Secure Payments</h3><p>E‑wallet for businesses </p></div>
    </div>

    <h2 class="section-title">Happy customers </h2>
    <div class="cards-grid">
        <?php if (empty($testimonials)): ?>
            <p style="color:#2c3e50;">No reviews yet. Be the first to share your experience! </p>
        <?php else: ?>
            <?php foreach ($testimonials as $t): ?>
            <div class="card testimonial-card">
                <div class="quote-icon"><i class="fa-solid fa-quote-right"></i></div>
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
                <p class="testimonial-text">"<?= htmlspecialchars(substr($t['comment'], 0, 150)) . (strlen($t['comment']) > 150 ? '...' : '') ?>"</p>
                <p style="margin-top:1rem; font-size:0.8rem; color:#6c7a8a;">– <?= htmlspecialchars($t['business_name']) ?></p>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if (!empty($leaderboard_businesses)): ?>
    <h2 class="section-title"> Top Rated in <?= htmlspecialchars($leaderboard_region) ?></h2>
    <div class="cards-grid">
        <?php foreach ($leaderboard_businesses as $biz): ?>
        <div class="card">
            <?php if (!empty($biz['logo_url']) && file_exists($biz['logo_url'])): ?>
                <img src="<?= htmlspecialchars($biz['logo_url']) ?>" style="width: 64px; height: 64px; border-radius: 50%; object-fit: cover; margin: 0 auto 1rem; display: block; border: 2px solid var(--purple-primary);">
            <?php else: ?>
                <div class="card-icon"><i class="fa-solid fa-sparkles"></i></div>
            <?php endif; ?>
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
                <a href="business-profile.php?id=<?= $biz['id'] ?>" class="btn"><i class="fa-regular fa-building"></i> View Details</a>
                <a href="book.php?id=<?= $biz['id'] ?>" class="btn" style="background:linear-gradient(135deg, var(--orange-primary), var(--orange-dark));"><i class="fa-regular fa-calendar-check"></i> Book Now</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <h2 class="section-title">Popular near you </h2>
<div style="text-align: center; margin-bottom: 1rem;">
    <button id="locate-btn"><i class="fa-solid fa-location-dot"></i> Use my location</button>
    <span id="location-status" style="margin-left:1rem; color:#2c3e50;"></span>
</div>

<div class="expandable-grid" id="expandableGrid">
    <div class="cards-grid" id="popular-grid">
        <?php if (empty($businesses)): ?>
            <p style="color:#2c3e50;">No car washes available.</p>
        <?php else: ?>
            <?php 
            $counter = 0;
            foreach ($businesses as $biz): 
                $hiddenClass = ($counter >= 2) ? 'hidden-card' : '';
            ?>
            <div class="card <?= $hiddenClass ?>">
                <?php if (!empty($biz['logo_url']) && file_exists($biz['logo_url'])): ?>
                    <img src="<?= htmlspecialchars($biz['logo_url']) ?>" style="width: 64px; height: 64px; border-radius: 50%; object-fit: cover; margin: 0 auto 1rem; display: block; border: 2px solid var(--purple-primary);">
                <?php else: ?>
                    <div class="card-icon"><i class="fa-solid fa-sparkles"></i></div>
                <?php endif; ?>
                <h3><?= htmlspecialchars($biz['name']) ?></h3>
                <p><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars(substr($biz['address'], 0, 40)) . (strlen($biz['address']) > 40 ? '...' : '') ?></p>
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
                        <a href="business-profile.php?id=<?= $biz['id'] ?>" class="btn"><i class="fa-regular fa-building"></i> Details</a>
                    <?php else: ?>
                        <a href="user-login.php?redirect=<?= urlencode('business-profile.php?id=' . $biz['id']) ?>" class="btn"><i class="fa-regular fa-building"></i> Login to View</a>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="book.php?id=<?= $biz['id'] ?>" class="btn" style="background:linear-gradient(135deg, var(--orange-primary), var(--orange-dark));"><i class="fa-regular fa-calendar-check"></i> Book Now</a>
                    <?php else: ?>
                        <a href="user-login.php?redirect=<?= urlencode('book.php?id=' . $biz['id']) ?>" class="btn" style="background:linear-gradient(135deg, var(--orange-primary), var(--orange-dark));"><i class="fa-regular fa-calendar-check"></i> Book Now</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php 
            $counter++;
            endforeach; 
            ?>
            <script>var businesses = <?= json_encode($businesses) ?>;</script>
        <?php endif; ?>
    </div>
</div>

<?php if (count($businesses) > 2): ?>
    <div style="text-align: center; margin-top: 1rem;">
        <button id="showMoreBtn" class="btn" style="background: var(--purple-primary); padding: 0.5rem 2rem; border: none; cursor: pointer;">Show more <i class="fa-solid fa-chevron-down"></i></button>
    </div>
<?php endif; ?>

    <section id="about" style="scroll-margin-top: 80px;">
        <h2 class="section-title">About No Q</h2>
        <div class="cards-grid" style="max-width:900px;">
            <div class="card how-card" style="width:100%; max-width:600px; margin:0 auto; text-align:center;">
                <p style="font-size:1.1rem; line-height:1.6;">We started in 2026 to make car care effortless. No more waiting in line. Just tap, book, and shine. We want to connect drivers with the best local car washes across South Africa.</p>
               
            </div>
        </div>
    </section>

    <section id="mission" style="scroll-margin-top: 80px;"> 
        <h2 class="section-title">Our Mission</h2>
        <div class="cards-grid" style="max-width: 900px;">
            <div class="card how-card" style="width: 100%; max-width: 600px; margin: 0 auto; text-align: center;">
                <p style="font-size: 1.1rem; line-height: 1.6;">To revolutionize the car wash experience by providing a seamless, tech‑driven platform that connects customers with top‑quality car washes, saving time and ensuring satisfaction.</p>
            </div>
        </div>
    </section>

    <section id="vision" style="scroll-margin-top: 80px;"> 
        <h2 class="section-title">Our Vision</h2>
        <div class="cards-grid" style="max-width: 900px;">
            <div class="card how-card" style="width: 100%; max-width: 600px; margin: 0 auto; text-align: center;">
                <p style="font-size: 1.1rem; line-height: 1.6;">To become the leading car wash booking platform across South Africa, empowering local businesses and delivering convenience to every driver.</p>
            </div>
        </div>
    </section>

    <div class="fab" onclick="window.location.href='search.php'">
        <i class="fa-solid fa-car"></i>
    </div>

    <footer class="app-footer">
        <p>&copy; <?= date('Y'); ?> No Q. All rights reserved</p>
        <p>Powered by <a href="https://www.jaekerna.com/" target="_blank">Jaekerna Investments</a></p>
    </footer>

    <script>
        // Typing effect for hero headline
        const phrases = [
            "Shine is just a tap away ",
            "Your car deserves the best ",
            "Book. Wash. Smile. ",
            "Clean car, happy heart "
        ];
        let phraseIndex = 0;
        let charIndex = 0;
        let isDeleting = false;
        const typingElement = document.getElementById('typing-headline');
        
        function typeEffect() {
            const currentPhrase = phrases[phraseIndex];
            if (isDeleting) {
                typingElement.textContent = currentPhrase.substring(0, charIndex - 1);
                charIndex--;
                if (charIndex === 0) {
                    isDeleting = false;
                    phraseIndex = (phraseIndex + 1) % phrases.length;
                    setTimeout(typeEffect, 500);
                    return;
                }
            } else {
                typingElement.textContent = currentPhrase.substring(0, charIndex + 1);
                charIndex++;
                if (charIndex === currentPhrase.length) {
                    isDeleting = true;
                    setTimeout(typeEffect, 2000);
                    return;
                }
            }
            setTimeout(typeEffect, isDeleting ? 50 : 100);
        }
        typeEffect();
        
        function formatNumberShort(num) {
            if (num < 1000) {
                return num.toString();
            } else if (num < 10000) {
                return (num / 1000).toFixed(1) + 'K';
            } else if (num < 100000) {
                return Math.round(num / 1000) + 'K';
            } else {
                return Math.round(num / 1000) + 'K+';
            }
        }

        function animateCounter(element, target, suffix = '', showConfetti = false) {
            let current = 0;
            const increment = Math.ceil(target / 50);
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    element.innerText = formatNumberShort(target) + suffix;
                    clearInterval(timer);
                    if (showConfetti) {
                        celebrate();
                    }
                } else {
                    element.innerText = formatNumberShort(current) + suffix;
                }
            }, 30);
        }

        function celebrate() {
            for (let i = 0; i < 50; i++) {
                const confetti = document.createElement('div');
                confetti.classList.add('confetti');
                confetti.style.left = Math.random() * 100 + '%';
                confetti.style.animationDelay = Math.random() * 0.5 + 's';
                confetti.style.background = `hsl(${Math.random() * 360}, 70%, 50%)`;
                document.body.appendChild(confetti);
                setTimeout(() => confetti.remove(), 3000);
            }
        }

        const statsObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    animateCounter(document.getElementById('washesCount'), <?= $totalWashes ?>, ' ', true);
                    animateCounter(document.getElementById('businessesCount'), <?= $totalBusinesses ?>, ' ');
                    animateCounter(document.getElementById('ratingCount'), <?= $avgRating ?>, ' ');
                    statsObserver.disconnect();
                }
            });
        }, { threshold: 0.3 });
        
        document.addEventListener('DOMContentLoaded', function() {
            const dropdownHover = document.querySelector('.dropdown');
            const blurOverlay = document.getElementById('blurOverlay');
            if (dropdownHover && blurOverlay && window.innerWidth > 768) {
                dropdownHover.addEventListener('mouseenter', () => blurOverlay.classList.add('show'));
                dropdownHover.addEventListener('mouseleave', () => blurOverlay.classList.remove('show'));
            }
            const statsBar = document.querySelector('.stats-bar');
            if (statsBar) statsObserver.observe(statsBar);
            
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

        function smoothScrollToSection(sectionId) {
            const section = document.getElementById(sectionId);
            if (!section) return;
            section.scrollIntoView({ behavior: 'smooth', block: 'start' });
            if (window.innerWidth <= 768) {
                document.getElementById('navLinks').classList.remove('show');
                var dropdown = document.getElementById('aboutDropdown');
                if (dropdown) dropdown.classList.remove('open');
                document.getElementById('blurOverlay').classList.remove('show');
            }
        }

        document.getElementById('menuToggle').addEventListener('click', function() {
            document.getElementById('navLinks').classList.toggle('show');
            document.getElementById('blurOverlay').classList.toggle('show');
        });
        
        document.getElementById('blurOverlay').addEventListener('click', function() {
            var navLinks = document.getElementById('navLinks');
            if (navLinks) navLinks.classList.remove('show');
            var dropdown = document.getElementById('aboutDropdown');
            if (dropdown) dropdown.classList.remove('open');
            this.classList.remove('show');
        });
        
        document.querySelectorAll('.nav-links > a:not(.dropdown .dropbtn)').forEach(link => {
            link.addEventListener('click', () => {
                document.getElementById('navLinks').classList.remove('show');
            });
        });

        var showMoreBtn = document.getElementById('showMoreBtn');
        if (showMoreBtn) {
            showMoreBtn.addEventListener('click', function() {
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
        }

        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function(position) {
                fetch('save-location.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'lat=' + position.coords.latitude + '&lng=' + position.coords.longitude
                });
            }, function(error) { console.log('Location error:', error); });
        }
        
        var loggedIn = <?= isset($_SESSION['user_id']) ? 'true' : 'false' ?>;
        var businesses = <?= json_encode($businesses ?: []) ?>;

        var locateBtn = document.getElementById('locate-btn');
        if (locateBtn) {
            locateBtn.addEventListener('click', function() {
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
                    businesses.slice(0, 12).forEach((b, idx) => {
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
                        if (idx >= 2) card.classList.add('hidden-card');
                        var logoHtml = (b.logo_url && b.logo_url !== '') ? `<img src="${escapeHtml(b.logo_url)}" style="width: 64px; height: 64px; border-radius: 50%; object-fit: cover; margin: 0 auto 1rem; display: block; border: 2px solid var(--purple-primary);">` : `<div class="card-icon"><i class="fa-solid fa-sparkles"></i></div>`;
                        card.innerHTML = `${logoHtml}<h3>${escapeHtml(b.name)}</h3><p><i class="fa-solid fa-location-dot"></i> ${escapeHtml(b.address.substring(0, 40))}${b.address.length > 40 ? '...' : ''}</p><div style="font-size:0.85rem; color:#666; margin-bottom:0.5rem;"> ${dist}</div><div class="rating">${stars}</div><div style="display: flex; gap: 0.5rem; justify-content: center;"><a href="business-profile.php?id=${b.id}" class="btn"><i class="fa-regular fa-building"></i> Details</a><a href="book.php?id=${b.id}" class="btn" style="background:linear-gradient(135deg, var(--orange-primary), var(--orange-dark));"><i class="fa-regular fa-calendar-check"></i> Book Now</a></div>`;
                        grid.appendChild(card);
                    });
                    status.innerHTML = 'Sorted by distance.';
                    
                    // Reset show more button
                    var showBtn = document.getElementById('showMoreBtn');
                    if (showBtn) {
                        if (businesses.length > 2) {
                            showBtn.style.display = 'inline-block';
                            var expandableGrid = document.getElementById('expandableGrid');
                            if (expandableGrid.classList.contains('expanded')) {
                                expandableGrid.classList.remove('expanded');
                                showBtn.innerHTML = 'Show more <i class="fa-solid fa-chevron-down"></i>';
                            }
                        } else {
                            showBtn.style.display = 'none';
                        }
                    }
                }, function(error) {
                    status.innerHTML = 'Unable to get location.';
                });
            });
        }

        function escapeHtml(unsafe) {
            if (!unsafe) return '';
            return unsafe.replace(/[&<>"']/g, m => {
                if (m === '&') return '&amp;';
                if (m === '<') return '&lt;';
                if (m === '>') return '&gt;';
                if (m === '"') return '&quot;';
                return '&#039;';
            });
        }

        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/carwash-connect/sw.js')
                .then(reg => console.log('Service Worker registered'))
                .catch(err => console.error('SW registration failed', err));
        }

        let deferredPrompt;
        let installButton = null;

        function createInstallButton(manual = false) {
            if (window.matchMedia('(display-mode: standalone)').matches) return;
            if (window.innerWidth > 768) return;
            if (installButton) return;
            installButton = document.createElement('button');
            installButton.className = 'install-btn';
            installButton.innerHTML = '<i class="fa-solid fa-download"></i> Install App';
            if (manual) {
                installButton.onclick = () => alert('To install, select "Add to Home Screen" from your browser menu.');
            } else {
                installButton.onclick = async () => {
                    if (!deferredPrompt) return;
                    deferredPrompt.prompt();
                    const { outcome } = await deferredPrompt.userChoice;
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

        document.addEventListener('DOMContentLoaded', function() {
            const dropdownHover = document.querySelector('.dropdown');
            const blurOverlay = document.getElementById('blurOverlay');
            if (dropdownHover && blurOverlay && window.innerWidth > 768) {
                dropdownHover.addEventListener('mouseenter', () => blurOverlay.classList.add('show'));
                dropdownHover.addEventListener('mouseleave', () => blurOverlay.classList.remove('show'));
            }
            var dropbtn = document.getElementById('dropbtn');
            var dropdown = document.getElementById('aboutDropdown');
            
            if (dropbtn && dropdown) {
                dropbtn.addEventListener('click', function(e) {
                    if (window.innerWidth <= 768) {
                        e.preventDefault();
                        e.stopPropagation();
                        dropdown.classList.toggle('open');
                    }
                });
            }
            
            document.addEventListener('click', function(e) {
                if (window.innerWidth <= 768 && dropdown && !dropdown.contains(e.target)) {
                    dropdown.classList.remove('open');
                }
            });
            
            var dropdownLinks = document.querySelectorAll('.dropdown-content a');
            dropdownLinks.forEach(function(link) {
                link.addEventListener('click', function() {
                    if (window.innerWidth <= 768) {
                        document.getElementById('navLinks').classList.remove('show');
                        if (dropdown) dropdown.classList.remove('open');
                    }
                });
            });
        });
    </script>
</body>
</html>
