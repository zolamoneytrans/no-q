<?php
session_start();
require_once '../db_connect.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id']) && (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin')) {
    header('Location: admin-login.php');
    exit;
}

$message = '';
$error = '';

// Handle verification actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $business_id = (int)$_GET['id'];
    $action = $_GET['action'];
    
    if ($action == 'verify') {
        $stmt = $pdo->prepare("UPDATE businesses SET bank_verified = 1 WHERE id = ?");
        if ($stmt->execute([$business_id])) {
            $message = "Business bank account verified successfully.";
        } else {
            $error = "Failed to verify account.";
        }
    } elseif ($action == 'unverify') {
        $stmt = $pdo->prepare("UPDATE businesses SET bank_verified = 0 WHERE id = ?");
        if ($stmt->execute([$business_id])) {
            $message = "Business bank account unverified.";
        } else {
            $error = "Failed to update.";
        }
    } elseif ($action == 'reject') {
        $stmt = $pdo->prepare("UPDATE businesses SET bank_verified = 2 WHERE id = ?");
        if ($stmt->execute([$business_id])) {
            $message = "Business bank account rejected.";
        } else {
            $error = "Failed to reject.";
        }
    }
}

// Get all businesses with bank details
$stmt = $pdo->prepare("
    SELECT id, name, email, bank_account_holder, bank_name, 
           bank_account_number, bank_branch_code, bank_account_type, bank_verified 
    FROM businesses 
    WHERE bank_account_number IS NOT NULL 
    ORDER BY bank_verified ASC, name ASC
");
$stmt->execute();
$businesses = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Verify Bank Accounts - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(145deg, #faf5ff 0%, #f3e8ff 100%);
            min-height: 100vh;
        }
        
        /* Header Styles */
        .app-header {
            background: rgba(255,255,255,0.85);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255,255,255,0.5);
            padding: 0.8rem 2rem;
            position: sticky;
            top: 0;
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
        }
        .logo-area { display: flex; align-items: center; gap: 10px; }
        .logo-icon { background: #6d28d9; color: white; width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        .logo-text { font-weight: 700; font-size: 1.5rem; background: linear-gradient(135deg, #6d28d9, #8b5cf6); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .nav-links { display: flex; gap: 1.2rem; align-items: center; }
        .nav-links a { text-decoration: none; font-weight: 500; color: #5b21b6; padding: 0.5rem 0.8rem; border-radius: 40px; transition: 0.2s; }
        .nav-links a:hover { background: rgba(139,92,246,0.1); color: #6d28d9; }
        .nav-links a i { margin-right: 6px; }
        .menu-toggle {
            display: none;
            font-size: 1.8rem;
            cursor: pointer;
            background: transparent;
            border: none;
            color: #6d28d9;
            padding: 0.5rem;
        }
        
        /* Container */
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem; }
        h1 {
            font-size: 2rem;
            margin-bottom: 1.5rem;
            background: linear-gradient(145deg, #6d28d9, #8b5cf6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        /* Card */
        .card {
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(10px);
            border-radius: 32px;
            padding: 2rem;
            border: 1px solid rgba(255,255,255,0.6);
            box-shadow: 0 20px 40px -12px rgba(109,40,217,0.2);
            margin-bottom: 2rem;
            overflow-x: auto;
        }
        
        /* Table */
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 1rem; text-align: left; border-bottom: 1px solid rgba(0,0,0,0.08); }
        th {
            background: rgba(139,92,246,0.1);
            font-weight: 600;
            color: #6d28d9;
            font-size: 0.9rem;
        }
        tr:hover { background: rgba(139,92,246,0.02); }
        
        /* Status Badges */
        .status {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .status.pending { background: #f97316; color: white; }
        .status.verified { background: #22c55e; color: white; }
        .status.rejected { background: #ef4444; color: white; }
        
        /* Buttons */
        .btn {
            padding: 6px 14px;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            margin: 2px;
            transition: 0.2s;
        }
        .btn-verify { background: #22c55e; color: white; }
        .btn-verify:hover { background: #16a34a; transform: scale(1.02); }
        .btn-unverify { background: #f97316; color: white; }
        .btn-unverify:hover { background: #ea580c; transform: scale(1.02); }
        .btn-reject { background: #ef4444; color: white; }
        .btn-reject:hover { background: #dc2626; transform: scale(1.02); }
        
        /* Bank Details */
        .bank-details { font-size: 0.85rem; color: #5b21b6; line-height: 1.5; }
        .bank-details strong { color: #6d28d9; }
        
        /* Messages */
        .message {
            padding: 1rem;
            border-radius: 30px;
            margin-bottom: 1.5rem;
            text-align: center;
            animation: slideDown 0.3s ease;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .success { background: #dcfce7; color: #166534; border-left: 4px solid #22c55e; }
        .error { background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444; }
        
        .footer-note {
            text-align: center;
            color: #7c3aed;
            margin-top: 2rem;
            padding: 1rem;
            font-size: 0.85rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #7c3aed;
        }
        .empty-state i { font-size: 3rem; color: #6d28d9; margin-bottom: 1rem; display: block; opacity: 0.5; }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .menu-toggle { display: block; }
            .nav-links {
                display: none;
                width: 100%;
                flex-direction: column;
                background: rgba(255,255,255,0.95);
                backdrop-filter: blur(10px);
                border-radius: 30px;
                padding: 1rem;
                margin-top: 1rem;
                gap: 0.5rem;
            }
            .nav-links.show { display: flex; }
            .app-header { padding: 0.8rem 1rem; }
            .nav-links a { width: 100%; text-align: center; padding: 0.8rem; }
            
            .container { padding: 1rem; }
            h1 { font-size: 1.5rem; flex-wrap: wrap; }
            
            .card { padding: 1rem; overflow-x: auto; }
            
            table, thead, tbody, th, td, tr { display: block; }
            th { display: none; }
            tr {
                margin-bottom: 1.2rem;
                border: 1px solid rgba(109,40,217,0.1);
                border-radius: 20px;
                padding: 1rem;
                background: rgba(255,255,255,0.8);
            }
            td {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                padding: 0.6rem 0;
                border: none;
                gap: 1rem;
                flex-wrap: wrap;
            }
            td:before {
                content: attr(data-label);
                font-weight: 700;
                color: #6d28d9;
                min-width: 100px;
                font-size: 0.8rem;
            }
            .bank-details { flex: 1; }
            .btn { padding: 5px 12px; font-size: 0.75rem; }
        }
        
        @media (max-width: 480px) {
            .btn { padding: 4px 10px; }
            td { flex-direction: column; align-items: flex-start; }
            td:before { margin-bottom: 4px; }
        }
    </style>
</head>
<body>
    <header class="app-header">
        <div class="logo-area">
            <img src="/NoQ.jpg" alt="No Q" style="height: 85px; width: auto;">
            <div>
                <span class="logo-text">No Q</span>
                <div style="font-size: 0.7rem; color: #5b21b6; letter-spacing: 0.5px;">No more Queues</div>
            </div>
        </div>
        <button class="menu-toggle" id="menuToggle"><i class="fa-solid fa-bars"></i></button>
        <nav class="nav-links" id="navLinks">
            <a href="admin-dashboard.php"><i class="fa-regular fa-chart-line"></i> Dashboard</a>
            <a href="verify-banks.php" style="background:rgba(139,92,246,0.1);"><i class="fa-regular fa-building-columns"></i> Verify Banks</a>
            <a href="admin-logout.php"><i class="fa-regular fa-sign-out"></i> Logout</a>
        </nav>
    </header>

    <div class="container">
        <h1>
            <i class="fa-regular fa-building-columns"></i> 
            Business Bank Account Verification
        </h1>
        
        <?php if ($message): ?>
            <div class="message success">
                <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error">
                <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <?php if (empty($businesses)): ?>
                <div class="empty-state">
                    <i class="fa-regular fa-building-columns"></i>
                    <p>No businesses have submitted bank details yet.</p>
                    <p style="font-size: 0.85rem; margin-top: 0.5rem;">When businesses add their banking information, they will appear here.</p>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Business</th>
                                <th>Bank Details</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($businesses as $biz):
                                // Decrypt sensitive data
                                $account_number = decryptData($biz['bank_account_number']);
                                $branch_code = decryptData($biz['bank_branch_code']);
                                
                                $status_class = 'pending';
                                $status_text = 'Pending';
                                if ($biz['bank_verified'] == 1) {
                                    $status_class = 'verified';
                                    $status_text = 'Verified';
                                } elseif ($biz['bank_verified'] == 2) {
                                    $status_class = 'rejected';
                                    $status_text = 'Rejected';
                                }
                            ?>
                            <tr>
                                <td data-label="Business">
                                    <strong><?= htmlspecialchars($biz['name']) ?></strong><br>
                                    <small style="color: #7c3aed;"><?= htmlspecialchars($biz['email']) ?></small>
                                </td>
                                <td data-label="Bank Details">
                                    <div class="bank-details">
                                        <div><strong><?= htmlspecialchars($biz['bank_account_holder']) ?></strong></div>
                                        <div><?= htmlspecialchars($biz['bank_name']) ?></div>
                                        <div>Acc: <?= htmlspecialchars($account_number) ?></div>
                                        <?php if ($branch_code): ?>
                                            <div>Branch: <?= htmlspecialchars($branch_code) ?></div>
                                        <?php endif; ?>
                                        <div><?= ucfirst($biz['bank_account_type'] ?? 'Cheque') ?> Account</div>
                                    </div>
                                </td>
                                <td data-label="Status">
                                    <span class="status <?= $status_class ?>"><?= $status_text ?></span>
                                </td>
                                <td data-label="Actions">
                                    <?php if ($biz['bank_verified'] != 1): ?>
                                        <a href="?action=verify&id=<?= $biz['id'] ?>" class="btn btn-verify" onclick="return confirm('Verify this bank account?')"><i class="fa-regular fa-check"></i> Verify</a>
                                    <?php endif; ?>
                                    
                                    <?php if ($biz['bank_verified'] == 1): ?>
                                        <a href="?action=unverify&id=<?= $biz['id'] ?>" class="btn btn-unverify" onclick="return confirm('Unverify this account?')"><i class="fa-regular fa-undo"></i> Unverify</a>
                                    <?php endif; ?>
                                    
                                    <?php if ($biz['bank_verified'] != 2 && $biz['bank_verified'] != 1): ?>
                                        <a href="?action=reject&id=<?= $biz['id'] ?>" class="btn btn-reject" onclick="return confirm('Reject this bank account?')"><i class="fa-regular fa-times"></i> Reject</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="footer-note">
            <i class="fa-solid fa-lock"></i> Bank details are encrypted and only visible to admins
        </div>
    </div>

    <script>
        // Hamburger menu toggle
        const menuToggle = document.getElementById('menuToggle');
        const navLinks = document.getElementById('navLinks');
        
        if (menuToggle) {
            menuToggle.addEventListener('click', function() {
                navLinks.classList.toggle('show');
            });
        }
        
        // Close menu when clicking a link
        document.querySelectorAll('.nav-links a').forEach(link => {
            link.addEventListener('click', () => {
                navLinks.classList.remove('show');
            });
        });
        
        // Auto-hide messages after 5 seconds
        setTimeout(() => {
            const messages = document.querySelectorAll('.message');
            messages.forEach(msg => {
                msg.style.opacity = '0';
                setTimeout(() => msg.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>