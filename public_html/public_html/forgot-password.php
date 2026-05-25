<?php
session_start();
require_once 'db_connect.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $error = 'Please enter your email address.';
    } else {
        // Check if user exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Generate token and expiry (1 hour)
            $token = bin2hex(random_bytes(32));

            
            $update = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = ?");
            if ($update->execute([$token, $user['id']])) {
                
                // Create reset link
                $resetLink = "https://carwashes.africa/reset-password.php?token=" . urlencode($token);

                // Send email
                $subject = "Password Reset - No Q";
                $body = "
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <style>
                            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                            .container { max-width: 500px; margin: 0 auto; padding: 20px; background: #f9f9f9; border-radius: 10px; }
                            .header { background: #6a1b9a; color: white; padding: 15px; text-align: center; border-radius: 10px 10px 0 0; }
                            .content { padding: 20px; background: white; border-radius: 0 0 10px 10px; }
                            .button { display: inline-block; background: #ff9800; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 15px 0; }
                            .footer { text-align: center; font-size: 12px; color: #666; margin-top: 20px; }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='header'>
                                <h2>No Q</h2>
                            </div>
                            <div class='content'>
                                <p>Hello,</p>
                                <p>You requested a password reset. Click the button below to reset your password:</p>
                                <div style='text-align: center;'>
                                    <a href='$resetLink' class='button'>Reset Password</a>
                                </div>
                                <p>If the button doesn't work, copy and paste this link into your browser:</p>
                                <p style='word-break: break-all;'>$resetLink</p>
                                <p>This link will expire in 1 hour.</p>
                                <p>If you did not request this, please ignore this email.</p>
                                <hr>
                                <p style='font-size: 14px;'>No Q Team</p>
                            </div>
                            <div class='footer'>
                                <p>&copy; " . date('Y') . " No Q. All Rights Reserved.</p>
                            </div>
                        </div>
                    </body>
                    </html>
                ";

                if (sendEmail($email, $subject, $body)) {
                    $success = 'A password reset link has been sent to your email.';
                } else {
                    $error = 'Failed to send email. Please try again later.';
                }
            } else {
                $error = 'Could not save reset token. Please try again.';
            }
        } else {
            // Always show same message for security
            $success = 'If that email exists in our system, a reset link has been sent.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Forgot Password · No Q</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
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
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .app-header {
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(106,27,154,0.1);
            padding: 0.8rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            position: sticky;
            top: 0;
            z-index: 100;
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
                background: rgba(255,255,255,0.98);
                backdrop-filter: blur(10px);
                border-radius: 24px;
                padding: 1rem;
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
        }
        
        .auth-container {
            max-width: 450px;
            margin: 3rem auto;
            padding: 0 1rem;
            flex: 1;
            width: 100%;
        }
        .auth-card {
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(10px);
            border-radius: 40px;
            padding: 2.5rem;
            box-shadow: 0 20px 40px -12px rgba(106,27,154,0.2);
            border: 1px solid rgba(106,27,154,0.1);
        }
        .auth-card h2 {
            font-size: 2rem;
            margin-bottom: 1rem;
            background: linear-gradient(145deg, var(--purple-primary), var(--orange-primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .auth-card p {
            color: #2c3e50;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group input {
            width: 100%;
            padding: 1rem;
            border: none;
            border-radius: 30px;
            background: #f0f4f8;
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
            transition: all 0.2s;
        }
        .form-group input:focus {
            outline: none;
            box-shadow: 0 0 0 2px rgba(106,27,154,0.2);
            background: white;
        }
        .btn-primary {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, var(--purple-primary), var(--purple-dark));
            color: white;
            border: none;
            border-radius: 40px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(106,27,154,0.3);
        }
        .error {
            color: #b71c1c;
            background: #ffebee;
            padding: 1rem;
            border-radius: 30px;
            margin-bottom: 1.5rem;
        }
        .success {
            color: var(--purple-primary);
            background: #e8f5e9;
            padding: 1rem;
            border-radius: 30px;
            margin-bottom: 1.5rem;
            word-break: break-word;
        }
        .back-link {
            text-align: center;
            margin-top: 1.5rem;
        }
        .back-link a {
            color: var(--purple-primary);
            text-decoration: none;
            transition: color 0.2s;
        }
        .back-link a:hover {
            color: var(--orange-primary);
            text-decoration: underline;
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
        
        @media (max-width: 480px) {
            .auth-card { padding: 1.5rem; }
            .auth-card h2 { font-size: 1.5rem; }
            .auth-container { margin: 2rem auto; }
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
            <a href="index.php">Home</a>
            <a href="search.php">Search</a>
            <a href="user-login.php">Login</a>
            <a href="user-signup.php">Sign Up</a>
        </nav>
    </header>

    <div class="auth-container">
        <div class="auth-card">
            <h2>Forgot Password</h2>
            <p style="margin-bottom:1.5rem;">Enter your email address and we'll send you a reset link.</p>

            <?php if ($error): ?>
                <div class="error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="success"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <form method="post">
                <div class="form-group">
                    <input type="email" name="email" placeholder="Your email address" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
                <button type="submit" class="btn-primary">Send Reset Link</button>
            </form>
            <div class="back-link">
                <a href="user-login.php">← Back to Login</a>
            </div>
        </div>
    </div>

    <footer class="app-footer">
        <p>&copy; <?= date('Y'); ?> No Q. All rights reserved.</p>
        <p>Powered by <a href="https://www.jaekerna.com/" target="_blank">Jaekerna Investments</a></p>
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
                    navLinks.classList.remove('show');
                });
            });
        });
    </script>
</body>
</html>