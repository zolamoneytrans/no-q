<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['business_id'])) {
    header('Location: business-login.php');
    exit;
}

$business_id = $_SESSION['business_id'];
$business_name = $_SESSION['business_name'];

// Get current balance
$stmt = $pdo->prepare("SELECT wallet_balance FROM businesses WHERE id = ?");
$stmt->execute([$business_id]);
$balance = $stmt->fetchColumn();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = (float)($_POST['amount'] ?? 0);
    
    // Get bank verification status
    $stmt = $pdo->prepare("SELECT bank_verified, bank_account_number FROM businesses WHERE id = ?");
    $stmt->execute([$business_id]);
    $business = $stmt->fetch();
    $has_bank = !empty($business['bank_account_number']);
    $is_verified = ($business['bank_verified'] ?? 0) == 1;
    
    if (!$has_bank) {
        $error = 'Please add your bank details before requesting a withdrawal.';
    } elseif (!$is_verified) {
        $error = 'Your bank account is pending verification. You can withdraw once verified.';
    } elseif ($amount <= 0) {
        $error = 'Please enter a valid amount.';
    } elseif ($amount < 2000) {
        $error = 'Minimum withdrawal amount is R2000.00.';
    } elseif ($amount > $balance) {
        $error = 'Amount exceeds your available balance.';
    } else {
        // Create withdrawal request
        $stmt = $pdo->prepare("INSERT INTO withdrawal_requests (business_id, amount, status) VALUES (?, ?, 'pending')");
        if ($stmt->execute([$business_id, $amount])) {
            // Deduct from wallet immediately
            $update = $pdo->prepare("UPDATE businesses SET wallet_balance = wallet_balance - ? WHERE id = ?");
            $update->execute([$amount, $business_id]);
            $success = 'Withdrawal request submitted. Funds will be transferred after approval.';
            $balance -= $amount;
            
            // Send email notification to admin
            $admin_email = "admin@carwashes.africa";
            $subject = "New Withdrawal Request";
            $body = "
                <!DOCTYPE html>
                <html>
                <head><style>body{font-family:Arial;}</style></head>
                <body>
                    <h2>New Withdrawal Request</h2>
                    <p>A business has requested a withdrawal.</p>
                    <p><strong>Business:</strong> {$business_name}<br>
                    <strong>Amount:</strong> R " . number_format($amount, 2) . "<br>
                    <strong>Requested at:</strong> " . date('Y-m-d H:i:s') . "</p>
                    <p><a href='https://carwashes.africa/admin/withdrawals.php'>View withdrawal requests</a></p>
                    <hr>
                    <p>No Q Admin</p>
                </body>
                </html>
            ";
            sendEmail($admin_email, $subject, $body);
        } else {
            $error = 'Failed to submit request.';
        }
    }
}

