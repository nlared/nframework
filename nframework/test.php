<?php
/*ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);//*/
$errores=[];
function return_bytes($val) {
    $val = (int)trim($val);
    $last = strtolower($val[strlen($val)-1]);
    switch($last) {
        // The 'G' modifier is available since PHP 5.1.0
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }

    return $val;
}


$inipath=php_ini_loaded_file();
$archivo=file_get_contents($inipath);
date_default_timezone_set('America/Monterrey');
$exts=get_loaded_extensions();
if(ini_get('display_errors')){
	$errores[]= "display_errors=Off";
}


$memory_limit = ini_get('memory_limit');

$inineeds=[
	'display_errors',
	'memory_limit',
	'opcache.jit',
	'auto_append_file',
	'post_max_size',
	'upload_max_filesize'
];



/*$iniPath = php_ini_loaded_file(); // Get path to active php.ini
$directive = 'memory_limit';
$newValue = '512M';

$contents = file_get_contents($iniPath);

// Use regex to replace the directive
if (!preg_match("/^$directive\s*=.*$/m", $contents)) {
    $contents .= "\n$directive = $newValue\n";
}

// Write back to php.ini
file_put_contents($iniPath, $contents);
//*/

/*
shell_exec('sudo service php8.2-fpm restart');
exec('sudo systemctl restart php8.2-fpm');

*/



/*
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
//*/
$phpver=number_format((float)phpversion(),1);
$apts=[];


$depends=[
	'mongodb'=>"php$phpver-mongodb",
	'intl'=>"php$phpver-intl",
	'gd'=>"php$phpver-gd",
	'curl'=>"php$phpver-curl",
	'pdo_sqlite'=>"php$phpver-sqlite",
	'zip'=>"php$phpver-zip",
	'mbstring'=>"php$phpver-mbstring",
	'libxml'=>"php$phpver-xml",
	'imagick'=>'php-imagick'
];

foreach($depends as $ext=>$command){
	if(!in_array($ext,$exts)){
		$apts[]=$command;
	}
}




if(count($apts)>0){
	$errores[]='sudo apt-get install '.implode(' ',$apts) ;
}


// Command to check Ghostscript version
$command = "gs --version";

// Execute the command
$output = [];
$return_var = 0;
exec($command, $output, $return_var);

// Check if Ghostscript is installed
if ($return_var === 0) {
    echo "Ghostscript is installed. Version: " . implode("\n", $output);
} else {
    $errores[]= "Ghostscript is not installed or not accessible. sudo apt-get -y install ghostscript ";
}




$includespath=get_include_path();

/*
$data = explode(PHP_EOL, file_get_contents("/proc/meminfo"));
print_r($data);
$meminfo = array();
foreach ($data as $line) {
    list($key, $val) = explode(":", $line);
    $meminfo[$key] = trim($val);
}
//*/
/*
opcache.enable_cli=1
opcache.jit_buffer_size=500000000
opcache.jit=1235
*/


include('config.php');
if(!isset($config)){
	$includepaths[]=explode(':',$includepath);
	//TODO:buscar path
	
	$errores[]='config.php not found OR include_path = "'.$includespath.'"';
}

if ((include 'vendor/autoload.php') != TRUE) {
	$errores[]='composer update --ignore-platform-reqs';
}

if(ini_get('auto_append_file')==''){
	$errores[]='auto_append_file =/var/www/html/includes/append_file.php';
}

if($config['sitedb']==''){
		$errores[]='$config[sitedb] no configurada';
}else{
	try{
		$m = new MongoDB\Client($config['mongo_connection_string']);
		
		
		
		$guest=$m->{$config['sitedb']}->users->findOne(['username'=>'guest']);
		if(empty($guest)){
			$errores[]="guest no existe";
			$m->{$config['sitedb']}->users->insertOne(['username'=>'guest']);
			$errores[]="guest creado error solucionado actualiza la pagina";
			$m->{$config['sitedb']}->users->createIndex( [ "username"=> 1 ], [ 'unique'=> true ] );
		}
		
		$admin=$m->{$config['sitedb']}->users->findOne(['username'=>'admin']);
		if(empty($admin)){
			$errores[]="admin no existe";
			$adminid=new  MongoDB\BSON\ObjectID();
			$m->{$config['sitedb']}->users->insertOne([
				'username'=>'admin',
				'_id'=>$adminid,
				'password'=>'c7ad44cbad762a5da0a452f9e854fdc1e0e7a52a38015f23f3eab1d80b931dd472634dfac71cd34ebc35d16ab7fb8a90c81f975113d6c7538dc69dd8de9077ec'
			]);
			$errores[]="admin creado error solucionado actualiza la pagina";
		}else{
			$adminid=$admin->_id;
		}
		$gadmin=$m->{$config['sitedb']}->usersgroups->findOne(['name'=>'admins']);
		if(empty($gadmin)){
			$m->{$config['sitedb']}->usersgroups->insertOne([
				'name'=>'admins',
				'description'=>'administrators',
				'users'=>[$adminid]
			]);
			$m->{$config['sitedb']}->usersgroups->createIndex( [ "name"=> 1 ], [ 'unique'=> true ] );
		}else{
			if(count($gadmin->users)==0){
				$m->{$config['sitedb']}->usersgroups->updateOne(['name'=>'admins'],[
					'$addToSet'=>[
						'users'=>$adminid
					]
				]);
			}
		}
		
		$gadmin=$m->{$config['sitedb']}->usersgroups->findOne(['name'=>'developers']);
		if(empty($gadmin)){
			$m->{$config['sitedb']}->usersgroups->insertOne([
				'name'=>'developers',
				'description'=>'developers',
				'users'=>[$adminid]
			]);
		}
		
		
		$configs=[
			'config'=>[],
			'security'=>[
				'blacklist'=>[],
				'blockedsBots'=>[
					'PetalBot', 'Googlebot', 'Bingbot', 'AhrefsBot', 'YandexBot'
					]
				],
				'blockedsUris'=>[
					'wp-includes/', '/xmlrpc.php','/wlwmanifest.xml'
				]
		];
		
		
		
		$m->{$config['sitedb']}->sessions->createIndex(['last_accessed'=>1]);	
		$m->{$config['sitedb']}->users->createIndex(['username'=>1]);	
		$m->{$config['sitedb']}->pages->createIndex(['path'=>1]);
		$m->{$config['sitedb']}->usergroups->createIndex(['name'=>1]);
	} catch (Exception $e) {
	   $errores[]= 'Excepción capturada: '.  $e->getMessage();
	}
}

$a=ini_get('post_max_size');
$b=ini_get('upload_max_filesize');
echo date("Y-m-d H:i:s").'<br>
Capacidad de post_max_size:'.$a.'<br>
Capacidad de upload_max_filesize:'.$b.'<br>
Tu capacidad de subida es de :'.
(return_bytes($a)<return_bytes($b)?
$a.' determinada por post_max_size':
$b.' determinada por upload_max_filesize').
'<br>';
if(count($errores)>0){
	foreach ($errores  as $errs){
		echo $errs.'<br>';
	}
}else{
	echo "No se encontraron errores de configuración";
}
require 'include.php';
echo "sid:".session_id().'<br>'.$_SESSION['nf']['browser']['language'];
