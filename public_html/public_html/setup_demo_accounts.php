<?php
require_once 'db_connect.php';

$secret_key = 'apple_review_setup_2026';
$provided_key = $_GET['secret'] ?? $_POST['secret'] ?? '';

if ($provided_key !== $secret_key) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        die("Invalid secret key.");
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Setup Demo Accounts for Apple App Review · No Q</title>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
        <style>
            body { font-family: 'Inter', sans-serif; background: #f8fafc; padding: 3rem; text-align: center; color: #1e293b; }
            .card { max-width: 500px; margin: 0 auto; background: white; padding: 2.5rem; border-radius: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
            h1 { color: #6a1b9a; font-size: 1.5rem; margin-bottom: 1rem; }
            input { padding: 0.8rem 1rem; border: 1.5px solid #cbd5e1; border-radius: 10px; width: 100%; margin-bottom: 1rem; }
            button { background: #6a1b9a; color: white; padding: 0.8rem 1.5rem; border: none; border-radius: 30px; font-weight: 600; cursor: pointer; width: 100%; }
        </style>
    </head>
    <body>
        <div class="card">
            <h1>Setup App Store Demo Accounts</h1>
            <p style="color: #64748b; margin-bottom: 1.5rem;">Run this script once on the live server to create or refresh pre-populated demo credentials for Apple App Review.</p>
            <form method="post">
                <input type="hidden" name="secret" value="<?= $secret_key ?>">
                <button type="submit">Populate Demo Accounts Now</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Setup Demo Customer Account
    $cust_email = 'demo@carwashes.africa';
    $cust_pass_raw = 'DemoPassword123!';
    $cust_pass_hashed = password_hash($cust_pass_raw, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$cust_email]);
    $demo_user_id = $stmt->fetchColumn();

    if (!$demo_user_id) {
        $ins = $pdo->prepare("INSERT INTO users (name, email, password, phone, points, is_verified, created_at) VALUES (?, ?, ?, ?, ?, 1, NOW())");
        $ins->execute(['Apple Reviewer (Customer)', $cust_email, $cust_pass_hashed, '+27110001234', 350]);
        $demo_user_id = $pdo->lastInsertId();
    } else {
        $upd = $pdo->prepare("UPDATE users SET name = ?, password = ?, points = 350, is_verified = 1 WHERE id = ?");
        $upd->execute(['Apple Reviewer (Customer)', $cust_pass_hashed, $demo_user_id]);
    }

    // 2. Setup Demo Business Account
    $biz_email = 'partner@carwashes.africa';
    $biz_pass_raw = 'DemoPassword123!';
    $biz_pass_hashed = password_hash($biz_pass_raw, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("SELECT id FROM businesses WHERE email = ?");
    $stmt->execute([$biz_email]);
    $demo_biz_id = $stmt->fetchColumn();

    $biz_desc = 'Premium eco-friendly car wash & professional detailing center in Sandton. Featuring foam bath, ceramic coating, and interior sanitization.';
    
    if (!$demo_biz_id) {
        $ins_biz = $pdo->prepare("INSERT INTO businesses (name, email, password, phone, address, city, province, description, is_approved, is_hidden, is_temporarily_closed, subscription_plan, rating, total_reviews, bank_name, account_number, account_holder, account_type, branch_code, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 0, 0, 'pro', 4.8, 14, ?, ?, ?, ?, ?, NOW())");
        $ins_biz->execute([
            'Executive Sparkle Auto Valet', $biz_email, $biz_pass_hashed, '+27110009876',
            '123 Sandton Drive, Sandton', 'Johannesburg', 'Gauteng', $biz_desc,
            'First National Bank', '62000000123', 'Executive Sparkle CC', 'Cheque', '250655'
        ]);
        $demo_biz_id = $pdo->lastInsertId();
    } else {
        $upd_biz = $pdo->prepare("UPDATE businesses SET name = ?, password = ?, address = ?, city = ?, province = ?, description = ?, is_approved = 1, is_hidden = 0, is_temporarily_closed = 0, subscription_plan = 'pro', rating = 4.8, total_reviews = 14 WHERE id = ?");
        $upd_biz->execute([
            'Executive Sparkle Auto Valet', $biz_pass_hashed, '123 Sandton Drive, Sandton', 'Johannesburg', 'Gauteng', $biz_desc, $demo_biz_id
        ]);
    }

    // Clean old demo test bookings/services/specials/notifications to prevent clutter on refresh
    $pdo->prepare("DELETE FROM bookings WHERE user_id = ? OR business_id = ?")->execute([$demo_user_id, $demo_biz_id]);
    $pdo->prepare("DELETE FROM services WHERE business_id = ?")->execute([$demo_biz_id]);
    $pdo->prepare("DELETE FROM specials WHERE business_id = ?")->execute([$demo_biz_id]);
    $pdo->prepare("DELETE FROM ratings WHERE business_id = ?")->execute([$demo_biz_id]);
    $pdo->prepare("DELETE FROM notifications WHERE user_id = ? OR business_id = ?")->execute([$demo_user_id, $demo_biz_id]);
    $pdo->prepare("DELETE FROM user_favorites WHERE user_id = ? AND business_id = ?")->execute([$demo_user_id, $demo_biz_id]);

    // Add Services for Demo Business
    $services = [
        ['Express Wash & Dry', 'Quick exterior foam wash, tire shine, and microfiber towel dry.', 120, 25],
        ['Full Interior & Exterior Detail', 'Complete exterior wash plus interior vacuum, dashboard conditioning, and window cleaning.', 350, 60],
        ['Executive Ceramic Polish & Protect', 'High-gloss polymer wax coating, clay bar decontamination, and UV interior shield.', 650, 120],
        ['Engine Bay Steam Clean', 'Safe high-pressure steam cleaning and degreasing of engine bay.', 180, 40]
    ];
    $srv_stmt = $pdo->prepare("INSERT INTO services (business_id, name, description, price, duration_minutes) VALUES (?, ?, ?, ?, ?)");
    $service_ids = [];
    foreach ($services as $s) {
        $srv_stmt->execute([$demo_biz_id, $s[0], $s[1], $s[2], $s[3]]);
        $service_ids[] = $pdo->lastInsertId();
    }

    // Add Specials
    $spec_stmt = $pdo->prepare("INSERT INTO specials (business_id, title, description, discount_percentage, valid_days) VALUES (?, ?, ?, ?, ?)");
    $spec_stmt->execute([$demo_biz_id, 'Midweek Happy Hour 20% Off', 'Get 20% off any wash on Tuesday, Wednesday, and Thursday mornings!', 20, 'Tue,Wed,Thu']);
    $spec_stmt->execute([$demo_biz_id, 'Weekend Polish Package Special', 'Book an Executive Ceramic Polish and receive free interior leather conditioning.', 15, 'Sat,Sun']);

    // Add Favorite
    $pdo->prepare("INSERT INTO user_favorites (user_id, business_id, created_at) VALUES (?, ?, NOW())")->execute([$demo_user_id, $demo_biz_id]);

    // Add Bookings (Completed rated, Completed unrated for Rate Car Wash testing, Confirmed upcoming, Pending)
    $book_stmt = $pdo->prepare("INSERT INTO bookings (user_id, business_id, service_id, booking_date, booking_time, status, vehicle_registration, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    
    // 1. Completed & rated (yesterday)
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $book_stmt->execute([$demo_user_id, $demo_biz_id, $service_ids[1] ?? null, $yesterday, '11:00:00', 'completed', 'CA 123-456', 'Please take extra care with rear seats']);
    $completed_booking_id = $pdo->lastInsertId();

    // 2. Completed & ready to be rated by Apple Reviewer (earlier today)
    $today = date('Y-m-d');
    $book_stmt->execute([$demo_user_id, $demo_biz_id, $service_ids[0] ?? null, $today, '09:00:00', 'completed', 'CA 123-456', 'Standard express wash']);

    // 3. Upcoming Confirmed (tomorrow)
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $book_stmt->execute([$demo_user_id, $demo_biz_id, $service_ids[2] ?? null, $tomorrow, '10:30:00', 'confirmed', 'CA 123-456', 'Ceramic polish requested']);

    // 4. Pending Booking (in 3 days)
    $future = date('Y-m-d', strtotime('+3 days'));
    $book_stmt->execute([$demo_user_id, $demo_biz_id, $service_ids[1] ?? null, $future, '14:00:00', 'pending', 'CA 123-456', 'Afternoon appointment']);

    // Add Ratings for Demo Business
    $rate_stmt = $pdo->prepare("INSERT INTO ratings (booking_id, user_id, business_id, rating, review, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $rate_stmt->execute([$completed_booking_id, $demo_user_id, $demo_biz_id, 5, 'Incredible service! My car looks brand new and the staff were so professional and quick. No queues at all!']);
    $rate_stmt->execute([null, $demo_user_id, $demo_biz_id, 5, 'Best car valet in Sandton. The online booking app makes scheduling effortless. Highly recommended.']);
    $rate_stmt->execute([null, $demo_user_id, $demo_biz_id, 4.5, 'Quick foam wash, great attention to detail on the rims and tire shine.']);

    // Add Notifications
    $notif_stmt = $pdo->prepare("INSERT INTO notifications (user_id, business_id, title, message, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
    $notif_stmt->execute([$demo_user_id, null, 'Welcome to No Q!', 'Your customer account is verified and ready to book car washes instantly.']);
    $notif_stmt->execute([$demo_user_id, $demo_biz_id, 'Booking Confirmed (#1024)', 'Your appointment at Executive Sparkle Auto Valet for tomorrow at 10:30 AM has been confirmed!']);
    $notif_stmt->execute([null, $demo_biz_id, 'New Booking Received (#1025)', 'You have a new incoming booking request for Full Interior & Exterior Detail on ' . $future . '.']);

    $pdo->commit();

    echo "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><title>Demo Accounts Ready · No Q</title><link href='https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap' rel='stylesheet'></head><body style='font-family:Inter,sans-serif; background:#f0fdf4; color:#166534; padding:3rem; text-align:center;'>";
    echo "<div style='max-width:600px; margin:0 auto; background:white; padding:2.5rem; border-radius:20px; box-shadow:0 10px 25px rgba(0,0,0,0.05); border:1px solid #bbf7d0;'>";
    echo "<h1 style='color:#15803d; font-size:1.8rem; margin-bottom:1rem;'>✔ Demo Accounts Successfully Setup!</h1>";
    echo "<p style='color:#374151; margin-bottom:1.5rem;'>You can now provide these exact credentials to Apple App Review in App Store Connect:</p>";
    echo "<div style='background:#f8fafc; padding:1.2rem; border-radius:12px; text-align:left; margin-bottom:1.2rem; border:1px solid #e2e8f0;'>";
    echo "<strong>👤 Customer Account (Demo User):</strong><br>";
    echo "Username / Email: <code>demo@carwashes.africa</code><br>";
    echo "Password: <code>DemoPassword123!</code>";
    echo "</div>";
    echo "<div style='background:#f8fafc; padding:1.2rem; border-radius:12px; text-align:left; margin-bottom:1.5rem; border:1px solid #e2e8f0;'>";
    echo "<strong>🏢 Business Account (Demo Partner):</strong><br>";
    echo "Username / Email: <code>partner@carwashes.africa</code><br>";
    echo "Password: <code>DemoPassword123!</code>";
    echo "</div>";
    echo "<p style='font-size:0.9rem; color:#64748b;'>Both accounts are pre-populated with active bookings, services, specials, notifications, and reviews.</p>";
    echo "<div style='margin-top:2rem;'><a href='user-login.php' style='display:inline-block; background:#6a1b9a; color:white; padding:0.8rem 1.5rem; border-radius:30px; text-decoration:none; font-weight:600; margin-right:1rem;'>Test Customer Login</a>";
    echo "<a href='business/business-login.php' style='display:inline-block; background:#ff9800; color:white; padding:0.8rem 1.5rem; border-radius:30px; text-decoration:none; font-weight:600;'>Test Business Login</a></div>";
    echo "</div></body></html>";

} catch (Exception $e) {
    $pdo->rollBack();
    die("Error setting up demo accounts: " . $e->getMessage());
}