// Get withdrawal history
$stmt = $pdo->prepare("SELECT * FROM withdrawal_requests WHERE business_id = ? ORDER BY requested_at DESC");
$stmt->execute([$business_id]);
$withdrawals = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Withdrawal · No Q</title>
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
        }
        .container { max-width: 800px; margin: 2rem auto; padding: 0 2rem; flex: 1; }
        .card {
            background: rgba(255,255,255,0.8);
            backdrop-filter: blur(10px);
            border-radius: 40px;
            padding: 2rem;
            border: 1px solid rgba(255,255,255,0.6);
            margin-bottom: 1.5rem;
        }
        h1 { font-size: 1.8rem; margin-bottom: 0.5rem; color: #1e3c72; }
        .balance-box {
            text-align: center;
            padding: 1.5rem;
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            border-radius: 30px;
            color: white;
            margin-bottom: 1.5rem;
        }
        .balance-box .amount { font-size: 3rem; font-weight: 700; }
        .form-group { margin-bottom: 1.2rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: #1e3c72; }
        .form-group input {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 30px;
            background: #f0f4f8;
            font-size: 1rem;
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
        .btn-success { background: #4caf50; }
        .error { background: #ffebee; color: #b71c1c; padding: 1rem; border-radius: 30px; margin-bottom: 1rem; }
        .success { background: #e8f5e9; color: #1e3c72; padding: 1rem; border-radius: 30px; margin-bottom: 1rem; }
        .warning { background: #fff3e0; color: #e67e22; padding: 1rem; border-radius: 30px; margin-bottom: 1rem; }
        .withdrawal-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        .withdrawal-item:last-child { border-bottom: none; }
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .status-pending { background: #ff9800; color: white; }
        .status-approved { background: #2196f3; color: white; }
        .status-completed { background: #4caf50; color: white; }
        .status-rejected { background: #f44336; color: white; }
        .app-footer { background: rgba(255,255,255,0.6); padding: 2rem; text-align: center; margin-top: auto; }
        @media (max-width: 600px) {
            .withdrawal-item { flex-direction: column; align-items: flex-start; gap: 0.5rem; }
        }
    </style>
</head>
<body>
    <header class="app-header">
        <div class="logo-area">
            <img src="/logo.jpeg" alt="No Q" style="height: 40px; width: auto;">
            <span class="logo-text">CarWash<span style="font-weight:400;">Connect</span></span>
        </div>
        <button class="menu-toggle" id="menuToggle"><i class="fa-solid fa-bars"></i></button>
        <nav class="nav-links" id="navLinks">
            <a href="business-dashboard.php">Dashboard</a>
            <a href="bank-details.php">Bank Details</a>
            <a href="business-logout.php">Logout</a>
        </nav>
    </header>

    <div class="container">
        <div class="balance-box">
            <div class="label">Available Balance</div>
            <div class="amount">R <?= number_format($balance, 2) ?></div>
        </div>

        <div class="card">
            <h1><i class="fa-regular fa-arrow-right-from-bracket"></i> Request Withdrawal</h1>

            <?php if ($error): ?>
                <div class="error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="success"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <?php
            $stmt = $pdo->prepare("SELECT bank_verified, bank_account_number FROM businesses WHERE id = ?");
            $stmt->execute([$business_id]);
            $bus = $stmt->fetch();
            $has_bank = !empty($bus['bank_account_number']);
            $is_verified = ($bus['bank_verified'] ?? 0) == 1;
            ?>

            <?php if (!$has_bank): ?>
                <div class="warning">
                    <i class="fa-solid fa-triangle-exclamation"></i> 
                    Please <a href="bank-details.php" style="color:#e67e22;">add your bank details</a> before requesting a withdrawal.
                </div>
            <?php elseif (!$is_verified): ?>
                <div class="warning">
                    <i class="fa-solid fa-clock"></i> 
                    Your bank account is pending verification. You'll be able to withdraw once verified.
                </div>
            <?php elseif ($balance < 2000): ?>
                <div class="warning">
                    <i class="fa-solid fa-info-circle"></i> 
                    Minimum withdrawal amount is R2000.00. Your current balance is R<?= number_format($balance, 2) ?>.
                </div>
            <?php else: ?>
                <form method="post">
                    <div class="form-group">
                        <label>Amount to withdraw (R)</label>
                        <input type="number" name="amount" step="0.01" min="2000" max="<?= $balance ?>" placeholder="Minimum R2000.00" required>
                        <small style="color:#666;">Minimum: R2000.00 | Maximum: R<?= number_format($balance, 2) ?></small>
                    </div>
                    <button type="submit" class="btn btn-success">Submit Request</button>
                </form>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2><i class="fa-regular fa-clock"></i> Withdrawal History</h2>
            <?php if (empty($withdrawals)): ?>
                <p style="text-align: center; padding: 1rem;">No withdrawal requests yet.</p>
            <?php else: ?>
                <?php foreach ($withdrawals as $w): ?>
                    <div class="withdrawal-item">
                        <div>
                            <strong>R <?= number_format($w['amount'], 2) ?></strong><br>
                            <small><?= date('d M Y', strtotime($w['requested_at'])) ?></small>
                        </div>
                        <div>
                            <span class="status-badge status-<?= $w['status'] ?>">
                                <?= ucfirst($w['status']) ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <footer class="app-footer">
        <p>&copy; <?= date('Y'); ?> No Q</p>
    </footer>

    <script>
        document.getElementById('menuToggle').addEventListener('click', function() {
            document.getElementById('navLinks').classList.toggle('show');
        });
    </script>
</body>
</html>
