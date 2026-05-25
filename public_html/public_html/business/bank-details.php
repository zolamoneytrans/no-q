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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Bank Details · No Q</title>
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

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-gradient);
            min-height: 100vh;
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
                background: rgba(255,255,255,0.98);
                backdrop-filter: blur(10px);
                border-radius: 24px;
                padding: 1rem;
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
        
        .container { max-width: 600px; margin: 2rem auto; padding: 0 1rem; }
        .card {
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(10px);
            border-radius: 32px;
            padding: 2rem;
            border: 1px solid rgba(106,27,154,0.1);
            box-shadow: 0 20px 40px -12px rgba(106,27,154,0.2);
        }
        h2 {
            font-size: 1.8rem;
            margin-bottom: 1rem;
            background: linear-gradient(145deg, var(--purple-primary), var(--orange-primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-align: center;
        }
        .form-group { margin-bottom: 1.2rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--purple-primary); }
        .form-group input, .form-group select {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 30px;
            background: #f0f4f8;
            font-family: 'Inter';
            font-size: 1rem;
            transition: all 0.2s;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none;
            box-shadow: 0 0 0 2px rgba(106,27,154,0.2);
            background: white;
        }
        .btn {
            background: linear-gradient(135deg, var(--purple-primary), var(--purple-dark));
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 40px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            font-size: 1rem;
            transition: all 0.2s ease;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(106,27,154,0.3);
        }
        .message { padding: 1rem; border-radius: 30px; margin-bottom: 1rem; text-align: center; }
        .success { background: #e8f5e9; color: var(--purple-primary); }
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
        .app-footer { 
            background: rgba(255,255,255,0.6); 
            padding: 1.5rem; 
            text-align: center; 
            margin-top: 2rem;
            color: var(--purple-primary);
            font-size: 0.85rem;
        }
        .app-footer a { color: var(--purple-primary); text-decoration: none; }
        .app-footer a:hover { color: var(--orange-primary); text-decoration: underline; }
        
        @media (max-width: 768px) {
            .container { margin: 1rem auto; }
            .card { padding: 1.2rem; }
            h2 { font-size: 1.4rem; }
        }
    </style>
</head>
<body>
    <header class="app-header">
        <div class="logo-area">
            <img src="../NoQ.jpg" alt="No Q" style="height: 85px; width: auto;">
            <div>
                <span class="logo-text">No Q</span>
                <div style="font-size: 0.7rem; color: var(--purple-primary); letter-spacing: 0.5px;">No more Queues</div>
            </div>
        </div>
        <button class="menu-toggle" id="menuToggle"><i class="fa-solid fa-bars"></i></button>
        <nav class="nav-links" id="navLinks">
            <a href="business-dashboard.php">Dashboard</a>
            <a href="bank-details.php" style="background:rgba(106,27,154,0.1);">Bank Details</a>
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
                    <select name="bank_name" required>
                        <option value="">Select a Bank</option>
                        <?php 
                        $banks = ['Absa Bank', 'African Bank', 'Capitec Bank', 'Discovery Bank', 'First National Bank (FNB)', 'Investec Bank', 'Nedbank', 'Standard Bank', 'TymeBank', 'Other'];
                        foreach ($banks as $bank) {
                            $selected = (($business['bank_name'] ?? '') == $bank) ? 'selected' : '';
                            echo "<option value=\"$bank\" $selected>$bank</option>";
                        }
                        ?>
                    </select>
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
        <p>&copy; <?= date('Y'); ?> No Q. All rights reserved.</p>
        <p>Powered by <a href="https://www.jaekerna.com/" target="_blank">Jaekerna Investments</a></p>
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

        // Branch code auto-fill
        const bankSelect = document.querySelector('select[name="bank_name"]');
        const branchInput = document.querySelector('input[name="branch_code"]');
        
        const branchCodes = {
            'Absa Bank': '632005',
            'African Bank': '430000',
            'Capitec Bank': '470010',
            'Discovery Bank': '679000',
            'First National Bank (FNB)': '250655',
            'Investec Bank': '580105',
            'Nedbank': '198765',
            'Standard Bank': '051001',
            'TymeBank': '678910'
        };

        bankSelect.addEventListener('change', function() {
            const selectedBank = this.value;
            if (branchCodes[selectedBank]) {
                branchInput.value = branchCodes[selectedBank];
            } else if (selectedBank === 'Other') {
                branchInput.value = '';
            }
        });
    </script>
</body>
</html>