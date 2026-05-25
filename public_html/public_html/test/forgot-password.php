<?php
session_start();
require_once 'db_connect.php';

// Load PHPMailer (adjust path if needed)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

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

// Let MySQL set the expiration time (1 hour from its own NOW())
$update = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = ?");
if ($update->execute([$token, $user['id']])) {
        
        // Create reset link
        $resetLink = "http://" . $_SERVER['HTTP_HOST'] . "/carwash-connect/reset-password.php?token=" . urlencode($token);

        // ---------- Send email using PHPMailer ----------
        $mail = new PHPMailer(true);
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'mayibongwemngometulu@gmail.com';
            $mail->Password   = 'wxfs ppra xgmm hroi';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            // Recipients
            $mail->setFrom('mayibongwemngometulu@gmail.com', 'No Q');
            $mail->addAddress($email);

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Password Reset - No Q';
            $mail->Body    = "Hello,<br><br>You requested a password reset. Click the link below to reset your password:<br><br>
                             <a href='$resetLink'>$resetLink</a><br><br>
                             This link will expire in 1 hour.<br><br>
                             If you did not request this, please ignore this email.<br><br>
                             Regards,<br>No Q Team";
            $mail->AltBody = "Hello,\n\nYou requested a password reset. Copy and paste this link into your browser:\n\n$resetLink\n\nThis link will expire in 1 hour.\n\nIf you did not request this, please ignore this email.";

            $mail->send();
            $success = 'A password reset link has been sent to your email.';
        } catch (Exception $e) {
            $error = 'Mail could not be sent. Error: ' . $mail->ErrorInfo;
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="favicon.svg" />
    <link rel="shortcut icon" href="favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="apple-touch-icon.png" />
    <meta name="apple-mobile-web-app-title" content="No Q" />
    <link rel="manifest" href="site.webmanifest" />
    <title>Forgot Password · No Q</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* (keep your existing styles – copy from previous version) */
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
            word-break: break-word;
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
            <img src="logo.jpeg" alt="No Q" style="height: 40px; width: auto;">
            <span class="logo-text">CarWash<span style="font-weight:400;">Connect</span></span>
        </div>
        <nav class="nav-links">
            <a href="index.php">Home</a>
            <a href="user-login.php">Login</a>
        </nav>
    </header>

    <div class="auth-container">
        <div class="auth-card">
            <h2>Forgot Password</h2>
            <p style="margin-bottom:1.5rem; color:#2c3e50;">Enter your email address and we'll send you a reset link.</p>

            <?php if ($error): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="success"><?= $success ?></div>
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
        <p>&copy; <?= date('Y'); ?> No Q</p>
    </footer>
</body>
</html>
