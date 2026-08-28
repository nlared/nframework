//$filename;
if ( 0 < $_FILES['file']['error'] ) {
    echo 'Error: ' . $_FILES['file']['error'] . '<br>';
}else {
	$certfile=$_FILES['file']['tmp_name'];
	exec("openssl x509 -inform DER -in \"$certfile\" -noout -text",$consola3);
	$serial=trim($consola3[4]);
	$serial=str_replace(':3', '', $serial);
	$no_cert=$serial;
	$error=[];
	$info['nocert']=$no_cert;
	$pos=strpos($consola3[9],':');
	$hoy = new DateTime("now");
	$vencimiento = new DateTime(substr($consola3[9], $pos+1));
	$vencido=($hoy > $vencimiento);
	
	$conversion=[
		'CN'=>'commonName',
		'SN'=>'serialNumber',
		'C'=>'CountryName',
		'2.5.4.45'=>'x500uniqueIdentifier',
		'E'=>'emailAddress',
		'O'=>'organizationName'
	];
	
	$pos=strpos($consola3[10],':')+1;
	
	$parts=explode(',',substr($consola3[10],$pos));
	foreach($parts as $part){
		$parts2=explode('=',$part);
		$parts2[0]=trim($parts2[0]);
		$parts2[1]=trim($parts2[1]);
		if (array_key_exists ($parts2[0],$conversion)){
			$data[$conversion[$parts2[0]]]=$parts2[1];
		}else{
			$data[$parts2[0]]=$parts2[1];	
		}
		
	}
	//print_r($data);
	/*
	if($nombre!='' &&  $this->normalizar($nombre)!=$this->normalizar($data['commonName'])){
		$error= 'El nombre del usuario no corresponde al certificado';
	}else{
		$info[]= "El nombre del usuario corresponde al certificado";
	}
	if($curp!='' && $curp!=$data['serialNumber']){
		$error='El CURP del usuario corresponde no corresponde al certificado';
	}else{
		$info[]="El CURP del usuario corresponde al certificado";
	}
	*/
	
	
	$certpem=sys_get_temp_dir().'/certpem'.uniqid();
	$keyinfo=sys_get_temp_dir().'/keyinfo'.uniqid();
	exec("openssl x509 -noout -modulus -in $certfile | openssl md5 ",$consola1);
	exec("openssl x509 -inform DER -outform PEM -in $certfile -out $certpem",$consola3);
	//echo '<br>openssl ocsp -issuer '.$vsatdir.'/ac2_4096.crt -cert '.$this->certpem.
	//' -text -url https://cfdit.sat.gob.mx/edofiel -VAfile '.$vsatdir.'/OCSP_AC_4096_SHA256.crt';
	exec('openssl ocsp -issuer '.$vsatdir.'/ac2_4096.crt -cert '.$certpem.
	' -text -url https://cfdit.sat.gob.mx/edofiel -VAfile '.$vsatdir.'/OCSP_AC_4096_SHA256.crt',$consola4,$otro);
	foreach($consola4 as $linea){
		if($fin){
			$res[]=$linea;
		}
		if ($linea=='-----END CERTIFICATE-----'){
			$fin=true;
		}
	}
	//print_r($res);
	$pos=strpos($res[0],':')+1;
	
	/*if ('good'!=trim(substr($res[0],$pos))){
		$error='Certificado Revocado ante SAT';	
	}else{
		$info[]= "Certificado activo ante SAT";
	}*/
	$revocado=('good'!=trim(substr($res[0],$pos))?false:true);
	move_uploaded_file($_FILES['file']['tmp_name'], __DIR__.'/certs/' . $no_cert);
    
    //move_uploaded_file($_FILES['file']['tmp_name'], 'uploads/' . $_FILES['file']['name']);
    //$filename = $_FILES['file']['name'];
   // echo $filename;
   $info=[
   	'revocado'=>$revocado,
	'noCert'=>$serial,
	'serialNumber'=>$data['serialNumber'],
	'commonName'=>$data['commonName'],
	'vencimiento'=>$vencimiento,
	'hoy'=>$hoy,
	'vencido'=>$vencido,
	's'=>$consola3
	];
	header("Content-type:application/json");
    echo json_encode($info);
}