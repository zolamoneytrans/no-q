<?php
session_start();
require_once '../db_connect.php';
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin-login.php');
    exit;
}

$message = $error = '';

if (isset($_GET['action']) && isset($_GET['id'])) {
    $business_id = (int)$_GET['id'];
    $action = $_GET['action'];
    if ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM businesses WHERE id = ?");
        if ($stmt->execute([$business_id])) $message = 'Business deleted.';
        else $error = 'Delete failed.';
    } elseif ($action === 'freeze') {
        $stmt = $pdo->prepare("UPDATE businesses SET is_active = 0 WHERE id = ?");
        if ($stmt->execute([$business_id])) $message = 'Business frozen.';
        else $error = 'Freeze failed.';
    } elseif ($action === 'unfreeze') {
        $stmt = $pdo->prepare("UPDATE businesses SET is_active = 1 WHERE id = ?");
        if ($stmt->execute([$business_id])) $message = 'Business unfrozen.';
        else $error = 'Unfreeze failed.';
    } elseif ($action === 'approve') {
        $stmt = $pdo->prepare("UPDATE businesses SET is_approved = 1 WHERE id = ?");
        if ($stmt->execute([$business_id])) $message = 'Business approved.';
        else $error = 'Approve failed.';
    } elseif ($action === 'reject') {
        $stmt = $pdo->prepare("DELETE FROM businesses WHERE id = ?");
        if ($stmt->execute([$business_id])) $message = 'Business rejected and removed.';
        else $error = 'Reject failed.';
    }
    header('Location: businesses.php');
    exit;
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$plan_filter = isset($_GET['plan_filter']) ? trim($_GET['plan_filter']) : '';

