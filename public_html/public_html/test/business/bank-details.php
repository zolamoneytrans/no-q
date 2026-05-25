<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['business_id'])) {
    header('Location: business-login.php');
    exit;
}

$business_id = $_SESSION['business_id'];
$error = '';
$success = '';

// Get current business details
$stmt = $pdo->prepare("SELECT * FROM businesses WHERE id = ?");
$stmt->execute([$business_id]);
$business = $stmt->fetch();

// Decrypt existing bank details
$bank_account_number = '';
$bank_branch_code = '';

if (!empty($business['bank_account_number'])) {
    $bank_account_number = decryptData($business['bank_account_number']);
}
if (!empty($business['bank_branch_code'])) {
    $bank_branch_code = decryptData($business['bank_branch_code']);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $account_holder = trim($_POST['account_holder'] ?? '');
    $bank_name = trim($_POST['bank_name'] ?? '');
    $account_number = trim($_POST['account_number'] ?? '');
    $branch_code = trim($_POST['branch_code'] ?? '');
    $account_type = $_POST['account_type'] ?? 'cheque';
    
    if (empty($account_holder) || empty($bank_name) || empty($account_number)) {
        $error = 'Account holder name, bank name, and account number are required.';
    } else {
        // Encrypt sensitive data
        $encrypted_account = encryptData($account_number);
        $encrypted_branch = encryptData($branch_code);
        
        $stmt = $pdo->prepare("
            UPDATE businesses 
            SET bank_account_holder = ?, 
                bank_name = ?, 
                bank_account_number = ?, 
                bank_branch_code = ?, 
                bank_account_type = ?
            WHERE id = ?
        ");
        
        if ($stmt->execute([$account_holder, $bank_name, $encrypted_account, $encrypted_branch, $account_type, $business_id])) {
            $success = 'Bank details saved successfully.';
            // Refresh data
            $stmt = $pdo->prepare("SELECT * FROM businesses WHERE id = ?");
            $stmt->execute([$business_id]);
            $business = $stmt->fetch();
            $bank_account_number = decryptData($business['bank_account_number'] ?? '');
            $bank_branch_code = decryptData($business['bank_branch_code'] ?? '');
        } else {
            $error = 'Failed to save bank details.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bank Details - No Q</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
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
        }
        .logo-area { display: flex; align-items: center; gap: 10px; }
        .logo-icon { background: #1e3c72; color: white; width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        .logo-text { font-weight: 700; font-size: 1.5rem; background: linear-gradient(135deg, #1e3c72, #2a5298); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .nav-links { display: flex; gap: 1.2rem; align-items: center; }
        .nav-links a { text-decoration: none; font-weight: 500; color: #2c3e50; padding: 0.5rem 0.8rem; border-radius: 40px; }
        .nav-links a:hover { background: rgba(42,82,152,0.08); }
        .menu-toggle { display: none; font-size: 1.8rem; cursor: pointer; background: transparent; border: none; }
        @media (max-width: 768px) {
            .menu-toggle { display: block; }
            .nav-links { display: none; width: 100%; flex-direction: column; background: rgba(255,255,255,0.95); border-radius: 30px; padding: 1rem; margin-top: 1rem; }
            .nav-links.show { display: flex; }
        }
        .container { max-width: 600px; margin: 2rem auto; padding: 0 1rem; }
        .card {
            background: rgba(255,255,255,0.8);
            backdrop-filter: blur(10px);
            border-radius: 32px;
            padding: 2rem;
            border: 1px solid rgba(255,255,255,0.6);
            box-shadow: 0 20px 40px -12px rgba(0,20,40,0.2);
        }
        h2 {
            font-size: 1.8rem;
            margin-bottom: 1rem;
            background: linear-gradient(145deg, #1e3c72, #2a5298);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-align: center;
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
        .message { padding: 1rem; border-radius: 30px; margin-bottom: 1rem; text-align: center; }
        .success { background: #e8f5e9; color: #1e3c72; }
        .error { background: #ffebee; color: #b71c1c; }
        .info { font-size: 0.8rem; color: #666; margin-top: 0.5rem; }
        .verified-badge {
            background: #4caf50;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            display: inline-block;
            margin-left: 10px;
        }
        .app-footer { background: rgba(255,255,255,0.6); padding: 1.5rem; text-align: center; margin-top: 2rem; }
    </style>
</head>
<body>
    <header class="app-header">
        <div class="logo-area">
            <img src="../logo.jpeg" alt="No Q" style="height: 40px; width: auto;">
            <span class="logo-text">CarWash<span style="font-weight:400;">Connect</span></span>
        </div>
        <button class="menu-toggle" id="menuToggle"><i class="fa-solid fa-bars"></i></button>
        <nav class="nav-links" id="navLinks">
            <a href="business-dashboard.php">Dashboard</a>
            <a href="bank-details.php" style="background:rgba(42,82,152,0.1);">Bank Details</a>
            <a href="business-logout.php">Logout</a>
        </nav>
    </header>

    <div class="container">
        <div class="card">
            <h2><i class="fa-regular fa-building-columns"></i> Bank Account Details</h2>
            <p style="text-align: center; margin-bottom: 1.5rem;">Enter your banking details to receive payments</p>
            
            <?php if ($success): ?>
                <div class="message success"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="message error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if (($business['bank_verified'] ?? 0) == 1): ?>
                <div style="text-align: center; margin-bottom: 1rem;">
                    <span class="verified-badge"><i class="fa-solid fa-check"></i> Verified Account</span>
                </div>
            <?php endif; ?>
            
            <form method="post">
                <div class="form-group">
                    <label>Account Holder Name *</label>
                    <input type="text" name="account_holder" value="<?= htmlspecialchars($business['bank_account_holder'] ?? '') ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Bank Name *</label>
                    <input type="text" name="bank_name" value="<?= htmlspecialchars($business['bank_name'] ?? '') ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Account Number *</label>
                    <input type="text" name="account_number" value="<?= htmlspecialchars($bank_account_number ?? '') ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Branch Code</label>
                    <input type="text" name="branch_code" value="<?= htmlspecialchars($bank_branch_code ?? '') ?>">
                    <div class="info">Optional for most banks, but recommended</div>
                </div>
                
                <div class="form-group">
                    <label>Account Type</label>
                    <select name="account_type">
                        <option value="cheque" <?= (($business['bank_account_type'] ?? 'cheque') == 'cheque') ? 'selected' : '' ?>>Cheque Account</option>
                        <option value="savings" <?= (($business['bank_account_type'] ?? '') == 'savings') ? 'selected' : '' ?>>Savings Account</option>
                    </select>
                </div>
                
                <button type="submit" class="btn"><i class="fa-regular fa-floppy-disk"></i> Save Bank Details</button>
            </form>
            
            <div class="info" style="text-align: center; margin-top: 1.5rem;">
                <i class="fa-solid fa-lock"></i> Your bank details are encrypted and secure.
            </div>
        </div>
    </div>

    <footer class="app-footer">
        <p>&copy; <?= date('Y'); ?> No Q</p>
    </footer>

    <script>
        const menuToggle = document.getElementById('menuToggle');
        const navLinks = document.getElementById('navLinks');
        if (menuToggle) {
            menuToggle.addEventListener('click', () => navLinks.classList.toggle('show'));
        }
        document.querySelectorAll('.nav-links a').forEach(link => {
            link.addEventListener('click', () => navLinks.classList.remove('show'));
        });
    </script>
</body>
</html>
