<?php
session_start();
require_once 'db_connect.php';

$error = '';
$success = '';

$token = isset($_GET['token']) ? trim($_GET['token']) : '';

// If token is provided in URL, validate it
if ($token) {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW()");
    $stmt->execute([$token]);
   
    $user = $stmt->fetch();

    if (!$user) {
        $error = 'Invalid or expired reset link.';
        $token = ''; // clear token
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    // Re-validate token
    $stmt = $pdo->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) {
        $error = 'Invalid or expired reset link.';
    } elseif (empty($password)) {
        $error = 'Please enter a new password.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        // Hash new password
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $update = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
        if ($update->execute([$hashed, $user['id']])) {
            $success = 'Password updated successfully. You can now <a href="user-login.php">login</a>.';
        } else {
            $error = 'Failed to update password. Please try again.';
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
    <title>Reset Password · No Q</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Same styles as forgot-password.php – you can copy from above */
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
        }
        .logo-area { display: flex; align-items: center; gap: 10px; }
        .logo-icon { background: #1e3c72; color: white; width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        .logo-text { font-weight: 700; font-size: 1.5rem; background: linear-gradient(135deg, #1e3c72, #2a5298); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .nav-links { display: flex; gap: 1.2rem; }
        .nav-links a { text-decoration: none; color: #2c3e50; }
        .auth-container {
            max-width: 450px;
            margin: 3rem auto;
            padding: 2rem;
            flex: 1;
        }
        .auth-card {
            background: rgba(255,255,255,0.8);
            backdrop-filter: blur(10px);
            border-radius: 40px;
            padding: 2.5rem;
            box-shadow: 0 20px 40px -12px rgba(0,20,40,0.2);
            border: 1px solid rgba(255,255,255,0.6);
        }
        .auth-card h2 {
            font-size: 2rem;
            margin-bottom: 1rem;
            background: linear-gradient(145deg, #1e3c72, #2a5298);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
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
        }
        .error {
            color: #b71c1c;
            background: #ffebee;
            padding: 1rem;
            border-radius: 30px;
            margin-bottom: 1.5rem;
        }
        .success {
            color: #1e3c72;
            background: #e8f5e9;
            padding: 1rem;
            border-radius: 30px;
            margin-bottom: 1.5rem;
        }
        .back-link {
            text-align: center;
            margin-top: 1.5rem;
        }
        .back-link a {
            color: #1e3c72;
            text-decoration: none;
        }
        .app-footer {
            background: rgba(255,255,255,0.6);
            padding: 2rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <header class="app-header">
        <div class="logo-area">
            <div class="logo-icon"><i class="fas fa-car-wash"></i></div>
            <span class="logo-text">CarWash<span style="font-weight:400;">Connect</span></span>
        </div>
        <nav class="nav-links">
            <a href="index.php">Home</a>
            <a href="user-login.php">Login</a>
        </nav>
    </header>

    <div class="auth-container">
        <div class="auth-card">
            <h2>Reset Password</h2>

            <?php if ($error): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="success"><?= $success ?></div>
            <?php endif; ?>

            <?php if ($token && !$error && !$success): ?>
                <form method="post">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                    <div class="form-group">
                        <input type="password" name="password" placeholder="New password" required>
                    </div>
                    <div class="form-group">
                        <input type="password" name="confirm_password" placeholder="Confirm new password" required>
                    </div>
                    <button type="submit" class="btn-primary">Reset Password</button>
                </form>
            <?php elseif (!$token): ?>
                <p style="color:#2c3e50;">Invalid or missing reset token.</p>
                <div class="back-link">
                    <a href="forgot-password.php">Request a new reset link</a>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="back-link">
                    <a href="user-login.php">Go to Login</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <footer class="app-footer">
        <p>&copy; <?= date('Y'); ?> No Q</p>
    </footer>
</body>
</html>
