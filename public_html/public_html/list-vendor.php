<?php
$dir = __DIR__ . '/vendor/phpmailer/';
echo "Contents of: " . $dir . "<br>";
if (is_dir($dir)) {
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            echo "- " . $file . "<br>";
            if (is_dir($dir . $file)) {
                $subfiles = scandir($dir . $file);
                foreach ($subfiles as $sub) {
                    if ($sub != '.' && $sub != '..') {
                        echo "  -- " . $sub . "<br>";
                    }
                }
            }
        }
    }
} else {
    echo "Folder does not exist!";
}
?>