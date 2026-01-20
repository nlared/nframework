<?php
//$developermode=true;
require_once 'include.php';
$datainfo = $_SESSION['datatable'][$_GET['id']];

if (empty($datainfo)) {
	echo 'error en session';
	die();
}
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");


$psort = $_GET['order'];
foreach ($psort as $nsort) {
	$sorts[$datainfo['columns'][$nsort['column']]] = ($nsort['dir'] == 'asc' ? 1 : -1);
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


if ($_GET['search']['value'] != '') {
	$globalfind = ($_GET['search']['regex'] ?
		new MongoDB\BSON\Regex($_GET['search']['value'], "i")
		:
		new MongoDB\BSON\Regex('/' . $_GET['search']['value'] . '/', "i")
	);
	foreach ($datainfo['columns'] as $cno => $co) {
		$matchs[][$co] = $globalfind;
	}
	$pipeline[] = ['$match' => ['$or' => $matchs]];
}

$columnaf = [];
foreach ($datainfo['columns'] as $index => $column) {
	if ($_GET['columns'][$index]['search']['value'] != '') {
		$columnaf[$column] = ($_GET['columns'][$index]['search']['regex'] ?
			new MongoDB\BSON\Regex($_GET['columns'][$index]['search']['value'])
			:
			new MongoDB\BSON\Regex('/' . $_GET['columns'][$index]['search']['value'] . '/', "i")
		);
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

$datastart = (int)$_GET['start'];
$datalength = (int)$_GET['length'];

$pipeline[] = ['$project' => $project];
$pipeline[] = ['$sort' => $sorts];
if ($datastart > 0) {
	$pipeline[] = ['$skip' => $datastart];
}
if ((int)$_GET['length'] > 0) {
	$pipeline[] = ['$limit' => $datastart + $datalength];
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
	$error = 'Error en la consulta: ' . $e->getMessage();
}
$result = [
	'draw' => (int)$_GET['draw'],
	"recordsTotal" => (is_null($datat['recordsTotal']) ? 0 : $datat['recordsTotal']),
	'pipeline' => $pipeline,
	"recordsFiltered" => (is_null($dataf['recordsTotal']) ? 0 : $dataf['recordsTotal']),
	'data' => $data,
];

if ($developermode) {
	$result['debug'] = [
		'pipeline' => $pipeline,
		'pipelinef' => $pipelinef,
		'datainfo' => $datainfo,
	];
}
if ($error) {
	$result['error'] = $error;
}
echo json_encode($result);
