<?php

session_start();
require_once 'db_connect.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: user-login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get booking ID from URL
$booking_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch booking details, ensure it belongs to user and is completed and not rated
$stmt = $pdo->prepare("
    SELECT b.*, biz.name as business_name, biz.id as business_id
    FROM bookings b
    JOIN businesses biz ON b.business_id = biz.id
    LEFT JOIN ratings r ON b.id = r.booking_id
    WHERE b.id = ? AND b.user_id = ? AND b.status = 'completed' AND r.id IS NULL
");
$stmt->execute([$booking_id, $user_id]);
$booking = $stmt->fetch();

if (!$booking) {
    // Invalid booking or already rated
    header('Location: user-dashboard.php?error=invalid_booking');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating_cs = (int)($_POST['rating_cs'] ?? 0);
    $rating_time = (int)($_POST['rating_time'] ?? 0);
    $rating_quality = (int)($_POST['rating_quality'] ?? 0);
    $rating_env = (int)($_POST['rating_env'] ?? 0);
    $rating_cost = (int)($_POST['rating_cost'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');

    if ($rating_cs < 1 || $rating_cs > 5 ||
        $rating_time < 1 || $rating_time > 5 ||
        $rating_quality < 1 || $rating_quality > 5 ||
        $rating_env < 1 || $rating_env > 5 ||
        $rating_cost < 1 || $rating_cost > 5) {
        $error = 'Please rate all criteria.';
    } else {
        // Calculate average rating
        $average_rating = round(($rating_cs + $rating_time + $rating_quality + $rating_env + $rating_cost) / 5, 1);

        $stmt = $pdo->prepare("
            INSERT INTO ratings (booking_id, user_id, business_id, rating, 
                rating_customer_service, rating_time_taken, rating_quality, 
                rating_environment, rating_cost, comment)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        if ($stmt->execute([
            $booking_id, $user_id, $booking['business_id'], $average_rating,
            $rating_cs, $rating_time, $rating_quality, $rating_env, $rating_cost, $comment
        ])) {
            // Add loyalty points
            $pdo->prepare("UPDATE users SET points = points + 10 WHERE id = ?")->execute([$user_id]);
          
            header('Location: rate-booking.php?id=' . $booking_id . '&submitted=1');
            exit;
        } else {
            $error = 'Failed to save review.';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <link rel="icon" type="image/png" href="favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="favicon.svg" />
    <link rel="shortcut icon" href="favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="apple-touch-icon.png" />
    <meta name="apple-mobile-web-app-title" content="No Q" />
    <link rel="manifest" href="site.webmanifest" />
    <title>Rate your experience · No Q</title>
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

        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-gradient);
            color: #1a2639;
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
                align-items: center;
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

        .rating-container {
            max-width: 600px;
            margin: 3rem auto;
            padding: 0 2rem;
        }
        .rating-card {
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(10px);
            border-radius: 40px;
            padding: 2.5rem;
            box-shadow: 0 20px 40px -12px rgba(106,27,154,0.2);
            border: 1px solid rgba(106,27,154,0.1);
        }
        .rating-card h2 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
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
        .star-rating {
            display: flex;
            flex-direction: row-reverse;
            justify-content: center;
            gap: 0.5rem;
            font-size: 2.5rem;
            margin: 2rem 0;
        }
        .star-rating input {
            display: none;
        }
        .star-rating label {
            color: #ddd;
            cursor: pointer;
            transition: color 0.2s;
        }
        .star-rating input:checked ~ label,
        .star-rating label:hover,
        .star-rating label:hover ~ label {
            color: #f8b84a;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--purple-primary);
        }
        .form-group textarea {
            width: 100%;
            padding: 1rem;
            border: none;
            border-radius: 30px;
            background: #f0f4f8;
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
            resize: vertical;
            transition: all 0.2s;
        }
        .form-group textarea:focus {
            outline: none;
            box-shadow: 0 0 0 2px rgba(106,27,154,0.2);
            background: white;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--purple-primary), var(--purple-dark));
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 40px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
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
            .rating-container { margin: 1rem auto; padding: 0 1rem; }
            .rating-card { padding: 1.2rem; }
            .rating-card h2 { font-size: 1.5rem; }
        }
    </style>
</head>
<body>
<?php

if (isset($_GET['submitted'])): ?>
<script>
    alert('Thank you! Your review has been submitted.');
    window.history.replaceState({}, document.title, window.location.pathname);
</script>
<?php endif; ?>
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

    <div class="rating-container">
        <div class="rating-card">
            <h2>Rate your experience</h2>
            <div class="booking-detail">
                <p><strong><?= htmlspecialchars($booking['business_name']) ?></strong></p>
                <p><?= date('l, d F Y', strtotime($booking['booking_date'])) ?> at <?= htmlspecialchars($booking['time_slot']) ?></p>
            </div>

            <?php if ($error): ?>
                <div class="error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post" action="" onsubmit="return confirm('Are you sure you want to submit this review?');">
                <div style="margin: 1.5rem 0;">
                    <div class="criteria-row" style="margin-bottom: 1rem;">
                        <label style="display: inline-block; width: 150px;">Customer service</label>
                        <div class="star-rating" style="display: inline-block;">
                            <input type="radio" name="rating_cs" value="5" id="cs5"><label for="cs5"><i class="fa-solid fa-star"></i></label>
                            <input type="radio" name="rating_cs" value="4" id="cs4"><label for="cs4"><i class="fa-solid fa-star"></i></label>
                            <input type="radio" name="rating_cs" value="3" id="cs3"><label for="cs3"><i class="fa-solid fa-star"></i></label>
                            <input type="radio" name="rating_cs" value="2" id="cs2"><label for="cs2"><i class="fa-solid fa-star"></i></label>
                            <input type="radio" name="rating_cs" value="1" id="cs1"><label for="cs1"><i class="fa-solid fa-star"></i></label>
                        </div>
                    </div>
                    <div class="criteria-row" style="margin-bottom: 1rem;">
                        <label style="display: inline-block; width: 150px;">Time taken</label>
                        <div class="star-rating" style="display: inline-block;">
                            <input type="radio" name="rating_time" value="5" id="time5"><label for="time5"><i class="fa-solid fa-star"></i></label>
                            <input type="radio" name="rating_time" value="4" id="time4"><label for="time4"><i class="fa-solid fa-star"></i></label>
                            <input type="radio" name="rating_time" value="3" id="time3"><label for="time3"><i class="fa-solid fa-star"></i></label>
                            <input type="radio" name="rating_time" value="2" id="time2"><label for="time2"><i class="fa-solid fa-star"></i></label>
                            <input type="radio" name="rating_time" value="1" id="time1"><label for="time1"><i class="fa-solid fa-star"></i></label>
                        </div>
                    </div>
                    <div class="criteria-row" style="margin-bottom: 1rem;">
                        <label style="display: inline-block; width: 150px;">Quality of wash</label>
                        <div class="star-rating" style="display: inline-block;">
                            <input type="radio" name="rating_quality" value="5" id="qual5"><label for="qual5"><i class="fa-solid fa-star"></i></label>
                            <input type="radio" name="rating_quality" value="4" id="qual4"><label for="qual4"><i class="fa-solid fa-star"></i></label>
                            <input type="radio" name="rating_quality" value="3" id="qual3"><label for="qual3"><i class="fa-solid fa-star"></i></label>
                            <input type="radio" name="rating_quality" value="2" id="qual2"><label for="qual2"><i class="fa-solid fa-star"></i></label>
                            <input type="radio" name="rating_quality" value="1" id="qual1"><label for="qual1"><i class="fa-solid fa-star"></i></label>
                        </div>
                    </div>
                    <div class="criteria-row" style="margin-bottom: 1rem;">
                        <label style="display: inline-block; width: 150px;">Environment</label>
                        <div class="star-rating" style="display: inline-block;">
                            <input type="radio" name="rating_env" value="5" id="env5"><label for="env5"><i class="fa-solid fa-star"></i></label>
                            <input type="radio" name="rating_env" value="4" id="env4"><label for="env4"><i class="fa-solid fa-star"></i></label>
                            <input type="radio" name="rating_env" value="3" id="env3"><label for="env3"><i class="fa-solid fa-star"></i></label>
                            <input type="radio" name="rating_env" value="2" id="env2"><label for="env2"><i class="fa-solid fa-star"></i></label>
                            <input type="radio" name="rating_env" value="1" id="env1"><label for="env1"><i class="fa-solid fa-star"></i></label>
                        </div>
                    </div>
                    <div class="criteria-row" style="margin-bottom: 1rem;">
                        <label style="display: inline-block; width: 150px;">Cost / value</label>
                        <div class="star-rating" style="display: inline-block;">
                            <input type="radio" name="rating_cost" value="5" id="cost5"><label for="cost5"><i class="fa-solid fa-star"></i></label>
                            <input type="radio" name="rating_cost" value="4" id="cost4"><label for="cost4"><i class="fa-solid fa-star"></i></label>
                            <input type="radio" name="rating_cost" value="3" id="cost3"><label for="cost3"><i class="fa-solid fa-star"></i></label>
                            <input type="radio" name="rating_cost" value="2" id="cost2"><label for="cost2"><i class="fa-solid fa-star"></i></label>
                            <input type="radio" name="rating_cost" value="1" id="cost1"><label for="cost1"><i class="fa-solid fa-star"></i></label>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Your review (optional)</label>
                    <textarea name="comment" rows="4" placeholder="Tell us about your experience..."></textarea>
                </div>

                <button type="submit" class="btn-primary">Submit Review</button>
            </form>
        </div>
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
                    if (navLinks) navLinks.classList.remove('show');
                });
            });
        });
    </script>
</body>
</html>
