<?
$developermode = true;
set_time_limit(0);
function utf8_to_win($string)
{
	for ($c = 0; $c < strlen($string); $c++) {
		$i = ord($string[$c]);
		if ($i <= 127) @$out .= $string[$c];
		if (@$byte2) {
			$new_c2 = ($c1 & 3) * 64 + ($i & 63);
			$new_c1 = ($c1 >> 2) & 5;
			$new_i = $new_c1 * 256 + $new_c2;
			if ($new_i == 1025) {
				$out_i = 168;
			} else {
				if ($new_i == 1105) {
					$out_i = 184;
				} else {
					$out_i = $new_i - 848;
				}
			}
			@$out .= chr($out_i);
			$byte2 = false;
		}
		if (($i >> 5) == 6) {
			$c1 = $i;
			$byte2 = true;
		}
	}
	return $out;
}
require_once 'include.php';
header('Content-Type: application/json');

function valid_filename($filename){
	$valido=true;
	//"(", ")",",", "&"
	$special_chars = ['.php','.asp',"?", "[", "]", "/", "\\", "=", "<", ">", ":", ";",  "'", "\"", "$", "#", "*",  "|", "~", "`", "!", "{", "}", "%", "+", chr(0)];
   	foreach($special_chars as $char){
   		if(strpos($filename,$char)!==false){
   			$valido=false;
   		}
   	}
   	return $valido;
}
if (isset($_POST['mid']) && isset($_SESSION['uploads4'][$_POST['mid']])) {
    $ret = [];
    $upload = $_SESSION['uploads4'][$_POST['mid']];
    $directorio=$upload['dir'];
    //if($upload['disabled']!=false){
    $error='';
    $ctime=time();
    $onresult=[];
   
    	if($ctime>= $upload['limit_time_start'] && $ctime<= $upload['limit_time_end']) {
	    if(!empty($upload['extension'])){
	    	require $upload['extension'];
	    }
	    if (!empty($_POST['delete'])){
	    	if($upload['delete']) {
		        $filename=rawurldecode($_POST['file']);
				$coding=mb_detect_encoding($filename);
				if($coding!='UTF-8'){
					//$filename=iconv($coding,'UTF-8', $filename);
					$filename=mb_convert_encoding($filename, 'UTF-8', 'auto');
				}
				$valido=valid_filename($filename);
		        if($valido){
		        	$filename=$directorio.$filename;
		        	unlink($filename);
		        }
		        if($upload['ondelete']!=''){
		        	$onresult[]=call_user_func($upload['ondelete'],$filename,$upload);
		        }
	    	}
	    } else {
	    	
	    	if(isset($_FILES[$upload['formname']]['tmp_name'])){
		    	$aafiles = (array)scandir($directorio);
		    	$aafiles=array_diff($aafiles,['.','..']);
		    	if($upload['countlimit']==0 || count($aafiles)<$upload['countlimit'] ){
			    	$ufile=$_FILES[$upload['formname']];
			    	if($ufile!=''){
				    	$filename=rawurldecode($ufile['name']);
						$coding=mb_detect_encoding($filename);
						if($coding!='UTF-8'){
							//$filename=iconv($coding,'UTF-8', $filename);
							$filename=mb_convert_encoding($filename, 'UTF-8', 'auto');
						}
						$valido=valid_filename($filename);
				        if($valido!==false){
					        if (!file_exists($directorio)) mkdir($directorio, 0777, true);
					        $filename=$directorio.$filename;
					        move_uploaded_file($ufile['tmp_name'],$filename);
					        if($upload['onupload']!=''){
					        	$onresult[]=call_user_func($upload['onupload'],$filename,$upload);
					        }
				        }
			    	}
		    	}else{
		    		$ufile=$_FILES[$upload['formname']];
			    	if($ufile!=''){
			    		unlink($ufile['tmp_name']);
			    		$error="lleno";
			    	}
		    	}
		    }else{
		    	$error='Form not found:'.$upload['formname']." ".json_encode($_FILES);
		    }
		    
	    }
    }else{
    	$error="Fuera de tiempo limite";
    }
    $ret=[];
    if(!empty($upload['onlist'])){
    	$ret=call_user_func($upload['onlist'],$upload);
    }else{
	    if(!empty($directorio)){
	        $afiles = scandir($directorio);
		    $o = 0;
		    foreach ($afiles as $afile) {
		        if ($afile != '.' && $afile != '..'){
		            $ret[] = array('id' => $o, 'name' => $afile, 'length' => filesize($directorio . "/$afile"));
		        $o++;
		        }
		    }
	    }
    }
    $result=[
    	'conf'=>$upload,
    	'delete'=>$upload['delete'],
    	'download'=>$upload['download'],
    	'preview'=>($upload['preview']!=false),
    	'files'=>$ret,
    	'onresult'=>$onresult,
    	'error'=>$error,
   		'ss'=>session_id(),
    	'll'=>$ufile,'os'=>$_SESSION['uploads5'],
    	'filea'=>$_FILES,
    	];
    echo json_encode($result);
}else{
	$result=[ 'session '=> session_id()]; 
}