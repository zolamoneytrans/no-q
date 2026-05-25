<?php
session_start();
require_once '../db_connect.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if (empty($email) || empty($password)) $error = 'Enter both fields.';
    else {
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ?");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();
        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_name'] = $admin['name'];
            $_SESSION['admin_role'] = $admin['role'];
            header('Location: admin-dashboard.php');
            exit;
        } else $error = 'Invalid credentials.';
    }
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
    <title>Admin Login · No Q</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Inter', sans-serif; background: linear-gradient(145deg, #faf5ff 0%, #f3e8ff 100%); min-height:100vh; display:flex; flex-direction:column; }
        .app-header {
            background: rgba(255,255,255,0.85); backdrop-filter:blur(12px); border-bottom:1px solid rgba(255,255,255,0.5);
            padding:0.8rem 2rem; display:flex; align-items:center; justify-content:space-between;
        }
        .logo-area { display:flex; align-items:center; gap:10px; }
        .logo-text { font-weight:700; font-size:1.5rem; background:linear-gradient(135deg,#6d28d9,#8b5cf6); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
        .nav-links { display:flex; gap:1.2rem; }
        .nav-links a { text-decoration:none; color:#5b21b6; }
        .menu-toggle { display:none; font-size:1.8rem; cursor:pointer; color:#6d28d9; background:transparent; border:none; padding:0.5rem; }
        @media (max-width:768px) {
            .menu-toggle { display:block; }
            .nav-links { display:none; width:100%; flex-direction:column; align-items:center; gap:0.5rem; padding:1rem 0; background:rgba(255,255,255,0.95); backdrop-filter:blur(10px); border-radius:30px; margin-top:1rem; }
            .nav-links.show { display:flex; }
            .app-header { padding:0.8rem 1rem; }
            .nav-links a { width:100%; text-align:center; padding:0.8rem; }
        }
        .login-container { max-width:400px; margin:5rem auto; padding:2rem; flex:1; }
        .login-card {
            background:rgba(255,255,255,0.9); backdrop-filter:blur(10px); border-radius:40px; padding:2.5rem;
            box-shadow:0 20px 40px -12px rgba(109,40,217,0.2); border:1px solid rgba(255,255,255,0.6);
        }
        .login-card h2 { font-size:2rem; margin-bottom:2rem; background:linear-gradient(145deg,#6d28d9,#8b5cf6); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
        .form-group { margin-bottom:1.5rem; position: relative; }
        .form-group input { width:100%; padding:1rem; padding-right: 45px; border:none; border-radius:30px; background:#f3e8ff; font-size:1rem; }
        .form-group .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #888;
            font-size: 1.1rem;
        }
        .btn-primary { width:100%; padding:1rem; background:#8b5cf6; color:white; border:none; border-radius:40px; font-size:1.1rem; font-weight:600; cursor:pointer; transition:0.2s; }
        .btn-primary:hover { background:#6d28d9; transform:translateY(-2px); }
        .error { color:#b91c1c; background:#fee2e2; padding:1rem; border-radius:30px; margin-bottom:1.5rem; }
        .app-footer { background:rgba(255,255,255,0.7); padding:2rem; text-align:center; color:#7c3aed; }
    </style>
</head>
<body>
    <header class="app-header">
        <div class="logo-area">
            <img src="../NoQ.jpg" alt="No Q" style="height: 85px; width: auto;">
            <div>
                <span class="logo-text">No Q</span>
                <div style="font-size: 0.7rem; color: #5b21b6; letter-spacing: 0.5px;">No more Queues</div>
            </div>
        </div>
        <button class="menu-toggle" id="menuToggle"><i class="fa-solid fa-bars"></i></button>
        <nav class="nav-links" id="navLinks">
            <a href="../index.php">Home</a>
        </nav>
    </header>

    <div class="login-container">
        <div class="login-card">
            <h2>Admin Login</h2>
            <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <form method="post">
                <div class="form-group">
                    <input type="email" name="email" placeholder="Email" required value="<?= htmlspecialchars($_POST['email']??'') ?>">
                </div>
                <div class="form-group">
                    <input type="password" name="password" id="password" placeholder="Password" required>
                    <i class="fa-regular fa-eye toggle-password" id="togglePassword"></i>
                </div>
                <button type="submit" class="btn-primary">Log In</button>
            </form>
        </div>
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

        
        const togglePassword = document.getElementById('togglePassword');
        const password = document.getElementById('password');

        togglePassword.addEventListener('click', function() {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    </script>
</body>
</html>