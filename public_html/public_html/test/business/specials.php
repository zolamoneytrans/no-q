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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Specials · No Q</title>
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
            padding: 0.5rem;
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
            border-radius: 40px;
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(255,255,255,0.6);
        }
        h1 { font-size: 1.8rem; margin-bottom: 0.5rem; color: #1e3c72; }
        .form-group { margin-bottom: 1.2rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: #1e3c72; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 30px;
            background: #f0f4f8;
            font-family: 'Inter';
            font-size: 1rem;
        }
        .form-row {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .form-row .form-group { flex: 1; }
        .btn {
            background: #1e3c72;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 40px;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-primary { background: #ff9800; width: 100%; }
        .btn-primary:hover { background: #e68900; }
        .error { background: #ffebee; color: #b71c1c; padding: 1rem; border-radius: 30px; margin-bottom: 1rem; }
        .success { background: #e8f5e9; color: #1e3c72; padding: 1rem; border-radius: 30px; margin-bottom: 1rem; }
        .special-item {
            background: rgba(255,255,255,0.5);
            border-radius: 30px;
            padding: 1rem;
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .special-info h3 { margin-bottom: 0.3rem; color: #1e3c72; }
        .special-info p { color: #2c3e50; font-size: 0.9rem; }
        .discount-badge {
            background: #4caf50;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
        }
        .date-badge {
            background: #ff9800;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            margin-left: 8px;
        }
        .btn-small {
            background: #1e3c72;
            color: white;
            border: none;
            padding: 0.3rem 0.8rem;
            border-radius: 30px;
            font-size: 0.8rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 0.2rem;
        }
        .btn-small.orange { background: #ff9800; }
        .btn-small.red { background: #f44336; }
        .app-footer { background: rgba(255,255,255,0.6); padding: 2rem; text-align: center; margin-top: 2rem; }
        @media (max-width: 600px) {
            .special-item { flex-direction: column; align-items: flex-start; }
            .form-row { flex-direction: column; }
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
            <a href="specials.php" style="background:rgba(42,82,152,0.1);">Specials</a>
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
                <?php foreach ($specials as $s): 
                    $is_expired = strtotime($s['end_date']) < time();
                ?>
                <div class="special-item">
                    <div class="special-info">
                        <h3><?= htmlspecialchars($s['title']) ?></h3>
                        <p><?= nl2br(htmlspecialchars($s['description'])) ?></p>
                        <p>
                            <span class="discount-badge">
                                <?= $s['discount_type'] == 'percentage' ? $s['discount_value'] . '% OFF' : 'R ' . number_format($s['discount_value'], 2) . ' OFF' ?>
                            </span>
                            <span class="date-badge">
                                <?= date('d M', strtotime($s['start_date'])) ?> - <?= date('d M Y', strtotime($s['end_date'])) ?>
                            </span>
                            <?php if ($is_expired): ?>
                                <span style="background:#f44336; color:white; padding:2px 8px; border-radius:15px; font-size:0.7rem; margin-left:8px;">Expired</span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div>
                        <a href="?edit=<?= $s['id'] ?>" class="btn-small orange">Edit</a>
                        <a href="?delete=<?= $s['id'] ?>" class="btn-small red" onclick="return confirm('Delete this special?')">Delete</a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <footer class="app-footer">
        <p>&copy; <?= date('Y'); ?> No Q</p>
    </footer>

    <script>
        document.getElementById('menuToggle').addEventListener('click', function() {
            document.getElementById('navLinks').classList.toggle('show');
        });
    </script>
</body>
</html>
