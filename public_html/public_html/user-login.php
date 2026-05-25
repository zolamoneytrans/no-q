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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Login · No Q</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
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
            transition: all 0.3s ease;
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
        .nav-links .btn-outline::after { display: none; }

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
            .btn-outline { width: 100%; }
        }

        .auth-container { max-width: 450px; margin: 3rem auto; padding: 0 1rem; flex: 1; width: 100%; }
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
            margin-bottom: 2rem; 
            background: linear-gradient(135deg, var(--purple-primary), var(--orange-primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .form-group { margin-bottom: 1.5rem; }
        .form-group input {
            width: 100%;
            padding: 1rem;
            border: none;
            border-radius: 30px;
            background: #f0f4f8;
            font-size: 1rem;
            font-family: 'Inter', sans-serif;
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
            margin-top: 1rem;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(106,27,154,0.3);
        }
        .forgot-password { text-align: right; margin: 1rem 0; }
        .forgot-password a { color: var(--purple-primary); text-decoration: none; font-size: 0.9rem; transition: color 0.2s; }
        .forgot-password a:hover { color: var(--orange-primary); text-decoration: underline; }
        .signup-link { text-align: center; margin-top: 1.5rem; }
        .signup-link a { color: var(--purple-primary); font-weight: 600; text-decoration: none; transition: color 0.2s; }
        .signup-link a:hover { color: var(--orange-primary); text-decoration: underline; }
        .error {
            color: #b71c1c;
            background: #ffebee;
            padding: 1rem;
            border-radius: 30px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }
        .error a { color: var(--orange-primary); text-decoration: underline; }
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
                <div class="form-group" style="position: relative;">
                    <input type="password" name="password" id="password" placeholder="Password" required style="padding-right: 45px;">
                    <i class="fa-regular fa-eye" id="togglePassword" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #888;"></i>
                </div>
                <div class="forgot-password"><a href="forgot-password.php">Forgot password?</a></div>
                <button type="submit" class="btn-primary">Log In</button>
            </form>
            <div class="signup-link">New customer? <a href="user-signup.php">Create account</a></div>
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

        const togglePassword = document.getElementById('togglePassword');
        const password = document.getElementById('password');
        if (togglePassword) {
            togglePassword.addEventListener('click', function() {
                const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                password.setAttribute('type', type);
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });
        }
    </script>
</body>
</html>