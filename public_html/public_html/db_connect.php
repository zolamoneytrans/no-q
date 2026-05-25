<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/vendor/phpmailer/PHPMailer.php';
require_once __DIR__ . '/vendor/phpmailer/SMTP.php';
require_once __DIR__ . '/vendor/phpmailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$host = 'localhost';
$dbname = 'natsrzbh_carwash_connect';
$username = 'natsrzbh_carwash_connect'; 
$password = 'MVDjWQsarRBj2JJMcN8x';     

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

function addNotification($pdo, $user_id, $message, $link = null) {
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)");
    return $stmt->execute([$user_id, $message, $link]);
}

function sendEmail($to, $subject, $body) {
    $mail = new PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp-relay.brevo.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'a117cd001@smtp-brevo.com'; 
        $mail->Password   = 'YOUR_SMTP_PASSWORD_HERE';  
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        $mail->setFrom('admin@carwashes.africa', 'No Q');
        $mail->addAddress($to);
        $mail->addReplyTo('aosolvers@carwashes.africa', 'No Q Support');
        
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body);
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email failed to {$to}: {$mail->ErrorInfo}");
        return false;
    }
}

// CUSTOMER WELCOME EMAIL
function sendWelcomeEmail($email, $name, $verification_token) {
    $subject = "Welcome to No Q – Please Verify Your Email";
    $body = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9; border-radius: 10px; }
            .header { background: #1e3c72; color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { padding: 20px; background: white; border-radius: 0 0 10px 10px; }
            .button { display: inline-block; background: #ff9800; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
            .footer { text-align: center; font-size: 12px; color: #666; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Welcome to No Q! 🚗</h2>
            </div>
            <div class='content'>
                <p>Hi <strong>" . htmlspecialchars($name) . "</strong>,</p>
                <p>Thank you for joining No Q.</p>
                <p><strong>One more step to get started:</strong></p>
                <p>Please verify your email address by clicking the button below.</p>
                <div style='text-align: center;'>
                    <a href='https://carwashes.africa/verify-email.php?token=" . $verification_token . "' class='button'>Verify My Email</a>
                </div>
                <p>If the button doesn't work, copy and paste this link into your browser:</p>
                <p style='word-break: break-all;'>https://carwashes.africa/verify-email.php?token=" . $verification_token . "</p>
                <hr>
                <p style='font-size: 14px;'>No Q Team</p>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " No Q. All Rights Reserved</p>
            </div>
        </div>
    </body>
    </html>
    ";
    return sendEmail($email, $subject, $body);
}

// BUSINESS WELCOME EMAIL
function sendBusinessWelcomeEmail($email, $business_name) {
    $subject = "Welcome to No Q – Account Pending Approval";
    $body = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9; border-radius: 10px; }
            .header { background: #1e3c72; color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { padding: 20px; background: white; border-radius: 0 0 10px 10px; }
            .footer { text-align: center; font-size: 12px; color: #666; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Welcome to No Q!</h2>
            </div>
            <div class='content'>
                <p>Hi <strong>" . htmlspecialchars($business_name) . "</strong>,</p>
                <p>Thank you for registering your car wash on No Q.</p>
                <p>Your account is now <strong>pending approval</strong> by our admin team. This process usually takes 1‑2 business days. Once approved, you'll receive a confirmation email and can start managing your business.</p>
                <p>If you have any questions, please contact us at admin@carwashes.africa.</p>
                <hr>
                <p style='font-size: 14px;'>No Q Team</p>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " No Q. All Rights Reserved</p>
            </div>
        </div>
    </body>
    </html>
    ";
    return sendEmail($email, $subject, $body);
}

// BUSINESS BOOKING NOTIFICATION FUNCTION 
function sendBusinessBookingNotification($pdo, $booking_id, $action) {
    $stmt = $pdo->prepare("
        SELECT b.*, 
               biz.name as business_name, biz.email as business_email,
               u.name as user_name,
               s.name as service_name
        FROM bookings b
        JOIN businesses biz ON b.business_id = biz.id
        JOIN users u ON b.user_id = u.id
        LEFT JOIN services s ON b.service_id = s.id
        WHERE b.id = ?
    ");
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch();
    if (!$booking) return;

    $business_email = $booking['business_email'];
    $subject = '';
    $body = '';

    switch ($action) {
        case 'new':
            $subject = "New Booking – {$booking['business_name']}";
            $body = "
                <p>A new booking has been made at your car wash.</p>
                <p><strong>Booking Code:</strong> {$booking['booking_code']}<br>
                <strong>Customer:</strong> {$booking['user_name']}<br>
                <strong>Service:</strong> {$booking['service_name']}<br>
                <strong>Date:</strong> {$booking['booking_date']}<br>
                <strong>Time:</strong> {$booking['time_slot']}<br>
                <strong>Amount:</strong> R {$booking['total_amount']}</p>
                <p><a href='https://carwashes.africa/business/bookings.php'>Manage in Dashboard</a></p>
            ";
            break;
        case 'confirmed':
            $subject = "Booking Confirmed – {$booking['business_name']}";
            $body = "
                <p>You have confirmed a booking.</p>
                <p><strong>Booking Code:</strong> {$booking['booking_code']}<br>
                <strong>Customer:</strong> {$booking['user_name']}<br>
                <strong>Date:</strong> {$booking['booking_date']}<br>
                <strong>Time:</strong> {$booking['time_slot']}</p>
                <p><em>This is a copy for your records.</em></p>
            ";
            break;
        case 'cancelled_by_business':
            $subject = "Booking Cancelled – {$booking['business_name']}";
            $body = "
                <p>A booking has been cancelled.</p>
                <p><strong>Booking Code:</strong> {$booking['booking_code']}<br>
                <strong>Customer:</strong> {$booking['user_name']}<br>
                <strong>Date:</strong> {$booking['booking_date']}<br>
                <strong>Time:</strong> {$booking['time_slot']}</p>
            ";
            break;
        case 'cancelled_by_customer':
            $subject = "Booking Cancelled by Customer – {$booking['business_name']}";
            $body = "
                <p>A customer has cancelled their booking.</p>
                <p><strong>Booking Code:</strong> {$booking['booking_code']}<br>
                <strong>Customer:</strong> {$booking['user_name']}<br>
                <strong>Date:</strong> {$booking['booking_date']}<br>
                <strong>Time:</strong> {$booking['time_slot']}</p>
            ";
            break;
        case 'completed':
            $subject = "Booking Completed – {$booking['business_name']}";
            $body = "
                <p>A booking has been marked as completed.</p>
                <p><strong>Booking Code:</strong> {$booking['booking_code']}<br>
                <strong>Customer:</strong> {$booking['user_name']}<br>
                <strong>Date:</strong> {$booking['booking_date']}<br>
                <strong>Time:</strong> {$booking['time_slot']}<br>
                <strong>Amount earned:</strong> R {$booking['total_amount']}</p>
            ";
            break;
        case 'rescheduled':
            $subject = "Booking Rescheduled – {$booking['business_name']}";
            $body = "
                <p>A booking has been rescheduled.</p>
                <p><strong>Booking Code:</strong> {$booking['booking_code']}<br>
                <strong>Customer:</strong> {$booking['user_name']}<br>
                <strong>New Date:</strong> {$booking['booking_date']}<br>
                <strong>New Time:</strong> {$booking['time_slot']}</p>
            ";
            break;
        default:
            return;
    }

    sendEmail($business_email, $subject, $body);
}

define('PF_MERCHANT_ID', '34531275');
define('PF_MERCHANT_KEY', 'abvypzhztseej');
define('PF_TEST_MODE', false);                

function generatePayFastSignature($data) {
    $pfOutput = '';
    foreach ($data as $key => $val) {
        if ($val !== '') {
            $pfOutput .= $key . '=' . urlencode(trim($val)) . '&';
        }
    }
    $pfOutput = rtrim($pfOutput, '&');
    return md5($pfOutput);
}

function getBusinessQueueStats($pdo, $business_id) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM bookings 
        WHERE business_id = ? AND booking_date = CURDATE() 
        AND status IN ('pending', 'confirmed', 'rescheduled')
    ");
    $stmt->execute([$business_id]);
    $queue_count = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT slot_duration FROM businesses WHERE id = ?");
    $stmt->execute([$business_id]);
    $slot_duration = $stmt->fetchColumn() ?: 30;

    $wait_minutes = $queue_count * $slot_duration;

    return ['queue' => $queue_count, 'wait_minutes' => $wait_minutes];
}

define('ENCRYPTION_KEY', '7xK9#mP2$vL8@nQ5!wR3^yT6&zU1*XcV4');

function encryptData($data) {
    if (empty($data)) return null;
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('AES-256-CBC'));
    $encrypted = openssl_encrypt($data, 'AES-256-CBC', ENCRYPTION_KEY, 0, $iv);
    return base64_encode($encrypted . '::' . base64_encode($iv));
}

function decryptData($encryptedData) {
    if (empty($encryptedData)) return null;
    $decoded = base64_decode($encryptedData);
    if ($decoded === false) return null;
    $parts = explode('::', $decoded, 2);
    if (count($parts) != 2) return null;
    $encrypted = $parts[0];
    $iv = base64_decode($parts[1]);
    return openssl_decrypt($encrypted, 'AES-256-CBC', ENCRYPTION_KEY, 0, $iv);
}

function getAvailableSlots($pdo, $business_id, $booking_date) {
    $stmt = $pdo->prepare("SELECT slot_duration, slots_per_hour, monday_open, monday_close, tuesday_open, tuesday_close, wednesday_open, wednesday_close, thursday_open, thursday_close, friday_open, friday_close, saturday_open, saturday_close, sunday_open, sunday_close FROM businesses WHERE id = ?");
    $stmt->execute([$business_id]);
    $business = $stmt->fetch();
    
    $day_of_week = strtolower(date('l', strtotime($booking_date)));
    $open_field = $day_of_week . '_open';
    $close_field = $day_of_week . '_close';
    
    $open_time = $business[$open_field] ?? null;
    $close_time = $business[$close_field] ?? null;
    $slot_duration = $business['slot_duration'] ?? 30;
    $slots_per_hour = $business['slots_per_hour'] ?? 1;
    
    if (!$open_time || !$close_time) {
        return []; 
    }
    
    // Generate all time slots
    $slots = [];
    $start = strtotime($open_time);
    $end = strtotime($close_time);
    
    for ($i = $start; $i < $end; $i += $slot_duration * 60) {
        $slot_time = date('H:i', $i);
        $slots[] = $slot_time;
    }
    
    // Get booked slots for this date
    $stmt = $pdo->prepare("
        SELECT time_slot FROM bookings 
        WHERE business_id = ? AND booking_date = ? 
        AND status NOT IN ('cancelled', 'completed')
    ");
    $stmt->execute([$business_id, $booking_date]);
    $booked_slots = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $booked_counts = array_count_values($booked_slots);
    $is_today = ($booking_date === date('Y-m-d'));
    $current_time = time();

    // Mark available/unavailable
    $available_slots = [];
    foreach ($slots as $slot) {
        $slot_timestamp = strtotime($booking_date . ' ' . $slot);
        
        // 10-minute buffer logic
        if ($is_today && ($slot_timestamp < ($current_time + 600))) {
            $available_slots[$slot] = false;
        } else {
            $count = isset($booked_counts[$slot]) ? $booked_counts[$slot] : 0;
            $available_slots[$slot] = ($count < $slots_per_hour);
        }
    }
    
    return $available_slots;
}

function getRegionFromLocation($pdo, $user_lat, $user_lng) {
    // Fetch all businesses with coordinates
    $stmt = $pdo->prepare("
        SELECT id, name, region, latitude, longitude,
               ( 6371 * acos( cos( radians(?) ) * cos( radians( latitude ) ) 
               * cos( radians( longitude ) - radians(?) ) + sin( radians(?) ) 
               * sin( radians( latitude ) ) ) ) AS distance
        FROM businesses
        WHERE latitude IS NOT NULL AND longitude IS NOT NULL
        AND region IS NOT NULL AND region != ''
        HAVING distance < 50
        ORDER BY distance
        LIMIT 1
    ");
    $stmt->execute([$user_lat, $user_lng, $user_lat]);
    $closest = $stmt->fetch();
    
    if ($closest) {
        return $closest['region'];
    }
    
    return null;
}

function formatNumber($number) {
    if ($number < 1000) {
        return (string)$number;
    } elseif ($number < 10000) {
        return round($number / 1000, 1) . 'K';
    } elseif ($number < 100000) {
        return round($number / 1000) . 'K';
    } else {
        return round($number / 1000) . 'K+';
    }
}

function getBusinessMonthlyBookingCount($pdo, $business_id) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM bookings 
        WHERE business_id = ? 
        AND status = 'completed' 
        AND MONTH(booking_date) = MONTH(CURDATE()) 
        AND YEAR(booking_date) = YEAR(CURDATE())
    ");
    $stmt->execute([$business_id]);
    return (int)$stmt->fetchColumn();
}

function getBusinessBookingLimit($subscription_plan) {
    switch ($subscription_plan) {
        case 'low':
            return 14;
        case 'medium':
            return 24;
        case 'high':
            return 999999; // Unlimited
        default:
            return 14;
    }
}
?>