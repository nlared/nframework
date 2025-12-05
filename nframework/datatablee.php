<?
require 'include.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;

$datainfo = $_SESSION['datatable'][$_GET['id']];
// Create a new spreadsheet


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
$options = [];
foreach ($m->{$datainfo['db']}->{$datainfo['collection']}->aggregate($pipeline, $options) as $d) {
	$d = mongotoarray($d);
	$d['_id'] = (string)$d['_id'];
	$toad = [];
	foreach ($datainfo['columns'] as $column) {
		if ($d[$column] instanceof MongoDB\BSON\UTCDateTime) {
			$toad[$column] = $d[$column]->toDateTime()->format("Y-m-d H:i:s");
		} elseif ($d[$column] instanceof MongoDB\BSON\ObjectId) {
			$toad[$column] = (string) $d[$column];
		} elseif (is_array($d[$column]) || is_object($d[$column])) {
			$toad[$column] = json_encode($d[$column]);
		} else {
			$toad[$column] = (string) $d[$column];
		}
	}
	$arrayData[] = array_values($toad);
}
if (!empty($_GET['type']) && $_GET['type'] == 'csv') {
	$filename = "reporte_" . date('Y-m-d_H-i-s') . ".csv";
	header('Content-Type: text/csv; charset=utf-8');
	header('Content-Disposition: attachment; filename=' . $filename);
	$output = fopen('php://output', 'w');
	// Output the column headings
	$header = [];
	foreach ($datainfo['columns'] as $column) {
		$header[] = $column;
	}
	fputcsv($output, $header);
	// Output the data
	foreach ($arrayData as $row) {
		fputcsv($output, $row);
	}
	fclose($output);
} else {
	if (empty($datainfo['excelFile'])) {
		$spreadsheet = new Spreadsheet();
	} else {
		$spreadsheet = IOFactory::load($datainfo['excelFile']);
	}
	// Access the active sheet
	$sheet = $spreadsheet->getActiveSheet();
	$spreadsheet->getActiveSheet()
		->fromArray(
			$arrayData,  // The data to set
			NULL,        // Array values with this value will not be set
			(empty($datainfo['excelCell']) ? 'A1' : $datainfo['excelCell'])         // Top left coordinate of the worksheet range where
			//    we want to set these values (default is A1)
		);
	$nframework->excelOut($spreadsheet, 'reporte' . date('Y-m-d H:i:s'));
}
