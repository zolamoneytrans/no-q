<?php
header('Content-Type: application/json');
require_once '../db_connect.php';

$region = isset($_GET['region']) ? trim($_GET['region']) : '';

$query = "
    SELECT id, name, address, rating_avg, logo_url, latitude, longitude, region
    FROM businesses 
    WHERE is_approved = 1 AND is_active = 1
";

$params = [];
if (!empty($region)) {
    $query .= " AND region = ?";
    $params[] = $region;
}

$query .= " ORDER BY rating_avg DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$businesses = $stmt->fetchAll();

echo json_encode($businesses);
?>