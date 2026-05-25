<?php
session_start();
require_once '../db_connect.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = 'Please enter your email address.';
    } else {
        // Check if business exists
        $stmt = $pdo->prepare("SELECT id, name FROM businesses WHERE email = ?");
        $stmt->execute([$email]);
        $business = $stmt->fetch();
        
        if ($business) {
            // Generate reset token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Save token in database
            $update = $pdo->prepare("UPDATE businesses SET reset_token = ?, reset_expires = ? WHERE id = ?");
            $update->execute([$token, $expires, $business['id']]);
            
            // Send reset email
            $reset_link = "https://carwashes.africa/business/business-reset-password.php?token=" . $token;
            $subject = "Reset Your Password – No Q";
            $body = "
            <html>
            <body>
                <h2>Password Reset Request</h2>
                <p>Hi {$business['name']},</p>
                <p>Click the link below to reset your password. This link expires in 1 hour.</p>
                <p><a href='{$reset_link}'>Reset Password</a></p>
                <p>If you didn't request this, ignore this email.</p>
                <p>No Q Team</p>
            </body>
            </html>
            ";
            
            if (sendEmail($email, $subject, $body)) {
                $message = "Password reset link sent to your email. Check your inbox (or spam folder).";
            } else {
                $error = "Failed to send email. Please try again.";
            }
        } else {
            $error = "No business found with that email address.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password · No Q</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Use same styles as business-login.php */
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
            <h2>Forgot Password?</h2>
            <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <?php if ($message): ?><div class="success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
            <form method="post">
                <div class="form-group">
                    <input type="email" name="email" placeholder="Business email" required>
                </div>
                <button type="submit" class="btn-primary">Send Reset Link</button>
            </form>
            <div class="login-link"><a href="business-login.php">Back to Login</a></div>
        </div>
    </div>
    <footer class="app-footer"><p>&copy; <?= date('Y'); ?> No Q</p></footer>
</body>
</html>