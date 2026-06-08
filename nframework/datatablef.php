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
function converttophptypepipeline($pipeline, $field, $phpfunction)
{
	foreach ($pipeline as $key => $value) {
		if (is_array($value)) {
			$pipeline[$key] = converttophptypepipeline($value, $field, $phptype);
		} else if (is_object($value)) {
			$pipeline[$key] = converttophptypepipeline((array)$value, $field, $phptype);
		} else {
			if ($key == $field) {
				$fn[$field] = eval("$phpfunction");
				$pipeline[$key] = $fn[$field]($value);
			}
		}
	}
	return $pipeline;
}

$pipeline = $_SESSION['datatable'][$_GET['id']]['original'];
if (!empty($_POST['pipelinequery'])) {
	$tmppipeline = $_POST['pipelinequery'];
	foreach ($filters as $filter) {
		if (isset($filter['field'])) {
			if ($filter['phptype'] == 'number') {
				$tmppipeline = converttophptypepipeline($tmppipeline, $filter['field'], 'function($value){return floatval($value);}');
			}
		}
	}
	$pipeline[] = [
		'$match' => $tmppipeline
	];
}

$_SESSION['datatable'][$_GET['id']]['pipeline'] = $pipeline;
