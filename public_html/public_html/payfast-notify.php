<?php

file_put_contents('payfast_log.txt', date('Y-m-d H:i:s') . "\n" . print_r($_POST, true) . "\n\n", FILE_APPEND);

require_once 'db_connect.php';

$data = $_POST;

if (isset($data['m_payment_id']) && isset($data['payment_status']) && $data['payment_status'] == 'COMPLETE') {
    $booking_code = $data['m_payment_id'];
    $amount = $data['amount_gross'];

    $stmt = $pdo->prepare("SELECT id FROM bookings WHERE booking_code = ? AND status = 'confirmed'");
    $stmt->execute([$booking_code]);
    $booking = $stmt->fetch();

    if ($booking) {
        $update = $pdo->prepare("UPDATE bookings SET payment_status = 'paid' WHERE id = ?");
        $update->execute([$booking['id']]);
    }
}

http_response_code(200);
echo "OK";