<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: user-login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$review_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Verify the review belongs to this user
$stmt = $pdo->prepare("SELECT id FROM ratings WHERE id = ? AND user_id = ?");
$stmt->execute([$review_id, $user_id]);
$review = $stmt->fetch();

if (!$review) {
    header('Location: user-dashboard.php?error=invalid_review');
    exit;
}

// Delete the review
$stmt = $pdo->prepare("DELETE FROM ratings WHERE id = ?");
if ($stmt->execute([$review_id])) {
    header('Location: user-dashboard.php?success=review_deleted');
} else {
    header('Location: user-dashboard.php?error=delete_failed');
}
exit;