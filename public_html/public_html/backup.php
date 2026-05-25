<?php
$files = [
    'index.php', 'user-login.php', 'user-signup.php', 'user-dashboard.php',
    'book.php', 'business-profile.php', 'search.php', 'logout.php',
    'business/login.php', 'business/signup.php', 'business/dashboard.php',
    'admin/login.php', 'admin/dashboard.php', 'admin/users.php', 'admin/businesses.php'
];

$backup_dir = 'backup_' . date('Y-m-d_H-i-s');
mkdir($backup_dir);

foreach ($files as $file) {
    if (file_exists($file)) {
        copy($file, $backup_dir . '/' . str_replace('/', '_', $file));
    }
}
echo "Backup created in folder: $backup_dir";