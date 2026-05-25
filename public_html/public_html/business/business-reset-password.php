<?php
session_start();
require_once '../db_connect.php';

$message = '';
$error = '';
$token = $_GET['token'] ?? '';

if (empty($token)) {
    $error = 'No reset token provided.';
} else {
    // Verify token
    $stmt = $pdo->prepare("SELECT id, name, email, reset_expires FROM businesses WHERE reset_token = ?");
    $stmt->execute([$token]);
    $business = $stmt->fetch();
    
    if (!$business) {
        $error = 'Invalid reset token.';
    } elseif (strtotime($business['reset_expires']) < time()) {
        $error = 'Reset link has expired. Please request a new one.';
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
        $new_password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (strlen($new_password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } else {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $update = $pdo->prepare("UPDATE businesses SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
            if ($update->execute([$hashed, $business['id']])) {
                $message = 'Password reset successfully! You can now log in.';
                // Clear token variable to prevent reuse
                $token = '';
            } else {
                $error = 'Failed to reset password. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password · No Q</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --purple-primary: #6a1b9a;
            --purple-dark: #4a0072;
            --orange-primary: #ff9800;
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
            padding: 0.8rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .logo-text {
            font-weight: 700;
            font-size: 1.5rem;
            background: linear-gradient(135deg, var(--purple-primary), var(--orange-primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .auth-container { max-width: 450px; margin: 3rem auto; padding: 0 1rem; flex: 1; }
        .auth-card {
            background: rgba(255,255,255,0.9);
            border-radius: 40px;
            padding: 2rem;
            box-shadow: 0 20px 40px -12px rgba(106,27,154,0.2);
        }
        .form-group { margin-bottom: 1.5rem; position: relative; }
        .form-group input {
            width: 100%;
            padding: 1rem;
            padding-right: 45px;
            border: none;
            border-radius: 30px;
            background: #f0f4f8;
            font-size: 1rem;
        }
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #888;
        }
        .btn-primary {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, var(--purple-primary), var(--purple-dark));
            color: white;
            border: none;
            border-radius: 40px;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-primary:hover { transform: translateY(-2px); }
        .error { color: #b71c1c; background: #ffebee; padding: 1rem; border-radius: 30px; margin-bottom: 1rem; }
        .success { color: var(--purple-primary); background: #e8f5e9; padding: 1rem; border-radius: 30px; margin-bottom: 1rem; }
        .login-link { text-align: center; margin-top: 1rem; }
        .login-link a { color: var(--purple-primary); text-decoration: none; }
        .app-footer { text-align: center; padding: 2rem; margin-top: auto; color: var(--purple-primary); }
    </style>
</head>
<body>
    <header class="app-header">
        <div class="logo-area">
            <img src="../NoQ.jpg" alt="No Q" style="height: 60px;">
            <span class="logo-text">No Q</span>
        </div>
    </header>
    <div class="auth-container">
        <div class="auth-card">
            <h2>Reset Password</h2>
            <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <?php if ($message): ?><div class="success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
            <?php if (!$error && !$message && !empty($token)): ?>
                <form method="post">
                    <div class="form-group">
                        <input type="password" name="password" id="password" placeholder="New password" required>
                        <i class="fa-regular fa-eye toggle-password" id="togglePassword"></i>
                    </div>
                    <div class="form-group">
                        <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm new password" required>
                        <i class="fa-regular fa-eye toggle-password" id="toggleConfirmPassword"></i>
                    </div>
                    <button type="submit" class="btn-primary">Reset Password</button>
                </form>
            <?php elseif ($message): ?>
                <div class="login-link"><a href="business-login.php">Go to Login</a></div>
            <?php endif; ?>
        </div>
    </div>
    <footer class="app-footer"><p>&copy; <?= date('Y'); ?> No Q</p></footer>
    <script>
        function togglePasswordVisibility(fieldId, toggleId) {
            const field = document.getElementById(fieldId);
            const toggle = document.getElementById(toggleId);
            if (field && toggle) {
                toggle.addEventListener('click', function() {
                    const type = field.getAttribute('type') === 'password' ? 'text' : 'password';
                    field.setAttribute('type', type);
                    this.classList.toggle('fa-eye');
                    this.classList.toggle('fa-eye-slash');
                });
            }
        }
        togglePasswordVisibility('password', 'togglePassword');
        togglePasswordVisibility('confirm_password', 'toggleConfirmPassword');
    </script>
</body>
</html>