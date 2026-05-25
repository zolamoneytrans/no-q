<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['business_id'])) {
    header('Location: business-login.php');
    exit;
}

$business_id = $_SESSION['business_id'];
$business_name = $_SESSION['business_name'];

// Fetch business data
$stmt = $pdo->prepare("SELECT * FROM businesses WHERE id = ?");
$stmt->execute([$business_id]);
$business = $stmt->fetch();

// Handle form submission for updating settings
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $region = trim($_POST['region'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $slot_duration = (int)($_POST['slot_duration'] ?? 30);

    if (empty($name)) {
        $error = 'Business name is required.';
    } else {
        $stmt = $pdo->prepare("
            UPDATE businesses 
            SET name = ?, phone = ?, address = ?, region = ?, description = ?, slot_duration = ?
            WHERE id = ?
        ");
        if ($stmt->execute([$name, $phone, $address, $region, $description, $slot_duration, $business_id])) {
            $_SESSION['business_name'] = $name;
            $message = 'Settings updated successfully!';
            // Refresh business data
            $stmt = $pdo->prepare("SELECT * FROM businesses WHERE id = ?");
            $stmt->execute([$business_id]);
            $business = $stmt->fetch();
        } else {
            $error = 'Failed to update settings.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Settings · No Q</title>
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
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .logo-area { display: flex; align-items: center; gap: 10px; }
        .logo-text { font-weight: 700; font-size: 1.5rem; background: linear-gradient(135deg, #1e3c72, #2a5298); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .nav-links { display: flex; gap: 1.2rem; align-items: center; }
        .nav-links a { text-decoration: none; font-weight: 500; color: #2c3e50; padding: 0.5rem 0.8rem; border-radius: 40px; transition: 0.2s; }
        .nav-links a:hover { background: rgba(42,82,152,0.08); }
        .menu-toggle { display: none; font-size: 1.8rem; cursor: pointer; background: transparent; border: none; color: #1e3c72; padding: 0.5rem; }
        
        @media (max-width: 768px) {
            .menu-toggle { display: block; }
            .app-header { padding: 0.8rem 1rem; }
            .nav-links { display: none; width: 100%; flex-direction: column; align-items: stretch; gap: 0.5rem; padding: 1rem; background: rgba(255,255,255,0.98); backdrop-filter: blur(10px); border-radius: 24px; margin-top: 1rem; }
            .nav-links.show { display: flex; }
            .nav-links a { width: 100%; text-align: center; padding: 0.8rem; border-radius: 30px; }
        }
        
        .container { max-width: 800px; margin: 2rem auto; padding: 0 2rem; flex: 1; }
        h1 { font-size: 1.8rem; margin-bottom: 1.5rem; color: #1e3c72; display: flex; align-items: center; gap: 0.5rem; }
        
        .card {
            background: rgba(255,255,255,0.8);
            backdrop-filter: blur(10px);
            border-radius: 32px;
            padding: 2rem;
            border: 1px solid rgba(255,255,255,0.6);
        }
        .form-group { margin-bottom: 1.2rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: #1e3c72; }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 0.8rem 1rem;
            border: none;
            border-radius: 30px;
            background: #f0f4f8;
            font-family: 'Inter', sans-serif;
            font-size: 0.95rem;
        }
        textarea { resize: vertical; min-height: 80px; }
        .btn-primary {
            background: #1e3c72;
            color: white;
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: 40px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
        }
        .btn-primary:hover { background: #2a5298; }
        .message { background: #e8f5e9; color: #1e3c72; padding: 1rem; border-radius: 30px; margin-bottom: 1rem; }
        .error { background: #ffebee; color: #b71c1c; padding: 1rem; border-radius: 30px; margin-bottom: 1rem; }
        .btn-back {
            display: inline-block;
            margin-top: 1rem;
            background: #ff9800;
            color: white;
            text-decoration: none;
            padding: 0.6rem 1.2rem;
            border-radius: 40px;
            font-size: 0.85rem;
        }
        .app-footer {
            background: rgba(255,255,255,0.6);
            padding: 1.5rem;
            text-align: center;
            margin-top: auto;
        }
        @media (max-width: 768px) {
            .container { padding: 0 1rem; margin: 1rem auto; }
            .card { padding: 1.2rem; }
            h1 { font-size: 1.4rem; }
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
            <a href="../index.php">Home</a>
            <a href="business-dashboard.php">Dashboard</a>
            <a href="bookings.php">Bookings</a>
            <a href="services.php">Services</a>
            <a href="withdraw.php">Withdraw</a>
            <a href="bank-details.php">Bank Details</a>
            <a href="scan-qr.php">Scan QR</a>
            <a href="business-settings.php" style="background:rgba(42,82,152,0.1);">Settings</a>
            <a href="business-logout.php">Logout</a>
        </nav>
    </header>

    <div class="container">
        <h1><i class="fa-regular fa-gear"></i> Business Settings</h1>
        
        <div class="card">
            <?php if ($message): ?>
                <div class="message"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <form method="post">
                <div class="form-group">
                    <label>Business Name</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($business['name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="tel" name="phone" value="<?= htmlspecialchars($business['phone'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Address</label>
                    <input type="text" name="address" value="<?= htmlspecialchars($business['address'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Region / City</label>
                    <select name="region">
                        <option value="">Select Region</option>
                        <option value="Esikhawini" <?= ($business['region'] ?? '') == 'Esikhawini' ? 'selected' : '' ?>>Esikhawini</option>
                        <option value="Richards Bay" <?= ($business['region'] ?? '') == 'Richards Bay' ? 'selected' : '' ?>>Richards Bay</option>
                        <option value="Empangeni" <?= ($business['region'] ?? '') == 'Empangeni' ? 'selected' : '' ?>>Empangeni</option>
                        <option value="Durban" <?= ($business['region'] ?? '') == 'Durban' ? 'selected' : '' ?>>Durban</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" placeholder="Tell customers about your car wash..."><?= htmlspecialchars($business['description'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label>Slot Duration (minutes)</label>
                    <input type="number" name="slot_duration" value="<?= $business['slot_duration'] ?? 30 ?>" min="15" max="120" step="5">
                </div>
                <button type="submit" class="btn-primary"><i class="fa-regular fa-floppy-disk"></i> Save Changes</button>
            </form>
            
            <a href="business-dashboard.php" class="btn-back"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>
        </div>
    </div>

    <footer class="app-footer">
        <p>&copy; <?= date('Y'); ?> No Q · Business Settings</p>
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
