<?php
// receipt-template.php - This file generates the PDF receipt
function generateReceiptHTML($booking, $payment) {
    return '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Payment Receipt</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                line-height: 1.6;
                color: #333;
                margin: 0;
                padding: 20px;
            }
            .receipt-container {
                max-width: 800px;
                margin: 0 auto;
                background: white;
                border: 1px solid #ddd;
                border-radius: 10px;
                padding: 30px;
            }
            .header {
                text-align: center;
                border-bottom: 2px solid #1e3c72;
                padding-bottom: 20px;
                margin-bottom: 20px;
            }
            .header h1 {
                color: #1e3c72;
                margin: 0;
            }
            .header p {
                color: #666;
                margin: 5px 0 0;
            }
            .receipt-title {
                text-align: center;
                font-size: 24px;
                font-weight: bold;
                margin: 20px 0;
                color: #1e3c72;
            }
            .info-section {
                background: #f9f9f9;
                padding: 15px;
                border-radius: 8px;
                margin: 20px 0;
            }
            .info-row {
                display: flex;
                justify-content: space-between;
                padding: 8px 0;
                border-bottom: 1px solid #eee;
            }
            .info-label {
                font-weight: bold;
                color: #1e3c72;
            }
            .total-row {
                font-size: 18px;
                font-weight: bold;
                color: #1e3c72;
                border-top: 2px solid #1e3c72;
                margin-top: 10px;
                padding-top: 10px;
            }
            .footer {
                text-align: center;
                font-size: 12px;
                color: #999;
                margin-top: 30px;
                padding-top: 20px;
                border-top: 1px solid #ddd;
            }
            .qr-code {
                text-align: center;
                margin: 20px 0;
            }
        </style>
    </head>
    <body>
        <div class="receipt-container">
            <div class="header">
                <h1>No Q</h1>
                <p>Your premium car wash app</p>
            </div>
            <div class="receipt-title">
                PAYMENT RECEIPT
            </div>
            
            <div class="info-section">
                <div class="info-row">
                    <span class="info-label">Receipt No:</span>
                    <span>' . $payment['id'] . '</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Date:</span>
                    <span>' . date('d M Y H:i', strtotime($payment['created_at'])) . '</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Transaction ID:</span>
                    <span>' . ($payment['transaction_id'] ?? $payment['id']) . '</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Payment Method:</span>
                    <span>PayFast</span>
                </div>
            </div>

            <div class="info-section">
                <div class="info-row">
                    <span class="info-label">Business:</span>
                    <span>' . htmlspecialchars($booking['business_name']) . '</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Service:</span>
                    <span>' . htmlspecialchars($booking['service_name'] ?? 'Car Wash') . '</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Booking Date:</span>
                    <span>' . date('d M Y', strtotime($booking['booking_date'])) . '</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Time:</span>
                    <span>' . $booking['time_slot'] . '</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Booking Code:</span>
                    <span><strong>' . htmlspecialchars($booking['booking_code']) . '</strong></span>
                </div>
            </div>

            <div class="info-section">
                <div class="info-row">
                    <span class="info-label">Subtotal:</span>
                    <span>R ' . number_format($booking['total_amount'], 2) . '</span>
                </div>
                <div class="info-row total-row">
                    <span class="info-label">TOTAL PAID:</span>
                    <span>R ' . number_format($payment['amount'], 2) . '</span>
                </div>
            </div>

            <div class="qr-code">
                <img src="https://carwashes.africa/qr.php?code=' . urlencode($booking['booking_code']) . '" width="120" alt="QR Code">
                <p>Show this QR code at the car wash</p>
            </div>

            <div class="footer">
                <p>Thank you for choosing No Q!</p>
                <p>For support, contact: admin@carwashes.africa</p>
                <p>&copy; ' . date('Y') . ' No Q</p>
            </div>
        </div>
    </body>
    </html>
    ';
}
?>
