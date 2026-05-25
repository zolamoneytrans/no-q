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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Manage Services · No Q</title>
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
        h2 { font-size: 2rem; margin-bottom: 1rem; background: linear-gradient(145deg, var(--purple-primary), var(--orange-primary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .card {
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(10px);
            border-radius: 40px;
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(106,27,154,0.1);
            box-shadow: 0 20px 40px -12px rgba(106,27,154,0.2);
        }
        h3 { color: var(--purple-primary); margin-bottom: 1rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.3rem; font-weight: 500; color: var(--purple-primary); }
        .form-group input, .form-group textarea { width: 100%; padding: 0.8rem; border: none; border-radius: 20px; background: #f0f4f8; font-family: 'Inter'; transition: all 0.2s; }
        .form-group input:focus, .form-group textarea:focus { outline: none; box-shadow: 0 0 0 2px rgba(106,27,154,0.2); background: white; }
        .form-row { display: flex; gap: 1rem; flex-wrap: wrap; }
        .form-row .form-group { flex: 1; }
        .btn { background: linear-gradient(135deg, var(--purple-primary), var(--purple-dark)); color: white; border: none; padding: 0.8rem 1.5rem; border-radius: 40px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; transition: all 0.2s; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 3px 10px rgba(106,27,154,0.3); }
        .btn-small { background: linear-gradient(135deg, var(--purple-primary), var(--purple-dark)); color: white; border: none; padding: 0.4rem 1rem; border-radius: 30px; font-size: 0.9rem; cursor: pointer; text-decoration: none; display: inline-block; transition: all 0.2s; }
        .btn-small.red { background: #f44336; }
        .btn-small.orange { background: linear-gradient(135deg, var(--orange-primary), var(--orange-dark)); }
        .error { color: #b71c1c; background: #ffebee; padding: 1rem; border-radius: 30px; margin-bottom: 1rem; }
        .success { color: var(--purple-primary); background: #e8f5e9; padding: 1rem; border-radius: 30px; margin-bottom: 1rem; }
        .app-footer { background: rgba(255,255,255,0.6); padding: 2rem; text-align: center; margin-top: auto; color: var(--purple-primary); font-size: 0.85rem; }
        .app-footer a { color: var(--purple-primary); text-decoration: none; }
        .app-footer a:hover { color: var(--orange-primary); text-decoration: underline; }
        
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
        .services-grid {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .service-card {
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
        .service-name { font-weight: 700; font-size: 1.1rem; color: var(--purple-primary); }
        .service-price { font-weight: 700; color: var(--purple-primary); }
        .card-body { display: flex; flex-direction: column; gap: 0.75rem; }
        .info-row { display: flex; flex-wrap: wrap; align-items: baseline; gap: 0.5rem; }
        .info-label { font-size: 0.7rem; font-weight: 600; text-transform: uppercase; color: #6c7a8a; min-width: 85px; }
        .info-value { font-size: 0.9rem; color: #1a2639; }
        .card-actions { margin-top: 0.5rem; display: flex; gap: 0.5rem; flex-wrap: wrap; }
        
        @media (min-width: 769px) {
            .services-grid { display: none; }
        }
        @media (max-width: 768px) {
            .desktop-table { display: none; }
            .container { padding: 0 1rem; margin: 1rem auto; }
            .card { padding: 1.2rem; }
            h2 { font-size: 1.5rem; }
            .form-row { flex-direction: column; }
        }
    </style>
</head>
<body>
    <header class="app-header">
        <div class="logo-area">
            <img src="/NoQ.jpg" alt="No Q" style="height: 70px; width: auto;">
            <div>
                <span class="logo-text">No Q</span>
                <div style="font-size: 0.7rem; color: var(--purple-primary); letter-spacing: 0.5px;">No more Queues</div>
            </div>
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
                <!-- Desktop Table -->
                <table class="desktop-table">
                    <thead><tr><th>Name</th><th>Description</th><th>Price</th><th>Duration</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($services as $s): ?>
                        <tr>
                            <td><?= htmlspecialchars($s['name']) ?></td>
                            <td><?= htmlspecialchars(substr($s['description'] ?? '', 0, 50)) ?>...</td>
                            <td>R <?= number_format($s['price'],2) ?></td>
                            <td><?= $s['duration'] ? $s['duration'].' min' : '—' ?></td>
                            <td>
                                <a href="?edit=<?= $s['id'] ?>" class="btn-small orange">Edit</a>
                                <a href="?delete=<?= $s['id'] ?>" class="btn-small red" onclick="return confirm('Delete?')">Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Mobile Cards -->
                <div class="services-grid">
                    <?php foreach ($services as $s): ?>
                    <div class="service-card">
                        <div class="card-header">
                            <span class="service-name"><?= htmlspecialchars($s['name']) ?></span>
                            <span class="service-price">R <?= number_format($s['price'],2) ?></span>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($s['description'])): ?>
                            <div class="info-row"><span class="info-label">Description</span><span class="info-value"><?= htmlspecialchars(substr($s['description'], 0, 80)) ?>...</span></div>
                            <?php endif; ?>
                            <?php if ($s['duration']): ?>
                            <div class="info-row"><span class="info-label">Duration</span><span class="info-value"><?= $s['duration'] ?> min</span></div>
                            <?php endif; ?>
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
    </script>
</body>
</html>