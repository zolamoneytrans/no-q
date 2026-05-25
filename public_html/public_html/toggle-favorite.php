<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to favourite businesses.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$business_id = isset($_POST['business_id']) ? (int)$_POST['business_id'] : 0;

if ($business_id == 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid business ID.']);
    exit;
}

// Check if business is a test business
$stmt = $pdo->prepare("SELECT is_test FROM businesses WHERE id = ?");
$stmt->execute([$business_id]);
$business = $stmt->fetch();

if ($business && $business['is_test'] == 1) {
    echo json_encode(['success' => false, 'message' => 'Cannot favourite test business.']);
    exit;
}

// Check if already favorited
$stmt = $pdo->prepare("SELECT id FROM user_favorites WHERE user_id = ? AND business_id = ?");
$stmt->execute([$user_id, $business_id]);
$exists = $stmt->fetch();

if ($exists) {
    // Remove favorite
    $stmt = $pdo->prepare("DELETE FROM user_favorites WHERE user_id = ? AND business_id = ?");
    $stmt->execute([$user_id, $business_id]);
    echo json_encode(['success' => true, 'action' => 'removed']);
} else {
    // Add favorite
    $stmt = $pdo->prepare("INSERT INTO user_favorites (user_id, business_id, created_at) VALUES (?, ?, NOW())");
    $stmt->execute([$user_id, $business_id]);
    echo json_encode(['success' => true, 'action' => 'added']);
}
?>