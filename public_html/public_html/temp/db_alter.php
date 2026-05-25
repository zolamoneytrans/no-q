<?php
require_once __DIR__ . '/../db_connect.php';
try {
    $pdo->exec("ALTER TABLE businesses ADD COLUMN slots_per_hour INT DEFAULT 1;");
    echo "Column slots_per_hour added successfully.";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Column slots_per_hour already exists.";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
?>
