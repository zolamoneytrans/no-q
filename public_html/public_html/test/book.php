<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: user-login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get business ID from URL
$business_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($business_id == 0) {
    die('No business selected.');
}

// Fetch business details
$stmt = $pdo->prepare("SELECT * FROM businesses WHERE id = ? AND is_approved = 1 AND is_active = 1");
$stmt->execute([$business_id]);
$business = $stmt->fetch();

if (!$business) {
    die('Business not found or not approved.');
}

// Fetch services for this business
$stmt = $pdo->prepare("SELECT * FROM services WHERE business_id = ? ORDER BY price");
$stmt->execute([$business_id]);
$services = $stmt->fetchAll();

// Platform fee percentage (5% as example)
$platform_fee_percent = 5;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $service_id = isset($_POST['service_id']) ? (int)$_POST['service_id'] : 0;
    $booking_date = $_POST['booking_date'] ?? '';
    $time_slot = $_POST['time_slot'] ?? '';
    
    // Get service details
    $stmt = $pdo->prepare("SELECT * FROM services WHERE id = ? AND business_id = ?");
    $stmt->execute([$service_id, $business_id]);
    $service = $stmt->fetch();
    
    if (!$service) {
        $error = 'Please select a valid service.';
    } elseif (empty($booking_date) || empty($time_slot)) {
        $error = 'Please select a date and time slot.';
    } elseif (strtotime($booking_date) < strtotime(date('Y-m-d'))) {
        $error = 'Booking date cannot be in the past.';
    } else {
        // Check if slot is available
        $available_slots = getAvailableSlots($pdo, $business_id, $booking_date);
        if (!isset($available_slots[$time_slot]) || !$available_slots[$time_slot]) {
            $error = 'This time slot is no longer available. Please select another.';
        } else {
            // Generate unique booking code
            $booking_code = strtoupper(substr(md5(uniqid()), 0, 8));
            
            // Calculate total with platform fee
            $amount = $service['price'];
            $platform_fee = ($amount * $platform_fee_percent) / 100;
            $total_amount = $amount + $platform_fee;
            
            // Insert booking
            $stmt = $pdo->prepare("
                INSERT INTO bookings (business_id, user_id, service_id, booking_date, time_slot, total_amount, booking_code, status, payment_status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', NOW())
            ");
            
            if ($stmt->execute([$business_id, $user_id, $service_id, $booking_date, $time_slot, $total_amount, $booking_code])) {
                $booking_id = $pdo->lastInsertId();
                
                // Send notification to business
                sendBusinessBookingNotification($pdo, $booking_id, 'new');
                
                // Send confirmation email to customer
                $subject = "Booking Submitted – No Q";
                $body = "
                    <p>Dear " . htmlspecialchars($_SESSION['user_name']) . ",</p>
                    <p>Your booking has been submitted and is pending confirmation from the business.</p>
                    <p><strong>Business:</strong> " . htmlspecialchars($business['name']) . "<br>
                    <strong>Service:</strong> " . htmlspecialchars($service['name']) . "<br>
                    <strong>Date:</strong> " . date('d M Y', strtotime($booking_date)) . "<br>
                    <strong>Time:</strong> " . $time_slot . "<br>
                    <strong>Service price:</strong> R " . number_format($amount, 2) . "<br>
                    <strong>Platform fee:</strong> R " . number_format($platform_fee, 2) . "<br>
                    <strong>Total amount:</strong> R " . number_format($total_amount, 2) . "<br>
                    <strong>Booking Code:</strong> " . $booking_code . "</p>
                    <p>You will receive an email once the business confirms your booking.</p>
                    <p><a href='https://carwashes.africa/user-dashboard.php'>View your dashboard</a></p>
                ";
                sendEmail($_SESSION['user_email'], $subject, $body);
                
                $success = "Booking submitted! Booking code: <strong>$booking_code</strong>. The business will confirm your booking shortly.";
                $_POST = [];
            } else {
                $error = 'Failed to create booking. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book · No Q</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(145deg, #f6f9fc 0%, #e9f1f8 100%);
            min-height: 100vh;
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
        .nav-links { display: flex; gap: 1.2rem; align-items: center; }
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
        .container { max-width: 600px; margin: 2rem auto; padding: 0 2rem; }
        .card {
            background: rgba(255,255,255,0.8);
            backdrop-filter: blur(10px);
            border-radius: 40px;
            padding: 2rem;
            border: 1px solid rgba(255,255,255,0.6);
            box-shadow: 0 20px 40px -12px rgba(0,20,40,0.2);
        }
        h1 {
            font-size: 2rem;
            margin-bottom: 1rem;
            background: linear-gradient(145deg, #1e3c72, #2a5298);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .form-group { margin-bottom: 1.2rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: #1e3c72; }
        .form-group input, .form-group select {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 30px;
            background: #f0f4f8;
            font-family: 'Inter';
            font-size: 1rem;
        }
        .time-slots-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 0.8rem;
            margin-top: 0.5rem;
        }
        .time-slot-btn {
            padding: 0.8rem;
            border: none;
            border-radius: 30px;
            background: #f0f4f8;
            cursor: pointer;
            transition: 0.2s;
            font-family: 'Inter';
            font-size: 0.9rem;
        }
        .time-slot-btn.selected {
            background: #4caf50;
            color: white;
        }
        .time-slot-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            text-decoration: line-through;
        }
        .btn {
            background: #1e3c72;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 40px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            font-size: 1rem;
        }
        .btn:hover { background: #2a5298; }
        .error { background: #ffebee; color: #b71c1c; padding: 1rem; border-radius: 30px; margin-bottom: 1rem; }
        .success { background: #e8f5e9; color: #1e3c72; padding: 1rem; border-radius: 30px; margin-bottom: 1rem; }
        .business-info { text-align: center; margin-bottom: 1.5rem; }
        .app-footer { background: rgba(255,255,255,0.6); padding: 2rem; text-align: center; margin-top: 2rem; }
    </style>
</head>
<body>
    <header class="app-header">
        <div class="logo-area">
            <img src="logo.jpeg" alt="No Q" style="height: 40px; width: auto;">
            <span class="logo-text">CarWash<span style="font-weight:400;">Connect</span></span>
        </div>
        <button class="menu-toggle" id="menuToggle">
            <i class="fa-solid fa-bars"></i>
        </button>
        <nav class="nav-links" id="navLinks">
            <a href="index.php">Home</a>
            <a href="user-dashboard.php">Dashboard</a>
            <a href="my-bookings.php">My Bookings</a>
            <a href="logout.php">Logout</a>
        </nav>
    </header>

    <div class="container">
        <div class="card">
            <div class="business-info">
                <h1><?= htmlspecialchars($business['name'] ?? '') ?></h1>
                <p><?= htmlspecialchars($business['address'] ?? '') ?></p>
                <p>⭐ <?= number_format($business['rating_avg'] ?? 0, 1) ?></p>
            </div>

            <?php if ($error): ?>
                <div class="error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success"><i class="fa-solid fa-circle-check"></i> <?= $success ?></div>
            <?php endif; ?>

            <form method="post" id="booking-form">
                <div class="form-group">
                    <label>Select Service *</label>
                    <select name="service_id" id="service_id" required>
                        <option value="">-- Select a service --</option>
                        <?php foreach ($services as $s): ?>
                            <option value="<?= $s['id'] ?>" data-price="<?= $s['price'] ?>"><?= htmlspecialchars($s['name'] ?? '') ?> – R <?= number_format($s['price'] ?? 0, 2) ?> (<?= $s['duration'] ?? 0 ?> min)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Booking Date *</label>
                    <input type="date" name="booking_date" id="booking_date" min="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="form-group">
                    <label>Select Time Slot *</label>
                    <div id="time-slots-grid" class="time-slots-grid">
                        <p>Please select a date first to see available time slots.</p>
                    </div>
                    <input type="hidden" name="time_slot" id="selected-time-slot" required>
                </div>

                <button type="submit" class="btn">Confirm Booking</button>
            </form>
        </div>
    </div>

    <footer class="app-footer">
        <p>&copy; <?= date('Y'); ?> No Q</p>
    </footer>

    <script>
        document.getElementById('menuToggle').addEventListener('click', function() {
            document.getElementById('navLinks').classList.toggle('show');
        });

        document.getElementById('booking_date').addEventListener('change', function() {
            var bookingDate = this.value;
            var businessId = <?= $business_id ?>;
            var slotsGrid = document.getElementById('time-slots-grid');
            var selectedInput = document.getElementById('selected-time-slot');
            
            if (!bookingDate) {
                slotsGrid.innerHTML = '<p>Please select a date first to see available time slots.</p>';
                return;
            }
            
            slotsGrid.innerHTML = '<p>Loading available slots...</p>';
            
            fetch('get-available-slots.php?business_id=' + businessId + '&date=' + bookingDate)
                .then(response => response.json())
                .then(data => {
                    if (data.length === 0) {
                        slotsGrid.innerHTML = '<p>No available slots on this day. The business may be closed.</p>';
                        return;
                    }
                    
                    slotsGrid.innerHTML = '';
                    data.forEach(slot => {
                        var button = document.createElement('button');
                        button.type = 'button';
                        button.className = 'time-slot-btn';
                        button.textContent = slot.time;
                        
                        if (!slot.available) {
                            button.disabled = true;
                        } else {
                            button.onclick = function() {
                                document.querySelectorAll('.time-slot-btn').forEach(btn => {
                                    btn.classList.remove('selected');
                                    btn.style.background = '#f0f4f8';
                                    btn.style.color = '#333';
                                });
                                this.classList.add('selected');
                                this.style.background = '#4caf50';
                                this.style.color = 'white';
                                selectedInput.value = slot.time;
                            };
                        }
                        slotsGrid.appendChild(button);
                    });
                })
                .catch(error => {
                    slotsGrid.innerHTML = '<p>Error loading slots. Please try again.</p>';
                    console.error(error);
                });
        });
    </script>
</body>
</html>
