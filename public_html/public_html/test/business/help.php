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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="/carwash-connect/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/carwash-connect/apple-touch-icon.png" />
    <meta name="apple-mobile-web-app-title" content="No Q" />
    <link rel="manifest" href="/carwash-connect/site.webmanifest" />
    <title>Help & FAQ · <?= htmlspecialchars($business_name) ?></title>
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
            position: sticky;
            top: 0;
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
        }
        .logo-area { display: flex; align-items: center; gap: 10px; }
        .logo-icon { background: #1e3c72; color: white; width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        .logo-text { font-weight: 700; font-size: 1.5rem; background: linear-gradient(135deg, #1e3c72, #2a5298); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .nav-links { display: flex; gap: 1.2rem; align-items: center; }
        .nav-links a { text-decoration: none; font-weight: 500; color: #2c3e50; transition: 0.2s; font-size: 0.95rem; display: flex; align-items: center; gap: 6px; padding: 0.5rem 0.8rem; border-radius: 40px; }
        .nav-links a i { font-size: 1rem; color: #2a5298; }
        .nav-links a:hover { background: rgba(42,82,152,0.08); color: #1e3c72; }
        .nav-links .btn-outline { border: 1.5px solid #1e3c72; padding: 0.4rem 1.2rem; border-radius: 40px; background: white; font-weight: 600; }
        .nav-links .btn-outline:hover { background: #1e3c72; color: white; }

        .menu-toggle { display: none; font-size: 1.8rem; cursor: pointer; color: #1e3c72; background: transparent; border: none; padding: 0.5rem; }
        @media (max-width: 768px) {
            .menu-toggle { display: block; }
            .nav-links { display: none; width:100%; flex-direction:column; align-items:center; gap:0.5rem; padding:1rem 0; background:rgba(255,255,255,0.9); backdrop-filter:blur(10px); border-radius:30px; margin-top:1rem; }
            .nav-links.show { display: flex; }
            .app-header { padding: 0.8rem 1rem; }
            .nav-links a { width:100%; text-align:center; padding:0.8rem; }
            .btn-outline { width:100%; }
        }

        .container { max-width: 900px; margin: 2rem auto; padding: 0 2rem; flex:1; }
        .card {
            background: rgba(255,255,255,0.8);
            backdrop-filter: blur(10px);
            border-radius: 40px;
            padding: 2rem;
            border: 1px solid rgba(255,255,255,0.6);
            margin-bottom: 2rem;
        }
        h1 { font-size: 2rem; margin-bottom: 0.5rem; background: linear-gradient(145deg, #1e3c72, #2a5298); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .subtitle { color: #2c3e50; margin-bottom: 2rem; }

        .faq-section h2 { font-size: 1.8rem; margin: 0 0 1rem 0; }
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
            font-size: 1.1rem;
            color: #1e3c72;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: 0.2s;
        }
        .faq-question:hover { color: #2a5298; }
        .faq-question i { transition: transform 0.2s; }
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
            color: #1e3c72;
        }
        .app-footer a { color: #1e3c72; text-decoration: none; }
        .app-footer a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <header class="app-header">
        <div class="logo-area">
            <img src="/logo.jpeg" alt="No Q" style="height: 40px; width: auto;">
            <span class="logo-text">CarWash<span style="font-weight:400;">Connect</span></span>
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
                <div class="faq-answer">Yes. Use the <strong>Complete by Code</strong> page in the sidebar. Enter the customer’s booking code to mark it completed instantly. This also adds the amount to your wallet.</div>
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
                <div class="faq-answer">Currently, password changes are not yet available. Contact support if you need to reset your password.</div>
            </div>
        </div>
    </div>

    <footer class="app-footer">
        <p>&copy; <?= date('Y'); ?> No Q. All rights reserved.</p>
        <p><a href="contact.php">Contact Us</a> 
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

            // FAQ Accordion
            document.querySelectorAll('.faq-question').forEach(button => {
                button.addEventListener('click', () => {
                    const answer = button.nextElementSibling;
                    const isOpen = answer.classList.contains('show');
                    // Optional: close all others
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
