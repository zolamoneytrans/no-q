<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['business_id'])) {
    header('Location: business-login.php');
    exit;
}

$business_id = $_SESSION['business_id'];

$stmt = $pdo->prepare("SELECT current_congestion, availability_text FROM businesses WHERE id = ?");
$stmt->execute([$business_id]);
$status = $stmt->fetch();

$error = $success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $congestion = $_POST['congestion'] ?? 'low';
    $availability_text = trim($_POST['availability_text'] ?? '');

    $stmt = $pdo->prepare("UPDATE businesses SET current_congestion=?, availability_text=? WHERE id=?");
    if ($stmt->execute([$congestion, $availability_text, $business_id])) {
        $success = 'Status updated.';
        $status = ['current_congestion'=>$congestion, 'availability_text'=>$availability_text];
    } else $error = 'Update failed.';
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
    <title>Update Status · No Q</title>
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
        .container { max-width:600px; margin:2rem auto; padding:0 2rem; flex:1; }
        .card {
            background:rgba(255,255,255,0.8); backdrop-filter:blur(10px); border-radius:40px; padding:2rem;
            border:1px solid rgba(255,255,255,0.6);
        }
        h1 { font-size:2rem; margin-bottom:1rem; background:linear-gradient(145deg,#1e3c72,#2a5298); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
        .form-group { margin-bottom:1.5rem; }
        .form-group label { display:block; margin-bottom:0.5rem; font-weight:500; color:#1e3c72; }
        .form-group select, .form-group input, .form-group textarea {
            width:100%; padding:0.8rem; border:none; border-radius:30px; background:#f0f4f8; font-family:'Inter'; font-size:1rem;
        }
        .form-group textarea { resize:vertical; min-height:80px; }
        .btn-primary { width:100%; padding:1rem; background:#1e3c72; color:white; border:none; border-radius:40px; font-size:1.1rem; font-weight:600; cursor:pointer; }
        .error { color:#b71c1c; background:#ffebee; padding:1rem; border-radius:30px; margin-bottom:1.5rem; }
        .success { color:#1e3c72; background:#e8f5e9; padding:1rem; border-radius:30px; margin-bottom:1.5rem; }
        .app-footer { background:rgba(255,255,255,0.6); padding:2rem; text-align:center; }
    </style>
</head>
<body>
    <header class="app-header">
         <div class="logo-area">
    <img src="/NoQ.jpg" alt="No Q" style="height: 85px; width: auto;">
    <div>
        <span class="logo-text">No Q</span>
        <div style="font-size: 0.7rem; color: #2c3e50; letter-spacing: 0.5px;">No more Queues</div>
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
        <div class="card">
            <h1>Update Status</h1>
            <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <?php if ($success): ?><div class="success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

            <form method="post">
                <div class="form-group">
                    <label>Congestion</label>
                    <select name="congestion">
                        <option value="low" <?= ($status['current_congestion']??'low')=='low'?'selected':'' ?>>Low – No wait</option>
                        <option value="moderate" <?= ($status['current_congestion']??'')=='moderate'?'selected':'' ?>>Moderate – Short wait</option>
                        <option value="high" <?= ($status['current_congestion']??'')=='high'?'selected':'' ?>>High – Busy</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Available slots (describe)</label>
                    <textarea name="availability_text" rows="3" placeholder="e.g., Today: 9am–12pm, 2pm–5pm"><?= htmlspecialchars($status['availability_text']??'') ?></textarea>
                </div>
                <button type="submit" class="btn-primary">Update Status</button>
            </form>
        </div>
    </div>

    <footer class="app-footer">
        <p>&copy; <?= date('Y'); ?> No Q. All Rights Reserved</p>
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
