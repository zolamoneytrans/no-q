<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: user-login.php?redirect=' . urlencode('user-delete-account.php'));
    exit;
}

$user_id = $_SESSION['user_id'];
$error = '';

$stmt = $pdo->prepare("SELECT email, name, password, points FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header('Location: user-login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm_check = $_POST['confirm_check'] ?? '';

    if (empty($password)) {
        $error = 'Please enter your current password to confirm deletion.';
    } elseif ($confirm_check !== 'yes') {
        $error = 'Please check the confirmation box to proceed.';
    } elseif (!password_verify($password, $user['password'])) {
        $error = 'Incorrect password. Deletion cancelled.';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Delete ratings
            $pdo->prepare("DELETE FROM ratings WHERE user_id = ?")->execute([$user_id]);
            // Delete notifications
            $pdo->prepare("DELETE FROM notifications WHERE user_id = ?")->execute([$user_id]);
            // Delete favorites
            $pdo->prepare("DELETE FROM user_favorites WHERE user_id = ?")->execute([$user_id]);
            // Delete payments
            $pdo->prepare("DELETE FROM payments WHERE user_id = ?")->execute([$user_id]);
            // Delete bookings
            $pdo->prepare("DELETE FROM bookings WHERE user_id = ?")->execute([$user_id]);
            // Delete user
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);
            
            $pdo->commit();

            $_SESSION = [];
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            session_destroy();

            header('Location: user-login.php?success=account_deleted');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'An error occurred while deleting your account: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Confirm Account Deletion · No Q</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --purple-primary: #6a1b9a;
            --orange-primary: #ff9800;
            --bg-gradient: linear-gradient(145deg, #faf5ff 0%, #f3e5f5 100%);
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--bg-gradient); min-height: 100vh; display: flex; flex-direction: column; color: #2c3e50; }
        .app-header { background: rgba(255,255,255,0.9); backdrop-filter: blur(12px); border-bottom: 1px solid rgba(106,27,154,0.1); padding: 0.8rem 2rem; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100; }
        .logo-area { display: flex; align-items: center; gap: 0.8rem; }
        .logo-text { font-size: 1.5rem; font-weight: 800; background: linear-gradient(135deg, var(--purple-primary), var(--orange-primary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .container { max-width: 650px; margin: 3rem auto; padding: 0 1.5rem; flex: 1; width: 100%; }
        .card { background: white; border-radius: 24px; padding: 2.5rem; box-shadow: 0 15px 35px rgba(220,38,38,0.08); border: 1px solid #fee2e2; }
        .card h1 { color: #dc2626; font-size: 1.8rem; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.7rem; }
        .warning-box { background: #fef2f2; border-left: 4px solid #dc2626; padding: 1.2rem; border-radius: 0 12px 12px 0; margin-bottom: 1.5rem; }
        .warning-box p { color: #7f1d1d; font-size: 0.95rem; line-height: 1.5; }
        .deletion-list { margin: 1.5rem 0; padding-left: 1.5rem; }
        .deletion-list li { margin-bottom: 0.6rem; color: #4b5563; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 0.5rem; color: #374151; font-size: 0.9rem; }
        .form-group input[type="password"] { width: 100%; padding: 0.8rem 1rem; border: 1.5px solid #d1d5db; border-radius: 12px; font-size: 1rem; }
        .form-group input[type="password"]:focus { border-color: #dc2626; outline: none; }
        .checkbox-group { display: flex; align-items: flex-start; gap: 0.8rem; margin: 1.5rem 0; }
        .checkbox-group input { margin-top: 0.3rem; width: 18px; height: 18px; accent-color: #dc2626; }
        .checkbox-group label { font-size: 0.9rem; color: #4b5563; line-height: 1.4; cursor: pointer; }
        .actions { display: flex; gap: 1rem; flex-wrap: wrap; margin-top: 2rem; }
        .btn { padding: 0.9rem 1.8rem; border-radius: 30px; border: none; font-weight: 600; cursor: pointer; transition: all 0.2s; text-decoration: none; text-align: center; flex: 1; min-width: 200px; }
        .btn-red { background: #dc2626; color: white; }
        .btn-red:hover { background: #b91c1c; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(220,38,38,0.3); }
        .btn-gray { background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; }
        .btn-gray:hover { background: #e5e7eb; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem; }
        .app-footer { background: rgba(255,255,255,0.6); padding: 2rem; text-align: center; margin-top: auto; color: var(--purple-primary); font-size: 0.85rem; }
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
        <a href="user-settings.php" style="color: var(--purple-primary); text-decoration: none; font-weight: 600;"><i class="fa-solid fa-arrow-left"></i> Back to Settings</a>
    </header>

    <div class="container">
        <?php if ($error): ?>
            <div class="alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="card">
            <h1><i class="fa-solid fa-triangle-exclamation"></i> Delete Your Account</h1>
            
            <div class="warning-box">
                <p><strong>Warning:</strong> You are initiating permanent account deletion for <strong><?= htmlspecialchars($user['email']) ?></strong>. This action is irreversible and cannot be undone.</p>
            </div>

            <p style="color: #374151; margin-bottom: 0.5rem;">When your account is deleted, the following data will be permanently removed from our servers immediately:</p>
            <ul class="deletion-list">
                <li>Personal profile information (Name: <?= htmlspecialchars($user['name']) ?>, Email, Phone)</li>
                <li>All past and upcoming car wash bookings and transaction records</li>
                <li>All ratings and reviews submitted by your account</li>
                <li>Saved favorite car washes and accumulated loyalty points (<?= (int)$user['points'] ?> pts)</li>
                <li>Push notification preferences and notification history</li>
            </ul>

            <form method="post">
                <div class="form-group">
                    <label for="password">Enter your current password to verify your identity:</label>
                    <input type="password" name="password" id="password" required placeholder="Your current password">
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" name="confirm_check" value="yes" id="confirm_check" required>
                    <label for="confirm_check">I confirm that I understand this deletion is permanent and that all my data will be erased immediately upon clicking the delete button below.</label>
                </div>

                <div class="actions">
                    <a href="user-settings.php" class="btn btn-gray"><i class="fa-solid fa-xmark"></i> Cancel & Keep Account</a>
                    <button type="submit" class="btn btn-red"><i class="fa-solid fa-trash-can"></i> Permanently Delete Account</button>
                </div>
            </form>
        </div>
    </div>

    <footer class="app-footer">
        <p>&copy; <?= date('Y'); ?> No Q. All rights reserved.</p>
    </footer>
</body>
</html>
