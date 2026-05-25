<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['business_id'])) {
    header('Location: business-login.php');
    exit;
}

$business_id = $_SESSION['business_id'];

// Handle status updates
if (isset($_GET['action']) && isset($_GET['id'])) {
    $booking_id = (int)$_GET['id'];
    $action = $_GET['action'];
    $new_status = '';
    
    if ($action === 'confirm') $new_status = 'confirmed';
    elseif ($action === 'cancel') $new_status = 'cancelled';
    elseif ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM bookings WHERE id = ? AND business_id = ?");
        $stmt->execute([$booking_id, $business_id]);
        header('Location: bookings.php');
        exit;
    } elseif ($action === 'confirm_reschedule') {
        // Confirm the rescheduled booking
        $stmt = $pdo->prepare("
            UPDATE bookings 
            SET status = 'confirmed', 
                rescheduled_from_date = NULL,
                rescheduled_from_time = NULL,
                rescheduled_at = NULL
            WHERE id = ? AND business_id = ?
        ");
        $stmt->execute([$booking_id, $business_id]);
        
        // Notify user that reschedule is confirmed
        $info = $pdo->prepare("
            SELECT b.user_id, u.email, u.name, biz.name as business_name, b.booking_date, b.time_slot, b.booking_code
            FROM bookings b 
            JOIN users u ON b.user_id = u.id 
            JOIN businesses biz ON b.business_id = biz.id 
            WHERE b.id = ?
        ");
        $info->execute([$booking_id]);
        $row = $info->fetch();
        
        if ($row) {
            $message = "Your rescheduled booking at {$row['business_name']} for {$row['booking_date']} at {$row['time_slot']} has been CONFIRMED!";
            addNotification($pdo, $row['user_id'], $message, "../user-dashboard.php", 'user');
            
            $subject = "Rescheduled Booking Confirmed – No Q";
            $body = "<p>Your rescheduled booking at <strong>{$row['business_name']}</strong> has been confirmed.</p>
                     <p><strong>Date:</strong> {$row['booking_date']}</p>
                     <p><strong>Time:</strong> {$row['time_slot']}</p>
                     <p><strong>Code:</strong> {$row['booking_code']}</p>
                     <p><a href='https://carwashes.africa/user-dashboard.php'>View Dashboard</a></p>";
            sendEmail($row['email'], $subject, $body);
        }
        
        header('Location: bookings.php');
        exit;
    } elseif ($action === 'reject_reschedule') {
        // Reject the reschedule - revert to original date/time
        $stmt = $pdo->prepare("
            UPDATE bookings 
            SET booking_date = rescheduled_from_date,
                time_slot = rescheduled_from_time,
                status = 'confirmed',
                rescheduled_from_date = NULL,
                rescheduled_from_time = NULL,
                rescheduled_at = NULL
            WHERE id = ? AND business_id = ?
        ");
        $stmt->execute([$booking_id, $business_id]);
        
        // Notify user that reschedule was rejected
        $info = $pdo->prepare("
            SELECT b.user_id, u.email, u.name, biz.name as business_name, b.rescheduled_from_date, b.rescheduled_from_time
            FROM bookings b 
            JOIN users u ON b.user_id = u.id 
            JOIN businesses biz ON b.business_id = biz.id 
            WHERE b.id = ?
        ");
        $info->execute([$booking_id]);
        $row = $info->fetch();
        
        if ($row) {
            $message = "Your reschedule request for {$row['business_name']} has been declined. Your original booking remains confirmed for {$row['rescheduled_from_date']} at {$row['rescheduled_from_time']}.";
            addNotification($pdo, $row['user_id'], $message, "../user-dashboard.php", 'user');
            
            $subject = "Reschedule Request Declined – No Q";
            $body = "<p>Your reschedule request for <strong>{$row['business_name']}</strong> has been declined.</p>
                     <p>Your original booking remains confirmed for <strong>{$row['rescheduled_from_date']}</strong> at <strong>{$row['rescheduled_from_time']}</strong>.</p>
                     <p><a href='https://carwashes.africa/user-dashboard.php'>View Dashboard</a></p>";
            sendEmail($row['email'], $subject, $body);
        }
        
        header('Location: bookings.php');
        exit;
    }

    if ($new_status) {
        $stmt = $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ? AND business_id = ?");
        $stmt->execute([$new_status, $booking_id, $business_id]);

        $info = $pdo->prepare("SELECT b.user_id, u.email, biz.name FROM bookings b JOIN users u ON b.user_id = u.id JOIN businesses biz ON b.business_id = biz.id WHERE b.id = ?");
        $info->execute([$booking_id]);
        $row = $info->fetch();
        if ($row) {
            if ($new_status === 'confirmed') {
                $message = "Your booking at {$row['name']} has been confirmed.";
            } else {
                $message = "Your booking at {$row['name']} has been cancelled by the business.";
            }
            addNotification($pdo, $row['user_id'], $message, "../user-dashboard.php");

            if ($new_status === 'confirmed') {
                $subject = "Booking Confirmed – No Q";
                $body = "<p>Your booking at <strong>{$row['name']}</strong> has been confirmed.</p>
                         <p><a href='https://carwashes.africa/user-dashboard.php'>View Dashboard</a></p>";
            } else {
                $subject = "Booking Cancelled – No Q";
                $body = "<p>Your booking at <strong>{$row['name']}</strong> has been cancelled by the business.</p>";
            }
            sendEmail($row['email'], $subject, $body);
        }

        if ($new_status === 'confirmed') {
            sendBusinessBookingNotification($pdo, $booking_id, 'confirmed');
        } elseif ($new_status === 'cancelled') {
            sendBusinessBookingNotification($pdo, $booking_id, 'cancelled_by_business');
        }

        header('Location: bookings.php');
        exit;
    }
}

// Get filter values
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$search_customer = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query with filters
$sql = "
    SELECT b.*, u.name as user_name, u.email, u.phone, s.name as service_name
    FROM bookings b
    LEFT JOIN users u ON b.user_id = u.id
    LEFT JOIN services s ON b.service_id = s.id
    WHERE b.business_id = ?
";
$params = [$business_id];

if (!empty($status_filter)) {
    if ($status_filter == 'rescheduled') {
        $sql .= " AND b.rescheduled_at IS NOT NULL";
    } else {
        $sql .= " AND b.status = ?";
        $params[] = $status_filter;
    }
}
if (!empty($date_from)) {
    $sql .= " AND b.booking_date >= ?";
    $params[] = $date_from;
}
if (!empty($date_to)) {
    $sql .= " AND b.booking_date <= ?";
    $params[] = $date_to;
}
if (!empty($search_customer)) {
    $sql .= " AND (u.name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search_customer%";
    $params[] = "%$search_customer%";
}

$sql .= " ORDER BY b.booking_date DESC, b.time_slot DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

// Calculate stats (including rescheduled)
$total_count = count($bookings);
$pending_count = 0;
$confirmed_count = 0;
$completed_count = 0;
$rescheduled_count = 0;
foreach ($bookings as $b) {
    if ($b['status'] == 'pending') $pending_count++;
    elseif ($b['status'] == 'confirmed') $confirmed_count++;
    elseif ($b['status'] == 'completed') $completed_count++;
    
    // Count rescheduled bookings
    if (!empty($b['rescheduled_at'])) $rescheduled_count++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Manage Bookings · No Q</title>
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
        .menu-toggle { display: none; font-size: 1.8rem; cursor: pointer; background: transparent; border: none; color: var(--purple-primary); padding: 0.5rem; transition: transform 0.2s; }
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
        
        .container { max-width: 1400px; margin: 2rem auto; padding: 0 2rem; flex: 1; }
        h1 { font-size: 1.8rem; margin-bottom: 1.5rem; background: linear-gradient(145deg, var(--purple-primary), var(--orange-primary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; display: flex; align-items: center; gap: 0.5rem; }
        
        .filter-bar {
            background: rgba(106,27,154,0.1);
            backdrop-filter: blur(8px);
            border-radius: 30px;
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: flex-end;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
        }
        .filter-group label {
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6c7a8a;
        }
        .filter-group input, .filter-group select {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 30px;
            background: white;
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem;
            min-width: 140px;
        }
        .btn-filter {
            background: linear-gradient(135deg, var(--purple-primary), var(--purple-dark));
            color: white;
            border: none;
            padding: 0.5rem 1.5rem;
            border-radius: 30px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s;
        }
        .btn-filter:hover {
            transform: translateY(-2px);
            box-shadow: 0 3px 10px rgba(106,27,154,0.3);
        }
        .btn-reset {
            background: var(--orange-primary);
            color: white;
            border: none;
            padding: 0.5rem 1.5rem;
            border-radius: 30px;
            cursor: pointer;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s;
        }
        .btn-reset:hover {
            transform: translateY(-2px);
            background: var(--orange-dark);
        }
        
        @media (max-width: 768px) {
            .filter-bar { flex-direction: column; align-items: stretch; }
            .filter-group input, .filter-group select { width: 100%; }
            .container { padding: 0 1rem; }
        }
        
        .stats-bar {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 2rem;
        }
        .stat-badge {
            background: rgba(255,255,255,0.6);
            backdrop-filter: blur(4px);
            padding: 0.5rem 1rem;
            border-radius: 30px;
            font-size: 0.9rem;
        }
        .stat-badge strong { color: var(--purple-primary); }
        
        .status {
            padding: 0.25rem 1rem;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }
        .status.pending { background: var(--orange-primary); color: white; }
        .status.confirmed { background: #4caf50; color: white; }
        .status.completed { background: #2196f3; color: white; }
        .status.cancelled { background: #f44336; color: white; }
        
        .bookings-grid {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .booking-card {
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(8px);
            border-radius: 24px;
            padding: 1.25rem;
            border: 1px solid rgba(106,27,154,0.1);
            transition: all 0.2s ease;
        }
        
        .booking-card:hover {
            background: rgba(255,255,255,0.95);
            box-shadow: 0 8px 20px rgba(106,27,154,0.1);
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
        
        .booking-code {
            font-family: monospace;
            font-size: 0.9rem;
            font-weight: 700;
            background: rgba(106,27,154,0.1);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            color: var(--purple-primary);
        }
        
        .card-body {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }
        
        .info-row {
            display: flex;
            flex-wrap: wrap;
            align-items: baseline;
            gap: 0.5rem;
        }
        
        .info-label {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6c7a8a;
            min-width: 85px;
        }
        
        .info-value {
            font-size: 0.95rem;
            color: #1a2639;
            font-weight: 500;
        }
        
        .customer-name { font-weight: 700; color: var(--purple-primary); }
        .customer-email { font-size: 0.8rem; color: #6c7a8a; word-break: break-all; }
        
        .card-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.5rem;
            padding-top: 0.75rem;
            border-top: 1px solid rgba(0,0,0,0.05);
        }
        
        .btn-small {
            background: linear-gradient(135deg, var(--purple-primary), var(--purple-dark));
            color: white;
            border: none;
            padding: 0.4rem 1rem;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            transition: 0.2s;
        }
        .btn-small.orange { background: var(--orange-primary); }
        .btn-small.red { background: #f44336; }
        .btn-small:hover { background: var(--purple-dark); transform: translateY(-1px); }
        
        .desktop-table {
            display: none;
            width: 100%;
            border-collapse: collapse;
            background: rgba(255,255,255,0.7);
            backdrop-filter: blur(8px);
            border-radius: 30px;
            overflow: hidden;
        }
        .desktop-table th, .desktop-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        .desktop-table th { background: rgba(106,27,154,0.1); color: var(--purple-primary); font-weight: 600; }
        
        @media (min-width: 992px) {
            .bookings-grid { display: none; }
            .desktop-table { display: table; }
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            background: rgba(255,255,255,0.6);
            border-radius: 30px;
            color: #6c7a8a;
        }
        .empty-state i { font-size: 3rem; margin-bottom: 1rem; opacity: 0.5; color: var(--purple-primary); }
        
        .app-footer {
            background: rgba(255,255,255,0.6);
            padding: 2rem;
            text-align: center;
            margin-top: auto;
            font-size: 0.85rem;
            color: var(--purple-primary);
        }
        .app-footer a { color: var(--purple-primary); text-decoration: none; }
        .app-footer a:hover { color: var(--orange-primary); text-decoration: underline; }
        
        @media (max-width: 768px) {
            .container { margin: 1rem auto; }
            h1 { font-size: 1.4rem; }
            .stats-bar { gap: 0.5rem; }
            .stat-badge { font-size: 0.7rem; padding: 0.3rem 0.6rem; }
        }
    </style>
</head>
<body>
    <header class="app-header">
        <div class="logo-area">
            <img src="../NoQ.jpg" alt="No Q" style="height: 70px; width: auto;">
            <div>
                <span class="logo-text">No Q</span>
                <div style="font-size: 0.7rem; color: var(--purple-primary); letter-spacing: 0.5px;">No more Queues</div>
            </div>
        </div>
        <button class="menu-toggle" id="menuToggle"><i class="fa-solid fa-bars"></i></button>
        <nav class="nav-links" id="navLinks">
            <a href="../index.php"><i class="fa-solid fa-house"></i> Home</a>
            <a href="business-dashboard.php"><i class="fa-solid fa-chart-line"></i> Dashboard</a>
            <a href="bookings.php" style="background:rgba(106,27,154,0.1);"><i class="fa-solid fa-calendar-check"></i> Bookings</a>
            <a href="services.php"><i class="fa-solid fa-broom"></i> Services</a>
            <a href="withdraw.php"><i class="fa-solid fa-money-bill-wave"></i> Withdraw</a>
            <a href="bank-details.php"><i class="fa-solid fa-building-columns"></i> Bank Details</a>
            <a href="scan-qr.php"><i class="fa-solid fa-qrcode"></i> Scan QR</a>
            <a href="business-logout.php"><i class="fa-solid fa-sign-out"></i> Logout</a>
        </nav>
    </header>

    <div class="container">
        <h1><i class="fa-solid fa-calendar-check"></i> Manage Bookings</h1>
        
        <form method="get" class="filter-bar">
            <div class="filter-group">
                <label><i class="fa-solid fa-magnifying-glass"></i> Customer</label>
                <input type="text" name="search" placeholder="Name or email..." value="<?= htmlspecialchars($search_customer) ?>">
            </div>
            <div class="filter-group">
                <label><i class="fa-solid fa-flag"></i> Status</label>
                <select name="status">
                    <option value="">All</option>
                    <option value="pending" <?= $status_filter == 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="confirmed" <?= $status_filter == 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                    <option value="completed" <?= $status_filter == 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="cancelled" <?= $status_filter == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    <option value="rescheduled" <?= $status_filter == 'rescheduled' ? 'selected' : '' ?>>Rescheduled</option>
                </select>
            </div>
            <div class="filter-group">
                <label><i class="fa-regular fa-calendar"></i> From Date</label>
                <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
            </div>
            <div class="filter-group">
                <label><i class="fa-regular fa-calendar"></i> To Date</label>
                <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
            </div>
            <div class="filter-group">
                <button type="submit" class="btn-filter"><i class="fa-solid fa-filter"></i> Apply</button>
            </div>
            <div class="filter-group">
                <a href="bookings.php" class="btn-reset"><i class="fa-solid fa-rotate-left"></i> Reset</a>
            </div>
        </form>
        
        <div class="stats-bar">
            <div class="stat-badge"><i class="fa-solid fa-clock"></i> Total: <strong><?= $total_count ?></strong> bookings</div>
            <div class="stat-badge"><i class="fa-solid fa-hourglass-half"></i> Pending: <strong><?= $pending_count ?></strong></div>
            <div class="stat-badge"><i class="fa-solid fa-check-circle"></i> Confirmed: <strong><?= $confirmed_count ?></strong></div>
            <div class="stat-badge"><i class="fa-solid fa-circle-check"></i> Completed: <strong><?= $completed_count ?></strong></div>
            <div class="stat-badge"><i class="fa-solid fa-arrows-spin"></i> Rescheduled: <strong><?= $rescheduled_count ?></strong></div>
        </div>
        
        <?php if (empty($bookings)): ?>
            <div class="empty-state">
                <i class="fa-solid fa-calendar-xmark"></i>
                <p>No bookings found.</p>
                <p style="font-size:0.85rem; margin-top:0.5rem;">Try changing your filters or check back later.</p>
            </div>
        <?php else: ?>
            
            <table class="desktop-table">
                <thead>
                    <tr><th>Code</th><th>Customer</th><th>Service</th><th>Date</th><th>Time</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $b): ?>
                    <tr>
                        <td><code><?= htmlspecialchars($b['booking_code']) ?></code></td>
                        <td><strong><?= htmlspecialchars($b['user_name']??'Guest') ?></strong></td>
                        <td><?= htmlspecialchars($b['service_name']??'—') ?></td>
                        <td><?= date('d M Y', strtotime($b['booking_date'])) ?></td>
                        <td><?= htmlspecialchars($b['time_slot']) ?></td>
                        <td><span class="status <?= $b['status'] ?>"><?= ucfirst($b['status']) ?></span></td>
                        <td>
                            <?php if ($b['status'] == 'pending' && !empty($b['rescheduled_at'])): ?>
                                <a href="?action=confirm_reschedule&id=<?= $b['id'] ?>" class="btn-small orange" onclick="return confirm('Confirm this rescheduled booking?')">Confirm Reschedule</a>
                                <a href="?action=reject_reschedule&id=<?= $b['id'] ?>" class="btn-small red" onclick="return confirm('Reject this reschedule?')">Reject Reschedule</a>
                            <?php elseif ($b['status'] == 'pending'): ?>
                                <a href="?action=confirm&id=<?= $b['id'] ?>" class="btn-small orange" onclick="return confirm('Confirm this booking?')">Confirm</a>
                                <a href="?action=cancel&id=<?= $b['id'] ?>" class="btn-small red" onclick="return confirm('Cancel this booking?')">Cancel</a>
                            <?php elseif ($b['status'] == 'confirmed'): ?>
                                <a href="?action=cancel&id=<?= $b['id'] ?>" class="btn-small red" onclick="return confirm('Cancel this booking?')">Cancel</a>
                            <?php endif; ?>
                            <a href="booking-details.php?id=<?= $b['id'] ?>" class="btn-small">View</a>
                         </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="bookings-grid">
                <?php foreach ($bookings as $b): ?>
                <div class="booking-card">
                    <div class="card-header">
                        <span class="booking-code"><i class="fa-solid fa-qrcode"></i> <?= htmlspecialchars($b['booking_code']) ?></span>
                        <span class="status <?= $b['status'] ?>"><?= ucfirst($b['status']) ?></span>
                    </div>
                    <div class="card-body">
                        <div class="info-row"><span class="info-label"><i class="fa-regular fa-user"></i> Customer</span><span class="info-value"><span class="customer-name"><?= htmlspecialchars($b['user_name']??'Guest') ?></span></span></div>
                        <div class="info-row"><span class="info-label"><i class="fa-regular fa-broom"></i> Service</span><span class="info-value"><?= htmlspecialchars($b['service_name']??'—') ?></span></div>
                        <div class="info-row"><span class="info-label"><i class="fa-regular fa-calendar"></i> Date</span><span class="info-value"><?= date('d M Y', strtotime($b['booking_date'])) ?> at <?= htmlspecialchars($b['time_slot']) ?></span></div>
                        <?php if (!empty($b['rescheduled_at']) && !empty($b['rescheduled_from_date']) && !empty($b['rescheduled_from_time'])): ?>
                        <div class="info-row">
                            <span class="info-label"><i class="fa-solid fa-arrows-spin"></i> Rescheduled</span>
                            <span class="info-value" style="color:var(--orange-primary);">
                                From <?= date('d M Y', strtotime($b['rescheduled_from_date'])) ?> at <?= htmlspecialchars($b['rescheduled_from_time']) ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-actions">
                        <?php if ($b['status'] == 'pending' && !empty($b['rescheduled_at'])): ?>
                            <a href="?action=confirm_reschedule&id=<?= $b['id'] ?>" class="btn-small orange" onclick="return confirm('Confirm this rescheduled booking?')">Confirm Reschedule</a>
                            <a href="?action=reject_reschedule&id=<?= $b['id'] ?>" class="btn-small red" onclick="return confirm('Reject this reschedule?')">Reject Reschedule</a>
                        <?php elseif ($b['status'] == 'pending'): ?>
                            <a href="?action=confirm&id=<?= $b['id'] ?>" class="btn-small orange" onclick="return confirm('Confirm this booking?')">Confirm</a>
                            <a href="?action=cancel&id=<?= $b['id'] ?>" class="btn-small red" onclick="return confirm('Cancel this booking?')">Cancel</a>
                        <?php elseif ($b['status'] == 'confirmed'): ?>
                            <a href="?action=cancel&id=<?= $b['id'] ?>" class="btn-small red" onclick="return confirm('Cancel this booking?')">Cancel</a>
                        <?php endif; ?>
                        <a href="booking-details.php?id=<?= $b['id'] ?>" class="btn-small">View</a>
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
