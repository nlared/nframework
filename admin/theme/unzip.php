<?
function onupload($filename,$upload){
	//rename ( $filename , $upload['extensioninfo']['path']);
	$b=basename($filename,'.zip');
	
	$data=explode('.',$b);
	
	
	/*$m->{$config['sitedb']}->configs->updateOne(['_id'=>'theme_'.$data[0]],[
		'version'=>substr($b,strlen($data[0])+1)
		]);//*/

	$zip = new ZipArchive;
	$res = $zip->open($filename);
	if ($res === TRUE) {
	  $zip->extractTo($upload['dir']);
	  $zip->close();
	}
	
}
function ondelete($filename,$upload){
	$b=basename($filename.'.zip');
	$data=explode('.',$b);
	/*$m->{$config['sitedb']}->configs->updateOne(['_id'=>'theme_'.$data[0]],[
		'version'=>substr($b,strlen($data[0])+1)
	]);//*/
	require 'fsfunc.php';
	delTreeI($upload['dir'].$data[0]);
	unlink ($filename);
}
function onlist($upload){
	$ret=[];
	$ds=glob($upload['dir'].'*.zip');
	$conta=0;
	foreach($ds as $d){
		$base=basename($d);
		$ret[] = array('id' => $conta, 'name' => $base, 'length' => filesize($d));
		$conta++;
	}
	
	return $ret;
}