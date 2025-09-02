<?
function onupload($filename,$upload){
	rename ( $filename , $upload['extensioninfo']['path']);
}
function ondelete($filename,$upload){
	unlink ($upload['extensioninfo']['path']);
}
function onlist($upload){
	$ret=[];
	if(file_exists($upload['extensioninfo']['path'])){
		$base=basename($upload['extensioninfo']['path']);
		$ret[] = array('id' => 0, 'name' => $base, 'length' => filesize($upload['extensioninfo']['path']));
	}
	return $ret;
}