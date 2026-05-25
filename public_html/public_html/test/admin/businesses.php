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

// Handle search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$query = "SELECT *, DATE(created_at) as joined_date FROM businesses";
$params = [];

if (!empty($search)) {
    $query .= " WHERE name LIKE :search OR email LIKE :search OR address LIKE :search";
    $params[':search'] = "%$search%";
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
    <link rel="icon" type="image/png" href="../favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="../favicon.svg" />
    <link rel="shortcut icon" href="../favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="../apple-touch-icon.png" />
    <meta name="apple-mobile-web-app-title" content="No Q" />
    <link rel="manifest" href="../site.webmanifest" />
    <title>Manage Businesses · Admin · No Q</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
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
        
        /* Header */
        .app-header {
            background: rgba(255,255,255,0.7);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255,255,255,0.5);
            padding: 0.8rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .logo-area { display: flex; align-items: center; gap: 10px; }
        .logo-icon { background: #1e3c72; color: white; width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        .logo-text { font-weight: 700; font-size: 1.5rem; background: linear-gradient(135deg, #1e3c72, #2a5298); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .nav-links { display: flex; gap: 1.2rem; align-items: center; flex-wrap: wrap; }
        .nav-links a { text-decoration: none; font-weight: 500; color: #2c3e50; padding: 0.5rem 0.8rem; border-radius: 40px; }
        .nav-links a:hover { background: rgba(42,82,152,0.08); }
        .nav-links .btn-outline { border: 1.5px solid #1e3c72; padding: 0.4rem 1.2rem; border-radius: 40px; background: white; font-weight: 600; }
        .nav-links .btn-outline:hover { background: #1e3c72; color: white; }
        
        /* Hamburger */
        .menu-toggle {
            display: none;
            font-size: 1.8rem;
            cursor: pointer;
            color: #1e3c72;
            background: transparent;
            border: none;
            padding: 0.5rem;
        }
        
        /* Container */
        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
            flex: 1;
        }
        h1 {
            font-size: 2rem;
            margin-bottom: 1rem;
            background: linear-gradient(145deg, #1e3c72, #2a5298);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        /* Search Bar */
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
            font-size: 0.95rem;
        }
        .search-bar button {
            background: #1e3c72;
            color: white;
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: 40px;
            cursor: pointer;
            font-weight: 600;
        }
        .search-bar .clear-btn {
            background: #777;
        }
        
        /* Messages */
        .message { background: #e8f5e9; color: #1e3c72; padding: 1rem; border-radius: 30px; margin-bottom: 1rem; }
        .error { background: #ffebee; color: #b71c1c; padding: 1rem; border-radius: 30px; margin-bottom: 1rem; }
        
        /* Table Styles - Responsive */
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
        tr:hover {
            background: rgba(30,60,114,0.02);
        }
        
        /* Status Badges */
        .status-badge {
            padding: 0.25rem 1rem;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
        }
        .status-active { background: #4caf50; color: white; }
        .status-frozen { background: #9e9e9e; color: white; }
        .status-pending { background: #ff9800; color: white; }
        
        /* Buttons */
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
        .btn-small.green { background: #4caf50; }
        
        .app-footer {
            background: rgba(255,255,255,0.6);
            padding: 2rem;
            text-align: center;
            margin-top: auto;
        }
        
        /* Mobile Responsive - Convert table to cards */
        @media (max-width: 768px) {
            .menu-toggle {
                display: block;
            }
            .nav-links {
                display: none;
                width: 100%;
                flex-direction: column;
                align-items: center;
                gap: 0.5rem;
                padding: 1rem;
                background: rgba(255,255,255,0.95);
                backdrop-filter: blur(10px);
                border-radius: 30px;
                margin-top: 1rem;
            }
            .nav-links.show {
                display: flex;
            }
            .app-header {
                padding: 0.8rem 1rem;
            }
            .nav-links a {
                width: 100%;
                text-align: center;
                padding: 0.8rem;
            }
            
            .container {
                padding: 0 1rem;
            }
            h1 {
                font-size: 1.6rem;
            }
            
            /* Table to cards */
            .table-wrapper {
                padding: 0.5rem;
            }
            table, thead, tbody, th, td, tr {
                display: block;
            }
            thead {
                display: none;
            }
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
                flex-wrap: wrap;
                gap: 0.5rem;
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
            .btn-small {
                margin-bottom: 0.3rem;
            }
        }
        
        @media (max-width: 480px) {
            .search-bar {
                flex-direction: column;
            }
            .search-bar button {
                width: 100%;
            }
            td:before {
                min-width: 80px;
                font-size: 0.75rem;
            }
            td {
                font-size: 0.85rem;
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
            <a href="users.php">Users</a>
            <a href="businesses.php" style="background:rgba(42,82,152,0.1);">Businesses</a>
            <a href="bookings.php">Bookings</a>
            <a href="payments.php">Payments</a>
            <a href="withdrawals.php">Withdrawals</a>
            <a href="verify-banks.php">Verify Banks</a>
            <a href="reports.php">Reports</a>
            <a href="admin-logout.php">Logout</a>
        </nav>
    </header>

    <div class="container">
        <h1><i class="fa-regular fa-building"></i> Manage Businesses</h1>
        
        <!-- Search Bar -->
        <form method="GET" action="" class="search-bar">
            <input type="text" name="search" placeholder="Search by name, email, or address..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
            <?php if (!empty($search)): ?>
                <a href="businesses.php" class="clear-btn" style="background:#777; color:white; padding:0.8rem 1.5rem; border-radius:40px; text-decoration:none;">Clear</a>
            <?php endif; ?>
        </form>
        
        <?php if ($message): ?><div class="message"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
        
        <?php if (empty($businesses)): ?>
            <p>No businesses found.</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Address</th>
                            <th>Joined</th>
                            <th>Approved</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($businesses as $b): ?>
                        <tr>
                            <td data-label="ID"><?= $b['id'] ?></td>
                            <td data-label="Name"><?= htmlspecialchars($b['name']) ?></td>
                            <td data-label="Email"><?= htmlspecialchars($b['email']) ?></td>
                            <td data-label="Phone"><?= htmlspecialchars($b['phone']??'—') ?></td>
                            <td data-label="Address"><?= htmlspecialchars($b['address']) ?></td>
                            <td data-label="Joined"><?= date('d M Y', strtotime($b['created_at'])) ?></td>
                            <td data-label="Approved">
                                <span class="status-badge <?= $b['is_approved']?'status-active':'status-pending' ?>">
                                    <?= $b['is_approved']?'Yes':'Pending' ?>
                                </span>
                            </td>
                            <td data-label="Status">
                                <span class="status-badge <?= ($b['is_active']??1)?'status-active':'status-frozen' ?>">
                                    <?= ($b['is_active']??1)?'Active':'Frozen' ?>
                                </span>
                            </td>
                            <td data-label="Actions">
                                <a href="businesses-view.php?id=<?= $b['id'] ?>" class="btn-small" style="background:#2196f3;">View</a>
                                <?php if (!$b['is_approved']): ?>
                                    <a href="?action=approve&id=<?= $b['id'] ?>" class="btn-small green" onclick="return confirm('Approve this business?')">Approve</a>
                                    <a href="?action=reject&id=<?= $b['id'] ?>" class="btn-small red" onclick="return confirm('Reject this business?')">Reject</a>
                                <?php endif; ?>
                                <?php if ($b['is_active']): ?>
                                    <a href="?action=freeze&id=<?= $b['id'] ?>" class="btn-small orange" onclick="return confirm('Freeze this business?')">Freeze</a>
                                <?php else: ?>
                                    <a href="?action=unfreeze&id=<?= $b['id'] ?>" class="btn-small" onclick="return confirm('Unfreeze this business?')">Unfreeze</a>
                                <?php endif; ?>
                                <a href="?action=delete&id=<?= $b['id'] ?>" class="btn-small red" onclick="return confirm('Permanently delete this business?')">Delete</a>
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
        document.addEventListener('DOMContentLoaded', function() {
            const menuToggle = document.getElementById('menuToggle');
            const navLinks = document.getElementById('navLinks');
            if (menuToggle) {
                menuToggle.addEventListener('click', function() {
                    navLinks.classList.toggle('show');
                });
            }
            document.querySelectorAll('.nav-links a').forEach(link => {
                link.addEventListener('click', () => {
                    navLinks.classList.remove('show');
                });
            });
        });
    </script>
</body>
</html>
