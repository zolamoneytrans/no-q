<?php
echo "Current directory: " . __DIR__ . "<br>";
echo "vendor/autoload.php exists? " . (file_exists(__DIR__ . '/vendor/autoload.php') ? 'YES' : 'NO') . "<br>";
echo "vendor/phpmailer/src/PHPMailer.php exists? " . (file_exists(__DIR__ . '/vendor/phpmailer/src/PHPMailer.php') ? 'YES' : 'NO') . "<br>";
?>