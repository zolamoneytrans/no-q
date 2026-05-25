<?php
session_start();
require_once 'db_connect.php';

$email = isset($_GET['email']) ? trim($_GET['email']) : '';
$message = '';
$error = '';

if (!empty($email)) {
    $stmt = $pdo->prepare("SELECT id, name, email, is_verified FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && $user['is_verified'] == 0) {
        $new_token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $update = $pdo->prepare("UPDATE users SET verification_token = ?, verification_expires = ? WHERE id = ?");
        $update->execute([$new_token, $expires, $user['id']]);

        if (sendWelcomeEmail($user['email'], $user['name'], $new_token)) {
            $message = "A new verification link has been sent to your email address. It will expire in 24 hours.";
        } else {
            $error = "Failed to send email. Please try again later.";
        }
    } elseif ($user && $user['is_verified'] == 1) {
        $error = "Your account is already verified. Please log in.";
    } else {
        $error = "No account found with that email address.";
    }
} else {
    $error = "Email address is required.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resend Verification · No Q</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(145deg, #f6f9fc 0%, #e9f1f8 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            max-width: 500px;
            margin: 20px;
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(10px);
            border-radius: 40px;
            padding: 40px;
            text-align: center;
            box-shadow: 0 20px 40px -12px rgba(0,20,40,0.2);
        }
        .icon { font-size: 60px; margin-bottom: 20px; }
        .success { color: #4caf50; }
        .error { color: #f44336; }
        h1 { font-size: 28px; margin-bottom: 15px; color: #1e3c72; }
        p { color: #2c3e50; margin-bottom: 20px; line-height: 1.6; }
        .btn {
            display: inline-block;
            background: #1e3c72;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 40px;
            font-weight: 600;
            margin-top: 10px;
        }
        .btn:hover { background: #2a5298; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group input {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 30px;
            background: #f0f4f8;
            font-size: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($message): ?>
            <div class="icon success"><i class="fa-solid fa-circle-check"></i></div>
            <h1>Verification Email Sent</h1>
            <p><?= htmlspecialchars($message) ?></p>
            <a href="user-login.php" class="btn">Go to Login</a>
        <?php elseif ($error): ?>
            <div class="icon error"><i class="fa-solid fa-circle-exclamation"></i></div>
            <h1>Verification Failed</h1>
            <p><?= htmlspecialchars($error) ?></p>
            <a href="user-login.php" class="btn">Back to Login</a>
        <?php else: ?>
            <h1>Resend Verification Email</h1>
            <p>Enter your email address and we'll send you a new verification link (valid for 24 hours).</p>
            <form method="get" action="">
                <div class="form-group">
                    <input type="email" name="email" placeholder="Your email address" required>
                </div>
                <button type="submit" class="btn">Send Verification Link</button>
            </form>
            <a href="user-login.php" style="display: inline-block; margin-top: 15px; color: #1e3c72;">Back to Login</a>
        <?php endif; ?>
    </div>
</body>
</html>