<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['business_id'])) {
    header('Location: business-login.php');
    exit;
}

$business_name = $_SESSION['business_name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Help & FAQ · <?= htmlspecialchars($business_name) ?></title>
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
            font-weight: 700; 
            font-size: 1.5rem; 
            background: linear-gradient(135deg, var(--purple-primary), var(--orange-primary)); 
            -webkit-background-clip: text; 
            -webkit-text-fill-color: transparent; 
        }
        .nav-links { display: flex; gap: 1.2rem; align-items: center; flex-wrap: wrap; }
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
        .nav-links .btn-outline { 
            border: 1.5px solid var(--purple-primary); 
            padding: 0.4rem 1.2rem; 
            border-radius: 40px; 
            background: white; 
            font-weight: 600; 
        }
        .nav-links .btn-outline:hover { background: var(--purple-primary); color: white; }

        .menu-toggle { display: none; font-size: 1.8rem; cursor: pointer; color: var(--purple-primary); background: transparent; border: none; padding: 0.5rem; transition: transform 0.2s; }
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
            .btn-outline { width: 100%; }
        }

        .container { max-width: 900px; margin: 2rem auto; padding: 0 2rem; flex:1; }
        .card {
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(10px);
            border-radius: 40px;
            padding: 2rem;
            border: 1px solid rgba(106,27,154,0.1);
            margin-bottom: 2rem;
            box-shadow: 0 20px 40px -12px rgba(106,27,154,0.2);
        }
        h1 { 
            font-size: 2rem; 
            margin-bottom: 0.5rem; 
            background: linear-gradient(145deg, var(--purple-primary), var(--orange-primary)); 
            -webkit-background-clip: text; 
            -webkit-text-fill-color: transparent; 
        }
        .subtitle { color: #2c3e50; margin-bottom: 2rem; }

        .faq-section h2 { 
            font-size: 1.4rem; 
            margin: 1.5rem 0 1rem; 
            color: var(--purple-primary);
            border-left: 3px solid var(--orange-primary);
            padding-left: 1rem;
        }
        .faq-item {
            border-bottom: 1px solid rgba(0,0,0,0.1);
            margin-bottom: 0.5rem;
        }
        .faq-question {
            background: transparent;
            border: none;
            width: 100%;
            text-align: left;
            padding: 1rem 0;
            font-weight: 600;
            font-size: 1rem;
            color: var(--purple-primary);
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: 0.2s;
        }
        .faq-question:hover { color: var(--orange-primary); }
        .faq-question i { transition: transform 0.2s; color: var(--orange-primary); }
        .faq-question.active i { transform: rotate(180deg); }
        .faq-answer {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
            padding: 0 0 0 0;
            color: #2c3e50;
            line-height: 1.6;
        }
        .faq-answer.show {
            max-height: 500px;
            padding: 0 0 1rem 0;
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
            .card { padding: 1.2rem; }
            h1 { font-size: 1.5rem; }
        }
    </style>
</head>
<body>
    <header class="app-header">
        <div class="logo-area">
            <img src="/NoQ.jpg" alt="No Q" style="height: 85px; width: auto;">
            <div>
                <span class="logo-text">No Q</span>
                <div style="font-size: 0.7rem; color: var(--purple-primary); letter-spacing: 0.5px;">No more Queues</div>
            </div>
        </div>
        <button class="menu-toggle" id="menuToggle"><i class="fa-solid fa-bars"></i></button>
        <nav class="nav-links" id="navLinks">
            <a href="../index.php">Home</a>
            <a href="business-dashboard.php">Dashboard</a>
            <a href="bookings.php">Bookings</a>
            <a href="services.php">Services</a>
            <a href="images.php">Images</a>
            <a href="status.php">Status</a>
            <a href="business-settings.php">Settings</a>
            <a href="reports.php">Reports</a>
            <a href="business-logout.php" class="btn-outline">Logout</a>
        </nav>
    </header>

    <div class="container">
        <div class="card faq-section">
            <h1>Help & FAQ</h1>
            <p class="subtitle">Everything you need to know about managing your car wash on No Q.</p>

            <h2>General</h2>
            <div class="faq-item">
                <button class="faq-question">How do I update my business hours? <i class="fa-solid fa-chevron-down"></i></button>
                <div class="faq-answer">Go to <strong>Settings</strong> from the sidebar. There you can set opening and closing times for each day, as well as slot duration (how long each booking lasts).</div>
            </div>
            <div class="faq-item">
                <button class="faq-question">How do I change my business address or phone number? <i class="fa-solid fa-chevron-down"></i></button>
                <div class="faq-answer">Same as above – visit <strong>Settings</strong> and edit the fields under Business Information. Changes reflect immediately.</div>
            </div>

            <h2>Bookings</h2>
            <div class="faq-item">
                <button class="faq-question">How do I confirm or cancel a booking? <i class="fa-solid fa-chevron-down"></i></button>
                <div class="faq-answer">Go to <strong>Manage Bookings</strong> in the sidebar. Find the booking and use the green Confirm or red Cancel buttons. Customers will be notified by email and in‑app.</div>
            </div>
            <div class="faq-item">
                <button class="faq-question">Can I mark a booking as completed without the customer? <i class="fa-solid fa-chevron-down"></i></button>
                <div class="faq-answer">Yes. Use the <strong>Complete by Code</strong> page in the sidebar. Enter the customer's booking code to mark it completed instantly. This also adds the amount to your wallet.</div>
            </div>
            <div class="faq-item">
                <button class="faq-question">What if a customer doesn't show up? <i class="fa-solid fa-chevron-down"></i></button>
                <div class="faq-answer">You can cancel the booking from the Manage Bookings page. The customer will be notified, and the slot becomes available again.</div>
            </div>

            <h2>Services & Pricing</h2>
            <div class="faq-item">
                <button class="faq-question">How do I add or edit services? <i class="fa-solid fa-chevron-down"></i></button>
                <div class="faq-answer">Go to <strong>Manage Services</strong>. You can add new services with name, description, price, and duration. Existing services can be edited or deleted.</div>
            </div>
            <div class="faq-item">
                <button class="faq-question">What is "slot duration"? <i class="fa-solid fa-chevron-down"></i></button>
                <div class="faq-answer">Slot duration determines how much time is allocated per booking. For example, 30 minutes means a slot like 09:00–09:30. Set this in your business Settings.</div>
            </div>

            <h2>Images</h2>
            <div class="faq-item">
                <button class="faq-question">How do I add photos of my car wash? <i class="fa-solid fa-chevron-down"></i></button>
                <div class="faq-answer">Visit <strong>Manage Images</strong> from the sidebar. You can upload JPG, PNG, GIF, or WEBP images (max 5MB). Uploaded images appear on your business profile for customers to see.</div>
            </div>

            <h2>Status & Congestion</h2>
            <div class="faq-item">
                <button class="faq-question">How do I show customers if I'm busy? <i class="fa-solid fa-chevron-down"></i></button>
                <div class="faq-answer">Use the <strong>Update Status</strong> page. You can set congestion level (Low/Moderate/High), current queue count, estimated wait time, and a text description of available slots. This is shown on your profile.</div>
            </div>

            <h2>E‑Wallet & Withdrawals</h2>
            <div class="faq-item">
                <button class="faq-question">How do I check my earnings? <i class="fa-solid fa-chevron-down"></i></button>
                <div class="faq-answer">Your wallet balance is displayed on the dashboard sidebar. Every completed booking adds the amount to your wallet.</div>
            </div>
            <div class="faq-item">
                <button class="faq-question">How do I request a payout? <i class="fa-solid fa-chevron-down"></i></button>
                <div class="faq-answer">Click <strong>Request Withdrawal</strong> on the dashboard. Enter the amount and submit. Your request will be reviewed by admin and funds will be transferred after approval.</div>
            </div>

            <h2>Reports</h2>
            <div class="faq-item">
                <button class="faq-question">How can I see my business performance? <i class="fa-solid fa-chevron-down"></i></button>
                <div class="faq-answer">Go to <strong>Reports</strong> in the sidebar. You'll see charts for monthly revenue, weekly bookings, and revenue by service. This helps you track trends.</div>
            </div>

            <h2>Account</h2>
            <div class="faq-item">
                <button class="faq-question">How do I change my password? <i class="fa-solid fa-chevron-down"></i></button>
                <div class="faq-answer">Go to Business Settings.  Scroll down to the password section. That's where you can change your password. </div>
            </div>
        </div>
    </div>

    <footer class="app-footer">
        <p>&copy; <?= date('Y'); ?> No Q. All rights reserved.</p>
        <p><a href="/contact.php">Contact Us</a> | Powered by <a href="https://www.jaekerna.com/" target="_blank">Jaekerna Investments</a></p>
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

            document.querySelectorAll('.faq-question').forEach(button => {
                button.addEventListener('click', () => {
                    const answer = button.nextElementSibling;
                    const isOpen = answer.classList.contains('show');
                    document.querySelectorAll('.faq-answer').forEach(ans => ans.classList.remove('show'));
                    document.querySelectorAll('.faq-question').forEach(btn => btn.classList.remove('active'));
                    if (!isOpen) {
                        answer.classList.add('show');
                        button.classList.add('active');
                    }
                });
            });
        });
    </script>
</body>
</html>
