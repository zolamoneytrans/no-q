<?php
session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: user-delete-account.php');
    exit;
} elseif (isset($_SESSION['business_id'])) {
    header('Location: business/business-delete-account.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Account Deletion Portal · No Q</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --purple-primary: #6a1b9a;
            --purple-dark: #4a0072;
            --orange-primary: #ff9800;
            --bg-gradient: linear-gradient(145deg, #faf5ff 0%, #f3e5f5 100%);
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--bg-gradient); min-height: 100vh; display: flex; flex-direction: column; color: #2c3e50; }
        .app-header { background: rgba(255,255,255,0.9); backdrop-filter: blur(12px); border-bottom: 1px solid rgba(106,27,154,0.1); padding: 0.8rem 2rem; display: flex; align-items: center; justify-content: space-between; }
        .logo-area { display: flex; align-items: center; gap: 0.8rem; text-decoration: none; }
        .logo-text { font-size: 1.5rem; font-weight: 800; background: linear-gradient(135deg, var(--purple-primary), var(--orange-primary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .container { max-width: 750px; margin: 3rem auto; padding: 0 1.5rem; flex: 1; width: 100%; }
        .portal-header { text-align: center; margin-bottom: 2.5rem; }
        .portal-header h1 { font-size: 2.2rem; color: var(--purple-primary); margin-bottom: 0.8rem; font-weight: 800; }
        .portal-header p { color: #4b5563; font-size: 1.05rem; line-height: 1.6; max-width: 600px; margin: 0 auto; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .card { background: white; border-radius: 24px; padding: 2.2rem; box-shadow: 0 10px 30px rgba(106,27,154,0.06); border: 1px solid rgba(106,27,154,0.08); display: flex; flex-direction: column; justify-content: space-between; transition: transform 0.3s ease; }
        .card:hover { transform: translateY(-4px); box-shadow: 0 15px 35px rgba(106,27,154,0.12); }
        .card-icon { width: 56px; height: 56px; border-radius: 16px; background: rgba(106,27,154,0.1); color: var(--purple-primary); display: flex; align-items: center; justify-content: center; font-size: 1.6rem; margin-bottom: 1.2rem; }
        .card.business .card-icon { background: rgba(255,152,0,0.1); color: var(--orange-primary); }
        .card h2 { font-size: 1.35rem; color: #1e293b; margin-bottom: 0.6rem; }
        .card p { color: #64748b; font-size: 0.95rem; line-height: 1.5; margin-bottom: 1.8rem; }
        .btn { padding: 0.9rem 1.5rem; border-radius: 30px; font-weight: 600; text-decoration: none; text-align: center; transition: all 0.2s; display: block; }
        .btn-purple { background: linear-gradient(135deg, var(--purple-primary), var(--purple-dark)); color: white; }
        .btn-purple:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(106,27,154,0.3); }
        .btn-orange { background: linear-gradient(135deg, var(--orange-primary), #f57c00); color: white; }
        .btn-orange:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(255,152,0,0.3); }
        .support-box { background: white; border-radius: 20px; padding: 1.8rem; text-align: center; border: 1px solid #e2e8f0; }
        .support-box h3 { color: #334155; font-size: 1.1rem; margin-bottom: 0.5rem; }
        .support-box p { color: #64748b; font-size: 0.9rem; }
        .support-box a { color: var(--purple-primary); font-weight: 600; text-decoration: none; }
        .support-box a:hover { text-decoration: underline; }
        .app-footer { background: rgba(255,255,255,0.6); padding: 2rem; text-align: center; margin-top: auto; color: var(--purple-primary); font-size: 0.85rem; }
    </style>
</head>
<body>
    <header class="app-header">
        <a href="index.php" class="logo-area">
            <img src="NoQ.jpg" alt="No Q" style="height: 85px; width: auto;">
            <div>
                <span class="logo-text">No Q</span>
                <div style="font-size: 0.7rem; color: var(--purple-primary); letter-spacing: 0.5px;">No more Queues</div>
            </div>
        </a>
        <a href="index.php" style="color: var(--purple-primary); text-decoration: none; font-weight: 600;"><i class="fa-solid fa-house"></i> Home</a>
    </header>

    <div class="container">
        <div class="portal-header">
            <h1>Account Deletion Portal</h1>
            <p>You have full control over your personal information. Select your account type below to log in and permanently delete your profile and associated data.</p>
        </div>

        <div class="grid">
            <!-- Customer Account -->
            <div class="card">
                <div>
                    <div class="card-icon"><i class="fa-solid fa-user-car"></i></div>
                    <h2>Customer Account</h2>
                    <p>For drivers and customers who book car washes. Deleting this account permanently removes your profile, booking records, favorites, and review history.</p>
                </div>
                <a href="user-login.php?redirect=user-delete-account.php" class="btn btn-purple"><i class="fa-solid fa-arrow-right-to-bracket"></i> Customer Login & Delete</a>
            </div>

            <!-- Business Account -->
            <div class="card business">
                <div>
                    <div class="card-icon"><i class="fa-solid fa-building"></i></div>
                    <h2>Business Partner Account</h2>
                    <p>For car wash owners and partners. Deleting this account removes your public listing, services, pricing specials, and historical customer bookings.</p>
                </div>
                <a href="business/business-login.php?redirect=../business/business-delete-account.php" class="btn btn-orange"><i class="fa-solid fa-arrow-right-to-bracket"></i> Business Login & Delete</a>
            </div>
        </div>

        <div class="support-box">
            <h3>Unable to access your account?</h3>
            <p>If you no longer have access to the email address or phone number associated with your account, please contact our privacy and support team at <a href="mailto:aosolvers@carwashes.africa">aosolvers@carwashes.africa</a> to verify your identity and process your deletion request.</p>
        </div>
    </div>

    <footer class="app-footer">
        <p>&copy; <?= date('Y'); ?> No Q. All rights reserved.</p>
    </footer>
</body>
</html>
