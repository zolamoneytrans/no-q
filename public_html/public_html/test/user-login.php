<?php
session_start();
require_once 'db_connect.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        $stmt = $pdo->prepare("SELECT id, name, email, password, points, is_active, is_verified FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password'])) {
            if ($user['is_verified'] == 0) {
                $error = 'Please verify your email address before logging in. Check your inbox for the verification link.';
                $_SESSION['unverified_email'] = $email;
            } elseif ($user['is_active'] == 1) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_points'] = $user['points'];
                
                if (isset($_GET['redirect']) && !empty($_GET['redirect'])) {
                    header('Location: ' . urldecode($_GET['redirect']));
                } else {
                    header('Location: user-dashboard.php');
                }
                exit;
            } else {
                $error = 'Your account has been frozen. Contact support.';
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login · No Q</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(145deg, #f6f9fc 0%, #e9f1f8 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
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
        .logo-icon { background: #1e3c72; color: white; width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        .logo-text { font-weight: 700; font-size: 1.5rem; background: linear-gradient(135deg, #1e3c72, #2a5298); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .nav-links { display: flex; gap: 1.2rem; align-items: center; }
        .nav-links a { text-decoration: none; font-weight: 500; color: #2c3e50; padding: 0.5rem 0.8rem; border-radius: 40px; }
        .nav-links a:hover { background: rgba(42,82,152,0.08); }
        .nav-links .btn-outline { border: 1.5px solid #1e3c72; padding: 0.4rem 1.2rem; border-radius: 40px; background: white; font-weight: 600; }
        .nav-links .btn-outline:hover { background: #1e3c72; color: white; }

        .menu-toggle {
            display: none;
            font-size: 1.8rem;
            cursor: pointer;
            background: transparent;
            border: none;
            color: #1e3c72;
            padding: 0.5rem;
        }
        @media (max-width: 768px) {
            .menu-toggle { display: block; }
            .nav-links {
                display: none;
                width: 100%;
                flex-direction: column;
                background: rgba(255,255,255,0.95);
                border-radius: 30px;
                padding: 1rem;
                margin-top: 1rem;
            }
            .nav-links.show { display: flex; }
            .app-header { padding: 0.8rem 1rem; }
            .nav-links a { width: 100%; text-align: center; padding: 0.8rem; }
            .btn-outline { width: 100%; }
        }

        .auth-container { max-width: 450px; margin: 3rem auto; padding: 2rem; flex: 1; }
        .auth-card {
            background: rgba(255,255,255,0.8);
            backdrop-filter: blur(10px);
            border-radius: 40px;
            padding: 2.5rem;
            box-shadow: 0 20px 40px -12px rgba(0,20,40,0.2);
            border: 1px solid rgba(255,255,255,0.6);
        }
        .auth-card h2 { font-size: 2rem; margin-bottom: 2rem; color: #1e3c72; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group input {
            width: 100%;
            padding: 1rem;
            border: none;
            border-radius: 30px;
            background: #f0f4f8;
            font-size: 1rem;
        }
        .btn-primary {
            width: 100%;
            padding: 1rem;
            background: #1e3c72;
            color: white;
            border: none;
            border-radius: 40px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 1rem;
        }
        .forgot-password { text-align: right; margin: 1rem 0; }
        .forgot-password a { color: #2a5298; text-decoration: none; font-size: 0.9rem; }
        .signup-link { text-align: center; margin-top: 1.5rem; }
        .signup-link a { color: #1e3c72; font-weight: 600; text-decoration: none; }
        .error {
            color: #b71c1c;
            background: #ffebee;
            padding: 1rem;
            border-radius: 30px;
            margin-bottom: 1.5rem;
        }
        .error a { color: #ff9800; text-decoration: underline; }
        .app-footer {
            background: rgba(255,255,255,0.6);
            padding: 2rem;
            text-align: center;
            margin-top: auto;
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
            <a href="index.php#about">About</a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="user-dashboard.php">Dashboard</a>
                <a href="logout.php?t=<?= time() ?>" class="btn-outline"><i class="fa-regular fa-sign-out"></i> Logout</a>
            <?php else: ?>
                <a href="user-signup.php" class="btn-outline">Sign Up</a>
            <?php endif; ?>
            <a href="business/business-signup.php" class="btn-outline">List Business</a>
        </nav>
    </header>

    <div class="auth-container">
        <div class="auth-card">
            <h2>Welcome back</h2>
            <?php if ($error): ?>
                <div class="error">
                    <?= htmlspecialchars($error) ?>
                    <?php if (strpos($error, 'verify') !== false && isset($_SESSION['unverified_email'])): ?>
                        <br><a href="resend-verification.php?email=<?= urlencode($_SESSION['unverified_email']) ?>">Resend verification email</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <form method="post">
                <div class="form-group"><input type="email" name="email" placeholder="Email" required value="<?= htmlspecialchars($_POST['email']??'') ?>"></div>
                <div class="form-group"><input type="password" name="password" placeholder="Password" required></div>
                <div class="forgot-password"><a href="forgot-password.php">Forgot password?</a></div>
                <button type="submit" class="btn-primary">Log In</button>
            </form>
            <div class="signup-link">New customer? <a href="user-signup.php">Create account</a></div>
        </div>
    </div>

    <footer class="app-footer">
        <p>&copy; <?= date('Y'); ?> No Q</p>
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
