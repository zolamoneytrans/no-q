<?php
session_start();
require_once 'db_connect.php';

$error = '';
$success = '';

$client_emails = ['admin@carwashes.africa']; 

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
        /* Same styles as before – we only need to add FAQ accordion CSS */
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
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; color: #1e3c72; }
        .form-group input, .form-group textarea { width: 100%; padding: 1rem; border: none; border-radius: 30px; background: #f0f4f8; font-family: 'Inter', sans-serif; font-size: 1rem; }
        .form-group textarea { resize: vertical; min-height: 120px; }
        .btn-primary { width: 100%; padding: 1rem; background: #1e3c72; color: white; border: none; border-radius: 40px; font-size: 1.1rem; font-weight: 600; cursor: pointer; transition: 0.15s; }
        .btn-primary:hover { background: #2a5298; }
        .error { color: #b71c1c; background: #ffebee; padding: 1rem; border-radius: 30px; margin-bottom: 1.5rem; }
        .success { color: #1e3c72; background: #e8f5e9; padding: 1rem; border-radius: 30px; margin-bottom: 1.5rem; }

        /* FAQ Accordion Styles */
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
            max-height: 300px;
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
            <img src="logo.jpeg" alt="No Q" style="height: 40px; width: auto;">
            <span class="logo-text">CarWash<span style="font-weight:400;">Connect</span></span>
        </div>
        <button class="menu-toggle" id="menuToggle"><i class="fa-solid fa-bars"></i></button>
        <nav class="nav-links" id="navLinks">
            <a href="index.php">Home</a>
            <a href="#about">About</a>
            <a href="#mission">Mission</a>
            <a href="#vision">Vision</a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="user-dashboard.php"><i class="fa-regular fa-user"></i> Dashboard</a>
                <a href="logout.php" class="btn-outline"><i class="fa-regular fa-sign-out"></i> Logout</a>
            <?php else: ?>
                <a href="user-login.php"><i class="fa-regular fa-user"></i> Sign In</a>
            <?php endif; ?>
            <a href="../business-signup.php" class="btn-outline"><i class="fa-regular fa-building"></i> List Business</a>
        </nav>
    </header>

    <div class="container">
        <div class="card">
            <h1>Contact Us</h1>
            <p class="subtitle">Have a question or feedback? Reach out to us.</p>

            <div class="contact-details" style="background: #f0f4f8; border-radius: 30px; padding: 1rem; margin-bottom: 1.5rem;">
                <h3 style="color: #1e3c72; margin-bottom: 0.5rem;">Contact the Team Directly</h3>
                <p><i class="fa-regular  fa-envelope"></i> <strong>Email:</strong><a href="mailto:admin@carwashes.africa" style= "text-decoration: none">admin@carwashes.africa</a> 
                <p><i class="fa-regular fa-phone"></i> <strong>Phone:</strong> <a href="tel:+27648089972" style= "text-decoration: none">+27 64 808 9972</a> | <a href="tel:+27814488044" style= "text-decoration: none">+27 81 448 8044</a></p>
            </div>    


            <?php if ($error): ?>
                <div class="error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="success"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <form method="post">
                <div class="form-group">
                    <label>Your Name *</label>
                    <input type="text" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Your Email *</label>
                    <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Subject (Optional)</label>
                    <input type="text" name="subject" value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Message *</label>
                    <textarea name="message" required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                </div>
                <button type="submit" class="btn-primary">Send Message</button>
            </form>
        </div>

        <!-- FAQ Section -->
        <div class="card faq-section">
            <h2>Frequently Asked Questions</h2>
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
                <div class="faq-answer">Yes. All payments are handled through secure gateways.</div>
            </div>
        </div>
    </div>

    <footer class="app-footer">
        <p>&copy; <?= date('Y'); ?> No Q. All rights reserved.</p>
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
                    // Close all answers (optional)
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
