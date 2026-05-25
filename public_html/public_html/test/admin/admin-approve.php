<?php
session_start();
require_once '../db_connect.php';
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin-login.php');
    exit;
}
$business_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action = $_GET['action'] ?? '';

if ($business_id && in_array($action, ['approve', 'reject'])) {
    if ($action === 'approve') {
        $pdo->prepare("UPDATE businesses SET is_approved = 1 WHERE id = ?")->execute([$business_id]);
    } else {
        $pdo->prepare("DELETE FROM businesses WHERE id = ?")->execute([$business_id]);
    }
}
header('Location: admin-dashboard.php');
exit;