<?php
session_start();
require_once 'db_connect.php';

$error = '';
$success = '';

$client_emails = ['aosolvers@carwashes.africa']; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (empty($name) || empty($email) || empty($message)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $email, $subject, $message]);

        // Send email
        $mail_subject = "Contact Form: " . ($subject ?: 'New Message');
        $body = "<p><strong>Name:</strong> " . htmlspecialchars($name) . "</p>
                 <p><strong>Email:</strong> " . htmlspecialchars($email) . "</p>
                 <p><strong>Subject:</strong> " . htmlspecialchars($subject ?: 'No subject') . "</p>
                 <p><strong>Message:</strong><br>" . nl2br(htmlspecialchars($message)) . "</p>";

        $mail_sent = true;
        foreach ($client_emails as $recipient) {
            if (!sendEmail($recipient, $mail_subject, $body)) {
                $mail_sent = false;
            }
        }

        if ($mail_sent) {
            $success = 'Your message has been sent. We will get back to you soon.';
        } else {
            $error = 'Message could not be sent. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <link rel="icon" type="image/png" href="favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="favicon.svg" />
    <link rel="shortcut icon" href="favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="apple-touch-icon.png" />
    <meta name="apple-mobile-web-app-title" content="No Q" />
    <link rel="manifest" href="site.webmanifest" />
    <title>Contact Us · No Q</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
       
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
        .nav-links { display: flex; gap: 1.2rem; align-items: center; }
        .nav-links a, .nav-links .dropdown { 
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
        .nav-links a i, .nav-links .dropdown i { font-size: 1rem; color: var(--purple-primary); }
        .nav-links a:hover, .nav-links .dropdown:hover > .dropbtn { background: rgba(106,27,154,0.08); color: var(--purple-primary); }
        .nav-links .btn-outline { 
            border: 1.5px solid var(--purple-primary); 
            padding: 0.4rem 1.2rem; 
            border-radius: 40px; 
            background: white; 
            font-weight: 600; 
        }
        .nav-links .btn-outline:hover { background: var(--purple-primary); color: white; }
        .nav-links .btn-outline:hover i { color: white; }

     
        .dropdown {
            position: relative;
            display: inline-block;
            padding: 0;
        }
        .dropbtn {
            text-decoration: none;
            font-weight: 500;
            color: #2c3e50;
            padding: 0.5rem 0.8rem;
            border-radius: 40px;
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            background: transparent;
            border: none;
            font-size: 0.95rem;
            font-family: 'Inter', sans-serif;
        }
        .dropbtn i {
            font-size: 0.7rem;
            transition: transform 0.2s;
        }
        .dropdown-content {
            display: none;
            position: absolute;
            background: white;
            min-width: 160px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            border-radius: 20px;
            z-index: 1;
            top: 100%;
            left: 0;
            overflow: hidden;
        }
        .dropdown-content a {
            color: #2c3e50;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
            text-align: left;
            border-radius: 0;
            font-size: 0.95rem;
        }
        .dropdown-content a:hover {
            background: rgba(106,27,154,0.1);
        }
        
        
        @media (min-width: 769px) {
            .dropdown:hover .dropdown-content {
                display: block;
            }
            .dropdown:hover .dropbtn i {
                transform: rotate(180deg);
            }
        }

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
                align-items: center;
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
            .nav-links a, .nav-links .dropdown { 
                width: 100%; 
                justify-content: center;
                padding: 0.8rem; 
                border-radius: 30px;
            }
            .btn-outline { width: 100%; text-align: center; }
            
          
            .dropdown {
                width: 100%;
                position: relative;
                padding: 0;
            }
            .dropbtn {
                width: 100%;
                justify-content: center;
                cursor: pointer;
                background: transparent;
                border: none;
                padding: 0.8rem;
                font-size: 0.95rem;
                color: #2c3e50;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 6px;
                font-family: 'Inter', sans-serif;
            }
            .dropdown-content {
                position: static;
                background: rgba(106,27,154,0.05);
                margin-top: 0.5rem;
                width: 100%;
                box-shadow: none;
                display: none;
                border-radius: 20px;
                overflow: hidden;
            }
            .dropdown-content a {
                text-align: center;
                padding: 0.8rem;
                display: block;
            }
            .dropdown.open .dropdown-content {
                display: block !important;
            }
        }

   
        .container {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 0 2rem;
            flex: 1;
        }
        
     
        .contact-hero {
            text-align: center;
            margin-bottom: 2rem;
            padding: 2rem;
            background: linear-gradient(135deg, rgba(106,27,154,0.05), rgba(74,0,114,0.05));
            border-radius: 48px;
        }
        .contact-hero h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            background: linear-gradient(145deg, var(--purple-primary), var(--orange-primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .contact-hero p {
            color: #2c3e50;
            font-size: 1.1rem;
        }
  
        .contact-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
     
        .card {
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(10px);
            border-radius: 32px;
            padding: 2rem;
            border: 1px solid rgba(106,27,154,0.1);
            box-shadow: 0 20px 35px -12px rgba(106,27,154,0.15);
        }
        
       
        .contact-info {
            background: linear-gradient(135deg, var(--purple-primary), var(--purple-dark));
            color: white;
        }
        .contact-info h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .contact-info .info-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid rgba(255,255,255,0.2);
        }
        .contact-info .info-item:last-child {
            border-bottom: none;
        }
        .contact-info .info-icon {
            width: 45px;
            height: 45px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        .contact-info .info-content a {
            color: white;
            text-decoration: none;
        }
        .contact-info .info-content a:hover {
            text-decoration: underline;
        }
        
       
        .form-card h2 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            background: linear-gradient(145deg, var(--purple-primary), var(--orange-primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .form-card .subtitle {
            color: #6c7a8a;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }
        .form-group {
            margin-bottom: 1.2rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--purple-primary);
            font-size: 0.85rem;
        }
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 0.9rem 1.2rem;
            border: none;
            border-radius: 30px;
            background: #f0f4f8;
            font-family: 'Inter', sans-serif;
            font-size: 0.95rem;
            transition: 0.2s;
        }
        .form-group input:focus, .form-group textarea:focus {
            outline: none;
            background: white;
            box-shadow: 0 0 0 2px rgba(106,27,154,0.2);
        }
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        .btn-primary {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, var(--purple-primary), var(--purple-dark));
            color: white;
            border: none;
            border-radius: 40px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(106,27,154,0.3);
        }
        
     
        .error {
            color: #b71c1c;
            background: #ffebee;
            padding: 0.8rem 1rem;
            border-radius: 30px;
            margin-bottom: 1.5rem;
            font-size: 0.85rem;
        }
        .success {
            color: var(--purple-primary);
            background: #e8f5e9;
            padding: 0.8rem 1rem;
            border-radius: 30px;
            margin-bottom: 1.5rem;
            font-size: 0.85rem;
        }
        
         
        .faq-section {
            margin-top: 2rem;
        }
        .faq-section h2 {
            font-size: 1.8rem;
            margin-bottom: 1rem;
            background: linear-gradient(145deg, var(--purple-primary), var(--orange-primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-align: center;
        }
        .faq-section .faq-subtitle {
            text-align: center;
            color: #6c7a8a;
            margin-bottom: 2rem;
        }
        .faq-item {
            border-bottom: 1px solid rgba(0,0,0,0.08);
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
        .faq-question:hover {
            color: var(--orange-primary);
        }
        .faq-question i {
            transition: transform 0.2s;
            color: var(--orange-primary);
        }
        .faq-question.active i {
            transform: rotate(180deg);
        }
        .faq-answer {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
            padding: 0;
            color: #2c3e50;
            line-height: 1.6;
            font-size: 0.9rem;
        }
        .faq-answer.show {
            max-height: 300px;
            padding: 0 0 1rem 0;
        }
        
      
        .app-footer {
            background: rgba(255,255,255,0.6);
            backdrop-filter: blur(8px);
            border-top: 1px solid white;
            padding: 1.5rem;
            text-align: center;
            margin-top: auto;
            color: var(--purple-primary);
            font-size: 0.85rem;
        }
        .app-footer a {
            color: var(--purple-primary);
            text-decoration: none;
        }
        .app-footer a:hover {
            color: var(--orange-primary);
            text-decoration: underline;
        }
        
   
        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
                margin: 1rem auto;
            }
            .contact-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            .contact-hero h1 {
                font-size: 1.8rem;
            }
            .contact-hero p {
                font-size: 1rem;
            }
            .card {
                padding: 1.2rem;
            }
            .faq-section h2 {
                font-size: 1.4rem;
            }
        }
        
        @media (max-width: 480px) {
            .contact-info .info-item {
                flex-direction: column;
                text-align: center;
                gap: 0.5rem;
            }
            .contact-info .info-icon {
                margin: 0 auto;
            }
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
            <div class="dropdown" id="aboutDropdown">
                <button class="dropbtn" id="dropbtn">About <i class="fa-solid fa-chevron-down"></i></button>
                <div class="dropdown-content" id="dropdownContent">
                    <a href="index.php#about">About Us</a>
                    <a href="index.php#mission">Our Mission</a>
                    <a href="index.php#vision">Our Vision</a>
                </div>
            </div>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="user-dashboard.php"><i class="fa-regular fa-user"></i> Dashboard</a>
                <a href="logout.php" class="btn-outline"><i class="fa-regular fa-sign-out"></i> Logout</a>
            <?php else: ?>
                <a href="user-login.php"><i class="fa-regular fa-user"></i> Sign In</a>
                <a href="business/business-signup.php" class="btn-outline"><i class="fa-regular fa-building"></i> List Business</a>
            <?php endif; ?>
        </nav>
    </header>

    <div class="container">
       
        <div class="contact-hero">
            <h1><i class="fa-regular fa-message"></i> Get in Touch</h1>
            <p>We'd love to hear from you. Send us a message and we'll respond within 24 hours.</p>
        </div>

        
        <div class="contact-grid">
            <div class="card contact-info">
                <h3><i class="fa-regular fa-address-card"></i> Contact Information</h3>
                <div class="info-item">
                    <div class="info-icon"><i class="fa-regular fa-envelope"></i></div>
                    <div class="info-content">
                        <strong>Email Us</strong><br>
                        <a href="mailto:aosolvers@carwashes.africa">aosolvers@carwashes.africa </a>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-icon"><i class="fa-regular fa-phone"></i></div>
                    <div class="info-content">
                        <strong>Call Us</strong><br>
                        <a href="tel:+27648089972">+27 64 808 9972</a> | <a href="tel:+27814488044">+27 81 448 8044</a>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-icon"><i class="fa-regular fa-clock"></i></div>
                    <div class="info-content">
                        <strong>Business Hours</strong><br>
                        Mon - Fri: 9am - 5pm<br>
                        Sat: 10am - 2pm
                    </div>
                </div>
            </div>

            
            <div class="card form-card">
                <h2><i class="fa-regular fa-pen-to-square"></i> Send us a Message</h2>
                <p class="subtitle">Fill out the form below and we'll get back to you soon.</p>

                <?php if ($error): ?>
                    <div class="error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="success"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success) ?></div>
                <?php endif; ?>

                <form method="post">
                    <div class="form-group">
                        <label><i class="fa-regular fa-user"></i> Your Name *</label>
                        <input type="text" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label><i class="fa-regular fa-envelope"></i> Your Email *</label>
                        <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label><i class="fa-regular fa-tag"></i> Subject</label>
                        <input type="text" name="subject" placeholder="What is this regarding?" value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label><i class="fa-regular fa-comment"></i> Message *</label>
                        <textarea name="message" placeholder="Tell us how we can help..."><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" class="btn-primary"><i class="fa-regular fa-paper-plane"></i> Send Message</button>
                </form>
            </div>
        </div>

       
        <div class="card faq-section">
            <h2><i class="fa-regular fa-circle-question"></i> Frequently Asked Questions</h2>
            <p class="faq-subtitle">Find quick answers to common questions</p>
            
            <div class="faq-item">
                <button class="faq-question">How do I book a car wash? <i class="fa-solid fa-chevron-down"></i></button>
                <div class="faq-answer">Simply search for a car wash near you, select a service, choose a date and time slot, and confirm your booking. You'll receive a unique booking code to show at the wash.</div>
            </div>
            <div class="faq-item">
                <button class="faq-question">What is the booking code used for? <i class="fa-solid fa-chevron-down"></i></button>
                <div class="faq-answer">The booking code is your proof of booking. Show it to the car wash staff when you arrive. Businesses can also use it to mark your booking as completed.</div>
            </div>
            <div class="faq-item">
                <button class="faq-question">Can I cancel or reschedule a booking? <i class="fa-solid fa-chevron-down"></i></button>
                <div class="faq-answer">Yes. From your dashboard, you can cancel or reschedule any upcoming booking. Rescheduled bookings will need business confirmation again.</div>
            </div>
            <div class="faq-item">
                <button class="faq-question">How do I register my car wash business? <i class="fa-solid fa-chevron-down"></i></button>
                <div class="faq-answer">Click "List Business" in the top navigation, fill out the registration form, and wait for admin approval. Once approved, you can manage your bookings and services.</div>
            </div>
            <div class="faq-item">
                <button class="faq-question">Is my payment secure? <i class="fa-solid fa-chevron-down"></i></button>
                <div class="faq-answer">Yes. All payments are handled through secure gateways. Your financial information is never stored on our servers.</div>
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
            
            // Close menu when clicking on links
            document.querySelectorAll('.nav-links a, .nav-links .dropdown-content a').forEach(link => {
                link.addEventListener('click', () => {
                    if (navLinks) navLinks.classList.remove('show');
                });
            });

            // Mobile dropdown toggle
            var dropbtn = document.getElementById('dropbtn');
            var dropdown = document.getElementById('aboutDropdown');
            
            if (dropbtn && dropdown) {
                dropbtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    dropdown.classList.toggle('open');
                });
            }
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (dropdown && !dropdown.contains(e.target)) {
                    dropdown.classList.remove('open');
                }
            });
            
            // Close mobile menu when clicking dropdown links
            var dropdownLinks = document.querySelectorAll('.dropdown-content a');
            dropdownLinks.forEach(function(link) {
                link.addEventListener('click', function() {
                    if (navLinks) navLinks.classList.remove('show');
                    if (dropdown) dropdown.classList.remove('open');
                });
            });

            // FAQ Accordion
            const faqButtons = document.querySelectorAll('.faq-question');
            faqButtons.forEach(button => {
                button.addEventListener('click', () => {
                    const answer = button.nextElementSibling;
                    const isOpen = answer.classList.contains('show');
                    
                    // Close all answers
                    document.querySelectorAll('.faq-answer').forEach(ans => ans.classList.remove('show'));
                    document.querySelectorAll('.faq-question').forEach(btn => btn.classList.remove('active'));
                    
                    // Open clicked if it wasn't open
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
