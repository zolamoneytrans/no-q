<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: user-login.php?redirect=' . urlencode('user-settings.php'));
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Fetch current user data
$stmt = $pdo->prepare("SELECT id, name, email, phone, points, created_at FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header('Location: user-login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if (empty($name)) {
            $error = 'Name cannot be empty.';
        } else {
            $update_stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?");
            if ($update_stmt->execute([$name, $phone, $user_id])) {
                $_SESSION['user_name'] = $name;
                $user['name'] = $name;
                $user['phone'] = $phone;
                $message = 'Profile updated successfully!';
            } else {
                $error = 'Failed to update profile.';
            }
        }
    } elseif ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        $pw_stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $pw_stmt->execute([$user_id]);
        $stored = $pw_stmt->fetchColumn();

        if (empty($current_password) || empty($new_password)) {
            $error = 'Please fill in all password fields.';
        } elseif (!password_verify($current_password, $stored)) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new_password) < 6) {
            $error = 'New password must be at least 6 characters.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'New passwords do not match.';
        } else {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $update_pw = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            if ($update_pw->execute([$hashed, $user_id])) {
                $message = 'Password changed successfully!';
            } else {
                $error = 'Failed to update password.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Account Settings · No Q</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --purple-primary: #6a1b9a;
            --purple-dark: #4a0072;
            --orange-primary: #ff9800;
            --orange-dark: #f57c00;
            --bg-gradient: linear-gradient(145deg, #faf5ff 0%, #f3e5f5 100%);
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--bg-gradient); min-height: 100vh; display: flex; flex-direction: column; color: #2c3e50; }
        .app-header { background: rgba(255,255,255,0.9); backdrop-filter: blur(12px); border-bottom: 1px solid rgba(106,27,154,0.1); padding: 0.8rem 2rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; position: sticky; top: 0; z-index: 100; }
        .logo-area { display: flex; align-items: center; gap: 0.8rem; }
        .logo-text { font-size: 1.5rem; font-weight: 800; background: linear-gradient(135deg, var(--purple-primary), var(--orange-primary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .nav-links { display: flex; gap: 1.2rem; align-items: center; flex-wrap: wrap; }
        .nav-links a { text-decoration: none; font-weight: 500; color: #2c3e50; padding: 0.5rem 0.8rem; border-radius: 40px; transition: all 0.3s ease; }
        .nav-links a:hover { background: rgba(106,27,154,0.08); color: var(--purple-primary); }
        .menu-toggle { display: none; background: none; border: none; font-size: 1.5rem; color: var(--purple-primary); cursor: pointer; }
        .container { max-width: 800px; margin: 2rem auto; padding: 0 1.5rem; flex: 1; width: 100%; }
        .card { background: white; border-radius: 24px; padding: 2rem; box-shadow: 0 10px 30px rgba(106,27,154,0.05); margin-bottom: 2rem; border: 1px solid rgba(106,27,154,0.05); }
        .card h2 { color: var(--purple-primary); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 0.5rem; color: #4a5568; font-size: 0.9rem; }
        .form-group input { width: 100%; padding: 0.8rem 1rem; border: 1.5px solid #e2e8f0; border-radius: 12px; font-size: 1rem; transition: border-color 0.2s; }
        .form-group input:focus { border-color: var(--purple-primary); outline: none; }
        .form-group input[disabled] { background: #f8fafc; color: #94a3b8; cursor: not-allowed; }
        .btn { padding: 0.8rem 1.8rem; border-radius: 30px; border: none; font-weight: 600; cursor: pointer; transition: all 0.2s; text-decoration: none; display: inline-block; }
        .btn-purple { background: linear-gradient(135deg, var(--purple-primary), var(--purple-dark)); color: white; }
        .btn-purple:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(106,27,154,0.3); }
        .btn-red { background: #dc2626; color: white; }
        .btn-red:hover { background: #b91c1c; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(220,38,38,0.3); }
        .danger-zone { border: 2px dashed #fca5a5; background: #fef2f2; border-radius: 24px; padding: 2rem; }
        .danger-zone h3 { color: #dc2626; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem; }
        .danger-zone p { color: #7f1d1d; margin-bottom: 1.5rem; font-size: 0.95rem; line-height: 1.5; }
        .alert { padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .app-footer { background: rgba(255,255,255,0.6); padding: 2rem; text-align: center; margin-top: auto; color: var(--purple-primary); font-size: 0.85rem; }
        @media (max-width: 768px) {
            .menu-toggle { display: block; }
            .nav-links { display: none; flex-direction: column; width: 100%; padding-top: 1rem; }
            .nav-links.show { display: flex; }
            .nav-links a { width: 100%; text-align: center; }
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
        <button class="menu-toggle" id="menuToggle" aria-label="Menu">
            <i class="fa-solid fa-bars"></i>
        </button>
        <nav class="nav-links" id="navLinks">
            <a href="index.php">Home</a>
            <a href="user-dashboard.php">Dashboard</a>
            <a href="my-bookings.php">My Bookings</a>
            <a href="user-settings.php" style="background:rgba(106,27,154,0.1);">Settings</a>
            <a href="logout.php">Logout</a>
        </nav>
    </header>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Profile Details -->
        <div class="card">
            <h2><i class="fa-regular fa-user"></i> Profile Details</h2>
            <form method="post">
                <input type="hidden" name="action" value="update_profile">
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                    <small style="color: #64748b;">Email address cannot be changed directly.</small>
                </div>
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="tel" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="+27 ...">
                </div>
                <button type="submit" class="btn btn-purple"><i class="fa-solid fa-check"></i> Save Changes</button>
            </form>
        </div>

        <!-- Change Password -->
        <div class="card">
            <h2><i class="fa-regular fa-lock"></i> Change Password</h2>
            <form method="post">
                <input type="hidden" name="action" value="change_password">
                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" name="current_password" required>
                </div>
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" required minlength="6">
                </div>
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" required minlength="6">
                </div>
                <button type="submit" class="btn btn-purple"><i class="fa-solid fa-key"></i> Update Password</button>
            </form>
        </div>

        <!-- Danger Zone: Account Deletion -->
        <div class="danger-zone">
            <h3><i class="fa-solid fa-triangle-exclamation"></i> Account Deletion</h3>
            <p>
                Deleting your account will permanently wipe your profile information, booking records, saved car washes, and loyalty points. Once initiated, this action is permanent and cannot be undone.
            </p>
            <a href="user-delete-account.php" class="btn btn-red"><i class="fa-solid fa-trash-can"></i> Initiate Account Deletion...</a>
        </div>
    </div>

    <footer class="app-footer">
        <p>&copy; <?= date('Y'); ?> No Q. All rights reserved.</p>
    </footer>

    <script>
        document.getElementById('menuToggle').addEventListener('click', function() {
            document.getElementById('navLinks').classList.toggle('show');
        });
    </script>
</body>
</html>
