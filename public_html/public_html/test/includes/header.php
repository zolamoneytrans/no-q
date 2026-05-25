<?php
// includes/header.php
// Make sure session is started before including this file.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? $pageTitle . ' · ' : '' ?>No Q</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* ===== GLOBAL STYLES ===== */
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

        /* ===== HAMBURGER MENU ===== */
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

        /* ===== COMMON UTILITY CLASSES ===== */
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
        }
        .card:hover {
            transform: translateY(-8px);
            background: white;
            box-shadow: 0 30px 50px -15px #1e3c7240;
        }
        .btn {
            display: inline-block;
            background: #1e3c72;
            color: white;
            text-decoration: none;
            padding: 0.7rem 1.5rem;
            border-radius: 40px;
            font-weight: 500;
            transition: 0.15s;
            border: none;
            cursor: pointer;
        }
        .btn:hover {
            background: #2a5298;
            box-shadow: 0 8px 18px #1e3c7240;
        }
        .app-footer {
            background: rgba(255,255,255,0.6);
            backdrop-filter: blur(8px);
            border-top: 1px solid white;
            padding: 2rem;
            text-align: center;
            margin-top: auto;
            color: #1e3c72;
            font-weight: 400;
        }

        /* ===== RESPONSIVE TABLES ===== */
        @media (max-width: 768px) {
            table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
        }
        @media (max-width: 480px) {
            .cards-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            .card {
                width: 100%;
            }
            .hero h2 {
                font-size: 2rem;
            }
            .hero p {
                font-size: 1rem;
            }
            .search-wrapper {
                flex-direction: column;
                padding: 0.5rem;
            }
            .search-wrapper input {
                width: 100%;
                margin-bottom: 0.5rem;
            }
            .search-wrapper button {
                width: 100%;
            }
        }

        /* Hero specific (these will be used in index.php) */
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
            font-size: clamp(2.5rem, 8vw, 4rem);
            font-weight: 700;
            letter-spacing: -1px;
            margin-bottom: 0.5rem;
        }
        .hero p {
            color: white;
            font-size: 1.2rem;
            max-width: 600px;
            margin: 1rem auto;
            opacity: 0.9;
        }
        .search-wrapper {
            background: white;
            max-width: 650px;
            margin: 2.5rem auto 0;
            border-radius: 60px;
            display: flex;
            align-items: center;
            padding: 0.3rem 0.3rem 0.3rem 1.5rem;
            box-shadow: 0 20px 40px -10px rgba(30,60,114,0.2);
            border: 1px solid rgba(255,255,255,0.5);
        }
        .search-wrapper i {
            color: #2a5298;
            font-size: 1.2rem;
        }
        .search-wrapper input {
            flex: 1;
            border: none;
            padding: 1rem 0.8rem;
            font-size: 1rem;
            font-family: 'Inter', sans-serif;
            background: transparent;
            outline: none;
        }
        .search-wrapper button {
            background: #1e3c72;
            border: none;
            color: white;
            font-weight: 600;
            padding: 0.9rem 2rem;
            border-radius: 50px;
            cursor: pointer;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: 0.15s;
            white-space: nowrap;
        }
        .search-wrapper button:hover {
            background: #2a5298;
            transform: scale(1.02);
            box-shadow: 0 8px 18px rgba(42,82,152,0.4);
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
        .stat-item {
            text-align: center;
        }
        .stat-item .number {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(145deg, #1e3c72, #2a5298);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .stat-item .label {
            color: #2c3e50;
            font-size: 0.9rem;
        }
        #locate-btn {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(255, 152, 0, 0.7); }
            50% { transform: scale(1.05); box-shadow: 0 0 0 10px rgba(255, 152, 0, 0); }
            100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(255, 152, 0, 0); }
        }
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }
    </style>
</head>
<body>
    <header class="app-header">
        <div class="logo-area">
            <div class="logo-icon"><i class="fas fa-car-wash"></i></div>
            <span class="logo-text">CarWash<span style="font-weight:400;">Connect</span></span>
        </div>
        <button class="menu-toggle" id="menuToggle" aria-label="Menu">
            <i class="fa-solid fa-bars"></i>
        </button>
        <nav class="nav-links" id="navLinks">
            <a href="<?= $baseUrl ?? '.' ?>/index.php">Home</a>
            <?php if (basename($_SERVER['PHP_SELF']) != 'index.php'): ?>
                <a href="<?= $baseUrl ?? '.' ?>/index.php#about">About</a>
            <?php else: ?>
                <a href="#about" onclick="smoothScrollToSection('about')">About</a>
            <?php endif; ?>

            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="<?= $baseUrl ?? '.' ?>/user-dashboard.php"><i class="fa-regular fa-user"></i> Dashboard</a>
                <a href="<?= $baseUrl ?? '.' ?>/logout.php" class="btn-outline"><i class="fa-regular fa-sign-out"></i> Logout</a>
            <?php elseif (isset($_SESSION['business_id'])): ?>
                <a href="<?= $baseUrl ?? '.' ?>/business/business-dashboard.php"><i class="fa-regular fa-building"></i> Business</a>
                <a href="<?= $baseUrl ?? '.' ?>/business/business-logout.php" class="btn-outline"><i class="fa-regular fa-sign-out"></i> Logout</a>
            <?php elseif (isset($_SESSION['admin_id'])): ?>
                <a href="<?= $baseUrl ?? '.' ?>/admin/admin-dashboard.php"><i class="fa-regular fa-shield"></i> Admin</a>
                <a href="<?= $baseUrl ?? '.' ?>/admin/admin-logout.php" class="btn-outline"><i class="fa-regular fa-sign-out"></i> Logout</a>
            <?php else: ?>
                <a href="<?= $baseUrl ?? '.' ?>/user-login.php"><i class="fa-regular fa-user"></i> Sign In</a>
            <?php endif; ?>
            <a href="<?= $baseUrl ?? '.' ?>/business/busines-signup.php" class="btn-outline"><i class="fa-regular fa-building"></i> List Business</a>
        </nav>
    </header>
