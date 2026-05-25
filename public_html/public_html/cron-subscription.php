<?php


require_once 'db_connect.php';

// Subscription fees
$subscription_fees = [
    'low' => 250,
    'medium' => 350,
    'high' => 500
];

$log = [];

// Get all active businesses
$stmt = $pdo->prepare("
    SELECT id, name, email, wallet_balance, subscription_plan 
    FROM businesses 
    WHERE is_active = 1 AND is_approved = 1
");
$stmt->execute();
$businesses = $stmt->fetchAll();

foreach ($businesses as $business) {
    $fee = $subscription_fees[$business['subscription_plan']];
    $new_balance = $business['wallet_balance'] - $fee;
    
    if ($business['wallet_balance'] >= $fee) {
        // Deduct fee
        $update = $pdo->prepare("UPDATE businesses SET wallet_balance = ? WHERE id = ?");
        $update->execute([$new_balance, $business['id']]);
        
        // Record transaction
        $trans = $pdo->prepare("
            INSERT INTO transactions (business_id, amount, type, description, created_at) 
            VALUES (?, ?, 'debit', ?, NOW())
        ");
        $trans->execute([$business['id'], $fee, "Monthly subscription fee - {$business['subscription_plan']} plan"]);
        
        $log[] = "✅ {$business['name']}: R{$fee} deducted. New balance: R{$new_balance}";
        
        // Send email receipt
        $subject = "Monthly Subscription Fee Deducted - No Q";
        $body = "
            <h2>Monthly Subscription Fee</h2>
            <p>Dear {$business['name']},</p>
            <p>Your monthly subscription fee of <strong>R{$fee}</strong> has been deducted from your wallet.</p>
            <p><strong>Plan:</strong> " . ucfirst($business['subscription_plan']) . " Demand<br>
            <strong>Previous Balance:</strong> R{$business['wallet_balance']}<br>
            <strong>Amount Deducted:</strong> R{$fee}<br>
            <strong>New Balance:</strong> R{$new_balance}</p>
            <p>If you have any questions, please contact support.</p>
        ";
        sendEmail($business['email'], $subject, $body);
        
    } else {
        // Insufficient funds - send warning
        $log[] = "⚠️ {$business['name']}: INSUFFICIENT FUNDS. Balance: R{$business['wallet_balance']}, Needed: R{$fee}";
        
        $subject = "⚠️ Subscription Payment Failed - No Q";
        $body = "
            <h2>Subscription Payment Failed</h2>
            <p>Dear {$business['name']},</p>
            <p>We attempted to deduct your monthly subscription fee of <strong>R{$fee}</strong>, but your wallet balance is insufficient.</p>
            <p><strong>Current Balance:</strong> R{$business['wallet_balance']}<br>
            <strong>Amount Due:</strong> R{$fee}<br>
            <strong>Shortfall:</strong> R" . ($fee - $business['wallet_balance']) . "</p>
            <p>Please top up your wallet to avoid service interruption.</p>
        ";
        sendEmail($business['email'], $subject, $body);
        
       
    }
}

// Send admin summary
$admin_email = "aosolvers@carwashes.africa";
$summary = implode("\n", $log);
sendEmail($admin_email, "Monthly Subscription Summary", "<pre>{$summary}</pre>");

echo "Subscription run completed.\n";
print_r($log);
?>