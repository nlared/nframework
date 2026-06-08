<?
require 'include.php';
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$datainfo = $_SESSION['datatable'][$_GET['id']];

if (empty($datainfo)) {
	echo 'error en session';
	die();
}

$filters = $datainfo['filters'];
function converttophptypepipeline($pipeline, $field, $phptype)
{
	foreach ($pipeline as $key => $value) {
		if (is_array($value)) {
			$pipeline[$key] = converttophptypepipeline($value, $field, $phptype);
		} else {
			if ($key == $field) {
				$pipeline[$key] = exec("return $phptype(\$value);");
			}
		}
	}
	return $pipeline;
}

$pipeline = $_SESSION['datatable'][$_GET['id']]['original'];
if (!empty($_POST['pipelinequery'])) {
	$tmppipeline = $_POST['pipelinequery'];
	foreach ($filters as $filter) {
		if (isset($filter['field']) ){
			if ($filter['type']=='inputnumber'){
				$tmppipeline = converttophptypepipeline($tmppipeline, $filter['field'], 'number');
			}		
		}
	}
	$pipeline[] = [
		'$match' => $tmppipeline
	];
}

$_SESSION['datatable'][$_GET['id']]['pipeline'] = $pipeline;
