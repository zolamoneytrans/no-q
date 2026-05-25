<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: user-login.php');
    exit;
}

$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Payment Cancelled</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; text-align: center; padding: 50px; }
        .container { background: white; border-radius: 10px; padding: 30px; max-width: 500px; margin: auto; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1 { color: #f44336; }
        .btn { background: #1e3c72; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <i class="fa-solid fa-circle-xmark" style="font-size: 50px; color: #f44336;"></i>
        <h1>Payment Cancelled</h1>
        <p>Your payment was not completed. You can try again from your dashboard.</p>
        <a href="user-dashboard.php" class="btn">Go to Dashboard</a>
    </div>
</body>
</html>