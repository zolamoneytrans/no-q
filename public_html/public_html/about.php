<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us · No Q</title>
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
            box-shadow: 0 4px 20px rgba(0,0,0,0.02);
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
            box-shadow: 0 8px 16px -4px rgba(30,60,114,0.3);
        }
        .logo-text {
            font-weight: 700;
            font-size: 1.5rem;
            letter-spacing: -0.5px;
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
        .nav-links a i { color: #2a5298; }
        .nav-links a:hover {
            background: rgba(42,82,152,0.08);
            color: #1e3c72;
        }
        .nav-links .btn-outline {
            border: 1.5px solid #1e3c72;
            padding: 0.4rem 1.2rem;
            border-radius: 40px;
            background: white;
            font-weight: 600;
        }
        .nav-links .btn-outline:hover {
            background: #1e3c72;
            color: white;
        }
        .page-title {
            text-align: center;
            padding: 3rem 2rem 1rem;
        }
        .page-title h1 {
            font-size: 3rem;
            font-weight: 700;
            background: linear-gradient(145deg, #1e3c72, #2a5298);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }
        .page-title p {
            font-size: 1.2rem;
            color: #2c3e50;
            max-width: 600px;
            margin: 0 auto;
            opacity: 0.8;
        }
        .content-section {
            max-width: 1000px;
            margin: 0 auto;
            padding: 2rem;
        }
        .glass-card {
            background: rgba(255,255,255,0.7);
            backdrop-filter: blur(8px);
            border-radius: 40px;
            padding: 2.5rem;
            border: 1px solid rgba(255,255,255,0.6);
            box-shadow: 0 20px 40px -12px rgba(0,20,40,0.2);
            margin-bottom: 2.5rem;
        }
        .glass-card h2 {
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            background: linear-gradient(145deg, #1e3c72, #2a5298);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .glass-card p {
            font-size: 1.1rem;
            line-height: 1.6;
            color: #2c3e50;
            margin-bottom: 1rem;
        }
        .values-grid {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
            margin-top: 2rem;
        }
        .value-item {
            flex: 1 1 200px;
            text-align: center;
            background: rgba(255,255,255,0.4);
            border-radius: 30px;
            padding: 2rem 1rem;
            backdrop-filter: blur(4px);
        }
        .value-item i {
            font-size: 2.5rem;
            color: #1e3c72;
            margin-bottom: 1rem;
        }
        .value-item h3 {
            font-size: 1.3rem;
            margin-bottom: 0.5rem;
        }
        .team-grid {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
            justify-content: center;
            margin-top: 2rem;
        }
        .team-member {
            background: white;
            border-radius: 30px;
            padding: 2rem 1.5rem;
            width: 220px;
            text-align: center;
            box-shadow: 0 15px 30px -8px rgba(30,60,114,0.2);
        }
        .team-member .avatar {
            width: 100px;
            height: 100px;
            background: #eef3fc;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 2.5rem;
            color: #1e3c72;
        }
        .team-member h3 {
            font-size: 1.2rem;
            margin-bottom: 0.25rem;
        }
        .team-member p {
            color: #2a5298;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        .team-member .social a {
            color: #2c3e50;
            margin: 0 5px;
            font-size: 1.1rem;
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
    </style>
</head>
<body>

<header class="app-header">
    <div class="logo-area">
        <div class="logo-icon"><i class="fas fa-car-wash"></i></div>
        <span class="logo-text">carwash<span style="font-weight:400;">connect</span></span>
    </div>
    <nav class="nav-links">
        <a href="index.php"><i class="fa-solid fa-home"></i> Home</a>
        <a href="about.php" style="background:rgba(42,82,152,0.1);"><i class="fa-regular fa-building"></i> About</a>
    </nav>
</header>

<div class="page-title">
    <h1>About Us</h1>
    <p>We're on a mission to make car care effortless and delightful</p>
</div>

<div class="content-section">
    <div class="glass-card">
        <h2>Our story</h2>
        <p>No Q was born from a simple idea: waiting in line for a car wash should be a thing of the past. In 2026, we noticed how busy professionals in Richards Bay struggled to find time for basic car maintenance. We built a platform that connects drivers with the best local car washes, allowing instant booking and digital check in.</p>
    </div>

    <div class="glass-card">
        <h2>Our values</h2>
        <div class="values-grid">
            <div class="value-item">
                <i class="fa-solid fa-gauge-high"></i>
                <h3>Speed</h3>
                <p>We respect your time. Book in under 60 seconds.</p>
            </div>
            
            <div class="value-item">
                <i class="fa-solid fa-star"></i>
                <h3>Quality</h3>
                <p>Only top‑rated washes make it to our platform.</p>
            </div>
        </div>
    </div>

    <!-- Meet the team (fictional, all static) 
    <div class="glass-card">
        <h2>Meet the team</h2>
        <div class="team-grid">
            <div class="team-member">
                <div class="avatar"><i class="fa-regular fa-user"></i></div>
                <h3>Thabo Nkosi</h3>
                <p>Founder & CEO</p>
                <div class="social">
                    <a href="#"><i class="fa-brands fa-linkedin"></i></a>
                    <a href="#"><i class="fa-brands fa-twitter"></i></a>
                </div>
            </div>
            <div class="team-member">
                <div class="avatar"><i class="fa-regular fa-user"></i></div>
                <h3>Lerato Mkhize</h3>
                <p>Head of Operations</p>
                <div class="social">
                    <a href="#"><i class="fa-brands fa-linkedin"></i></a>
                    <a href="#"><i class="fa-brands fa-twitter"></i></a>
                </div>
            </div>
            <div class="team-member">
                <div class="avatar"><i class="fa-regular fa-user"></i></div>
                <h3>Sipho Dlamini</h3>
                <p>Tech Lead</p>
                <div class="social">
                    <a href="#"><i class="fa-brands fa-linkedin"></i></a>
                    <a href="#"><i class="fa-brands fa-github"></i></a>
                </div>
            </div>
        </div>
    </div>-->
</div>

<footer class="app-footer">
    <p>&copy <?= date('Y'); ?> No Q. All rights reserved</p>
    <p>Powered by <a href="https://www.jaekerna.com/">Jaekerna Investments</a></p>

</footer>

</body>
</html>
