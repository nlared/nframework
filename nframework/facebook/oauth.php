<?
//require 'include.php';

$provider = new \League\OAuth2\Client\Provider\Facebook([
    'clientId'           => $config['facebook_oauth_client_id'],
    'clientSecret'       => $config['facebook_oauth_client_secret'],
    'redirectUri'        => 'https://'.$config['cookie_domain'].'/login-facebook/oauth',
    'graphApiVersion'    => 'v2.10',
]);

if (!isset($_GET['code'])) {
    // If no authorization code, get one
    $authUrl = $provider->getAuthorizationUrl([
        'scope' => ['email'],
    ]);
    $_SESSION['oauth2state'] = $provider->getState();
    //echo '<a href="' . $authUrl . '">Log in with Facebook!</a>';
    header('Location: '.$authUrl);
    exit;
} elseif (empty($_GET['state']) || ($_GET['state'] !== $_SESSION['oauth2state'])) {
    unset($_SESSION['oauth2state']);
    echo 'Invalid state.';
    exit;
}

// Get an access token (using the authorization code grant)
$token = $provider->getAccessToken('authorization_code', [
    'code' => $_GET['code'],
]);

// Optional: Look up user profile data
try {
    $user = $provider->getResourceOwner($token);
    //printf('Hello %s!', $user->getFirstName());
    
    
    $useroauth=$m->{$config['sitedb']}->users->findOne(['username'=>$user->getEmail()]);
    if(!empty($useroauth->_id)){
    	$_SESSION['user']=(string)$useroauth->_id;
    }else{
    	$newid=new MongoDB\BSON\ObjectID();
		$doc=[
			'_id'=>	$newid,
			'username'=>$user->getEmail(),
			'name'=>$user->getName(),
		];
		$m->{$config['sitedb']}->users->insertOne($doc);
		$_SESSION['user']=(string)$newid;
    }
    
    
    $_SESSION['oauth']=[
    	'provider'=>'facebook',
    	'picture'=>$user->getPictureUrl(),
    	'name'=>$user->getName(),
    	'token'=>$token,
    	//'tokenExpires'=>$user->getExpires(),
    ];
    session_write_close();
    // Redirect to profile page
    header('Location: /');
    /*echo '<pre>';
    var_dump($user); // User details
    echo '</pre>';*/
} catch (\Exception $e) {
    exit('Failed to get user details.');
}
/*
// Interact with an API on the user's behalf
echo '<pre>';
var_dump($token->getToken()); // Access token
var_dump($token->getExpires()); // Expiration time (epoch)
echo '</pre>';
//*/