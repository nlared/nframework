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
			$pipeline[$key] = converttophptypepipeline($value, $field, $phpfunction);
		} else if (is_object($value)) {
			$pipeline[$key] = converttophptypepipeline((array)$value, $field, $phpfunction);
		} else {
			if ($key == $field) {
				$fn = eval('return ' . $phpfunction);
				if (!is_callable($fn)) {
					throw new Exception("La función PHP no es válida: $phpfunction");
				}
				if (is_array($value)) {
					foreach ($value as $k => $v) {
						$value[$k] = $fn($v);
					}
					$pipeline[$key] = $value;
					//continue;
				} else {
					$pipeline[$key] = $fn($value);
				}
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
				$tmppipeline = converttophptypepipeline($tmppipeline, $filter['field'], 'function($value) { return floatval($value); };');
			}
		}
	}
	$pipeline[] = [
		'$match' => $tmppipeline
	];
}

$_SESSION['datatable'][$_GET['id']]['pipeline'] = $pipeline;
$result = [
	'pipeline' => $pipeline,
	'errors' => error_get_last()
];
