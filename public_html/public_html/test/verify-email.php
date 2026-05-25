<?php
session_start();
require_once 'db_connect.php';

$message = '';
$error = '';

if (isset($_GET['token'])) {
    $token = trim($_GET['token']);

    $stmt = $pdo->prepare("SELECT id, name, email, verification_expires FROM users WHERE verification_token = ? AND is_verified = 0");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        $expires = strtotime($user['verification_expires']);
        $now = time();
        if ($now > $expires) {
            $error = "This verification link has expired. Please request a new one.";
        } else {
            $update = $pdo->prepare("UPDATE users SET is_verified = 1, verification_token = NULL, verification_expires = NULL WHERE id = ?");
            if ($update->execute([$user['id']])) {
                $message = "Email verified successfully! You can now log in to your account.";
            } else {
                $error = "Failed to verify email. Please try again.";
            }
        }
    } else {
        $error = "Invalid verification link or email already verified.";
    }
} else {
    $error = "No verification token provided.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification · No Q</title>
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
        .resend-link {
            display: inline-block;
            margin-top: 15px;
            color: #ff9800;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($message): ?>
            <div class="icon success"><i class="fa-solid fa-circle-check"></i></div>
            <h1>Email Verified! ✅</h1>
            <p><?= htmlspecialchars($message) ?></p>
            <a href="user-login.php" class="btn">Log In Now</a>
        <?php elseif ($error): ?>
            <div class="icon error"><i class="fa-solid fa-circle-exclamation"></i></div>
            <h1>Verification Failed</h1>
            <p><?= htmlspecialchars($error) ?></p>
            <?php if (strpos($error, 'expired') !== false && isset($user['email'])): ?>
                <a href="resend-verification.php?email=<?= urlencode($user['email']) ?>" class="resend-link">Request a new verification link</a><br>
            <?php endif; ?>
            <a href="user-login.php" class="btn">Back to Login</a>
        <?php endif; ?>
    </div>
</body>
</html>
