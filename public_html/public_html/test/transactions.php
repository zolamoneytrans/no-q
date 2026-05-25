<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: user-login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get filter values
$search_business = isset($_GET['business']) ? trim($_GET['business']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build query with filters
$sql = "
    SELECT p.*, bk.booking_date, biz.name as business_name, bk.booking_code
    FROM payments p
    JOIN bookings bk ON p.booking_id = bk.id
    JOIN businesses biz ON bk.business_id = biz.id
    WHERE p.user_id = ?
";
$params = [$user_id];

if (!empty($search_business)) {
    $sql .= " AND biz.name LIKE ?";
    $params[] = "%$search_business%";
}
if (!empty($date_from)) {
    $sql .= " AND DATE(p.created_at) >= ?";
    $params[] = $date_from;
}
if (!empty($date_to)) {
    $sql .= " AND DATE(p.created_at) <= ?";
    $params[] = $date_to;
}

$sql .= " ORDER BY p.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$all_payments = $stmt->fetchAll();

// Get total spent
$total_spent = array_sum(array_column($all_payments, 'amount'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Transactions · No Q</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: 'Inter', sans-serif; background: linear-gradient(145deg, #f6f9fc 0%, #e9f1f8 100%); min-height: 100vh; }
        .container { max-width: 1000px; margin: 2rem auto; padding: 0 1rem; }
        .card { background: rgba(255,255,255,0.8); backdrop-filter: blur(10px); border-radius: 32px; padding: 2rem; }
        h1 { margin-bottom: 0.5rem; color: #1e3c72; }
        .subtitle { color: #666; margin-bottom: 1.5rem; }
        .filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 2rem;
            padding: 1rem;
            background: rgba(55, 15, 216, 0.5);
            border-radius: 30px;
            align-items: flex-end;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
        }
        .filter-group label {
            font-size: 0.8rem;
            font-weight: 600;
            color: #1e3c72;
        }
        .filter-group input {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 30px;
            background: white;
            font-family: 'Inter', sans-serif;
        }
        .btn-filter {
            background: #1e3c72;
            color: white;
            border: none;
            padding: 0.5rem 1.5rem;
            border-radius: 30px;
            cursor: pointer;
            font-weight: 600;
        }
        .btn-reset {
            background: #ff9800;
            color: white;
            border: none;
            padding: 0.5rem 1.5rem;
            border-radius: 30px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-weight: 600;
        }
        .total-box {
            background: rgba(30,60,114,0.1);
            border-radius: 20px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            text-align: right;
        }
        .total-box .label { font-size: 0.9rem; color: #2c3e50; }
        .total-box .value { font-size: 1.8rem; font-weight: 700; color: #1e3c72; }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            text-align: left;
            padding: 1rem 0.5rem;
            background: rgba(30,60,114,0.1);
            border-radius: 20px;
        }
        td {
            padding: 1rem 0.5rem;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        .no-data {
            text-align: center;
            padding: 2rem;
            color: #666;
        }
        .btn-back {
            background: #1e3c72;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 30px;
            text-decoration: none;
            display: inline-block;
            margin-top: 1.5rem;
        }
        @media (max-width: 768px) {
            .filter-bar { flex-direction: column; align-items: stretch; }
            .filter-group input { width: 100%; }
            table, thead, tbody, th, td, tr { display: block; }
            th { display: none; }
            td { display: block; padding: 0.8rem; border-bottom: 1px solid #ddd; }
            td:before { content: attr(data-label); font-weight: 600; display: inline-block; width: 120px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1><i class="fa-regular fa-credit-card"></i> My Transactions</h1>
            <p class="subtitle">View all your payments and search by business or date</p>

            <!-- Filter Form -->
            <form method="get" class="filter-bar">
                <div class="filter-group">
                    <label><i class="fa-regular fa-building"></i> Business Name</label>
                    <input type="text" name="business" placeholder="Search business..." value="<?= htmlspecialchars($search_business) ?>">
                </div>
                <div class="filter-group">
                    <label><i class="fa-regular fa-calendar"></i> From Date</label>
                    <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
                </div>
                <div class="filter-group">
                    <label><i class="fa-regular fa-calendar"></i> To Date</label>
                    <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
                </div>
                <div class="filter-group">
                    <button type="submit" class="btn-filter"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
                </div>
                <div class="filter-group">
                    <a href="transactions.php" class="btn-reset"><i class="fa-solid fa-rotate-left"></i> Reset</a>
                </div>
            </form>

            <!-- Total Spent -->
            <div class="total-box">
                <div class="label">Total spent (filtered)</div>
                <div class="value">R <?= number_format($total_spent, 2) ?></div>
            </div>

            <!-- Transactions Table -->
            <?php if (empty($all_payments)): ?>
                <div class="no-data">
                    <i class="fa-regular fa-receipt"></i> No transactions found.
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Business</th>
                                <th>Booking Code</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_payments as $pay): ?>
                            <tr>
                                <td data-label="Date"><?= date('d M Y, H:i', strtotime($pay['created_at'])) ?></td>
                                <td data-label="Business"><strong><?= htmlspecialchars($pay['business_name']) ?></strong></td>
                                <td data-label="Booking Code"><code><?= htmlspecialchars($pay['booking_code']) ?></code></td>
                                <td data-label="Amount" style="font-weight:700; color:#1e3c72;">R <?= number_format($pay['amount'], 2) ?></td>
                                <td data-label="Status"><span style="background:#4caf50; color:white; padding:2px 12px; border-radius:20px;">Paid</span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <a href="user-dashboard.php" class="btn-back"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>
        </div>
    </div>
</body>
</html>
