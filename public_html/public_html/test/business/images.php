<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['business_id'])) {
    header('Location: business-login.php');
    exit;
}

$business_id = $_SESSION['business_id'];
$upload_dir = '../uploads/business_' . $business_id . '/';
$error = $success = '';

if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    $file = $_FILES['image'];
    $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
    $max_size = 5*1024*1024;

    if ($file['error'] !== UPLOAD_ERR_OK) $error = 'Upload error.';
    elseif (!in_array($file['type'], $allowed)) $error = 'Invalid file type.';
    elseif ($file['size'] > $max_size) $error = 'File too large (max 5MB).';
    else {
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '.' . $ext;
        $destination = $upload_dir . $filename;
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            $relative_path = 'uploads/business_' . $business_id . '/' . $filename;
            $stmt = $pdo->prepare("INSERT INTO business_images (business_id, image_path) VALUES (?, ?)");
            if ($stmt->execute([$business_id, $relative_path])) $success = 'Uploaded.';
            else $error = 'Database error.';
        } else $error = 'Failed to save file.';
    }
}

if (isset($_GET['delete'])) {
    $img_id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("SELECT image_path FROM business_images WHERE id=? AND business_id=?");
    $stmt->execute([$img_id, $business_id]);
    $img = $stmt->fetch();
    if ($img) {
        $file = '../' . $img['image_path'];
        if (file_exists($file)) unlink($file);
        $stmt = $pdo->prepare("DELETE FROM business_images WHERE id=?");
        $stmt->execute([$img_id]);
        $success = 'Deleted.';
    }
}

$stmt = $pdo->prepare("SELECT * FROM business_images WHERE business_id=? ORDER BY created_at DESC");
$stmt->execute([$business_id]);
$images = $stmt->fetchAll();
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
    <title>Manage Images · No Q</title>
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
        h1 { font-size:2rem; margin-bottom:1rem; background:linear-gradient(145deg,#1e3c72,#2a5298); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
        .upload-card {
            background:rgba(255,255,255,0.7); backdrop-filter:blur(8px); border-radius:30px; padding:2rem; margin-bottom:2rem;
        }
        .upload-form { display:flex; gap:1rem; align-items:center; flex-wrap:wrap; }
        .upload-form input[type="file"] { flex:1; padding:0.8rem; background:white; border:none; border-radius:40px; }
        .btn { background:#1e3c72; color:white; border:none; padding:0.8rem 2rem; border-radius:40px; font-weight:600; cursor:pointer; }
        .btn.orange { background:#ff9800; }
        .image-grid {
            display:grid; grid-template-columns:repeat(auto-fill, minmax(200px,1fr)); gap:1.5rem; margin-top:1rem;
        }
        .image-item {
            background:rgba(255,255,255,0.7); backdrop-filter:blur(8px); border-radius:20px; padding:1rem; text-align:center;
        }
        .image-item img { width:100%; height:150px; object-fit:cover; border-radius:15px; margin-bottom:0.5rem; }
        .btn-small { background:#1e3c72; color:white; border:none; padding:0.3rem 0.8rem; border-radius:30px; font-size:0.8rem; cursor:pointer; text-decoration:none; display:inline-block; }
        .btn-small.red { background:#f44336; }
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
        <h1>Manage Images</h1>
        <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

        <div class="upload-card">
            <h3>Upload New Image</h3>
            <form class="upload-form" method="post" enctype="multipart/form-data">
                <input type="file" name="image" accept="image/*" required>
                <button type="submit" class="btn orange">Upload</button>
            </form>
            <p style="margin-top:0.5rem;">Max 5MB. JPG, PNG, GIF, WEBP</p>
        </div>

        <?php if (empty($images)): ?>
            <p>No images yet. Upload your first image.</p>
        <?php else: ?>
            <div class="image-grid">
                <?php foreach ($images as $img): ?>
                <div class="image-item">
                    <img src="../<?= htmlspecialchars($img['image_path']) ?>" alt="Business image">
                    <div class="image-actions">
                        <a href="?delete=<?= $img['id'] ?>" class="btn-small red" onclick="return confirm('Delete?')">Delete</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
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
