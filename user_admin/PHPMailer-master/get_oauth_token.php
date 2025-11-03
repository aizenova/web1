<?php

require 'vendor/autoload.php';

use League\OAuth2\Client\Provider\Google;
use Hayageek\OAuth2\Client\Provider\Yahoo;
use Stevenmaguire\OAuth2\Client\Provider\Microsoft;
use Greew\OAuth2\Client\Provider\Azure;

session_start();

$providerName = '';
$clientId = '';
$clientSecret = '';
$tenantId = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['provider'])) {
    $providerName = $_POST['provider'];
    $clientId = isset($_POST['clientId']) ? $_POST['clientId'] : '';
    $clientSecret = isset($_POST['clientSecret']) ? $_POST['clientSecret'] : '';
    $tenantId = isset($_POST['tenantId']) ? $_POST['tenantId'] : '';
    $_SESSION['provider'] = $providerName;
    $_SESSION['clientId'] = $clientId;
    $_SESSION['clientSecret'] = $clientSecret;
    $_SESSION['tenantId'] = $tenantId;
} elseif (!empty($_SESSION['provider'])) {
    $providerName = $_SESSION['provider'];
    $clientId = $_SESSION['clientId'];
    $clientSecret = $_SESSION['clientSecret'];
    $tenantId = $_SESSION['tenantId'];
} else {
    // Show form if no provider selected and no code received
    if (!isset($_GET['code'])) {
        ?>
<html>
<body>
<form method="post">
    <h1>Select Provider</h1>
    <input type="radio" name="provider" value="Google" id="providerGoogle">
    <label for="providerGoogle">Google</label><br>
    <input type="radio" name="provider" value="Yahoo" id="providerYahoo">
    <label for="providerYahoo">Yahoo</label><br>
    <input type="radio" name="provider" value="Microsoft" id="providerMicrosoft">
    <label for="providerMicrosoft">Microsoft</label><br>
    <input type="radio" name="provider" value="Azure" id="providerAzure">
    <label for="providerAzure">Azure</label><br>
    <h1>Enter id and secret</h1>
    <p>These details are obtained by setting up an app in your provider's developer console.
    </p>
    <p>ClientId: <input type="text" name="clientId"><p>
    <p>ClientSecret: <input type="text" name="clientSecret"></p>
    <p>TenantID (only relevant for Azure): <input type="text" name="tenantId"></p>
    <input type="submit" value="Continue">
</form>
</body>
</html>
        <?php
        exit;
    }
}

$params = [
    'clientId' => $clientId,
    'clientSecret' => $clientSecret,
    'redirectUri' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'],
    'accessType' => 'offline'
];

$options = [];
$provider = null;

switch ($providerName) {
    case 'Google':
        $provider = new Google($params);
        $options = [
            'scope' => [
                'https://mail.google.com/'
            ]
        ];
        break;
    case 'Yahoo':
        $provider = new Yahoo($params);
        break;
    case 'Microsoft':
        $provider = new Microsoft($params);
        $options = [
            'scope' => [
                'wl.imap',
                'wl.offline_access'
            ]
        ];
        break;
    case 'Azure':
        // Azure provider expects 'tenant' key, not 'tenantId'
        $params['tenant'] = $tenantId;
        unset($params['tenantId']);
        $provider = new Azure($params);
        $options = [
            'scope' => [
                'https://outlook.office.com/SMTP.Send',
                'offline_access'
            ]
        ];
        break;
    default:
        exit('Provider missing or invalid');
}

if ($provider === null) {
    exit('Provider missing');
}

if (!isset($_GET['code'])) {
    // If we don't have an authorization code then get one
    $authUrl = $provider->getAuthorizationUrl($options);
    $_SESSION['oauth2state'] = $provider->getState();
    header('Location: ' . $authUrl);
    exit;
}

// Check given state against previously stored one to mitigate CSRF attack
if (empty($_GET['state']) || !isset($_SESSION['oauth2state']) || ($_GET['state'] !== $_SESSION['oauth2state'])) {
    unset($_SESSION['oauth2state']);
    unset($_SESSION['provider']);
    exit('Invalid state');
}

unset($_SESSION['provider']);

// Try to get an access token (using the authorization code grant)
try {
    $token = $provider->getAccessToken(
        'authorization_code',
        [
            'code' => $_GET['code']
        ]
    );
    // Use this to interact with an API on the users behalf
    // Use this to get a new access token if the old one expires
    echo 'Refresh Token: ', htmlspecialchars($token->getRefreshToken());
} catch (Exception $e) {
    exit('Failed to get access token: ' . $e->getMessage());
}
