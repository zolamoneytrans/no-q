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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Send Notification · No Q</title>
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
        .nav-links { display: flex; gap: 1.2rem; align-items: center; flex-wrap: wrap; }
        .nav-links a { 
            text-decoration: none; 
            font-weight: 500; 
            color: #2c3e50; 
            padding: 0.5rem 0.8rem; 
            border-radius: 40px;
            transition: 0.2s;
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
        }
        .container { max-width: 1000px; margin: 2rem auto; padding: 0 2rem; flex: 1; }
        .card {
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(10px);
            border-radius: 40px;
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(106,27,154,0.1);
            box-shadow: 0 20px 40px -12px rgba(106,27,154,0.2);
        }
        h1 { font-size: 1.8rem; margin-bottom: 1rem; background: linear-gradient(145deg, var(--purple-primary), var(--orange-primary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        h2 { font-size: 1.3rem; margin-bottom: 1rem; color: var(--purple-primary); }
        .form-group { margin-bottom: 1.2rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--purple-primary); }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 30px;
            background: #f0f4f8;
            font-family: 'Inter';
            font-size: 1rem;
            transition: all 0.2s;
        }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
            outline: none;
            box-shadow: 0 0 0 2px rgba(106,27,154,0.2);
            background: white;
        }
        .btn { background: linear-gradient(135deg, var(--purple-primary), var(--purple-dark)); color: white; border: none; padding: 12px 24px; border-radius: 40px; font-weight: 600; cursor: pointer; font-size: 1rem; transition: all 0.2s; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 3px 10px rgba(106,27,154,0.3); }
        .btn-primary { background: linear-gradient(135deg, var(--orange-primary), var(--orange-dark)); width: 100%; }
        .user-list {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid rgba(106,27,154,0.1);
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
        .success { background: #e8f5e9; color: var(--purple-primary); }
        .error { background: #ffebee; color: #b71c1c; }
        .history-item {
            padding: 1rem;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        .history-item:last-child { border-bottom: none; }
        .history-title { font-weight: 600; color: var(--purple-primary); }
        .history-date { font-size: 0.75rem; color: #666; margin-top: 0.3rem; }
        .app-footer { background: rgba(255,255,255,0.6); padding: 2rem; text-align: center; margin-top: auto; color: var(--purple-primary); font-size: 0.85rem; }
        .app-footer a { color: var(--purple-primary); text-decoration: none; }
        .app-footer a:hover { color: var(--orange-primary); text-decoration: underline; }
        
        @media (max-width: 768px) {
            .container { padding: 0 1rem; margin: 1rem auto; }
            .card { padding: 1.2rem; }
            h1 { font-size: 1.4rem; }
        }
    </style>
</head>
<body>
    <header class="app-header">
        <div class="logo-area">
            <img src="/NoQ.jpg" alt="No Q" style="height: 85px; width: auto;">
            <div>
                <span class="logo-text">No Q</span>
                <div style="font-size: 0.7rem; color: var(--purple-primary); letter-spacing: 0.5px;">No more Queues</div>
            </div>
        </div>
        <button class="menu-toggle" id="menuToggle"><i class="fa-solid fa-bars"></i></button>
        <nav class="nav-links" id="navLinks">
            <a href="business-dashboard.php">Dashboard</a>
            <a href="send-notification.php" style="background:rgba(106,27,154,0.1);">Send Notification</a>
            <a href="favorites.php">Favourited Clients</a>
            <a href="business-logout.php">Logout</a>
        </nav>
    </header>

    <div class="container">
        <div class="card">
            <h1><i class="fa-regular fa-bell"></i> Send Notification to Favourited Clients</h1>
            
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
                        <option value="all">All favourited clients</option>
                        <option value="selected">Select specific clients</option>
                    </select>
                </div>
                
                <div id="user-selection" style="display: none;">
                    <div class="form-group">
                        <label>Select Clients:</label>
                        <div class="user-list">
                            <?php if (empty($favorited_users)): ?>
                                <p style="padding: 1rem; text-align: center;">No clients have favourited your business yet.</p>
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

    <footer class="app-footer">
        <p>&copy; <?= date('Y'); ?> No Q. All rights reserved.</p>
        <p>Powered by <a href="https://www.jaekerna.com/" target="_blank">Jaekerna Investments</a></p>
    </footer>

    <script>
        document.getElementById('menuToggle').addEventListener('click', function() {
            document.getElementById('navLinks').classList.toggle('show');
        });
        document.querySelectorAll('.nav-links a').forEach(link => {
            link.addEventListener('click', () => {
                document.getElementById('navLinks').classList.remove('show');
            });
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