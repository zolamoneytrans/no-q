<?php
session_start();
require_once 'vendor/autoload.php';

$client = new Google_Client();
$client->setClientId('921003921220-05jlk1gnapdk39422t2h252gori1a56f');      // Same as above
$client->setClientSecret('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');
$client->setRedirectUri('http://localhost/carwash-connect/google-callback.php');
$client->addScope('email');
$client->addScope('profile');

if (isset($_GET['redirect'])) {
    $_SESSION['login_redirect'] = $_GET['redirect'];
}

$csrf_token = bin2hex(random_bytes(32));
$_SESSION['google_csrf_token'] = $csrf_token;
$client->setState($csrf_token);

$auth_url = $client->createAuthUrl();
header('Location: ' . filter_var($auth_url, FILTER_SANITIZE_URL));
exit;