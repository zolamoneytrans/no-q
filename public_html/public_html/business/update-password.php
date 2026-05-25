<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['business_id'])) {
    header('Location: business-login.php');
    exit;
}

$business_id = $_SESSION['business_id'];
$current_password = $_POST['current_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// Verify current password
$stmt = $pdo->prepare("SELECT password FROM businesses WHERE id = ?");
$stmt->execute([$business_id]);
$business = $stmt->fetch();

if (!$business || !password_verify($current_password, $business['password'])) {
    $_SESSION['password_error'] = 'Current password is incorrect.';
    header('Location: business-settings.php');
    exit;
}

if (strlen($new_password) < 6) {
    $_SESSION['password_error'] = 'New password must be at least 6 characters.';
    header('Location: business-settings.php');
    exit;
}

if ($new_password !== $confirm_password) {
    $_SESSION['password_error'] = 'New passwords do not match.';
    header('Location: business-settings.php');
    exit;
}

// Update password
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
$update_stmt = $pdo->prepare("UPDATE businesses SET password = ? WHERE id = ?");
if ($update_stmt->execute([$hashed_password, $business_id])) {
    $_SESSION['password_success'] = 'Password changed successfully.';
    header('Location: business-settings.php');
    exit;
} else {
    $_SESSION['password_error'] = 'Failed to update password.';
    header('Location: business-settings.php');
    exit;
}
?>