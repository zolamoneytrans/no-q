<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['business_id'])) {
    echo json_encode(['pending' => false]);
    exit;
}

$business_id = $_SESSION['business_id'];

$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM bookings 
    WHERE business_id = ? AND status = 'pending'
");
$stmt->execute([$business_id]);
$count = $stmt->fetchColumn();

echo json_encode(['pending' => $count > 0, 'count' => $count]);
