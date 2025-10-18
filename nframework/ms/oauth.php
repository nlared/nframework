<?php

/**
 *  Copyright (c) Microsoft. All rights reserved. Licensed under the MIT license.
 *  See LICENSE in the project root for license information.
 *
 *  PHP version 5
 *
 *  @category Code_Sample
 *  @package  php-connect-rest-sample
 *  @author   Ricardo Loo <ricardol@microsoft.com>
 *  @license  MIT License
 *  @link     http://github.com/microsoftgraph/php-connect-rest-sample
 */

/*! 
    @abstract The page that the user will be redirected to after 
              Azure Active Directory (AD) finishes the authentication flow.
 */
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



function normalizar($texto)
{
	$texto = str_replace(['á', 'é', 'í', 'ó', 'ú', 'ñ', 'Ñ'], ['A', 'E', 'I', 'O', 'U', 'N', 'N'], strtoupper(trim($texto)));
	while (strpos($texto, '  ') !== false) {
		$texto = str_replace('  ', ' ', $texto);
	}
	return $texto;
}


function normalizar2($texto)
{
	$texto = strtoupper(trim($texto));
	while (strpos($texto, '  ') !== false) {
		$texto = str_replace('  ', ' ', $texto);
	}
	return $texto;
}

use Microsoft\Graph\Connect\Constants;

require 'src/Constants.php';

print_r($config);

$provider = new \League\OAuth2\Client\Provider\GenericProvider([
	'clientId'                => $config['microsoft_oauth_client_id'],
	'clientSecret'            => $config['microsoft_oauth_client_secret'],
	'redirectUri'             => 'https://' . $_SERVER['HTTP_HOST'] . '/login-microsoft/oauth',
	'urlAuthorize'            => Constants::AUTHORITY_URL . Constants::AUTHORIZE_ENDPOINT,
	'urlAccessToken'          => Constants::AUTHORITY_URL . Constants::TOKEN_ENDPOINT,
	'urlResourceOwnerDetails' => '',
	'scopes'                  => Constants::SCOPES
]);

if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['code']) && !isset($_GET['error'])) {
	$authorizationUrl = $provider->getAuthorizationUrl();

	// The OAuth library automaticaly generates a state value that we can
	// validate later. We just save it for now.
	$_SESSION['state'] = $provider->getState();

	header('Location: ' . $authorizationUrl);
	exit();
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['error'])) {
	// Answer from the authentication service contains an error.
	printf('Something went wrong while authenticating: [%s] %s', $_GET['error'], $_GET['error_description']);
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['code'])) {
	// Validate the OAuth state parameter
	if (empty($_GET['state']) || ($_GET['state'] !== $_SESSION['state'])) {
		//  require 'common.php';
		session_destroy();
		//unset($_SESSION['state']);
		exit('State value does not match the one initially sent <a href="/" class="button">Inicio</a>');
	}

	// With the authorization code, we can retrieve access tokens and other data.
	try {
		// Get an access token using the authorization code grant
		$accessToken = $provider->getAccessToken('authorization_code', [
			'code'     => $_GET['code']
		]);
		$_SESSION['access_token'] = $accessToken->getToken();
		// The id token is a JWT token that contains information about the user
		// It's a base64 coded string that has a header, payload and signature
		$idToken = $accessToken->getValues()['id_token'];
		$decodedAccessTokenPayload = base64_decode(
			explode('.', $idToken)[1]
		);
		$jsonAccessTokenPayload = json_decode($decodedAccessTokenPayload, true);
		// The following user properties are needed in the next page
		$_SESSION['preferred_username'] = $jsonAccessTokenPayload['preferred_username'];
		$_SESSION['given_name'] = $jsonAccessTokenPayload['name'];
		$useroauth = $m->{$config['sitedb']}->users->findOne([
			'$or' => [
				['username' => $jsonAccessTokenPayload['preferred_username']],
				['nombrenormalizado' => normalizar($jsonAccessTokenPayload['name'])]
			]
		]);
		if (!empty($useroauth->_id)) {
			file_put_contents('ola.txt', $decodedAccessTokenPayload);
			if ($useroauth->username == '') {
				$m->{$config['sitedb']}->users->updateOne(
					['nombrenormalizado' => normalizar($jsonAccessTokenPayload['name'])],
					[
						'$set' => [
							'username' => $jsonAccessTokenPayload['preferred_username']
						]
					]
				);
				$_SESSION['user'] = $jsonAccessTokenPayload['preferred_username'];
			} else {
				$m->{$config['sitedb']}->users->updateOne([
					'username' => $jsonAccessTokenPayload['preferred_username']
				], [
					'$set' => [
						'nombrecompleto' => normalizar2($jsonAccessTokenPayload['name']),
						'nombrenormalizado' => normalizar($jsonAccessTokenPayload['name']),
						'temporal' => true,
					]
				]);


				$_SESSION['user'] = (string)$useroauth->_id;
			}
			session_write_close();
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
		} else {
			if (strpos($jsonAccessTokenPayload['preferred_username'], '@uadec.edu.mx') !== false) {
				$newid = new MongoDB\BSON\ObjectID();
				$m->{$config['sitedb']}->users->insertOne([
					'nombrecompleto' => normalizar2($jsonAccessTokenPayload['name']),
					'nombrenormalizado' => normalizar($jsonAccessTokenPayload['name']),
					'temporal' => true,
					'_id' => $newid,
					'username' => $jsonAccessTokenPayload['preferred_username']
				]);
				$_SESSION['user'] = (string)$newid;
				header('Location: /');
				echo '<a href="/">Presiona aqui para continuar</a>';
			} else {
				session_destroy();
				header('Location: /ms/logout.php');
				echo 'Esta no es una cuenta oficial<a href="/account/logout.php">Inicio</a>';
			}
		}
		exit();
	} catch (League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
		printf('Something went wrong, couldn\'t get tokens: %s', $e->getMessage());
		session_destroy();
		//require 'common.php';
		echo 'Intente de nuevo <a href="/" class="button">Inicio</a>';
	}
}
