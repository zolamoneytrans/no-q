<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['business_id'])) {
    header('Location: business-login.php');
    exit;
}

$business_id = $_SESSION['business_id'];
$error = '';
$success = '';

// Get all users who favorited this business
$stmt = $pdo->prepare("
    SELECT u.id, u.name, u.email 
    FROM user_favorites f
    JOIN users u ON f.user_id = u.id
    WHERE f.business_id = ?
    ORDER BY f.created_at DESC
");
$stmt->execute([$business_id]);
$favorited_users = $stmt->fetchAll();

// Handle sending notification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $send_to = $_POST['send_to'] ?? 'all';
    $selected_users = isset($_POST['selected_users']) ? $_POST['selected_users'] : [];
    
    if (empty($title) || empty($message)) {
        $error = 'Please enter both a title and message.';
    } else {
        // Determine recipients
        if ($send_to == 'all') {
            $recipients = $favorited_users;
        } else {
            $recipients = array_filter($favorited_users, function($user) use ($selected_users) {
                return in_array($user['id'], $selected_users);
            });
        }
        
        if (empty($recipients)) {
            $error = 'No recipients selected.';
        } else {
            $sent_count = 0;
            $recipient_ids = [];
            
            foreach ($recipients as $user) {
                // Insert in-app notification
                $notify = $pdo->prepare("
                    INSERT INTO notifications (user_id, title, message, link, is_read, created_at) 
                    VALUES (?, ?, ?, 'user-dashboard.php', 0, NOW())
                ");
                if ($notify->execute([$user['id'], $title, $message])) {
                    $sent_count++;
                    $recipient_ids[] = $user['id'];
                    
                    // Send email notification
                    $email_body = "
                        <p>Dear " . htmlspecialchars($user['name']) . ",</p>
                        <p><strong>{$_SESSION['business_name']}</strong> has sent you a notification:</p>
                        <div style='background: #f0f4f8; padding: 15px; border-radius: 10px; margin: 15px 0;'>
                            <strong>{$title}</strong><br>
                            {$message}
                        </div>
                        <p><a href='https://carwashes.africa/user-dashboard.php'>View in your dashboard</a></p>
                        <p>Thank you for being a valued customer!</p>
                    ";
                    sendEmail($user['email'], "New Message from {$_SESSION['business_name']}", $email_body);
                }
            }
            
            // Save to business_notifications table for history
            $sent_to_json = json_encode($recipient_ids);
            $history = $pdo->prepare("
                INSERT INTO business_notifications (business_id, title, message, sent_to, sent_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $history->execute([$business_id, $title, $message, $sent_to_json]);
            
            $success = "Notification sent to $sent_count customer(s).";
        }
    }
}

// Get notification history
$stmt = $pdo->prepare("
    SELECT * FROM business_notifications 
    WHERE business_id = ? 
    ORDER BY sent_at DESC
");
$stmt->execute([$business_id]);
$history = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Notification · No Q</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(145deg, #f6f9fc 0%, #e9f1f8 100%);
            min-height: 100vh;
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
        }
        .logo-area { display: flex; align-items: center; gap: 10px; }
        .logo-icon { background: #1e3c72; color: white; width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        .logo-text { font-weight: 700; font-size: 1.5rem; background: linear-gradient(135deg, #1e3c72, #2a5298); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .nav-links { display: flex; gap: 1.2rem; align-items: center; }
        .nav-links a { text-decoration: none; font-weight: 500; color: #2c3e50; padding: 0.5rem 0.8rem; border-radius: 40px; }
        .nav-links a:hover { background: rgba(42,82,152,0.08); }
        .menu-toggle {
            display: none;
            font-size: 1.8rem;
            cursor: pointer;
            background: transparent;
            border: none;
            color: #1e3c72;
        }
        @media (max-width: 768px) {
            .menu-toggle { display: block; }
            .nav-links { display: none; width: 100%; flex-direction: column; background: rgba(255,255,255,0.95); border-radius: 30px; padding: 1rem; margin-top: 1rem; }
            .nav-links.show { display: flex; }
            .app-header { padding: 0.8rem 1rem; }
        }
        .container { max-width: 1000px; margin: 2rem auto; padding: 0 2rem; }
        .card {
            background: rgba(255,255,255,0.8);
            backdrop-filter: blur(10px);
            border-radius: 32px;
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(255,255,255,0.6);
        }
        h1 { font-size: 1.8rem; margin-bottom: 1rem; color: #1e3c72; }
        h2 { font-size: 1.3rem; margin-bottom: 1rem; color: #1e3c72; }
        .form-group { margin-bottom: 1.2rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: #1e3c72; }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 30px;
            background: #f0f4f8;
            font-family: 'Inter';
            font-size: 1rem;
        }
        .btn {
            background: #1e3c72;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 40px;
            font-weight: 600;
            cursor: pointer;
            font-size: 1rem;
        }
        .btn-primary { background: #ff9800; width: 100%; }
        .btn-primary:hover { background: #e68900; }
        .user-list {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid rgba(0,0,0,0.1);
            border-radius: 20px;
            padding: 0.5rem;
            background: white;
        }
        .user-checkbox {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        .user-checkbox:last-child { border-bottom: none; }
        .message { padding: 1rem; border-radius: 30px; margin-bottom: 1rem; text-align: center; }
        .success { background: #e8f5e9; color: #1e3c72; }
        .error { background: #ffebee; color: #b71c1c; }
        .history-item {
            padding: 1rem;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        .history-item:last-child { border-bottom: none; }
        .history-title { font-weight: 600; color: #1e3c72; }
        .history-date { font-size: 0.75rem; color: #666; margin-top: 0.3rem; }
        @media (max-width: 768px) {
            .container { padding: 0 1rem; }
            .card { padding: 1.5rem; }
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
            <a href="business-dashboard.php">Dashboard</a>
            <a href="send-notification.php" style="background:rgba(42,82,152,0.1);">Send Notification</a>
            <a href="favorites.php">Favorited Clients</a>
            <a href="business-logout.php">Logout</a>
        </nav>
    </header>

    <div class="container">
        <div class="card">
            <h1><i class="fa-regular fa-bell"></i> Send Notification to Favorited Clients</h1>
            
            <?php if ($error): ?>
                <div class="message error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="message success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <form method="post">
                <div class="form-group">
                    <label>Send to:</label>
                    <select name="send_to" id="send_to" onchange="toggleUserSelection()">
                        <option value="all">All favorited clients</option>
                        <option value="selected">Select specific clients</option>
                    </select>
                </div>
                
                <div id="user-selection" style="display: none;">
                    <div class="form-group">
                        <label>Select Clients:</label>
                        <div class="user-list">
                            <?php if (empty($favorited_users)): ?>
                                <p style="padding: 1rem; text-align: center;">No clients have favorited your business yet.</p>
                            <?php else: ?>
                                <?php foreach ($favorited_users as $user): ?>
                                    <div class="user-checkbox">
                                        <input type="checkbox" name="selected_users[]" value="<?= $user['id'] ?>" id="user_<?= $user['id'] ?>">
                                        <label for="user_<?= $user['id'] ?>">
                                            <strong><?= htmlspecialchars($user['name']) ?></strong><br>
                                            <small><?= htmlspecialchars($user['email']) ?></small>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Notification Title:</label>
                    <input type="text" name="title" placeholder="e.g., Special Offer This Weekend" required>
                </div>
                
                <div class="form-group">
                    <label>Notification Message:</label>
                    <textarea name="message" rows="5" placeholder="Write your message here..." required></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary">Send Notification</button>
            </form>
        </div>
        
        <?php if (!empty($history)): ?>
        <div class="card">
            <h2><i class="fa-regular fa-clock"></i> Sent History</h2>
            <?php foreach ($history as $h): ?>
                <div class="history-item">
                    <div class="history-title"><?= htmlspecialchars($h['title']) ?></div>
                    <div class="history-date"><?= date('d M Y H:i', strtotime($h['sent_at'])) ?></div>
                    <div style="margin-top: 0.5rem;"><?= nl2br(htmlspecialchars($h['message'])) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <script>
        document.getElementById('menuToggle').addEventListener('click', function() {
            document.getElementById('navLinks').classList.toggle('show');
        });
        
        function toggleUserSelection() {
            var sendTo = document.getElementById('send_to').value;
            var userSelection = document.getElementById('user-selection');
            if (sendTo === 'selected') {
                userSelection.style.display = 'block';
            } else {
                userSelection.style.display = 'none';
            }
        }
    </script>
</body>
</html>
