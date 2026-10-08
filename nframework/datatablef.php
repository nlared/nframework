<?
require 'include.php';
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$datainfo = $_SESSION['datatable'][(string) ($_GET['id'] ?? '')] ?? null;

if (empty($datainfo)) {
	echo 'error en session';
	die();
}

$filters = $datainfo['filters'];
function converttophptypepipeline($pipeline, $field, callable $fn)
{
	if (!is_array($pipeline)) {
		return $pipeline;
	}
	foreach ($pipeline as $key => $value) {
		if ($key == $field) {
			if (is_array($value)) {
				foreach ($value as $k => $v) {
					$value[$k] = $fn($v);
				}
				$pipeline[$key] = $value;
				continue;
			} else {
				$pipeline[$key] = $fn($value);
			}
		} else {
			$pipeline[$key] = converttophptypepipeline($value, $field, $fn);
		}
	}
	return $pipeline;
}

$pipeline = $datainfo['original'];
if (!empty($_POST['pipelinequery']) && is_array($_POST['pipelinequery'])) {
	// La consulta viene del navegador: se quitan operadores que ejecutan código en el servidor.
	$tmppipeline = nfSanitizeMongoQuery($_POST['pipelinequery']);
	foreach ($filters as $filter) {
		if (isset($filter['field'])) {
			if ($filter['phptype'] == 'number') {
				$tmppipeline = converttophptypepipeline($tmppipeline, $filter['field'], fn($value) => floatval($value));
			} else if ($filter['phptype'] == 'date') {
				$tmppipeline = converttophptypepipeline($tmppipeline, $filter['field'], fn($value) => new \MongoDB\BSON\UTCDateTime(strtotime((string) $value) * 1000));
			}
		}
	}
	$pipeline[] = [
		'$match' => $tmppipeline
	];
}

$_SESSION['datatable'][(string) $_GET['id']]['pipeline'] = $pipeline;
$result = [
	'pipeline' => $pipeline,
];
