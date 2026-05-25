<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: user-login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$booking_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("
    SELECT b.*, biz.name as business_name, biz.id as business_id, s.name as service_name
    FROM bookings b
    JOIN businesses biz ON b.business_id = biz.id
    LEFT JOIN services s ON b.service_id = s.id
    WHERE b.id = ? AND b.user_id = ? AND b.status IN ('pending', 'confirmed')
");
$stmt->execute([$booking_id, $user_id]);
$booking = $stmt->fetch();
if (!$booking) die("Booking not found or cannot be rescheduled.");

$error = $success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_date = $_POST['booking_date'] ?? '';
    $new_time = $_POST['time_slot'] ?? '';
    if (empty($new_date) || empty($new_time)) {
        $error = 'Please select new date and time.';
    } elseif ($new_date < date('Y-m-d')) {
        $error = 'Date must be in the future.';
    } else {
        // Update booking: set new date/time and status to 'rescheduled'
        $stmt = $pdo->prepare("UPDATE bookings SET booking_date = ?, time_slot = ?, status = 'rescheduled' WHERE id = ?");
        if ($stmt->execute([$new_date, $new_time, $booking_id])) {
            // Get business name and user email for notifications
            $info = $pdo->prepare("SELECT biz.name as biz_name, u.email FROM bookings b JOIN businesses biz ON b.business_id = biz.id JOIN users u ON b.user_id = u.id WHERE b.id = ?");
            $info->execute([$booking_id]);
            $row = $info->fetch();
            if ($row) {
                // In-app notification
                addNotification($pdo, $user_id, "Your booking at {$row['biz_name']} has been rescheduled and is pending business confirmation.", "user-dashboard.php");
                // Email notification
                $subject = "Booking Rescheduled – No Q";
                $body = "<p>Your booking at <strong>{$row['biz_name']}</strong> has been rescheduled.</p>
                         <p><strong>New date:</strong> $new_date</p>
                         <p><strong>New time:</strong> $new_time</p>
                         <p>The business will need to confirm the new time. You will be notified once confirmed.</p>";
                sendEmail($row['email'], $subject, $body);
            }
            $success = 'Booking rescheduled successfully! The business will need to confirm the new time.';
            $booking['booking_date'] = $new_date;
            $booking['time_slot'] = $new_time;
        } else {
            $error = 'Failed to reschedule.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="favicon.svg" />
    <link rel="shortcut icon" href="favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="apple-touch-icon.png" />
    <meta name="apple-mobile-web-app-title" content="No Q" />
    <link rel="manifest" href="site.webmanifest" />
    <title>Reschedule Booking · No Q</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
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
            color: #1e3c72;
            background: transparent;
            border: none;
            padding: 0.5rem;
        }
        @media (max-width: 768px) {
            .menu-toggle {
                display: block;
            }
            .nav-links {
                display: none;
                width: 100%;
                flex-direction: column;
                align-items: center;
                gap: 0.5rem;
                padding: 1rem 0;
                background: rgba(255,255,255,0.9);
                backdrop-filter: blur(10px);
                border-radius: 30px;
                margin-top: 1rem;
            }
            .nav-links.show {
                display: flex;
            }
            .app-header {
                padding: 0.8rem 1rem;
            }
            .nav-links a {
                width: 100%;
                text-align: center;
                padding: 0.8rem;
            }
        }

        .container { max-width: 600px; margin: 2rem auto; padding: 2rem; flex: 1; }
        .card {
            background: rgba(255,255,255,0.8);
            backdrop-filter: blur(10px);
            border-radius: 40px;
            padding: 2rem;
            border: 1px solid rgba(255,255,255,0.6);
        }
        h2 { font-size: 2rem; margin-bottom: 1rem; background: linear-gradient(145deg, #1e3c72, #2a5298); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .booking-detail {
            background: #f0f4f8;
            border-radius: 30px;
            padding: 1.5rem;
            margin: 1.5rem 0;
        }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; color: #1e3c72; }
        .form-group input, .form-group select {
            width: 100%;
            padding: 1rem;
            border: none;
            border-radius: 30px;
            background: #f0f4f8;
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
        }
        .btn-primary {
            width: 100%;
            padding: 1rem;
            background: #1e3c72;
            color: white;
            border: none;
            border-radius: 40px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
        }
        .error { color: #b71c1c; background: #ffebee; padding: 1rem; border-radius: 30px; margin-bottom: 1.5rem; }
        .success { color: #1e3c72; background: #e8f5e9; padding: 1rem; border-radius: 30px; margin-bottom: 1.5rem; }
        .app-footer { background: rgba(255,255,255,0.6); padding: 2rem; text-align: center; }
    </style>
</head>
<body>
    <header class="app-header">
        <div class="logo-area">
            <img src="logo.jpeg" alt="No Q" style="height: 40px; width: auto;">
            <span class="logo-text">CarWash<span style="font-weight:400;">Connect</span></span>
        </div>
        <button class="menu-toggle" id="menuToggle" aria-label="Menu">
            <i class="fa-solid fa-bars"></i>
        </button>
        <nav class="nav-links" id="navLinks">
            <a href="index.php">Home</a>
            <a href="user-dashboard.php">Dashboard</a>
            <a href="logout.php">Logout</a>
        </nav>
    </header>

    <div class="container">
        <div class="card">
            <h2>Reschedule Booking</h2>
            <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <?php if ($success): ?><div class="success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

            <div class="booking-detail">
                <p><strong><?= htmlspecialchars($booking['business_name']) ?></strong></p>
                <p>Current: <?= date('d M Y', strtotime($booking['booking_date'])) ?>, <?= htmlspecialchars($booking['time_slot']) ?></p>
                <p>Code: <?= htmlspecialchars($booking['booking_code']) ?></p>
            </div>

            <form method="post">
                <div class="form-group">
                    <label>New date</label>
                    <input type="date" name="booking_date" required min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d', strtotime($booking['booking_date'])) ?>">
                </div>
                <div class="form-group">
                    <label>New time slot</label>
                    <select name="time_slot" required>
                        <option value="">Choose time</option>
                        <option value="09:00-10:00" <?= $booking['time_slot']=='09:00-10:00'?'selected':'' ?>>09:00 – 10:00</option>
                        <option value="10:00-11:00" <?= $booking['time_slot']=='10:00-11:00'?'selected':'' ?>>10:00 – 11:00</option>
                        <option value="11:00-12:00" <?= $booking['time_slot']=='11:00-12:00'?'selected':'' ?>>11:00 – 12:00</option>
                        <option value="12:00-13:00" <?= $booking['time_slot']=='12:00-13:00'?'selected':'' ?>>12:00 – 13:00</option>
                        <option value="13:00-14:00" <?= $booking['time_slot']=='13:00-14:00'?'selected':'' ?>>13:00 – 14:00</option>
                        <option value="14:00-15:00" <?= $booking['time_slot']=='14:00-15:00'?'selected':'' ?>>14:00 – 15:00</option>
                        <option value="15:00-16:00" <?= $booking['time_slot']=='15:00-16:00'?'selected':'' ?>>15:00 – 16:00</option>
                    </select>
                </div>
                <button type="submit" class="btn-primary">Update Booking</button>
            </form>
            <p style="text-align:center; margin-top:1rem;"><a href="user-dashboard.php">← Back to Dashboard</a></p>
        </div>
    </div>

    <footer class="app-footer">
        <p>&copy; <?= date('Y'); ?> No Q</p>
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
                    if (navLinks) navLinks.classList.remove('show');
                });
            });
        });
    </script>
</body>
</html>
