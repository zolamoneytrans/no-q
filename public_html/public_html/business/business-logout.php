<?php
session_start();
session_destroy();
header('Location: business-login.php');
exit;