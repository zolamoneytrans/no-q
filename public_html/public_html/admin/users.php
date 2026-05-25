<?php
session_start();
require_once '../db_connect.php';
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin-login.php');
    exit;
}

$message = $error = '';

if (isset($_GET['action']) && isset($_GET['id'])) {
    $user_id = (int)$_GET['id'];
    $action = $_GET['action'];
    if ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        if ($stmt->execute([$user_id])) $message = 'User deleted.';
        else $error = 'Delete failed.';
    } elseif ($action === 'freeze') {
        $stmt = $pdo->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
        if ($stmt->execute([$user_id])) $message = 'User frozen.';
        else $error = 'Freeze failed.';
    } elseif ($action === 'unfreeze') {
        $stmt = $pdo->prepare("UPDATE users SET is_active = 1 WHERE id = ?");
        if ($stmt->execute([$user_id])) $message = 'User unfrozen.';
        else $error = 'Unfreeze failed.';
    }
    header('Location: users.php');
    exit;
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$query = "SELECT *, DATE(created_at) as joined_date FROM users";
$params = [];

if (!empty($search)) {
    $query .= " WHERE name LIKE :search OR email LIKE :search";
    $params[':search'] = "%$search%";
}
$query .= " ORDER BY id DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Manage Users · Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
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
            flex-wrap: wrap;
        }
        .logo-area { display: flex; align-items: center; gap: 10px; }
        .logo-text { font-weight: 700; font-size: 1.5rem; background: linear-gradient(135deg, #1e3c72, #2a5298); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .nav-links { display: flex; gap: 1.2rem; align-items: center; flex-wrap: wrap; }
        .nav-links a { text-decoration: none; font-weight: 500; color: #2c3e50; padding: 0.5rem 0.8rem; border-radius: 40px; }
        .nav-links a:hover { background: rgba(42,82,152,0.08); }
        .menu-toggle {
            display: none;
            font-size: 1.8rem;
            cursor: pointer;
            background: transparent;
            border: none;
            color: #1e3c72;
            padding: 0.5rem;
        }
        @media (max-width: 768px) {
            .menu-toggle { display: block; }
            .nav-links { display: none; width: 100%; flex-direction: column; background: rgba(255,255,255,0.95); border-radius: 30px; padding: 1rem; margin-top: 1rem; }
            .nav-links.show { display: flex; }
            .app-header { padding: 0.8rem 1rem; }
            .nav-links a { width: 100%; text-align: center; padding: 0.8rem; }
        }
        .container { max-width: 1400px; margin: 2rem auto; padding: 0 2rem; flex: 1; }
        h1 { font-size: 2rem; margin-bottom: 1rem; color: #1e3c72; }
        
        .search-bar {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        .search-bar input {
            flex: 1;
            padding: 0.8rem 1.2rem;
            border: none;
            border-radius: 40px;
            background: rgba(255,255,255,0.7);
            font-family: 'Inter', sans-serif;
        }
        .search-bar button {
            background: #1e3c72;
            color: white;
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: 40px;
            cursor: pointer;
        }
        
        /* Desktop Table */
        .desktop-table {
            width: 100%;
            border-collapse: collapse;
            background: rgba(255,255,255,0.5);
            backdrop-filter: blur(8px);
            border-radius: 30px;
            overflow: hidden;
        }
        .desktop-table th, .desktop-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        .desktop-table th { background: rgba(30,60,114,0.1); color: #1e3c72; font-weight: 600; }
        
        /* Mobile Cards */
        .users-grid {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .user-card {
            background: rgba(255,255,255,0.85);
            backdrop-filter: blur(8px);
            border-radius: 24px;
            padding: 1.2rem;
            border: 1px solid rgba(255,255,255,0.6);
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        .user-name {
            font-weight: 700;
            font-size: 1.1rem;
            color: #1e3c72;
        }
        .status-badge {
            padding: 0.25rem 1rem;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
        }
        .status-active { background: #4caf50; color: white; }
        .status-frozen { background: #9e9e9e; color: white; }
        .card-body { display: flex; flex-direction: column; gap: 0.75rem; }
        .info-row { display: flex; flex-wrap: wrap; align-items: baseline; gap: 0.5rem; }
        .info-label { font-size: 0.7rem; font-weight: 600; text-transform: uppercase; color: #6c7a8a; min-width: 85px; }
        .info-value { font-size: 0.9rem; color: #1a2639; }
        .card-actions { margin-top: 0.5rem; display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .btn-small {
            background: #1e3c72;
            color: white;
            border: none;
            padding: 0.3rem 0.8rem;
            border-radius: 30px;
            font-size: 0.75rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-small.red { background: #f44336; }
        .btn-small.orange { background: #ff9800; }
        
        .message { background: #e8f5e9; color: #1e3c72; padding: 1rem; border-radius: 30px; margin-bottom: 1rem; }
        .error { background: #ffebee; color: #b71c1c; padding: 1rem; border-radius: 30px; margin-bottom: 1rem; }
        .app-footer { background: rgba(255,255,255,0.6); padding: 2rem; text-align: center; margin-top: auto; }
        
        /* Hide cards on desktop, hide table on mobile */
        @media (min-width: 769px) {
            .users-grid { display: none; }
        }
        @media (max-width: 768px) {
            .desktop-table { display: none; }
            .container { padding: 0 1rem; }
            h1 { font-size: 1.6rem; }
            .search-bar { flex-direction: column; }
            .search-bar button { width: 100%; }
        }
    </style>
</head>
<body>
    <header class="app-header">
         <div class="logo-area">
    <img src="/NoQ.jpg" alt="No Q" style="height: 85px; width: auto;">
    <div>
        <span class="logo-text">No Q</span>
        <div style="font-size: 0.7rem; color: #2c3e50; letter-spacing: 0.5px;">No more Queues</div>
    </div>
</div>
        <button class="menu-toggle" id="menuToggle"><i class="fa-solid fa-bars"></i></button>
        <nav class="nav-links" id="navLinks">
            <a href="admin-dashboard.php">Dashboard</a>
            <a href="users.php" style="background:rgba(42,82,152,0.1);">Users</a>
            <a href="businesses.php">Businesses</a>
            <a href="admin-logout.php">Logout</a>
        </nav>
    </header>

    <div class="container">
        <h1><i class="fa-regular fa-user"></i> Manage Users</h1>
        
        <form method="GET" action="" class="search-bar">
            <input type="text" name="search" placeholder="Search by name or email..." value="<?= htmlspecialchars($search ?? '') ?>">
            <button type="submit"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
            <?php if (!empty($search)): ?>
                <a href="users.php" style="background:#777; color:white; padding:0.8rem 1.5rem; border-radius:40px; text-decoration:none;">Clear</a>
            <?php endif; ?>
        </form>
        
        <?php if ($message): ?><div class="message"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
        
        <?php if (empty($users)): ?>
            <p>No users found.</p>
        <?php else: ?>
            
            <!-- Desktop Table -->
            <table class="desktop-table">
                <thead>
                    <tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><!--<th>Points</th>--><th>Joined</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= $u['id'] ?></td>
                        <td><?= htmlspecialchars($u['name']) ?></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td><?= htmlspecialchars($u['phone']??'—') ?></td>
                    <!--    <td><?= $u['points'] ?></td> -->
                        <td><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                        <td><span class="status-badge <?= $u['is_active']?'status-active':'status-frozen' ?>"><?= $u['is_active']?'Active':'Frozen' ?></span></td>
                        <td>
                            <a href="users-view.php?id=<?= $u['id'] ?>" class="btn-small" style="background:#2196f3;">View</a>
                            <?php if ($u['is_active']): ?>
                                <a href="?action=freeze&id=<?= $u['id'] ?>" class="btn-small orange" onclick="return confirm('Freeze this user?')">Freeze</a>
                            <?php else: ?>
                                <a href="?action=unfreeze&id=<?= $u['id'] ?>" class="btn-small" onclick="return confirm('Unfreeze this user?')">Unfreeze</a>
                            <?php endif; ?>
                            <a href="?action=delete&id=<?= $u['id'] ?>" class="btn-small red" onclick="return confirm('Delete this user?')">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Mobile Cards -->
            <div class="users-grid">
                <?php foreach ($users as $u): ?>
                <div class="user-card">
                    <div class="card-header">
                        <span class="user-name"><?= htmlspecialchars($u['name']) ?></span>
                        <span class="status-badge <?= $u['is_active']?'status-active':'status-frozen' ?>"><?= $u['is_active']?'Active':'Frozen' ?></span>
                    </div>
                    <div class="card-body">
                        <div class="info-row"><span class="info-label">ID</span><span class="info-value"><?= $u['id'] ?></span></div>
                        <div class="info-row"><span class="info-label">Email</span><span class="info-value"><?= htmlspecialchars($u['email']) ?></span></div>
                        <div class="info-row"><span class="info-label">Phone</span><span class="info-value"><?= htmlspecialchars($u['phone']??'—') ?></span></div>
                        <div class="info-row"><span class="info-label">Points</span><span class="info-value"><?= $u['points'] ?></span></div>
                        <div class="info-row"><span class="info-label">Joined</span><span class="info-value"><?= date('d M Y', strtotime($u['created_at'])) ?></span></div>
                    </div>
                    <div class="card-actions">
                        <a href="users-view.php?id=<?= $u['id'] ?>" class="btn-small" style="background:#2196f3;">View</a>
                        <?php if ($u['is_active']): ?>
                            <a href="?action=freeze&id=<?= $u['id'] ?>" class="btn-small orange" onclick="return confirm('Freeze this user?')">Freeze</a>
                        <?php else: ?>
                            <a href="?action=unfreeze&id=<?= $u['id'] ?>" class="btn-small" onclick="return confirm('Unfreeze this user?')">Unfreeze</a>
                        <?php endif; ?>
                        <a href="?action=delete&id=<?= $u['id'] ?>" class="btn-small red" onclick="return confirm('Delete this user?')">Delete</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
        <?php endif; ?>
    </div>

    <footer class="app-footer">
        <p>&copy; <?= date('Y'); ?> No Q · Admin</p>
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
    </script>
</body>
</html>