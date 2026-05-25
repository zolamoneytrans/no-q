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

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_image'])) {
    $file = $_FILES['profile_image'];
    $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
    $max_size = 5*1024*1024;

    if ($file['error'] !== UPLOAD_ERR_OK) $error = 'Upload error.';
    elseif (!in_array($file['type'], $allowed)) $error = 'Invalid file type.';
    elseif ($file['size'] > $max_size) $error = 'File too large (max 5MB).';
    else {
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'profile_' . uniqid() . '.' . $ext;
        $destination = $upload_dir . $filename;
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            $relative_path = 'uploads/business_' . $business_id . '/' . $filename;
            $stmt = $pdo->prepare("UPDATE businesses SET logo_url = ? WHERE id = ?");
            if ($stmt->execute([$relative_path, $business_id])) $success = 'Profile picture updated!';
            else $error = 'Database error.';
        } else $error = 'Failed to save file.';
    }
}

// Handle gallery image upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['gallery_image'])) {
    $file = $_FILES['gallery_image'];
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
            if ($stmt->execute([$business_id, $relative_path])) $success = 'Gallery image uploaded.';
            else $error = 'Database error.';
        } else $error = 'Failed to save file.';
    }
}

// Delete gallery image
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
        $success = 'Image deleted.';
    }
}

// Get current business data
$stmt = $pdo->prepare("SELECT logo_url FROM businesses WHERE id = ?");
$stmt->execute([$business_id]);
$business_data = $stmt->fetch();
$current_logo = $business_data['logo_url'] ?? null;

// Get gallery images
$stmt = $pdo->prepare("SELECT * FROM business_images WHERE business_id=? ORDER BY created_at DESC");
$stmt->execute([$business_id]);
$images = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Manage Images · No Q</title>
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
        h1 { font-size: 2rem; margin-bottom: 1rem; background: linear-gradient(145deg, var(--purple-primary), var(--orange-primary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        h2 { font-size: 1.4rem; margin: 1.5rem 0 1rem; color: var(--purple-primary); }
        .upload-card {
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(10px);
            border-radius: 40px;
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(106,27,154,0.1);
            box-shadow: 0 20px 40px -12px rgba(106,27,154,0.2);
        }
        .profile-preview {
            display: flex;
            align-items: center;
            gap: 2rem;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
        }
        .current-logo {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            background: rgba(106,27,154,0.1);
            border: 3px solid var(--purple-primary);
        }
        .upload-form { display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; }
        .upload-form input[type="file"] { flex: 1; padding: 0.8rem; background: white; border: none; border-radius: 40px; }
        .btn { background: linear-gradient(135deg, var(--purple-primary), var(--purple-dark)); color: white; border: none; padding: 0.8rem 2rem; border-radius: 40px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 3px 10px rgba(106,27,154,0.3); }
        .btn.orange { background: linear-gradient(135deg, var(--orange-primary), var(--orange-dark)); }
        .image-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }
        .image-item {
            background: rgba(255,255,255,0.8);
            backdrop-filter: blur(8px);
            border-radius: 20px;
            padding: 1rem;
            text-align: center;
            border: 1px solid rgba(106,27,154,0.1);
        }
        .image-item img { width: 100%; height: 150px; object-fit: cover; border-radius: 15px; margin-bottom: 0.5rem; }
        .btn-small { background: linear-gradient(135deg, var(--purple-primary), var(--purple-dark)); color: white; border: none; padding: 0.3rem 0.8rem; border-radius: 30px; font-size: 0.8rem; cursor: pointer; text-decoration: none; display: inline-block; transition: all 0.2s; }
        .btn-small.red { background: #f44336; }
        .error { color: #b71c1c; background: #ffebee; padding: 1rem; border-radius: 30px; margin-bottom: 1rem; }
        .success { color: var(--purple-primary); background: #e8f5e9; padding: 1rem; border-radius: 30px; margin-bottom: 1rem; }
        .app-footer { background: rgba(255,255,255,0.6); padding: 2rem; text-align: center; margin-top: auto; color: var(--purple-primary); font-size: 0.85rem; }
        .app-footer a { color: var(--purple-primary); text-decoration: none; }
        .app-footer a:hover { color: var(--orange-primary); text-decoration: underline; }
        hr { margin: 2rem 0; border: none; height: 1px; background: rgba(0,0,0,0.1); }
        
        @media (max-width: 768px) {
            .container { padding: 0 1rem; margin: 1rem auto; }
            .upload-card { padding: 1.2rem; }
            h1 { font-size: 1.6rem; }
            .profile-preview { flex-direction: column; text-align: center; gap: 1rem; }
            .upload-form { flex-direction: column; }
            .upload-form input[type="file"] { width: 100%; }
            .upload-form button { width: 100%; }
            .image-grid { grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 1rem; }
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
        <h1>Manage Images</h1>
        <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

        <!-- PROFILE PICTURE SECTION -->
        <div class="upload-card">
            <h2>Profile Picture</h2>
            <div class="profile-preview">
                <?php if ($current_logo && file_exists('../' . $current_logo)): ?>
                    <img src="../<?= htmlspecialchars($current_logo) ?>" class="current-logo" alt="Profile">
                <?php else: ?>
                    <div class="current-logo" style="display: flex; align-items: center; justify-content: center; background: rgba(106,27,154,0.1);">
                        <i class="fa-regular fa-building" style="font-size: 3rem; color: var(--purple-primary);"></i>
                    </div>
                <?php endif; ?>
                <div style="flex:1;">
                    <p style="margin-bottom: 0.5rem;">This logo appears on your business card in search results and on your dashboard.</p>
                    <form method="post" enctype="multipart/form-data">
                        <div class="upload-form">
                            <input type="file" name="profile_image" accept="image/*" required>
                            <button type="submit" class="btn orange">Upload Profile Picture</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <hr>

        <!-- GALLERY IMAGES SECTION -->
        <div class="upload-card">
            <h2>Gallery Images</h2>
            <form method="post" enctype="multipart/form-data">
                <div class="upload-form">
                    <input type="file" name="gallery_image" accept="image/*" required>
                    <button type="submit" class="btn orange">Upload Gallery Image</button>
                </div>
            </form>
            <p style="margin-top:0.5rem;">Max 5MB each. JPG, PNG, GIF, WEBP</p>
        </div>

        <?php if (empty($images)): ?>
            <p>No gallery images yet. Upload your first image.</p>
        <?php else: ?>
            <h2>Your Gallery</h2>
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