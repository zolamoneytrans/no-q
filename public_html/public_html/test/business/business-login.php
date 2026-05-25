<?php

session_set_cookie_params(['path' => '/']);
session_start();

require_once '../db_connect.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM businesses WHERE email = ?");
        $stmt->execute([$email]);
        $business = $stmt->fetch();
        if ($business && password_verify($password, $business['password'])) {
            if ($business['is_approved'] == 1) {
                if ($business['is_active'] == 1) {
                    // Clear any existing user session
                    unset($_SESSION['user_id']);
                    unset($_SESSION['user_name']);
                    unset($_SESSION['user_email']);
                    unset($_SESSION['user_points']);

                    // Set business session variables
                    $_SESSION['business_id'] = $business['id'];
                    $_SESSION['business_name'] = $business['name'];
                    $_SESSION['business_email'] = $business['email'];

                    // after setting $_SESSION['business_id']...
                    setcookie('user_type', 'business', time() + 86400 * 30, '/'); // 30 days
                    setcookie('user_id', $business['id'], time() + 86400 * 30, '/');

                    // Write session data before redirect
                    session_write_close();

                    header('Location: business-dashboard.php');
                    exit;
                } else {
                    $error = 'Your account is frozen. Contact support.';
                }
            } else {
                $error = 'Your account is pending approval.';
            }
        } else {
            $error = 'Invalid email or password.';
        }
        
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <link rel="icon" type="image/png" href="../favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="../favicon.svg" />
    <link rel="shortcut icon" href="../favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="../apple-touch-icon.png" />
    <meta name="apple-mobile-web-app-title" content="No Q" />
    <link rel="manifest" href="../site.webmanifest" />
    <title>Business Login · No Q</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Inter', sans-serif; background: linear-gradient(145deg, #f6f9fc 0%, #e9f1f8 100%); min-height:100vh; display:flex; flex-direction:column; }
        
        /* Header Styles */
        .app-header {
            background: rgba(255,255,255,0.7);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255,255,255,0.5);
            padding: 0.8rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .logo-area { display: flex; align-items: center; gap: 10px; }
        .logo-text { font-weight: 700; font-size: 1.5rem; background: linear-gradient(135deg, #1e3c72, #2a5298); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .nav-links { display: flex; gap: 1.2rem; align-items: center; }
        .nav-links a { text-decoration: none; font-weight: 500; color: #2c3e50; padding: 0.5rem 0.8rem; border-radius: 40px; transition: 0.2s; }
        .nav-links a:hover { background: rgba(42,82,152,0.08); }
        .nav-links .btn-outline { border: 1.5px solid #1e3c72; padding: 0.4rem 1.2rem; border-radius: 40px; background: white; font-weight: 600; }
        .nav-links .btn-outline:hover { background: #1e3c72; color: white; }
        .menu-toggle { display: none; font-size: 1.8rem; cursor: pointer; background: transparent; border: none; color: #1e3c72; padding: 0.5rem; }
        
        /* Mobile Navigation */
        @media (max-width: 768px) {
            .menu-toggle { display: block; }
            .app-header { padding: 0.8rem 1rem; position: relative; }
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

        .auth-container { max-width: 450px; margin: 3rem auto; padding: 0 1rem; flex: 1; width: 100%; }
        .auth-card {
            background: rgba(255,255,255,0.8);
            backdrop-filter: blur(10px);
            border-radius: 40px;
            padding: 2.5rem;
            box-shadow: 0 20px 40px -12px rgba(0,20,40,0.2);
            border: 1px solid rgba(255,255,255,0.6);
        }
        .auth-card h2 { font-size: 2rem; margin-bottom: 2rem; background: linear-gradient(145deg, #1e3c72, #2a5298); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group input { width: 100%; padding: 1rem; border: none; border-radius: 30px; background: #f0f4f8; font-size: 1rem; font-family: 'Inter', sans-serif; }
        .btn-primary { width: 100%; padding: 1rem; background: #1e3c72; color: white; border: none; border-radius: 40px; font-size: 1.1rem; font-weight: 600; cursor: pointer; margin-top: 1rem; transition: 0.2s; }
        .btn-primary:hover { background: #2a5298; transform: translateY(-1px); }
        .signup-link { text-align: center; margin-top: 1.5rem; }
        .signup-link a { color: #1e3c72; font-weight: 600; text-decoration: none; }
        .signup-link a:hover { text-decoration: underline; }
        .error { color: #b71c1c; background: #ffebee; padding: 1rem; border-radius: 30px; margin-bottom: 1.5rem; font-size: 0.9rem; }
        .app-footer { background: rgba(255,255,255,0.6); padding: 2rem; text-align: center; margin-top: auto; font-size: 0.85rem; color: #6c7a8a; }
        
        @media (max-width: 480px) {
            .auth-card { padding: 1.5rem; }
            .auth-card h2 { font-size: 1.5rem; }
        }
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
            <a href="../index.php"><i class="fa-solid fa-house"></i> Home</a>
            <a href="business-signup.php"><i class="fa-solid fa-building"></i> Register</a>
        </nav>
    </header>

    <div class="auth-container">
        <div class="auth-card">
            <h2><i class="fa-solid fa-building"></i> Business login</h2>
            <?php if ($error): ?>
                <div class="error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="post">
                <div class="form-group">
                    <input type="email" name="email" placeholder="Business email" required value="<?= htmlspecialchars($_POST['email']??'') ?>">
                </div>
                <div class="form-group">
                    <input type="password" name="password" placeholder="Password" required>
                </div>
                <button type="submit" class="btn-primary"><i class="fa-solid fa-arrow-right-to-bracket"></i> Log In</button>
            </form>
            <div class="signup-link">New business? <a href="business-signup.php">Register here</a></div>
        </div>
    </div>

    <footer class="app-footer">
        <p>&copy; <?= date('Y'); ?> No Q. All rights reserved.</p>
        <p>Powered by <a href="https://www.jaekerna.com/" target="_blank" style="color:#1e3c72;">Jaekerna Investments</a></p>
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
