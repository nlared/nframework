<?
require 'include.php';

$nframework->usecommon=false;
$result=[];

// nocert proviene del cliente y se usa como nombre de archivo: solo dígitos/hex.
$nocert=is_string($_GET['nocert'] ?? null) ? $_GET['nocert'] : '';
$sello=is_string($_GET['sello'] ?? null) ? $_GET['sello'] : '';
if(!preg_match('/^[0-9A-Fa-f]{1,64}$/',$nocert) || $sello==='' || empty($_SESSION['logintoken'])){
	$result['status']='mala';
	$result['error']='Solicitud inválida';
	echo json_encode($result);
	return;
}
// El token de inicio de sesión es de un solo uso.
$logintoken=$_SESSION['logintoken'];
unset($_SESSION['logintoken']);

$certpath=__DIR__.'/certs/'.$nocert;
if(!file_exists($certpath.'.pem')){
	if(!file_exists($certpath)){
		$result['status']='mala';
		$result['error']='Certificado no encontrado';
		echo json_encode($result);
		return;
	}
	// Conversión DER -> PEM sin invocar comandos de shell.
	file_put_contents($certpath.'.pem',"-----BEGIN CERTIFICATE-----\n".chunk_split(base64_encode(file_get_contents($certpath)),64,"\n")."-----END CERTIFICATE-----\n");
}
$certf=file_get_contents($certpath.'.pem');
$pubkeyid=openssl_pkey_get_public($certf);
if ($pubkeyid===false){
	$result['status']='mala';
	$result['error']='errorcert';
	echo json_encode($result);
	return;
}

$details = openssl_pkey_get_details($pubkeyid);
if (!array_key_exists('rsa', $details)) {
	throw new \Exception('Unable to load the key');
}

/*
 * El certificado lo sube el propio usuario: sin validar que fue emitido por el SAT cualquiera
 * podría generar uno autofirmado con el RFC de otra persona. Se exige la cadena de confianza
 * configurada en $config['sat_ca_bundle'] (archivo PEM o directorio con los certificados raíz
 * e intermedios del SAT).
 */
$cabundle=$config['sat_ca_bundle'] ?? '';
if($cabundle==='' || !file_exists($cabundle) || openssl_x509_checkpurpose($certf, X509_PURPOSE_ANY, [$cabundle])!==true){
	$result['status']='mala';
	$result['error']=($cabundle==='' ? 'Validación de certificados SAT no configurada (sat_ca_bundle)' : 'El certificado no fue emitido por el SAT');
	echo json_encode($result);
	return;
}
$certinfo=openssl_x509_parse($certf,true);
if(!empty($certinfo['validTo_time_t']) && $certinfo['validTo_time_t']<time()){
	$result['status']='mala';
	$result['error']='Certificado vencido';
	echo json_encode($result);
	return;
}

$sello=base64_decode(strtr($sello,'-_','+/'));
$firma=@hex2bin((string)$sello);
if($firma===false){
	$result['status']='mala';
	echo json_encode($result);
	return;
}

$ok = openssl_verify($logintoken, $firma, $pubkeyid,OPENSSL_ALGO_SHA256 );
if ($ok == 1) {
    $result['status']= "buena";
    if(str_contains($logintoken,'||')){
    	$result['donde']='aqui1';
    }else{
    	$result['donde']='aqui2';
    	$certd=$certinfo;
    	file_put_contents(__DIR__.'/certs/'.$nocert.'.json',json_encode($certd));
    	$uid=(string)($certd['subject']['x500UniqueIdentifier'] ?? '');
    	$username=(
    		str_contains($uid,'/')?
    		trim(substr($uid,0,strpos($uid,'/')))
    		:
    		trim($uid)
    	);
    	if($username===''){
    		$result['status']='mala';
    		echo json_encode($result);
    		return;
    	}
    	$m->{$config['sitedb']}->users->updateOne([
    		'username'=>$username,
    		],[
    		'$set'=>[
    			'username'=>$username,
    			'nocert'=>$nocert,
    			'razonsocial'=>$certd['subject']['name'] ?? '',
    			'curp'=>$certd['subject']['serialNumber'] ?? ''
    			]
    		],['upsert'=>true]);

    	$user= new User([
			'username'=> $username,
		]);
		session_regenerate_id(true);
		$_SESSION['user']=$user->_id;
    }

} elseif ($ok == 0) {
    $result['status']= "mala";
} else {
    $result['status']= "alarmante, error verificando la firma";
}

echo json_encode($result);
