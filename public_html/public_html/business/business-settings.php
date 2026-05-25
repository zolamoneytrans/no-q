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

// Check if upgrade was requested
if (!empty($_POST['upgrade_plan'])) {
    $new_plan = $_POST['upgrade_plan'];
    
    // Validate upgrade is allowed (can't downgrade)
    $current_plan = $business['subscription_plan'];
    $allowed_upgrades = ['low' => 'medium', 'medium' => 'high'];
    
    if (isset($allowed_upgrades[$current_plan]) && $allowed_upgrades[$current_plan] == $new_plan) {
        // Update the plan
        $upgrade_stmt = $pdo->prepare("UPDATE businesses SET subscription_plan = ?, is_hidden = 0, bookings_blocked = 0, upgrade_warning_sent = 0, upgrade_grace_deadline = NULL WHERE id = ?");
        $upgrade_stmt->execute([$new_plan, $business_id]);
        
        // Send confirmation email
        $subject = "Plan Upgrade Confirmation – No Q";
        $body = "<p>Dear {$business['name']},</p><p>Your plan has been upgraded to <strong>" . ucfirst($new_plan) . "</strong>. Your business profile is now visible to customers.</p><p>No Q Team</p>";
        sendEmail($business['email'], $subject, $body);
        
        $message = "Plan upgraded to " . ucfirst($new_plan) . " successfully!";
        
        // Refresh business data
        $stmt = $pdo->prepare("SELECT * FROM businesses WHERE id = ?");
        $stmt->execute([$business_id]);
        $business = $stmt->fetch();
    } else {
        $error = "Invalid upgrade option.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST['upgrade_plan'])) {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $public_email = trim($_POST['public_email'] ?? '');
    $public_phone = trim($_POST['public_phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $region = trim($_POST['region'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $slots_per_hour = isset($_POST['slots_per_hour']) ? (int)$_POST['slots_per_hour'] : 1;

    if (empty($name)) {
        $error = 'Business name is required.';
    } else {
        $stmt = $pdo->prepare("
            UPDATE businesses 
            SET name = ?, phone = ?, public_email = ?, public_phone = ?, address = ?, region = ?, description = ?, slots_per_hour = ?
            WHERE id = ?
        ");
        if ($stmt->execute([$name, $phone, $public_email, $public_phone, $address, $region, $description, $slots_per_hour, $business_id])) {
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
	// Display success/error messages for email/password changes
if (isset($_SESSION['email_success'])) {
    $message = $_SESSION['email_success'];
    unset($_SESSION['email_success']);
}
if (isset($_SESSION['email_error'])) {
    $error = $_SESSION['email_error'];
    unset($_SESSION['email_error']);
}
if (isset($_SESSION['password_success'])) {
    $message = $_SESSION['password_success'];
    unset($_SESSION['password_success']);
}
if (isset($_SESSION['password_error'])) {
    $error = $_SESSION['password_error'];
    unset($_SESSION['password_error']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Business Settings · No Q</title>
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
        
        .container { max-width: 800px; margin: 2rem auto; padding: 0 2rem; flex: 1; }
        h1 { 
            font-size: 1.8rem; 
            margin-bottom: 1.5rem; 
            background: linear-gradient(145deg, var(--purple-primary), var(--orange-primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .card {
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(10px);
            border-radius: 32px;
            padding: 2rem;
            border: 1px solid rgba(106,27,154,0.1);
            box-shadow: 0 20px 40px -12px rgba(106,27,154,0.2);
        }
        .form-group { margin-bottom: 1.2rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--purple-primary); }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 0.8rem 1rem;
            border: none;
            border-radius: 30px;
            background: #f0f4f8;
            font-family: 'Inter', sans-serif;
            font-size: 0.95rem;
            transition: all 0.2s;
        }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
            outline: none;
            box-shadow: 0 0 0 2px rgba(106,27,154,0.2);
            background: white;
        }
        textarea { resize: vertical; min-height: 80px; }
        .btn-primary {
            background: linear-gradient(135deg, var(--purple-primary), var(--purple-dark));
            color: white;
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: 40px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: all 0.2s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(106,27,154,0.3);
        }
        .message { 
            background: #e8f5e9; 
            color: var(--purple-primary); 
            padding: 1rem; 
            border-radius: 30px; 
            margin-bottom: 1rem; 
        }
        .error { 
            background: #ffebee; 
            color: #b71c1c; 
            padding: 1rem; 
            border-radius: 30px; 
            margin-bottom: 1rem; 
        }
        .btn-back {
            display: inline-block;
            margin-top: 1rem;
            background: var(--orange-primary);
            color: white;
            text-decoration: none;
            padding: 0.6rem 1.2rem;
            border-radius: 40px;
            font-size: 0.85rem;
            transition: all 0.2s ease;
        }
        .btn-back:hover {
            transform: translateY(-2px);
            background: var(--orange-dark);
        }
        .app-footer {
            background: rgba(255,255,255,0.6);
            padding: 1.5rem;
            text-align: center;
            margin-top: auto;
            color: var(--purple-primary);
            font-size: 0.85rem;
        }
        .app-footer a { color: var(--purple-primary); text-decoration: none; }
        .app-footer a:hover { color: var(--orange-primary); text-decoration: underline; }
        
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
            <img src="../NoQ.jpg" alt="No Q" style="height: 70px; width: auto;">
            <div>
                <span class="logo-text">No Q</span>
                <div style="font-size: 0.7rem; color: var(--purple-primary); letter-spacing: 0.5px;">No more Queues</div>
            </div>
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
            <a href="business-settings.php" style="background:rgba(106,27,154,0.1);">Settings</a>
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
                    <label>Owner Personal Phone (Hidden from customers)</label>
                    <input type="tel" name="phone" value="<?= htmlspecialchars($business['phone'] ?? '') ?>">
                </div>
                <h3 style="color: var(--purple-primary); margin: 1rem 0 0.5rem;"><i class="fa-solid fa-address-book"></i> Customer-Facing Contact Details</h3>
                <div class="form-group">
                    <label>Business Email *</label>
                    <input type="email" name="public_email" required value="<?= htmlspecialchars($business['public_email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Business Phone *</label>
                    <input type="tel" name="public_phone" required value="<?= htmlspecialchars($business['public_phone'] ?? '') ?>">
                </div>
                <h3 style="color: var(--purple-primary); margin: 1.5rem 0 0.5rem;"><i class="fa-solid fa-map-location-dot"></i> Location & Details</h3>
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
                    <label>Cars per hour (Capacity)</label>
                    <input type="number" name="slots_per_hour" value="<?= htmlspecialchars($business['slots_per_hour'] ?? '1') ?>" min="1" required>
                    <small style="color: #7c3aed; font-size: 0.7rem;">How many cars can you wash simultaneously per hour?</small>
                </div>

				<div class="form-group">
    <label>Upgrade Plan</label>
    <select name="upgrade_plan" id="upgrade_plan" onchange="this.form.submit()">
        <option value="">-- Select to upgrade -- (Current: <?= ucfirst($business['subscription_plan']) ?>)</option>
        <?php if ($business['subscription_plan'] == 'low'): ?>
            <option value="medium">Upgrade to Medium</option>
        <?php endif; ?>
        <?php if ($business['subscription_plan'] == 'medium'): ?>
            <option value="high">Upgrade to High</option>
        <?php endif; ?>
        <?php if ($business['subscription_plan'] == 'high'): ?>
            <option value="" disabled> You're on the highest plan!</option>
        <?php endif; ?>
    </select>
    <small style="color: #7c3aed;">Selecting an option will immediately upgrade your plan.</small>
</div>
                
                <button type="submit" class="btn-primary"><i class="fa-regular fa-floppy-disk"></i> Save Changes</button>
            </form>
			<!-- Change Email Section -->
<div style="margin-top: 2rem; padding-top: 2rem; border-top: 2px solid rgba(106,27,154,0.1);">
    <h3 style="color: var(--purple-primary); margin-bottom: 1rem;"><i class="fa-regular fa-envelope"></i> Change Email Address</h3>
    <form method="post" action="update-email.php" onsubmit="return confirm('Are you sure you want to change your email? You will need to login with the new email.');">
        <div class="form-group">
            <label>Current Email</label>
            <input type="email" value="<?= htmlspecialchars($business['email'] ?? '') ?>" disabled style="background:#e0e0e0;">
        </div>
        <div class="form-group">
            <label>New Email Address</label>
            <input type="email" name="new_email" required placeholder="Enter new email address">
        </div>
        <div class="form-group">
            <label>Confirm New Email</label>
            <input type="email" name="confirm_email" required placeholder="Confirm new email address">
        </div>
        <button type="submit" class="btn-primary" style="background: linear-gradient(135deg, var(--orange-primary), var(--orange-dark));"><i class="fa-regular fa-envelope"></i> Update Email</button>
    </form>
</div>

<!-- Change Password Section -->
<div style="margin-top: 2rem; padding-top: 2rem; border-top: 2px solid rgba(106,27,154,0.1);">
    <h3 style="color: var(--purple-primary); margin-bottom: 1rem;"><i class="fa-solid fa-lock"></i> Change Password</h3>
    <form method="post" action="update-password.php" onsubmit="return validatePassword()">
        <div class="form-group">
            <label>Current Password</label>
            <input type="password" name="current_password" required placeholder="Enter your current password">
        </div>
        <div class="form-group">
            <label>New Password</label>
            <input type="password" name="new_password" id="new_password" required placeholder="Enter new password (min 6 characters)">
        </div>
        <div class="form-group">
            <label>Confirm New Password</label>
            <input type="password" name="confirm_password" id="confirm_password" required placeholder="Confirm new password">
        </div>
        <button type="submit" class="btn-primary" style="background: linear-gradient(135deg, var(--purple-primary), var(--purple-dark));"><i class="fa-solid fa-key"></i> Change Password</button>
    </form>
</div>

<script>
function validatePassword() {
    var newPass = document.getElementById('new_password').value;
    var confirmPass = document.getElementById('confirm_password').value;
    
    if (newPass.length < 6) {
        alert('Password must be at least 6 characters long.');
        return false;
    }
    
    if (newPass !== confirmPass) {
        alert('New password and confirm password do not match.');
        return false;
    }
    
    return true;
}
</script>
            
            <a href="business-dashboard.php" class="btn-back"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>
        </div>
    </div>

    <footer class="app-footer">
        <p>&copy; <?= date('Y'); ?> No Q · Business Settings</p>
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