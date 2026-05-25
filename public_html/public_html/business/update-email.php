<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['business_id'])) {
    header('Location: business-login.php');
    exit;
}

$business_id = $_SESSION['business_id'];
$new_email = trim($_POST['new_email'] ?? '');
$confirm_email = trim($_POST['confirm_email'] ?? '');

if ($new_email !== $confirm_email) {
    $_SESSION['email_error'] = 'Emails do not match.';
    header('Location: business-settings.php');
    exit;
}

if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['email_error'] = 'Invalid email format.';
    header('Location: business-settings.php');
    exit;
}

// Check if email already exists
$check_stmt = $pdo->prepare("SELECT id FROM businesses WHERE email = ? AND id != ?");
$check_stmt->execute([$new_email, $business_id]);
if ($check_stmt->fetch()) {
    $_SESSION['email_error'] = 'This email is already registered.';
    header('Location: business-settings.php');
    exit;
}

// Update email
$update_stmt = $pdo->prepare("UPDATE businesses SET email = ? WHERE id = ?");
if ($update_stmt->execute([$new_email, $business_id])) {
    $_SESSION['email_success'] = 'Email updated successfully. Please login with your new email.';
    session_destroy();
    header('Location: business-login.php');
    exit;
} else {
    $_SESSION['email_error'] = 'Failed to update email.';
    header('Location: business-settings.php');
    exit;
}
?>