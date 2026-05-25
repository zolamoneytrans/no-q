<?php
$code = isset($_GET['code']) ? trim($_GET['code']) : '';
if (empty($code)) {
    die('No code provided');
}

$size = 300;
$qr_url = "https://quickchart.io/qr?text=" . urlencode($code) . "&size={$size}";

header("Location: $qr_url");
exit;
?>