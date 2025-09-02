<?php

class nextcloud{
// Configuration
	private $nextcloud_url = 'https://your-nextcloud-instance.com';
	private $admin_username = 'admin';
	private $admin_password = 'admin_password';
	public $err;

	function __construct($nextcloud_url,$admin_username,$admin_password){
		$this->nextcloud_url=$nextcloud_url;
		$this->admin_username=$admin_username;
		$this->admin_password=$admin_password;
		
	}
	
	// Create user function
	function createUser($newUser, $newPass,$displayName) {
	    $ch = curl_init();
	    curl_setopt($ch, CURLOPT_URL, $this->nextcloud_url . '/ocs/v1.php/cloud/users');
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_VERBOSE, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
		    'userid' => $newUser,
		    'password' => $newPass,
		    'displayName' => $displayName,
		    'email'=>$newUser
		]));
		curl_setopt($ch, CURLOPT_HTTPHEADER, ['OCS-APIRequest: true']);
		
	    curl_setopt($ch, CURLOPT_USERPWD, $this->admin_username . ':' . $this->admin_password);
	
	    $response = curl_exec($ch);
	    if (curl_errno($ch)) {
	        return 'Error: ' . curl_error($ch);
	    } else {
	        return 'Response: ' . $response;
	    }
	    curl_close($ch);
	}
	
	function existsUser($username){
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->nextcloud_url."/ocs/v1.php/cloud/users/$username");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
		    'OCS-APIRequest: true'
		]);
		curl_setopt($ch, CURLOPT_USERPWD, $this->admin_username . ':' . $this->admin_password);
		$response = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		$resultado=false;
		
		if($httpCode === 200){
			$xml = simplexml_load_string($response);
    		if ($xml !== false && $xml->data->id==$username) {
				$resultado= true;
    		}
		}
		return $resultado;
		
	}
	
	function changeUserPropery($user, $property, $value) {
	    $ch = curl_init();
	    curl_setopt($ch, CURLOPT_URL, $this->nextcloud_url . '/ocs/v1.php/cloud/users/' . $user);
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
	    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
	        'key' => $property,
	        'value' => $value,
	    ]));
	    curl_setopt($ch, CURLOPT_HTTPHEADER, [
	        'OCS-APIRequest: true',
	        'Content-Type: application/x-www-form-urlencoded',
	    ]);
	   	curl_setopt($ch, CURLOPT_USERPWD, $this->admin_username . ':' . $this->admin_password);
	
	    $response = curl_exec($ch);
	    if (curl_errno($ch)) {
	        $this->err=curl_error($ch);
	    } 
	    curl_close($ch);
	    return $response;
	}
	
	
	function disableNextcloudUser($user) {
	    $ch = curl_init();
	    curl_setopt($ch, CURLOPT_URL, $this->nextcloud_url . '/ocs/v1.php/cloud/users/' . $user . '/disable');
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	    curl_setopt($ch, CURLOPT_POST, 1);
	    curl_setopt($ch, CURLOPT_HTTPHEADER, [
	        'OCS-APIRequest: true',
	        'Content-Type: application/x-www-form-urlencoded',
	    ]);
	    curl_setopt($ch, CURLOPT_USERPWD, $this->admin_username . ':' . $this->admin_password);
	
	    $response = curl_exec($ch);
	    if (curl_errno($ch)) {
	        echo 'Error: ' . curl_error($ch);
	    } else {
	        echo 'Response: ' . $response;
	    }
	}
	function deleteNextcloudUser( $user) {
	    $ch = curl_init();
	    curl_setopt($ch, CURLOPT_URL, $this->nextcloud_url  . '/ocs/v1.php/cloud/users/' . $user);
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
	    curl_setopt($ch, CURLOPT_HTTPHEADER, [
	        'OCS-APIRequest: true',
	        'Content-Type: application/x-www-form-urlencoded',
	    ]);
	    curl_setopt($ch, CURLOPT_USERPWD, $this->admin_username . ':' . $this->admin_password);
	
	    $response = curl_exec($ch);
	    if (curl_errno($ch)) {
	        echo 'Error: ' . curl_error($ch);
	    } else {
	        echo 'Response: ' . $response;
	    }
	}
	function backupUserData($url, $admin_user, $admin_pass, $user, $backup_location) {
	    $user_data_url = $this->nextcloud_url  . $user . '/';
	    $backup_folder = $backup_location . $user . '_backup/';
	
	    if (!is_dir($backup_folder)) {
	        mkdir($backup_folder, 0777, true);
	    }
	
	    $ch = curl_init();
	    curl_setopt($ch, CURLOPT_URL, $user_data_url);
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	    curl_setopt($ch, CURLOPT_HTTPHEADER, [
	        'Content-Type: application/x-www-form-urlencoded',
	    ]);
	    curl_setopt($ch, CURLOPT_USERPWD, $this->admin_username . ':' . $this->admin_password);
	
	    $response = curl_exec($ch);
	    if (curl_errno($ch)) {
	        echo 'Error: ' . curl_error($ch);
	    } else {
	        // Save user data to the backup location
	        file_put_contents($backup_folder . 'user_data.zip', $response);
	        echo 'User data backed up successfully.';
	    }
	    curl_close($ch);
	}
}