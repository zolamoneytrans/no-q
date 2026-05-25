<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['business_id'])) {
    header('Location: business-login.php');
    exit;
}

$business_id = $_SESSION['business_id'];

// Get current status
$stmt = $pdo->prepare("SELECT is_temporarily_closed FROM businesses WHERE id = ?");
$stmt->execute([$business_id]);
$current_status = $stmt->fetchColumn() ?: 0;

// Toggle the status (1 becomes 0, 0 becomes 1)
$new_status = $current_status == 1 ? 0 : 1;

$update = $pdo->prepare("UPDATE businesses SET is_temporarily_closed = ? WHERE id = ?");
$update->execute([$new_status, $business_id]);

// Redirect back to dashboard
header('Location: business-dashboard.php');
exit;
?>