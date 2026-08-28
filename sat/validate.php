<?
require 'include.php';
require 'RSAUtils.php';
$developermode=true;
$nocert=$_GET['nocert'];
$sello=$_GET['sello'];
//$sello2=$_GET['sello2'];
/*$developermode=true;
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
//*/

$nframework->usecommon=false;
function hex2str($hex) {
    $str = '';
	for($i=0;$i<strlen($hex);$i+=2) $str .= chr(hexdec(substr($hex,$i,2)));
    return $str;
}

function hex2asc($myin) {
	for ($i=0; $i<strlen($myin)/2; $i++) {
		$myout.=chr(base_convert(substr($myin,$i*2,2),16,10));
	}
	return $myout;
}

if(!file_exists('certs/'.$nocert.'.pem')){
	exec('openssl x509 -inform DER -outform PEM -in '.__DIR__.'/certs/'.$nocert.' -out '.__DIR__.'/certs/'.$nocert.'.pem',$consola4);
}
$certf=file_get_contents('certs/'.$nocert.'.pem');
$pubkeyid=openssl_pkey_get_public($certf);
//$pubkeyid=openssl_csr_get_public_key(file_get_contents('./certs/'.$nocert));
if ($pubkeyid===false){
	$result['error']= 'errorcert:';
}

 $details = openssl_pkey_get_details($pubkeyid);
 //$result['d']=$details;
 if (!array_key_exists('rsa', $details)) {
     throw new \Exception('Unable to load the key');
 }
//echo $sello;
$sello=base64_decode($sello);
$oo=base64_encode(hex2bin($sello));

$result['sello']=$oo;


$ok = openssl_verify($_SESSION['logintoken'], hex2bin($sello), $pubkeyid,OPENSSL_ALGO_SHA256 );
if ($ok == 1) {
    $result['status']= "buena";
    if(str_contains($_SESSION['logintoken'],'||')){
    	$result['donde']='aqui1';
    }else{
    	$result['donde']='aqui2';
    	$certd=openssl_x509_parse($certf,true);
    	file_put_contents('certs/'.$nocert.'.json',json_encode($certd));
    	$username=(
    		str_contains($certd['subject']['x500UniqueIdentifier'],'/')?
    		trim(substr($certd['subject']['x500UniqueIdentifier'],0,strpos($certd['subject']['x500UniqueIdentifier'],'/')))
    		:
    		trim($certd['subject']['x500UniqueIdentifier'])
    	);
    	$m->{$config['sitedb']}->users->updateOne([
    		'username'=>$username,
    		],[
    		'$set'=>[
    			'username'=>$username,
    			'nocert'=>$nocert,
    			'razonsocial'=>$certd['subject']['name'],
    			'curp'=>$certd['subject']['serialNumber']
    			]
    		],['upsert'=>true]);
    	
    	$user= new User([
			'username'=> $username,
		]);
		/*$tmp=(array)$user->sessions;
		$tmp[]=session_id();
		$user->sessions=array_values(array_unique($tmp));
    	//*/
		$_SESSION['user']=$user->_id;
		
    }
    
} elseif ($ok == 0) {
    $result['status']= "mala";
    $result['ok']=$ok;
    $result['signature']=$sello;
    $result['data']=$_SESSION['logintoken'];
} else {
    $result['status']= "alarmante, error verificando la firma";
}
/*
while ($msg = openssl_error_string() !== false) {
    echo $msg;
}//*/

echo json_encode($result);
