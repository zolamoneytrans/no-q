<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin-login.php');
    exit;
}

$business_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch business details
$stmt = $pdo->prepare("SELECT * FROM businesses WHERE id = ?");
$stmt->execute([$business_id]);
$business = $stmt->fetch();

// Get favorite count
$favStmt = $pdo->prepare("SELECT COUNT(*) FROM user_favorites WHERE business_id = ?");
$favStmt->execute([$business_id]);
$favorite_count = $favStmt->fetchColumn();

if (!$business) {
    die("Business not found.");
}

// Fetch services
$services = $pdo->prepare("SELECT * FROM services WHERE business_id = ? ORDER BY price");
$services->execute([$business_id]);
$services = $services->fetchAll();

// Fetch bookings (with user names)
$bookings = $pdo->prepare("
    SELECT b.*, u.name as user_name 
    FROM bookings b 
    LEFT JOIN users u ON b.user_id = u.id 
    WHERE b.business_id = ? 
    ORDER BY b.created_at DESC
");
$bookings->execute([$business_id]);
$bookings = $bookings->fetchAll();

// Fetch ratings received (with user names)
$ratings = $pdo->prepare("
    SELECT r.*, u.name as user_name 
    FROM ratings r 
    JOIN users u ON r.user_id = u.id 
    WHERE r.business_id = ? 
    ORDER BY r.created_at DESC
");
$ratings->execute([$business_id]);
$ratings = $ratings->fetchAll();

// Fetch images
$images = $pdo->prepare("SELECT * FROM business_images WHERE business_id = ?");
$images->execute([$business_id]);
$images = $images->fetchAll();

// Fetch users who favorited this business
$favUsers = $pdo->prepare("
    SELECT u.id, u.name, u.email, f.created_at as favorited_at,
        (SELECT COUNT(*) FROM bookings WHERE user_id = u.id AND business_id = ? AND status = 'completed') as booking_count
    FROM user_favorites f
    JOIN users u ON f.user_id = u.id
    WHERE f.business_id = ?
    ORDER BY f.created_at DESC
");
$favUsers->execute([$business_id, $business_id]);
$favorited_users = $favUsers->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Business Details · Admin</title>
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
        .logo-icon { background: #1e3c72; color: white; width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        .logo-text { font-weight: 700; font-size: 1.5rem; background: linear-gradient(135deg, #1e3c72, #2a5298); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .nav-links { display: flex; gap: 1.2rem; align-items: center; flex-wrap: wrap; }
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
            .nav-links a { width: 100%; text-align: center; padding: 0.8rem; }
        }
        .container { max-width: 1200px; margin: 2rem auto; padding: 0 2rem; flex: 1; }
        .card {
            background: rgba(255,255,255,0.8);
            backdrop-filter: blur(10px);
            border-radius: 40px;
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(255,255,255,0.6);
        }
        h1 { font-size: 2.2rem; margin-bottom: 0.5rem; color: #1e3c72; }
        .section-title {
            font-size: 1.5rem;
            margin: 2rem 0 1rem;
            color: #1e3c72;
            border-bottom: 2px solid rgba(30,60,114,0.1);
            padding-bottom: 0.5rem;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        .info-item { padding: 0.5rem; }
        .info-label { font-weight: 600; color: #1e3c72; }
        .info-value { color: #2c3e50; }
        
        /* Table to cards on mobile */
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; background: rgba(255,255,255,0.5); border-radius: 20px; overflow: hidden; min-width: 600px; }
        th, td { padding: 0.8rem; text-align: left; border-bottom: 1px solid rgba(0,0,0,0.05); }
        th { background: rgba(30,60,114,0.1); color: #1e3c72; font-weight: 600; }
        
        .badge {
            display: inline-block;
            padding: 0.25rem 0.8rem;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .badge-active { background: #4caf50; color: white; }
        .badge-inactive { background: #9e9e9e; color: white; }
        .badge-approved { background: #2196f3; color: white; }
        .badge-pending { background: #ff9800; color: white; }
        .badge-status { background: #e0e0e0; }
        
        .image-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        .image-item img {
            width: 100%;
            height: 120px;
            object-fit: cover;
            border-radius: 15px;
        }
        .btn-back { display: inline-block; margin-top: 1rem; color: #1e3c72; text-decoration: none; }
        .app-footer { background: rgba(255,255,255,0.6); padding: 2rem; text-align: center; margin-top: auto; }
        
        @media (max-width: 768px) {
            .container { padding: 0 1rem; }
            .card { padding: 1.2rem; }
            h1 { font-size: 1.6rem; }
            .section-title { font-size: 1.2rem; }
            .info-grid { grid-template-columns: 1fr; }
            .table-wrapper { overflow-x: auto; }
            .image-grid { grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); }
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
            <a href="businesses.php">Businesses</a>
            <a href="admin-logout.php">Logout</a>
        </nav>
    </header>

    <div class="container">
        <div class="card">
            <h1><?= htmlspecialchars($business['name']) ?></h1>
            <p style="color:#2c3e50;">Business details and activity</p>
            <div class="info-grid">
                <div class="info-item"><span class="info-label">Favorites:</span> <?= $favorite_count ?> customers</div>
                <div class="info-item"><span class="info-label">ID:</span> <?= $business['id'] ?></div>
                <div class="info-item"><span class="info-label">Email:</span> <?= htmlspecialchars($business['email']) ?></div>
                <div class="info-item"><span class="info-label">Phone:</span> <?= htmlspecialchars($business['phone'] ?? '—') ?></div>
                <div class="info-item"><span class="info-label">Address:</span> <?= htmlspecialchars($business['address']) ?></div>
                <div class="info-item"><span class="info-label">Joined:</span> <?= date('d M Y H:i', strtotime($business['created_at'])) ?></div>
                <div class="info-item"><span class="info-label">Region:</span> <?= htmlspecialchars($business['region'] ?? 'Not set') ?></div>
                <div class="info-item">
                    <span class="info-label">Approved:</span> 
                    <span class="badge <?= $business['is_approved'] ? 'badge-approved' : 'badge-pending' ?>">
                        <?= $business['is_approved'] ? 'Yes' : 'Pending' ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">Status:</span> 
                    <span class="badge <?= $business['is_active'] ? 'badge-active' : 'badge-inactive' ?>">
                        <?= $business['is_active'] ? 'Active' : 'Frozen' ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Services -->
        <div class="card">
            <h2 class="section-title"><i class="fa-solid fa-broom"></i> Services</h2>
            <?php if (empty($services)): ?>
                <p>No services listed.</p>
            <?php else: ?>
                <div class="table-wrapper">
                    <table>
                        <thead><tr><th>Name</th><th>Price</th><th>Duration</th></tr></thead>
                        <tbody>
                            <?php foreach ($services as $s): ?>
                            <tr><td><?= htmlspecialchars($s['name']) ?></td><td>R <?= number_format($s['price'],2) ?></td><td><?= $s['duration'] ? $s['duration'].' min' : '—' ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Bookings -->
        <div class="card">
            <h2 class="section-title"><i class="fa-regular fa-calendar-check"></i> Bookings</h2>
            <?php if (empty($bookings)): ?>
                <p>No bookings yet.</p>
            <?php else: ?>
                <div class="table-wrapper">
                    <table>
                        <thead><tr><th>ID</th><th>User</th><th>Date</th><th>Time</th><th>Amount</th><th>Status</th><th>Code</th></tr></thead>
                        <tbody>
                            <?php foreach ($bookings as $b): ?>
                            <tr>
                                <td><?= $b['id'] ?></td>
                                <td><?= htmlspecialchars($b['user_name'] ?? 'Guest') ?></td>
                                <td><?= $b['booking_date'] ?></td>
                                <td><?= $b['time_slot'] ?></td>
                                <td>R <?= number_format($b['total_amount'],2) ?></td>
                                <td><span class="badge badge-status"><?= ucfirst($b['status']) ?></span></td>
                                <td><?= $b['booking_code'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Ratings -->
        <div class="card">
            <h2 class="section-title"><i class="fa-regular fa-star"></i> Ratings Received</h2>
            <?php if (empty($ratings)): ?>
                <p>No ratings yet.</p>
            <?php else: ?>
                <div class="table-wrapper">
                    </table>
                        <thead><tr><th>User</th><th>Rating</th><th>Comment</th><th>Date</th></tr></thead>
                        <tbody>
                            <?php foreach ($ratings as $r): ?>
                            <tr><td><?= htmlspecialchars($r['user_name']) ?></td><td><?= $r['rating'] ?> ⭐</td><td><?= htmlspecialchars($r['comment'] ?? '—') ?></td><td><?= date('d M Y', strtotime($r['created_at'])) ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Favorited Users -->
        <div class="card">
            <h2 class="section-title"><i class="fa-regular fa-heart"></i> Users Who Favorited This Business</h2>
            <?php if (empty($favorited_users)): ?>
                <p>No users have favorited this business yet.</p>
            <?php else: ?>
                <div class="table-wrapper">
                    <table>
                        <thead><tr><th>Name</th><th>Email</th><th>Favorited On</th><th>Bookings</th></tr></thead>
                        <tbody>
                            <?php foreach ($favorited_users as $fu): ?>
                            <tr><td><?= htmlspecialchars($fu['name']) ?></td><td><?= htmlspecialchars($fu['email']) ?></td><td><?= date('d M Y', strtotime($fu['favorited_at'])) ?></td><td><?= $fu['booking_count'] ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Images -->
        <div class="card">
            <h2 class="section-title"><i class="fa-regular fa-image"></i> Images</h2>
            <?php if (empty($images)): ?>
                <p>No images uploaded.</p>
            <?php else: ?>
                <div class="image-grid">
                    <?php foreach ($images as $img): ?>
                    <div class="image-item"><img src="../<?= htmlspecialchars($img['image_path']) ?>" alt="Business image"></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <a href="businesses.php" class="btn-back"><i class="fa-regular fa-arrow-left"></i> Back to Businesses</a>
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
    </script>
</body>
</html>
