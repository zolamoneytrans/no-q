<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['business_id'])) {
    header('Location: business-login.php');
    exit;
}

$business_id = $_SESSION['business_id'];
$error = $success = '';

if (isset($_GET['delete'])) {
    $service_id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM services WHERE id = ? AND business_id = ?");
    if ($stmt->execute([$service_id, $business_id])) $success = 'Service deleted.';
    else $error = 'Delete failed.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $service_id = isset($_POST['service_id']) ? (int)$_POST['service_id'] : 0;
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $duration = (int)($_POST['duration'] ?? 0);

    if (empty($name) || $price <= 0) {
        $error = 'Name and valid price required.';
    } else {
        if ($service_id > 0) {
            $stmt = $pdo->prepare("UPDATE services SET name=?, description=?, price=?, duration=? WHERE id=? AND business_id=?");
            if ($stmt->execute([$name, $description, $price, $duration, $service_id, $business_id])) $success = 'Service updated.';
            else $error = 'Update failed.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO services (business_id, name, description, price, duration) VALUES (?,?,?,?,?)");
            if ($stmt->execute([$business_id, $name, $description, $price, $duration])) $success = 'Service added.';
            else $error = 'Add failed.';
        }
    }
}

$stmt = $pdo->prepare("SELECT * FROM services WHERE business_id = ? ORDER BY price");
$stmt->execute([$business_id]);
$services = $stmt->fetchAll();

$edit_service = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    foreach ($services as $s) if ($s['id'] == $edit_id) { $edit_service = $s; break; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="../favicon.svg" />
    <link rel="shortcut icon" href="../favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="../apple-touch-icon.png" />
    <meta name="apple-mobile-web-app-title" content="No Q" />
    <link rel="manifest" href="../site.webmanifest" />
    <title>Manage Services · No Q</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Inter', sans-serif; background: linear-gradient(145deg,#f6f9fc 0%,#e9f1f8 100%); min-height:100vh; display:flex; flex-direction:column; }
        .app-header {
            background: rgba(255,255,255,0.7); backdrop-filter:blur(12px); border-bottom:1px solid rgba(255,255,255,0.5);
            padding:0.8rem 2rem; display:flex; align-items:center; justify-content:space-between;
        }
        .logo-area { display:flex; align-items:center; gap:10px; }
        .logo-icon { background:#1e3c72; color:white; width:40px; height:40px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:1.5rem; }
        .logo-text { font-weight:700; font-size:1.5rem; background:linear-gradient(135deg,#1e3c72,#2a5298); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
        .nav-links { display:flex; gap:1.2rem; }
        .nav-links a { text-decoration:none; color:#2c3e50; }
        .menu-toggle { display:none; font-size:1.8rem; cursor:pointer; color:#1e3c72; background:transparent; border:none; padding:0.5rem; }
        @media (max-width:768px) {
            .menu-toggle { display:block; }
            .nav-links { display:none; width:100%; flex-direction:column; align-items:center; gap:0.5rem; padding:1rem 0; background:rgba(255,255,255,0.9); backdrop-filter:blur(10px); border-radius:30px; margin-top:1rem; }
            .nav-links.show { display:flex; }
            .app-header { padding:0.8rem 1rem; }
            .nav-links a { width:100%; text-align:center; padding:0.8rem; }
        }
        .container { max-width:1000px; margin:2rem auto; padding:0 2rem; flex:1; }
        h2 { font-size:2rem; margin-bottom:1rem; background:linear-gradient(145deg,#1e3c72,#2a5298); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
        .card {
            background:rgba(255,255,255,0.7); backdrop-filter:blur(8px); border-radius:30px; padding:2rem; margin-bottom:2rem;
            border:1px solid rgba(255,255,255,0.6);
        }
        .form-group { margin-bottom:1rem; }
        .form-group label { display:block; margin-bottom:0.3rem; font-weight:500; color:#1e3c72; }
        .form-group input, .form-group textarea { width:100%; padding:0.8rem; border:none; border-radius:20px; background:#f0f4f8; font-family:'Inter'; }
        .form-row { display:flex; gap:1rem; flex-wrap:wrap; }
        .form-row .form-group { flex:1; }
        .btn { background:#1e3c72; color:white; border:none; padding:0.8rem 1.5rem; border-radius:40px; font-weight:600; cursor:pointer; text-decoration:none; display:inline-block; }
        .btn-small { background:#1e3c72; color:white; border:none; padding:0.4rem 1rem; border-radius:30px; font-size:0.9rem; cursor:pointer; text-decoration:none; display:inline-block; }
        .btn-small.red { background:#f44336; }
        .btn-small.orange { background:#ff9800; }
        .service-list { margin-top:2rem; }
        .service-item { display:flex; justify-content:space-between; align-items:center; padding:1rem; border-bottom:1px solid rgba(0,0,0,0.05); }
        .service-info h3 { font-size:1.2rem; }
        .service-info p { color:#2c3e50; font-size:0.9rem; }
        .service-actions { display:flex; gap:0.5rem; }
        .error { color:#b71c1c; background:#ffebee; padding:1rem; border-radius:30px; margin-bottom:1rem; }
        .success { color:#1e3c72; background:#e8f5e9; padding:1rem; border-radius:30px; margin-bottom:1rem; }
        .app-footer { background:rgba(255,255,255,0.6); padding:2rem; text-align:center; }
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
            <a href="../index.php">Home</a>
            <a href="business-dashboard.php">Dashboard</a>
            <a href="business-logout.php">Logout</a>
        </nav>
    </header>

    <div class="container">
        <h2>Manage Services</h2>
        <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

        <div class="card">
            <h3><?= $edit_service ? 'Edit Service' : 'Add New Service' ?></h3>
            <form method="post">
                <?php if ($edit_service): ?><input type="hidden" name="service_id" value="<?= $edit_service['id'] ?>"><?php endif; ?>
                <div class="form-group"><label>Service name *</label><input type="text" name="name" required value="<?= htmlspecialchars($edit_service['name']??'') ?>"></div>
                <div class="form-group"><label>Description</label><textarea name="description" rows="2"><?= htmlspecialchars($edit_service['description']??'') ?></textarea></div>
                <div class="form-row">
                    <div class="form-group"><label>Price (R) *</label><input type="number" step="0.01" min="0" name="price" required value="<?= htmlspecialchars($edit_service['price']??'') ?>"></div>
                    <div class="form-group"><label>Duration (min)</label><input type="number" name="duration" min="0" value="<?= htmlspecialchars($edit_service['duration']??'') ?>"></div>
                </div>
                <button type="submit" class="btn"><?= $edit_service ? 'Update' : 'Add' ?></button>
                <?php if ($edit_service): ?><a href="services.php" class="btn-small" style="background:#777;">Cancel</a><?php endif; ?>
            </form>
        </div>

        <div class="card">
            <h3>Your Services</h3>
            <?php if (empty($services)): ?><p>No services yet.</p>
            <?php else: ?>
                <div class="service-list">
                    <?php foreach ($services as $s): ?>
                    <div class="service-item">
                        <div class="service-info">
                            <h3><?= htmlspecialchars($s['name']) ?></h3>
                            <p><?= htmlspecialchars($s['description'] ?? '') ?></p>
                            <p><strong>R <?= number_format($s['price'],2) ?></strong> <?= $s['duration'] ? " · {$s['duration']} min" : '' ?></p>
                        </div>
                        <div class="service-actions">
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
        <p>&copy; <?= date('Y'); ?> No Q</p>
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
