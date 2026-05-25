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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Payment Cancelled</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { 
            font-family: Arial, sans-serif; 
            background: #f4f4f4; 
            text-align: center; 
            padding: 20px; 
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container { 
            background: white; 
            border-radius: 10px; 
            padding: 30px 20px; 
            max-width: 500px; 
            width: 100%;
            margin: auto; 
            box-shadow: 0 0 10px rgba(0,0,0,0.1); 
        }
        h1 { 
            color: #f44336; 
            font-size: 28px;
            margin: 15px 0;
        }
        p {
            font-size: 16px;
            line-height: 1.5;
            color: #555;
        }
        .btn { 
            background: #1e3c72; 
            color: white; 
            padding: 12px 24px; 
            text-decoration: none; 
            border-radius: 40px; 
            display: inline-block; 
            margin-top: 20px;
            font-weight: 600;
            transition: background 0.2s;
        }
        .btn:hover {
            background: #2a5298;
        }
        i {
            font-size: 50px;
            color: #f44336;
        }
        @media (max-width: 480px) {
            .container {
                padding: 25px 15px;
            }
            h1 {
                font-size: 24px;
            }
            p {
                font-size: 14px;
            }
            .btn {
                padding: 10px 20px;
                font-size: 14px;
            }
            i {
                font-size: 45px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <i class="fa-solid fa-circle-xmark"></i>
        <h1>Payment Cancelled</h1>
        <p>Your payment was not completed. You can try again from your dashboard.</p>
        <a href="user-dashboard.php" class="btn">Go to Dashboard</a>
    </div>
</body>
</html>