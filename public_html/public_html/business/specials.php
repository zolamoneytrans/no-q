<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['business_id'])) {
    header('Location: business-login.php');
    exit;
}

$business_id = $_SESSION['business_id'];
$business_name = $_SESSION['business_name'];
$error = '';
$success = '';

// Handle delete
if (isset($_GET['delete'])) {
    $special_id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM specials WHERE id = ? AND business_id = ?");
    if ($stmt->execute([$special_id, $business_id])) {
        $success = 'Special deleted successfully.';
    } else {
        $error = 'Failed to delete special.';
    }
}

// Handle add/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $special_id = isset($_POST['special_id']) ? (int)$_POST['special_id'] : 0;
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $discount_type = $_POST['discount_type'] ?? 'percentage';
    $discount_value = (float)($_POST['discount_value'] ?? 0);
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    
    if (empty($title) || empty($description) || $discount_value <= 0 || empty($start_date) || empty($end_date)) {
        $error = 'Please fill in all required fields.';
    } elseif ($start_date > $end_date) {
        $error = 'End date must be after start date.';
    } else {
        if ($special_id > 0) {
            $stmt = $pdo->prepare("
                UPDATE specials SET title = ?, description = ?, discount_type = ?, discount_value = ?, start_date = ?, end_date = ? 
                WHERE id = ? AND business_id = ?
            ");
            if ($stmt->execute([$title, $description, $discount_type, $discount_value, $start_date, $end_date, $special_id, $business_id])) {
                $success = 'Special updated successfully.';
            } else {
                $error = 'Update failed.';
            }
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO specials (business_id, title, description, discount_type, discount_value, start_date, end_date) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            if ($stmt->execute([$business_id, $title, $description, $discount_type, $discount_value, $start_date, $end_date])) {
                $success = 'Special added successfully.';
            } else {
                $error = 'Add failed.';
            }
        }
    }
}

// Fetch all specials for this business
$stmt = $pdo->prepare("SELECT * FROM specials WHERE business_id = ? ORDER BY created_at DESC");
$stmt->execute([$business_id]);
$specials = $stmt->fetchAll();

