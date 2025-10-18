<?php
// Initialize the session
/*require_once __DIR__ . '/vendor/autoload.php';
require 'config.php';

$m = new MongoDB\Client($config['mongo_connection_string']);
use Altmetric\MongoSessionHandler;
$sessions = $m->{$config['sitedb']}->sessions;///aguassssssssssssssssssssssssssssssssssss
$handler = new MongoSessionHandler($sessions);
session_set_save_handler($handler);
session_name(str_replace('.','_',$config['cookie_domain']));
session_set_cookie_params(0, '/', $config['cookie_domain'],$config['forcehttps'],false);
session_start();
*/

// Update the following variables
$google_oauth_client_id = $config['google_oauth_client_id'];
$google_oauth_client_secret =  $config['google_oauth_client_secret'];
$google_oauth_redirect_uri = 'https://' . $config['cookie_domain'] . '/login-google/oauth';
$google_oauth_version = 'v3';
// If the captured code param exists and is valid
if (isset($_GET['code']) && !empty($_GET['code'])) {
    // Execute cURL request to retrieve the access token
    $params = [
        'code' => $_GET['code'],
        'client_id' => $google_oauth_client_id,
        'client_secret' => $google_oauth_client_secret,
        'redirect_uri' => $google_oauth_redirect_uri,
        'grant_type' => 'authorization_code'
    ];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://accounts.google.com/o/oauth2/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    $response = json_decode($response, true);
    // Make sure access token is valid
    if (isset($response['access_token']) && !empty($response['access_token'])) {
        // Execute cURL request to retrieve the user info associated with the Google account
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://www.googleapis.com/oauth2/' . $google_oauth_version . '/userinfo');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $response['access_token']]);
        $token = $response['access_token'];
        $response = curl_exec($ch);
        curl_close($ch);
        $profile = json_decode($response, true);
        // Make sure the profile data exists
        if (isset($profile['email'])) {
            $google_name_parts = [];
            $google_name_parts[] = isset($profile['given_name']) ? preg_replace('/[^a-zA-Z0-9]/s', '', $profile['given_name']) : '';
            $google_name_parts[] = isset($profile['family_name']) ? preg_replace('/[^a-zA-Z0-9]/s', '', $profile['family_name']) : '';
            // Authenticate the user
            session_regenerate_id();
            $_SESSION['google_loggedin'] = TRUE;
            $_SESSION['google_email'] = $profile['email'];
            $_SESSION['google_name'] = implode(' ', $google_name_parts);
            $_SESSION['google_picture'] = isset($profile['picture']) ? $profile['picture'] : '';

            $useroauth = $m->{$config['sitedb']}->users->findOne(['username' => $profile['email']]);
            if (!empty($useroauth->_id)) {
                $_SESSION['user'] = (string)$useroauth->_id;
            } else {
                $newid = new MongoDB\BSON\ObjectID();
                $doc = [
                    '_id' =>    $newid,
                    'username' => $profile['email'],
                    'name' => implode(' ', $google_name_parts),
                ];
                $m->{$config['sitedb']}->users->insertOne($doc);
                $_SESSION['user'] = (string)$newid;
            }
            $_SESSION['oauth'] = [
                'provider' => 'google',
                'picture' => isset($profile['picture']) ? $profile['picture'] : '',
                'name' => implode(' ', $google_name_parts),
                'token' => $token,
                //'tokenExpires'=>$token->getExpires(),
            ];
            session_write_close();
            // Redirect to profile page
            if (!empty($_SESSION['login_redirect'])) {
                $redir = $_SESSION['login_redirect'];
                unset($_SESSION['login_redirect']);
                if (strpos($redir, '//') !== 0) {
                    $redir .= '?uid=' . encryptSessionId($_SESSION['user'], SESSION_KEY);
                }
                header('Location: ' . $redir);
                exit;
            }
            header('Location: /');

            exit;
        } else {
            exit('Could not retrieve profile information! Please try again later!');
        }
    } else {
        exit('Invalid access token! Please try again later!');
    }
} else {
    // Define params and redirect to Google Authentication page
    $params = [
        'response_type' => 'code',
        'client_id' => $google_oauth_client_id,
        'redirect_uri' => $google_oauth_redirect_uri,
        'scope' => 'https://www.googleapis.com/auth/userinfo.email https://www.googleapis.com/auth/userinfo.profile',
        'access_type' => 'offline',
        'prompt' => 'consent'
    ];
    header('Location: https://accounts.google.com/o/oauth2/auth?' . http_build_query($params));
    exit;
}
