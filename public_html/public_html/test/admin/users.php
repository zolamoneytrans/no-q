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
        .logo-icon { background: #1e3c72; color: white; width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
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
        
        .table-wrapper {
            overflow-x: auto;
            background: rgba(255,255,255,0.5);
            backdrop-filter: blur(8px);
            border-radius: 30px;
            padding: 1rem;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }
        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        th {
            background: rgba(30,60,114,0.1);
            color: #1e3c72;
            font-weight: 600;
        }
        .status-badge {
            padding: 0.25rem 1rem;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
        }
        .status-active { background: #4caf50; color: white; }
        .status-frozen { background: #9e9e9e; color: white; }
        
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
            margin-right: 0.3rem;
            margin-bottom: 0.3rem;
        }
        .btn-small.red { background: #f44336; }
        .btn-small.orange { background: #ff9800; }
        
        .message { background: #e8f5e9; color: #1e3c72; padding: 1rem; border-radius: 30px; margin-bottom: 1rem; }
        .error { background: #ffebee; color: #b71c1c; padding: 1rem; border-radius: 30px; margin-bottom: 1rem; }
        .app-footer { background: rgba(255,255,255,0.6); padding: 2rem; text-align: center; margin-top: auto; }
        
        @media (max-width: 768px) {
            .container { padding: 0 1rem; }
            h1 { font-size: 1.6rem; }
            .search-bar { flex-direction: column; }
            .search-bar button { width: 100%; }
            .table-wrapper { padding: 0.5rem; }
            
            table, thead, tbody, th, td, tr { display: block; }
            thead { display: none; }
            tr {
                margin-bottom: 1rem;
                border: 1px solid rgba(30,60,114,0.1);
                border-radius: 20px;
                padding: 0.8rem;
                background: rgba(255,255,255,0.7);
            }
            td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 0.6rem 0.5rem;
                border: none;
            }
            td:before {
                content: attr(data-label);
                font-weight: 700;
                color: #1e3c72;
                min-width: 100px;
                font-size: 0.8rem;
            }
            td:last-child {
                justify-content: flex-start;
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <header class="app-header">
        <div class="logo-area">
            <img src="/logo.jpeg" alt="No Q" style="height: 40px; width: auto;">
            <span class="logo-text">Admin<span style="font-weight:400;">Panel</span></span>
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
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Points</th><th>Joined</th><th>Status</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td data-label="ID"><?= $u['id'] ?></td>
                            <td data-label="Name"><?= htmlspecialchars($u['name']) ?></td>
                            <td data-label="Email"><?= htmlspecialchars($u['email']) ?></td>
                            <td data-label="Phone"><?= htmlspecialchars($u['phone']??'—') ?></td>
                            <td data-label="Points"><?= $u['points'] ?></td>
                            <td data-label="Joined"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                            <td data-label="Status">
                                <span class="status-badge <?= $u['is_active']?'status-active':'status-frozen' ?>">
                                    <?= $u['is_active']?'Active':'Frozen' ?>
                                </span>
                            </td>
                            <td data-label="Actions">
                                <a href="users-view.php?id=<?= $u['id'] ?>" class="btn-small" style="background:#2196f3;">View</a>
                                <?php if ($u['is_active']): ?>
                                    <a href="?action=freeze&id=<?= $u['id'] ?>" class="btn-small orange" onclick="return confirm('Freeze this user?')">Freeze</a>
                                <?php else: ?>
                                    <a href="?action=unfreeze&id=<?= $u['id'] ?>" class="btn-small" onclick="return confirm('Unfreeze this user?')">Unfreeze</a>
                                <?php endif; ?>
                                <a href="?action=delete&id=<?= $u['id'] ?>" class="btn-small red" onclick="return confirm('Permanently delete this user?')">Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
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