$query = "SELECT *, DATE(created_at) as joined_date FROM businesses WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (name LIKE :search OR email LIKE :search OR address LIKE :search)";
    $params[':search'] = "%$search%";
}
if (!empty($plan_filter)) {
    $query .= " AND subscription_plan = :plan_filter";
    $params[':plan_filter'] = $plan_filter;
}
$query .= " ORDER BY id DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$businesses = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Manage Businesses · Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(145deg, #faf5ff 0%, #f3e8ff 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .app-header {
            background: rgba(255,255,255,0.85);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255,255,255,0.5);
            padding: 0.8rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
        }
        .logo-area { display: flex; align-items: center; gap: 10px; }
        .logo-text { font-weight: 700; font-size: 1.5rem; background: linear-gradient(135deg, #6d28d9, #8b5cf6); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .nav-links { display: flex; gap: 1.2rem; align-items: center; flex-wrap: wrap; }
        .nav-links a { text-decoration: none; font-weight: 500; color: #5b21b6; padding: 0.5rem 0.8rem; border-radius: 40px; transition: all 0.2s; }
        .nav-links a:hover { background: rgba(139,92,246,0.1); color: #6d28d9; }
        .menu-toggle {
            display: none;
            font-size: 1.8rem;
            cursor: pointer;
            background: transparent;
            border: none;
            color: #6d28d9;
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
        h1 { font-size: 2rem; margin-bottom: 1rem; color: #6d28d9; }
        
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
            background: #edb088;
            font-family: 'Inter', sans-serif;
        }
        .search-bar button {
            background: #8b5cf6;
            color: white;
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: 40px;
            cursor: pointer;
        }
        
        .desktop-table {
            width: 100%;
            border-collapse: collapse;
            background: rgba(255,255,255,0.7);
            backdrop-filter: blur(8px);
            border-radius: 30px;
            overflow: hidden;
        }
        .desktop-table th, .desktop-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        .desktop-table th { background: rgba(139,92,246,0.1); color: #6d28d9; font-weight: 600; }
        
        .businesses-grid {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .business-card {
            background: rgba(255,255,255,0.9);
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
        .business-name {
            font-weight: 700;
            font-size: 1.1rem;
            color: #6d28d9;
        }
        .status-badge {
            padding: 0.25rem 1rem;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
        }
        .status-active { background: #22c55e; color: white; }
        .status-frozen { background: #9ca3af; color: white; }
        .status-pending { background: #f97316; color: white; }
        .card-body { display: flex; flex-direction: column; gap: 0.75rem; }
        .info-row { display: flex; flex-wrap: wrap; align-items: baseline; gap: 0.5rem; }
        .info-label { font-size: 0.7rem; font-weight: 600; text-transform: uppercase; color: #7c3aed; min-width: 85px; }
        .info-value { font-size: 0.9rem; color: #1a2639; }
        .card-actions { margin-top: 0.5rem; display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .btn-small {
            background: #8b5cf6;
            color: white;
            border: none;
            padding: 0.3rem 0.8rem;
            border-radius: 30px;
            font-size: 0.75rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-small.red { background: #ef4444; }
        .btn-small.orange { background: #f97316; }
        .btn-small.green { background: #22c55e; }
        
        .message { background: #e8f5e9; color: #6d28d9; padding: 1rem; border-radius: 30px; margin-bottom: 1rem; }
        .error { background: #ffebee; color: #b71c1c; padding: 1rem; border-radius: 30px; margin-bottom: 1rem; }
        .app-footer { background: rgba(255,255,255,0.7); padding: 2rem; text-align: center; margin-top: auto; color: #7c3aed; }
        
        @media (min-width: 769px) {
            .businesses-grid { display: none; }
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
                <div style="font-size: 0.7rem; color: #5b21b6; letter-spacing: 0.5px;">No more Queues</div>
            </div>
        </div>
        <button class="menu-toggle" id="menuToggle"><i class="fa-solid fa-bars"></i></button>
        <nav class="nav-links" id="navLinks">
            <a href="admin-dashboard.php">Dashboard</a>
            <a href="users.php">Users</a>
            <a href="businesses.php" style="background:rgba(139,92,246,0.1);">Businesses</a>
            <a href="admin-logout.php">Logout</a>
        </nav>
    </header>

    <div class="container">
        <h1><i class="fa-regular fa-building"></i> Manage Businesses</h1>
       <form method="GET" action="" class="search-bar">
    <input type="text" name="search" placeholder="Search by name, email, or address..." value="<?= htmlspecialchars($search) ?>">
    <select name="plan_filter" style="padding:0.8rem 1.2rem; border:none; border-radius:40px; background:#0274e5;">
        <option value="">All Plans</option>
        <option value="low" <?= ($_GET['plan_filter'] ?? '') == 'low' ? 'selected' : '' ?>>Low (Green)</option>
        <option value="medium" <?= ($_GET['plan_filter'] ?? '') == 'medium' ? 'selected' : '' ?>>Medium (Orange)</option>
        <option value="high" <?= ($_GET['plan_filter'] ?? '') == 'high' ? 'selected' : '' ?>>High (Red)</option>
    </select>
    <button type="submit"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
    <?php if (!empty($search) || !empty($_GET['plan_filter'])): ?>
        <a href="businesses.php" style="background:#7c3aed; color:white; padding:0.8rem 1.5rem; border-radius:40px; text-decoration:none;">Clear</a>
    <?php endif; ?>
</form>
        
        <?php if ($message): ?><div class="message"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
        
        <?php if (empty($businesses)): ?>
            <p>No businesses found.</p>
        <?php else: ?>
            
            <table class="desktop-table">
                <thead>
                    <tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Address</th><th>Plan</th><th>Joined</th><th>Approved</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($businesses as $b): ?>
                    <tr>
                        <td><?= $b['id'] ?></td>
                        <td><?= htmlspecialchars($b['name']) ?></td>
                        <td><?= htmlspecialchars($b['email']) ?></td>
                        <td><?= htmlspecialchars($b['phone']??'—') ?></td>
                        <td><?= htmlspecialchars($b['address']) ?></td>
						<td data-label="Plan"><span class="status-badge" style="background: <?= $b['subscription_plan'] == 'low' ? '#22c55e' : ($b['subscription_plan'] == 'medium' ? '#f97316' : '#ef4444') ?>;"><?= ucfirst($b['subscription_plan'] ?? 'low') ?></span></td>
                        <td><?= date('d M Y', strtotime($b['created_at'])) ?></td>
                        <td><span class="status-badge <?= $b['is_approved']?'status-active':'status-pending' ?>"><?= $b['is_approved']?'Yes':'Pending' ?></span></td>
                        <td><span class="status-badge <?= ($b['is_active']??1)?'status-active':'status-frozen' ?>"><?= ($b['is_active']??1)?'Active':'Frozen' ?></span></td>
                        <td>
                            <a href="businesses-view.php?id=<?= $b['id'] ?>" class="btn-small" style="background:#8b5cf6;">View</a>
                            <?php if (!$b['is_approved']): ?>
                                <a href="?action=approve&id=<?= $b['id'] ?>" class="btn-small green" onclick="return confirm('Approve?')">Approve</a>
                                <a href="?action=reject&id=<?= $b['id'] ?>" class="btn-small red" onclick="return confirm('Reject?')">Reject</a>
                            <?php endif; ?>
                            <?php if ($b['is_active']): ?>
                                <a href="?action=freeze&id=<?= $b['id'] ?>" class="btn-small orange" onclick="return confirm('Freeze?')">Freeze</a>
                            <?php else: ?>
                                <a href="?action=unfreeze&id=<?= $b['id'] ?>" class="btn-small" onclick="return confirm('Unfreeze?')">Unfreeze</a>
                            <?php endif; ?>
                            <a href="?action=delete&id=<?= $b['id'] ?>" class="btn-small red" onclick="return confirm('Delete?')">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="businesses-grid">
                <?php foreach ($businesses as $b): ?>
                <div class="business-card">
                    <div class="card-header">
                        <span class="business-name"><?= htmlspecialchars($b['name']) ?></span>
                        <span class="status-badge <?= $b['is_approved']?'status-active':'status-pending' ?>"><?= $b['is_approved']?'Approved':'Pending' ?></span>
                    </div>
                    <div class="card-body">
                        <div class="info-row"><span class="info-label">ID</span><span class="info-value"><?= $b['id'] ?></span></div>
                        <div class="info-row"><span class="info-label">Email</span><span class="info-value"><?= htmlspecialchars($b['email']) ?></span></div>
                        <div class="info-row"><span class="info-label">Phone</span><span class="info-value"><?= htmlspecialchars($b['phone']??'—') ?></span></div>
                        <div class="info-row"><span class="info-label">Address</span><span class="info-value"><?= htmlspecialchars($b['address']) ?></span></div>
						<div class="info-row"><span class="info-label">Plan</span><span class="info-value"><span class="status-badge" style="background: <?= $b['subscription_plan'] == 'low' ? '#22c55e' : ($b['subscription_plan'] == 'medium' ? '#f97316' : '#ef4444') ?>;"><?= ucfirst($b['subscription_plan'] ?? 'low') ?></span></span></div>
                        <div class="info-row"><span class="info-label">Joined</span><span class="info-value"><?= date('d M Y', strtotime($b['created_at'])) ?></span></div>
                        <div class="info-row"><span class="info-label">Status</span><span class="status-badge <?= ($b['is_active']??1)?'status-active':'status-frozen' ?>"><?= ($b['is_active']??1)?'Active':'Frozen' ?></span></div>
                    </div>
                    <div class="card-actions">
                        <a href="businesses-view.php?id=<?= $b['id'] ?>" class="btn-small" style="background:#8b5cf6;">View</a>
                        <?php if (!$b['is_approved']): ?>
                            <a href="?action=approve&id=<?= $b['id'] ?>" class="btn-small green" onclick="return confirm('Approve?')">Approve</a>
                            <a href="?action=reject&id=<?= $b['id'] ?>" class="btn-small red" onclick="return confirm('Reject?')">Reject</a>
                        <?php endif; ?>
                        <?php if ($b['is_active']): ?>
                            <a href="?action=freeze&id=<?= $b['id'] ?>" class="btn-small orange" onclick="return confirm('Freeze?')">Freeze</a>
                        <?php else: ?>
                            <a href="?action=unfreeze&id=<?= $b['id'] ?>" class="btn-small" onclick="return confirm('Unfreeze?')">Unfreeze</a>
                        <?php endif; ?>
                        <a href="?action=delete&id=<?= $b['id'] ?>" class="btn-small red" onclick="return confirm('Delete?')">Delete</a>
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