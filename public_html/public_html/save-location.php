<?php
session_start();

if (isset($_POST['lat']) && isset($_POST['lng'])) {
    $_SESSION['user_lat'] = (float)$_POST['lat'];
    $_SESSION['user_lng'] = (float)$_POST['lng'];
}

echo 'ok';
?>