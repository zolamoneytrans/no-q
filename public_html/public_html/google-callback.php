<?php
session_start();
require_once 'db_connect.php';
require_once 'vendor/autoload.php';

if (!isset($_GET['state']) || $_GET['state'] !== ($_SESSION['google_csrf_token'] ?? '')) {
    die('Invalid state parameter');
}
unset($_SESSION['google_csrf_token']);

$client = new Google_Client();
$client->setClientId('921003921220-05jlk1gnapdk39422t2h252gori1a56f');
$client->setClientSecret('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');
$client->setRedirectUri('http://localhost/carwash-connect/google-callback.php');

if (isset($_GET['code'])) {
    // Authenticate using the authorization code (older method)
    $token = $client->authenticate($_GET['code']);
    if (isset($client->getAccessToken()['error'])) {
        die('Error fetching token');
    }
    $client->setAccessToken($token);

    // Get user info using the old service class
    $oauth = new Google_Service_Oauth2($client);
    $userInfo = $oauth->userinfo->get();

    $google_id = $userInfo->id;
    $email = $userInfo->email;
    $name = $userInfo->name;
    $picture = $userInfo->picture;

    // Check if user exists by google_id
    $stmt = $pdo->prepare("SELECT * FROM users WHERE google_id = ?");
    $stmt->execute([$google_id]);
    $user = $stmt->fetch();

    if (!$user) {
        // Check if email already exists (maybe registered via email)
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user) {
            // Link this google_id to existing account
            $stmt = $pdo->prepare("UPDATE users SET google_id = ?, google_picture = ? WHERE id = ?");
            $stmt->execute([$google_id, $picture, $user['id']]);
        } else {
            // Create new user
            $stmt = $pdo->prepare("INSERT INTO users (name, email, google_id, google_picture) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $email, $google_id, $picture]);
            $user_id = $pdo->lastInsertId();
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
        }
    }

    // Log the user in
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_points'] = $user['points'];

    $redirect = $_SESSION['login_redirect'] ?? 'user-dashboard.php';
    unset($_SESSION['login_redirect']);
    header('Location: ' . $redirect);
    exit;
} else {
    die('No authorization code received');
}