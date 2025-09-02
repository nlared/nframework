<?
require 'include.php';
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$datainfo=$_SESSION['datatable'][$_GET['id']];
if(empty($datainfo)){
	echo 'error en session';
	die();
}

$pipeline=$_SESSION['datatable'][$_GET['id']]['original'];
if(!empty($_POST['pipelinequery'])){
	$pipeline[]=[
		'$match'=>$_POST['pipelinequery']
	];
}

$_SESSION['datatable'][$_GET['id']]['pipeline']=$pipeline;