// Get special for editing
$edit_special = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    foreach ($specials as $s) {
        if ($s['id'] == $edit_id) {
            $edit_special = $s;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Manage Specials · No Q</title>
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
        h1 { font-size: 1.8rem; margin-bottom: 0.5rem; background: linear-gradient(145deg, var(--purple-primary), var(--orange-primary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .form-group { margin-bottom: 1.2rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--purple-primary); }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 30px;
            background: #f0f4f8;
            font-family: 'Inter';
            font-size: 1rem;
            transition: all 0.2s;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            box-shadow: 0 0 0 2px rgba(106,27,154,0.2);
            background: white;
        }
        .form-row { display: flex; gap: 1rem; flex-wrap: wrap; }
        .form-row .form-group { flex: 1; }
        .btn { background: linear-gradient(135deg, var(--purple-primary), var(--purple-dark)); color: white; border: none; padding: 12px 24px; border-radius: 40px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 3px 10px rgba(106,27,154,0.3); }
        .btn-primary { background: linear-gradient(135deg, var(--orange-primary), var(--orange-dark)); width: 100%; }
        .error { background: #ffebee; color: #b71c1c; padding: 1rem; border-radius: 30px; margin-bottom: 1rem; }
        .success { background: #e8f5e9; color: var(--purple-primary); padding: 1rem; border-radius: 30px; margin-bottom: 1rem; }
        
        /* Desktop Table */
        .desktop-table {
            width: 100%;
            border-collapse: collapse;
            background: rgba(255,255,255,0.5);
            border-radius: 20px;
            overflow: hidden;
        }
        .desktop-table th, .desktop-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        .desktop-table th { background: rgba(106,27,154,0.1); color: var(--purple-primary); font-weight: 600; }
        
        /* Mobile Cards */
        .specials-grid {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .special-card {
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(8px);
            border-radius: 24px;
            padding: 1.2rem;
            border: 1px solid rgba(106,27,154,0.1);
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        .special-title { font-weight: 700; font-size: 1.1rem; color: var(--purple-primary); }
        .discount-badge { background: #4caf50; color: white; padding: 4px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; display: inline-block; }
        .date-badge { background: var(--orange-primary); color: white; padding: 4px 12px; border-radius: 20px; font-size: 0.7rem; }
        .card-body { display: flex; flex-direction: column; gap: 0.75rem; }
        .info-row { display: flex; flex-wrap: wrap; align-items: baseline; gap: 0.5rem; }
        .info-label { font-size: 0.7rem; font-weight: 600; text-transform: uppercase; color: #6c7a8a; min-width: 85px; }
        .info-value { font-size: 0.9rem; color: #1a2639; }
        .card-actions { margin-top: 0.5rem; display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .btn-small { background: linear-gradient(135deg, var(--purple-primary), var(--purple-dark)); color: white; border: none; padding: 0.3rem 0.8rem; border-radius: 30px; font-size: 0.75rem; cursor: pointer; text-decoration: none; display: inline-block; transition: all 0.2s; }
        .btn-small.orange { background: linear-gradient(135deg, var(--orange-primary), var(--orange-dark)); }
        .btn-small.red { background: #f44336; }
        
        .app-footer { background: rgba(255,255,255,0.6); padding: 2rem; text-align: center; margin-top: 2rem; color: var(--purple-primary); font-size: 0.85rem; }
        .app-footer a { color: var(--purple-primary); text-decoration: none; }
        .app-footer a:hover { color: var(--orange-primary); text-decoration: underline; }
        
        @media (min-width: 769px) {
            .specials-grid { display: none; }
        }
        @media (max-width: 768px) {
            .desktop-table { display: none; }
            .container { padding: 0 1rem; margin: 1rem auto; }
            .card { padding: 1.2rem; }
            h1 { font-size: 1.4rem; }
            .form-row { flex-direction: column; }
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
            <a href="specials.php" style="background:rgba(106,27,154,0.1);">Specials</a>
            <a href="business-logout.php">Logout</a>
        </nav>
    </header>

    <div class="container">
        <div class="card">
            <h1><i class="fa-regular fa-tag"></i> Manage Specials & Promotions</h1>
            <p>Create special offers to attract more customers.</p>
            
            <?php if ($error): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <form method="post">
                <?php if ($edit_special): ?>
                    <input type="hidden" name="special_id" value="<?= $edit_special['id'] ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label>Offer Title *</label>
                    <input type="text" name="title" placeholder="e.g., Winter Special, Student Discount, 2-for-1" required value="<?= htmlspecialchars($edit_special['title'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label>Description *</label>
                    <textarea name="description" rows="3" placeholder="Describe your special offer..." required><?= htmlspecialchars($edit_special['description'] ?? '') ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Discount Type</label>
                        <select name="discount_type">
                            <option value="percentage" <?= (($edit_special['discount_type'] ?? 'percentage') == 'percentage') ? 'selected' : '' ?>>Percentage (%)</option>
                            <option value="fixed" <?= (($edit_special['discount_type'] ?? '') == 'fixed') ? 'selected' : '' ?>>Fixed Amount (R)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Discount Value *</label>
                        <input type="number" name="discount_value" step="0.01" min="0" placeholder="e.g., 20 or 50.00" required value="<?= htmlspecialchars($edit_special['discount_value'] ?? '') ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Start Date *</label>
                        <input type="date" name="start_date" required value="<?= htmlspecialchars($edit_special['start_date'] ?? date('Y-m-d')) ?>">
                    </div>
                    <div class="form-group">
                        <label>End Date *</label>
                        <input type="date" name="end_date" required value="<?= htmlspecialchars($edit_special['end_date'] ?? date('Y-m-d', strtotime('+30 days'))) ?>">
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary"><?= $edit_special ? 'Update Special' : 'Add Special' ?></button>
                <?php if ($edit_special): ?>
                    <a href="specials.php" class="btn" style="background:#777; margin-top: 0.5rem; display: inline-block;">Cancel Edit</a>
                <?php endif; ?>
            </form>
        </div>
        
        <div class="card">
            <h2><i class="fa-regular fa-clock"></i> Your Specials</h2>
            <?php if (empty($specials)): ?>
                <p>No specials created yet.</p>
            <?php else: ?>
                <!-- Desktop Table -->
                <table class="desktop-table">
                    <thead>
                        <tr><th>Title</th><th>Description</th><th>Discount</th><th>Valid Period</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($specials as $s): 
                            $is_expired = strtotime($s['end_date']) < time();
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($s['title']) ?></td>
                            <td><?= htmlspecialchars(substr($s['description'], 0, 50)) ?>...</td>
                            <td><?= $s['discount_type'] == 'percentage' ? $s['discount_value'] . '% OFF' : 'R ' . number_format($s['discount_value'], 2) . ' OFF' ?></td>
                            <td><?= date('d M Y', strtotime($s['start_date'])) ?> - <?= date('d M Y', strtotime($s['end_date'])) ?><?= $is_expired ? ' (Expired)' : '' ?></td>
                            <td>
                                <a href="?edit=<?= $s['id'] ?>" class="btn-small orange">Edit</a>
                                <a href="?delete=<?= $s['id'] ?>" class="btn-small red" onclick="return confirm('Delete?')">Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Mobile Cards -->
                <div class="specials-grid">
                    <?php foreach ($specials as $s): 
                        $is_expired = strtotime($s['end_date']) < time();
                    ?>
                    <div class="special-card">
                        <div class="card-header">
                            <span class="special-title"><?= htmlspecialchars($s['title']) ?></span>
                            <span class="discount-badge"><?= $s['discount_type'] == 'percentage' ? $s['discount_value'] . '% OFF' : 'R ' . number_format($s['discount_value'], 2) . ' OFF' ?></span>
                        </div>
                        <div class="card-body">
                            <div class="info-row"><span class="info-label">Description</span><span class="info-value"><?= htmlspecialchars(substr($s['description'], 0, 80)) ?>...</span></div>
                            <div class="info-row"><span class="info-label">Valid</span><span class="info-value"><?= date('d M Y', strtotime($s['start_date'])) ?> - <?= date('d M Y', strtotime($s['end_date'])) ?><?= $is_expired ? ' (Expired)' : '' ?></span></div>
                        </div>
                        <div class="card-actions">
                            <a href="?edit=<?= $s['id'] ?>" class="btn-small orange">Edit</a>
                            <a href="?delete=<?= $s['id'] ?>" class="btn-small red" onclick="return confirm('Delete?')">Delete</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <footer class="app-footer">
        <p>&copy; <?= date('Y'); ?> No Q. All Rights Reserved</p>
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
    </script>
</body>
</html>