<?php
require_once 'db_connect.php';

try {
    $pdo->exec("ALTER TABLE businesses ADD COLUMN public_email VARCHAR(255) NULL AFTER email");
    echo "Added public_email column.<br>";
} catch (PDOException $e) {
    echo "public_email might already exist or error: " . $e->getMessage() . "<br>";
}

try {
    $pdo->exec("ALTER TABLE businesses ADD COLUMN public_phone VARCHAR(50) NULL AFTER phone");
    echo "Added public_phone column.<br>";
} catch (PDOException $e) {
    echo "public_phone might already exist or error: " . $e->getMessage() . "<br>";
}

echo "Database update completed successfully.";
?>
