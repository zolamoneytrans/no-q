<?php
session_start();
require_once '../db_connect.php';
$pageTitle = 'Business Signup';
$baseUrl = '..';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $public_email = trim($_POST['public_email'] ?? '');
    $public_phone = trim($_POST['public_phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = $_POST['city'] ?? '';
    $other_city = trim($_POST['other_city'] ?? '');
    $region = $_POST['region'] ?? '';
    $other_region = trim($_POST['other_region'] ?? '');
    $business_type = $_POST['business_type'] ?? '';
    $password = $_POST['password'] ?? '';

    // Handle "Other" for region
    if ($region === 'Other' && !empty($other_region)) {
        $region = $other_region;
    }

    // Operating hours
    $monday_open = $_POST['monday_open'] ?? null;
    $monday_close = $_POST['monday_close'] ?? null;
    $tuesday_open = $_POST['tuesday_open'] ?? null;
    $tuesday_close = $_POST['tuesday_close'] ?? null;
    $wednesday_open = $_POST['wednesday_open'] ?? null;
    $wednesday_close = $_POST['wednesday_close'] ?? null;
    $thursday_open = $_POST['thursday_open'] ?? null;
    $thursday_close = $_POST['thursday_close'] ?? null;
    $friday_open = $_POST['friday_open'] ?? null;
    $friday_close = $_POST['friday_close'] ?? null;
    $saturday_open = !empty($_POST['saturday_open']) ? $_POST['saturday_open'] : null;
    $saturday_close = !empty($_POST['saturday_close']) ? $_POST['saturday_close'] : null;
    $sunday_open = !empty($_POST['sunday_open']) ? $_POST['sunday_open'] : null;
    $sunday_close = !empty($_POST['sunday_close']) ? $_POST['sunday_close'] : null;
    $slots_per_hour = isset($_POST['slots_per_hour']) ? (int)$_POST['slots_per_hour'] : 1;
  

    // Services arrays
    $service_names = $_POST['service_name'] ?? [];
    $service_prices = $_POST['service_price'] ?? [];
    $service_durations = $_POST['service_duration'] ?? [];

   
    if ($city === 'Other' && !empty($other_city)) {
        $city = $other_city;
    }

    // Validation
    if (empty($name) || empty($email) || empty($public_email) || empty($password) || empty($address) || empty($city) || empty($region)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || !filter_var($public_email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif (empty($service_names) || empty($service_names[0])) {
        $error = 'Please add at least one service.';
    } else {
        // Check if email already exists in businesses
        $stmt = $pdo->prepare("SELECT id FROM businesses WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'This email is already registered. Please login.';
        } else {
            // Begin transaction
            $pdo->beginTransaction();
            try {
                // Combine address with city for full address
                $full_address = $address . ', ' . $city;

                // Hash password
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                // Insert into businesses table with operating hours and region
               $stmt = $pdo->prepare("
    INSERT INTO businesses (
        name, email, phone, public_email, public_phone, address, region, description, password, is_approved, subscription_plan, slots_per_hour,
        monday_open, monday_close, tuesday_open, tuesday_close,
        wednesday_open, wednesday_close, thursday_open, thursday_close,
        friday_open, friday_close, saturday_open, saturday_close,
        sunday_open, sunday_close
    ) VALUES (
        ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?,
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
    )
");
                $stmt->execute([
                    $name, $email, $phone, $public_email, $public_phone, $full_address, $region, '', $hashedPassword,
				    $_POST['subscription_plan'], $slots_per_hour,
                    $monday_open, $monday_close, $tuesday_open, $tuesday_close,
                    $wednesday_open, $wednesday_close, $thursday_open, $thursday_close,
                    $friday_open, $friday_close, $saturday_open, $saturday_close,
                    $sunday_open, $sunday_close
                ]);
                $business_id = $pdo->lastInsertId();

                // Insert services
                $service_stmt = $pdo->prepare("
                    INSERT INTO services (business_id, name, price, duration) 
                    VALUES (?, ?, ?, ?)
                ");
                for ($i = 0; $i < count($service_names); $i++) {
                    if (!empty($service_names[$i]) && is_numeric($service_prices[$i]) && $service_prices[$i] > 0) {
                        $service_stmt->execute([
                            $business_id,
                            trim($service_names[$i]),
                            floatval($service_prices[$i]),
                            intval($service_durations[$i] ?? 0)
                        ]);
                    }
                }

                // Send welcome email to business
                sendBusinessWelcomeEmail($email, $name);

                $pdo->commit();
                $success = 'Registration successful! Your business is pending approval.';
                header("refresh:3;url=business-login.php");
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Registration failed: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <link rel="icon" type="image/png" href="../favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="../favicon.svg" />
    <link rel="shortcut icon" href="../favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="../apple-touch-icon.png" />
    <meta name="apple-mobile-web-app-title" content="No Q" />
    <link rel="manifest" href="../site.webmanifest" />
    <title><?= $pageTitle ?> · No Q</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
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

        * { margin: 0; padding: 0; box-sizing: border-box; }
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
        .nav-links a i { margin-right: 6px; color: var(--purple-primary); }
        .nav-links a:hover { background: rgba(106,27,154,0.08); color: var(--purple-primary); }
        .nav-links .btn-outline { 
            border: 1.5px solid var(--purple-primary); 
            padding: 0.4rem 1.2rem; 
            border-radius: 40px; 
            background: white; 
            font-weight: 600; 
        }
        .nav-links .btn-outline:hover { background: var(--purple-primary); color: white; }
        
        
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
            .btn-outline { width: 100%; text-align: center; }
        }

       
        .auth-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 1rem;
            flex: 1;
            width: 100%;
        }
        .auth-card {
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(10px);
            border-radius: 40px;
            padding: 2rem;
            box-shadow: 0 20px 40px -12px rgba(106,27,154,0.2);
            border: 1px solid rgba(106,27,154,0.1);
        }
        .auth-card h2 {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
            background: linear-gradient(145deg, var(--purple-primary), var(--orange-primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .auth-card .subtitle {
            color: #2c3e50;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }
        .form-row {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        .form-group {
            flex: 1;
            min-width: 200px;
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--purple-primary);
            font-size: 0.85rem;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 0.8rem;
            border: none;
            border-radius: 20px;
            background: #f0f4f8;
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            box-shadow: 0 0 0 2px rgba(106,27,154,0.2);
            background: white;
        }
        .hours-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 0.8rem;
            margin-bottom: 1.5rem;
        }
        .hours-grid .form-group {
            margin-bottom: 0;
        }
        .hours-grid .form-group label {
            font-size: 0.75rem;
        }
        .service-item {
            background: rgba(106,27,154,0.05);
            border-radius: 30px;
            padding: 1rem;
            margin-bottom: 0.8rem;
            display: flex;
            gap: 0.8rem;
            align-items: center;
            flex-wrap: wrap;
            border: 1px solid rgba(106,27,154,0.1);
        }
        .service-item input {
            flex: 1 1 150px;
            min-width: 140px;
            padding: 0.7rem 1rem;
            border: none;
            border-radius: 40px;
            background: white;
        }
        .service-item .btn-remove {
            background: #f44336;
            color: white;
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            cursor: pointer;
            font-weight: bold;
            transition: 0.15s;
        }
        .service-item .btn-remove:hover {
            background: #d32f2f;
            transform: scale(1.05);
        }
        .btn-add-service {
            background: var(--orange-primary);
            color: white;
            border: none;
            padding: 0.7rem 1.5rem;
            border-radius: 50px;
            cursor: pointer;
            margin: 0.5rem 0 1.5rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: 0.2s;
        }
        .btn-add-service:hover {
            background: var(--orange-dark);
            transform: translateY(-2px);
        }
        .btn-primary {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, var(--purple-primary), var(--purple-dark));
            color: white;
            border: none;
            border-radius: 40px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 0.5rem;
            transition: all 0.2s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(106,27,154,0.3);
        }
        .login-link {
            text-align: center;
            margin-top: 1.5rem;
        }
        .login-link a {
            color: var(--purple-primary);
            font-weight: 600;
            text-decoration: none;
            transition: color 0.2s;
        }
        .login-link a:hover {
            color: var(--orange-primary);
            text-decoration: underline;
        }
        .app-footer {
            background: rgba(255,255,255,0.6);
            padding: 1.5rem;
            text-align: center;
            margin-top: auto;
            font-size: 0.8rem;
            color: var(--purple-primary);
        }
        .app-footer a { color: var(--purple-primary); text-decoration: none; }
        .app-footer a:hover { color: var(--orange-primary); text-decoration: underline; }
        .error { 
            color: #b71c1c; 
            background: #ffebee; 
            padding: 0.8rem; 
            border-radius: 30px; 
            margin-bottom: 1rem; 
            font-size: 0.85rem;
        }
        .success { 
            color: var(--purple-primary); 
            background: #e8f5e9; 
            padding: 0.8rem; 
            border-radius: 30px; 
            margin-bottom: 1rem; 
            font-size: 0.85rem;
        }
        .services-heading {
            font-size: 1.1rem;
            font-weight: 600;
            margin: 1.5rem 0 0.8rem;
            background: linear-gradient(145deg, var(--purple-primary), var(--orange-primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            border-bottom: 2px solid rgba(106,27,154,0.1);
            padding-bottom: 0.4rem;
        }
        
        @media (max-width: 480px) {
            .auth-card { padding: 1.2rem; }
            .auth-card h2 { font-size: 1.4rem; }
            .form-row { flex-direction: column; gap: 0; }
            .form-group { min-width: 100%; }
            .service-item { flex-direction: column; align-items: stretch; }
            .service-item input { width: 100%; }
            .service-item .btn-remove { width: 100%; border-radius: 40px; }
        }
    </style>
</head>
<body>
    <header class="app-header">
        <div class="logo-area">
            <img src="/NoQ.jpg" alt="No Q" style="height: 85px; width: auto;">
            <div>
                <span class="logo-text">No Q</span>
                <div style="font-size: 0.7rem; color: var(--purple-primary); letter-spacing: 0.5px;">No more Queues</div>
            </div>
        </div>
        <button class="menu-toggle" id="menuToggle"><i class="fa-solid fa-bars"></i></button>
        <nav class="nav-links" id="navLinks">
            <a href="../index.php"><i class="fa-solid fa-house"></i> Home</a>
            <a href="business-login.php"><i class="fa-solid fa-building"></i> Business Login</a>
        </nav>
    </header>

    <div class="auth-container">
        <div class="auth-card">
            <h2><i class="fa-solid fa-building"></i> List your car wash</h2>
            <p class="subtitle">Join as a business partner and grow your customers</p>

            <?php if ($error): ?>
                <div class="error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="success"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <form method="post" action="" id="signup-form">
            
                <div class="form-row">
                    <div class="form-group">
                        <label>Business name *</label>
                        <input type="text" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Owner Personal Email * (Hidden from customers)</label>
                        <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Owner Personal Phone (Hidden from customers)</label>
                        <input type="tel" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                    </div>
                    <div class="form-group" style="position: relative;">
                        <label>Password *</label>
                        <input type="password" name="password" id="password" required style="padding-right: 45px;">
                        <i class="fa-regular fa-eye" id="togglePassword" style="position: absolute; right: 15px; top: 38px; cursor: pointer; color: #888;"></i>
                    </div>
                </div>

                <h3 class="services-heading"><i class="fa-solid fa-address-book"></i> Customer-Facing Contact Details</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label>Business Email *</label>
                        <input type="email" name="public_email" required value="<?= htmlspecialchars($_POST['public_email'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Business Phone *</label>
                        <input type="tel" name="public_phone" required value="<?= htmlspecialchars($_POST['public_phone'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Street address *</label>
                    <input type="text" name="address" placeholder="Street address" required value="<?= htmlspecialchars($_POST['address'] ?? '') ?>">
                </div>

                <!-- City with Other option -->
                <div class="form-row">
                    <div class="form-group" style="flex: 2;">
                        <label>City/Town *</label>
                        <select name="city" id="city-select" required>
                            <option value="">Select</option>
                            <option value="Esikhawini" <?= ($_POST['city'] ?? '') == 'Esikhawini' ? 'selected' : '' ?>>Esikhawini</option>
                            <option value="Richards Bay" <?= ($_POST['city'] ?? '') == 'Richards Bay' ? 'selected' : '' ?>>Richards Bay</option>
                            <option value="Empangeni" <?= ($_POST['city'] ?? '') == 'Empangeni' ? 'selected' : '' ?>>Empangeni</option>
                            <option value="Durban" <?= ($_POST['city'] ?? '') == 'Durban' ? 'selected' : '' ?>>Durban</option>
                            <option value="Other">Other (specify)</option>
                        </select>
                    </div>
                    <div class="form-group" id="other-city-group" style="display: none; flex: 1;">
                        <label>Other city/town</label>
                        <input type="text" name="other_city" id="other-city" placeholder="Enter city name" value="<?= htmlspecialchars($_POST['other_city'] ?? '') ?>">
                    </div>
                </div>

                <!-- Region with Other option -->
                <div class="form-row">
                    <div class="form-group" style="flex: 2;">
                        <label>Region *</label>
                        <select name="region" id="region-select" required>
                            <option value="">-- Select Region --</option>
                            <option value="Richards Bay" <?= ($_POST['region'] ?? '') == 'Richards Bay' ? 'selected' : '' ?>>Richards Bay</option>
                            <option value="Empangeni" <?= ($_POST['region'] ?? '') == 'Empangeni' ? 'selected' : '' ?>>Empangeni</option>
                            <option value="Mzingazi" <?= ($_POST['region'] ?? '') == 'Mzingazi' ? 'selected' : '' ?>>Mzingazi</option>
                            <option value="Mtunzini" <?= ($_POST['region'] ?? '') == 'Mtunzini' ? 'selected' : '' ?>>Mtunzini</option>
                            <option value="Esikhawini" <?= ($_POST['region'] ?? '') == 'Esikhawini' ? 'selected' : '' ?>>Esikhawini</option>
                            <option value="Ngwelezane" <?= ($_POST['region'] ?? '') == 'Ngwelezane' ? 'selected' : '' ?>>Ngwelezane</option>
                            <option value="Other">Other (specify)</option>
                        </select>
                    </div>
                    <div class="form-group" id="other-region-group" style="display: none; flex: 1;">
                        <label>Other region</label>
                        <input type="text" name="other_region" id="other-region" placeholder="Enter region name" value="<?= htmlspecialchars($_POST['other_region'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Business type *</label>
                        <select name="business_type" required>
                            <option value="">Select</option>
                            <option value="Fixed location" <?= ($_POST['business_type'] ?? '') == 'Fixed location' ? 'selected' : '' ?>>Fixed location</option>
                            <option value="Mobile service" <?= ($_POST['business_type'] ?? '') == 'Mobile service' ? 'selected' : '' ?>>Mobile service</option>
                            <option value="Both" <?= ($_POST['business_type'] ?? '') == 'Both' ? 'selected' : '' ?>>Both</option>
                        </select>
                    </div>
                </div>

				<div class="form-row">
    <div class="form-group">
        <label>Cars per hour (Capacity) *</label>
        <input type="number" name="slots_per_hour" value="<?= htmlspecialchars($_POST['slots_per_hour'] ?? '1') ?>" min="1" required>
        <small style="color: #7c3aed; font-size: 0.7rem;">How many cars can you wash simultaneously per hour?</small>
    </div>
</div>
<div class="form-row">
    <div class="form-group">
        <label>Demand Level (Monthly Subscription) *</label>
        <select name="subscription_plan" required>
            <option value="low">Low Demand (0-14 cars)</option>
            <option value="medium">Medium Demand (15-24 cars)</option>
            <option value="high">High Demand (25+ cars)</option>
        </select>
        <small style="color: #7c3aed; font-size: 0.7rem;">Based on your expected peak period car washes</small>
    </div>
</div>

                <!-- Operating Hours -->
                <h3 class="services-heading"><i class="fa-regular fa-clock"></i> Operating Hours</h3>
                <div class="hours-grid">
                    <div class="form-group">
                        <label>Monday Open</label>
                        <input type="time" name="monday_open" value="<?= htmlspecialchars($_POST['monday_open'] ?? '09:00') ?>">
                    </div>
                    <div class="form-group">
                        <label>Monday Close</label>
                        <input type="time" name="monday_close" value="<?= htmlspecialchars($_POST['monday_close'] ?? '17:00') ?>">
                    </div>
                    <div class="form-group">
                        <label>Tuesday Open</label>
                        <input type="time" name="tuesday_open" value="<?= htmlspecialchars($_POST['tuesday_open'] ?? '09:00') ?>">
                    </div>
                    <div class="form-group">
                        <label>Tuesday Close</label>
                        <input type="time" name="tuesday_close" value="<?= htmlspecialchars($_POST['tuesday_close'] ?? '17:00') ?>">
                    </div>
                    <div class="form-group">
                        <label>Wednesday Open</label>
                        <input type="time" name="wednesday_open" value="<?= htmlspecialchars($_POST['wednesday_open'] ?? '09:00') ?>">
                    </div>
                    <div class="form-group">
                        <label>Wednesday Close</label>
                        <input type="time" name="wednesday_close" value="<?= htmlspecialchars($_POST['wednesday_close'] ?? '17:00') ?>">
                    </div>
                    <div class="form-group">
                        <label>Thursday Open</label>
                        <input type="time" name="thursday_open" value="<?= htmlspecialchars($_POST['thursday_open'] ?? '09:00') ?>">
                    </div>
                    <div class="form-group">
                        <label>Thursday Close</label>
                        <input type="time" name="thursday_close" value="<?= htmlspecialchars($_POST['thursday_close'] ?? '17:00') ?>">
                    </div>
                    <div class="form-group">
                        <label>Friday Open</label>
                        <input type="time" name="friday_open" value="<?= htmlspecialchars($_POST['friday_open'] ?? '09:00') ?>">
                    </div>
                    <div class="form-group">
                        <label>Friday Close</label>
                        <input type="time" name="friday_close" value="<?= htmlspecialchars($_POST['friday_close'] ?? '17:00') ?>">
                    </div>
                    <div class="form-group">
                        <label>Saturday Open</label>
                        <input type="time" name="saturday_open">
                    </div>
                    <div class="form-group">
                        <label>Saturday Close</label>
                        <input type="time" name="saturday_close">
                    </div>
                    <div class="form-group">
                        <label>Sunday Open</label>
                        <input type="time" name="sunday_open">
                    </div>
                    <div class="form-group">
                        <label>Sunday Close</label>
                        <input type="time" name="sunday_close">
                    </div>
                </div>

                <!-- Services Section -->
                <h3 class="services-heading"><i class="fa-solid fa-broom"></i> Services Offered</h3>
                <div id="services-container">
                    <div class="service-item service-row">
                        <input type="text" name="service_name[]" placeholder="Service name (e.g., Exterior Wash)" required>
                        <input type="number" name="service_price[]" placeholder="Price (R)" step="0.01" min="0" required>
                        <input type="number" name="service_duration[]" placeholder="Duration (min)" min="0">
                        <button type="button" class="btn-remove" onclick="this.closest('.service-row').remove()">✕</button>
                    </div>
                </div>
                <button type="button" class="btn-add-service" onclick="addServiceRow()">
                    <i class="fa-regular fa-plus"></i> Add Another Service
                </button>

                <button type="submit" class="btn-primary"><i class="fa-solid fa-arrow-right-to-bracket"></i> Register Business</button>
            </form>

            <div class="login-link">
                Already registered? <a href="business-login.php">Business login</a>
            </div>
        </div>
    </div>

    <footer class="app-footer">
        <p>&copy; <?= date('Y'); ?> No Q. All rights reserved.</p>
        <p>Powered by <a href="https://www.jaekerna.com/" target="_blank">Jaekerna Investments</a></p>
    </footer>

    <script>
    // Hamburger menu toggle
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

    // City select toggle
    document.getElementById('city-select').addEventListener('change', function() {
        var otherGroup = document.getElementById('other-city-group');
        if (this.value === 'Other') {
            otherGroup.style.display = 'block';
            document.getElementById('other-city').setAttribute('required', 'required');
        } else {
            otherGroup.style.display = 'none';
            document.getElementById('other-city').removeAttribute('required');
        }
    });
    
    // Region select toggle
    document.getElementById('region-select').addEventListener('change', function() {
        var otherGroup = document.getElementById('other-region-group');
        if (this.value === 'Other') {
            otherGroup.style.display = 'block';
            document.getElementById('other-region').setAttribute('required', 'required');
        } else {
            otherGroup.style.display = 'none';
            document.getElementById('other-region').removeAttribute('required');
        }
    });
    
    window.addEventListener('load', function() {
        var citySelect = document.getElementById('city-select');
        if (citySelect.value === 'Other') {
            document.getElementById('other-city-group').style.display = 'block';
        }
        var regionSelect = document.getElementById('region-select');
        if (regionSelect.value === 'Other') {
            document.getElementById('other-region-group').style.display = 'block';
        }
    });

    function addServiceRow() {
        var container = document.getElementById('services-container');
        var newRow = document.createElement('div');
        newRow.className = 'service-item service-row';
        newRow.innerHTML = `
            <input type="text" name="service_name[]" placeholder="Service name" required>
            <input type="number" name="service_price[]" placeholder="Price (R)" step="0.01" min="0" required>
            <input type="number" name="service_duration[]" placeholder="Duration (min)" min="0">
            <button type="button" class="btn-remove" onclick="this.closest('.service-row').remove()">✕</button>
        `;
        container.appendChild(newRow);
    }

    const togglePassword = document.getElementById('togglePassword');
    const password = document.getElementById('password');
    if (togglePassword) {
        togglePassword.addEventListener('click', function() {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    }
    </script>
</body>
</html>