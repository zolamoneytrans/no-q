<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
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
        // Store old values
        $old_date = $booking['booking_date'];
        $old_time = $booking['time_slot'];
        
        $stmt = $pdo->prepare("UPDATE bookings SET booking_date = ?, time_slot = ?, status = 'pending', rescheduled_at = NOW(), rescheduled_from_date = ?, rescheduled_from_time = ? WHERE id = ?");
        if ($stmt->execute([$new_date, $new_time, $old_date, $old_time, $booking_id])) {
            $info = $pdo->prepare("SELECT biz.name as biz_name, u.email FROM bookings b JOIN businesses biz ON b.business_id = biz.id JOIN users u ON b.user_id = u.id WHERE b.id = ?");
            $info->execute([$booking_id]);
            $row = $info->fetch();
            if ($row) {
                addNotification($pdo, $user_id, "Your booking at {$row['biz_name']} has been rescheduled and is pending business confirmation.", "user-dashboard.php");
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Reschedule Booking · No Q</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Purple, Orange & White Theme */
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
        .nav-links { display: flex; gap: 1.2rem; align-items: center; }
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
            color: var(--purple-primary);
            background: transparent;
            border: none;
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

        .container { max-width: 600px; margin: 2rem auto; padding: 0 2rem; flex: 1; }
        .card {
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(10px);
            border-radius: 40px;
            padding: 2rem;
            border: 1px solid rgba(106,27,154,0.1);
            box-shadow: 0 20px 40px -12px rgba(106,27,154,0.2);
        }
        h2 { 
            font-size: 2rem; 
            margin-bottom: 1rem; 
            background: linear-gradient(145deg, var(--purple-primary), var(--orange-primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .booking-detail {
            background: rgba(106,27,154,0.05);
            border-radius: 30px;
            padding: 1.5rem;
            margin: 1.5rem 0;
        }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; color: var(--purple-primary); }
        .form-group input, .form-group select {
            width: 100%;
            padding: 1rem;
            border: none;
            border-radius: 30px;
            background: #f0f4f8;
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
            transition: all 0.2s;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none;
            box-shadow: 0 0 0 2px rgba(106,27,154,0.2);
            background: white;
        }
        .btn-primary {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, var(--purple-primary), var(--purple-dark));
            color: white;
            border: none;
            border-radius: 40px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(106,27,154,0.3);
        }
        .error { 
            color: #b71c1c; 
            background: #ffebee; 
            padding: 1rem; 
            border-radius: 30px; 
            margin-bottom: 1.5rem; 
        }
        .success { 
            color: var(--purple-primary); 
            background: #e8f5e9; 
            padding: 1rem; 
            border-radius: 30px; 
            margin-bottom: 1.5rem; 
        }
        .back-link {
            text-align: center;
            margin-top: 1rem;
        }
        .back-link a {
            color: var(--purple-primary);
            text-decoration: none;
            transition: color 0.2s;
        }
        .back-link a:hover {
            color: var(--orange-primary);
            text-decoration: underline;
        }
        .app-footer { 
            background: rgba(255,255,255,0.6); 
            padding: 2rem; 
            text-align: center; 
            margin-top: auto;
            color: var(--purple-primary);
            font-size: 0.85rem;
        }
        .app-footer a { color: var(--purple-primary); text-decoration: none; }
        .app-footer a:hover { color: var(--orange-primary); text-decoration: underline; }
        
        @media (max-width: 768px) {
            .container { margin: 1rem auto; padding: 0 1rem; }
            .card { padding: 1.2rem; }
            h2 { font-size: 1.5rem; }
        }
    </style>
</head>
<body>
    <header class="app-header">
        <div class="logo-area">
            <img src="NoQ.jpg" alt="No Q" style="height: 85px; width: auto;">
            <div>
                <span class="logo-text">No Q</span>
                <div style="font-size: 0.7rem; color: var(--purple-primary); letter-spacing: 0.5px;">No more Queues</div>
            </div>
        </div>
        <button class="menu-toggle" id="menuToggle"><i class="fa-solid fa-bars"></i></button>
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
            <div class="back-link">
                <a href="user-dashboard.php">← Back to Dashboard</a>
            </div>
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
    </script>
</body>
</html>
