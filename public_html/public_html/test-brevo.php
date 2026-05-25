<?php
require_once 'db_connect.php';

$result = sendEmail('mayibongwemngometulu@gmail.com', 'Brevo Test Email', '<p>This is a test email sent through Brevo SMTP from No Q platform.</p>');

if ($result) {
    echo "✅ Email sent successfully via Brevo!";
} else {
    echo "❌ Email failed. Check error log.";
}
?>