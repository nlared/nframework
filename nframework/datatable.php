<?php
//$developermode=true;
require_once 'include.php';
$datainfo = $_SESSION['datatable'][(string) ($_GET['id'] ?? '')] ?? null;

if (empty($datainfo)) {
	echo 'error en session';
	die();
}
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");


/**
 * DataTables envía regex como cadena "true"/"false"; la búsqueda literal se escapa para
 * que el usuario no pueda inyectar expresiones regulares costosas.
 */
function datatableSearchRegex($search): ?MongoDB\BSON\Regex
{
	$value = is_array($search) ? ($search['value'] ?? '') : '';
	if (!is_string($value) || $value === '') {
		return null;
	}
	$isRegex = filter_var($search['regex'] ?? false, FILTER_VALIDATE_BOOLEAN);
	return new MongoDB\BSON\Regex($isRegex ? $value : preg_quote($value), 'i');
}

$sorts = [];
foreach ((array) ($_GET['order'] ?? []) as $nsort) {
	$column = $datainfo['columns'][(int) ($nsort['column'] ?? -1)] ?? null;
	if ($column !== null) {
		$sorts[$column] = (($nsort['dir'] ?? '') == 'asc' ? 1 : -1);
	}
}
if (empty($sorts)) {
	$sorts = ['_id' => 1];
}
foreach ($datainfo['columns'] as $column) {
	if ($column == '_id') {
		$project['_id'] = ['$toString' => '$_id'];
	} else {
		$project[$column] = 1;
	}
}

$pipeline = (isset($datainfo['pipeline']) ? $datainfo['pipeline'] : []);
$pipeline[] = ['$project' => $project];
$pipeline[] = ['$count' => 'recordsTotal'];
modifyArray($pipeline, '$regex', '$options', 'i');
$options = [];

function modifyArray(&$array, $targetKey, $newKey, $newValue)
{
	foreach ($array as &$item) {
		if (is_array($item)) {
			modifyArray($item, $targetKey, $newKey, $newValue);
		} elseif ($item instanceof \MongoDB\BSON\ObjectId) {
		} elseif ($item instanceof \MongoDB\BSON\UTCDateTime) {
			//no hacer nada
		} elseif ($item instanceof \MongoDB\BSON\Regex) {
			//no hacer nada		
		} elseif (isset($item[$targetKey])) {
			$item[$newKey] = $newValue;
		}
	}
}



$filtrados = 0;
foreach ($m->{$datainfo['db']}->{$datainfo['collection']}->aggregate($pipeline, $options) as $doc) {
	$datat = (array)$doc;
}



$pipeline = (isset($datainfo['pipeline']) ? $datainfo['pipeline'] : []);

modifyArray($pipeline, '$regex', '$options', 'i');


$globalfind = datatableSearchRegex($_GET['search'] ?? null);
if ($globalfind !== null) {
	$matchs = [];
	foreach ($datainfo['columns'] as $co) {
		$matchs[][$co] = $globalfind;
	}
	$pipeline[] = ['$match' => ['$or' => $matchs]];
}

$columnaf = [];
foreach ($datainfo['columns'] as $index => $column) {
	$columnfind = datatableSearchRegex($_GET['columns'][$index]['search'] ?? null);
	if ($columnfind !== null) {
		$columnaf[$column] = $columnfind;
	}
}
if (count($columnaf) > 0) {
	$pipeline[] = ['$match' => $columnaf];
}
$pipelinef = $pipeline;
$pipelinef[] = ['$project' => $project];
$pipelinef[] = ['$count' => 'recordsTotal'];
foreach ($m->{$datainfo['db']}->{$datainfo['collection']}->aggregate($pipelinef, $options) as $doc) {
	$dataf = (array)$doc;
}

$datastart = max(0, (int) ($_GET['start'] ?? 0));
$datalength = (int) ($_GET['length'] ?? 0);

$pipeline[] = ['$project' => $project];
$pipeline[] = ['$sort' => $sorts];
if ($datastart > 0) {
	$pipeline[] = ['$skip' => $datastart];
}
if ($datalength > 0) {
	$pipeline[] = ['$limit' => min($datalength, 10000)];
}
$data = [];
try {
	foreach ($m->{$datainfo['db']}->{$datainfo['collection']}->aggregate($pipeline, $options) as $doc) {
		$toad = [];
		foreach ($datainfo['columns'] as $column) {
			$toad[$column] = normalizeBsonValue($doc[$column]);
		}
		$data[] = array_values($toad);
		$filtrados++;
	}
} catch (Exception $e) {
	error_log('nframework datatable: ' . $e->getMessage());
	$error = 'Error en la consulta' . ($developermode ? ': ' . $e->getMessage() : '');
}
$result = [
	'draw' => (int) ($_GET['draw'] ?? 0),
	"recordsTotal" => $datat['recordsTotal'] ?? 0,
	"recordsFiltered" => $dataf['recordsTotal'] ?? 0,
	'data' => $data,
];

if ($developermode) {
	$result['debug'] = [
		'pipeline' => $pipeline,
		'pipelinef' => $pipelinef,
		'datainfo' => $datainfo,
	];
}
if (!empty($error)) {
	$result['error'] = $error;
}
echo json_encode($result);
