<?
$developermode = true;
set_time_limit(0);
require_once 'include.php';


use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

header('Content-Type: application/json');
if (isset($_GET['mid']) && isset($_SESSION['nfxspreadsheet'][$_GET['mid']])) {
    $options = $_SESSION['nfxspreadsheet'][$_GET['mid']];
    try {

        $spreadsheet = IOFactory::load($options['filename']);
        $worksheet = $spreadsheet->getActiveSheet();

        $dataArray = [];
        foreach ($worksheet->getRowIterator() as $row) {
            $rowData = [];
            foreach ($row->getCellIterator() as $cell) {
                $rowData[] = $cell->getValue(); // Get the value
            }
            $dataArray[] = $rowData;
        }

        $jsonInput = file_get_contents('php://input');
        // Decode the JSON into a PHP array
        $update = json_decode($jsonInput, true);
        // Check the decoded data
        $cambios = [];
        if ($update) {
            foreach ($update['rows'] as $row => $datarow) {
                foreach ($datarow['cells'] as $cell => $datacell) {
                    if ($dataArray[$row][$cell] != $datacell['text']) {
                        $cambios[$row][$cell] = $datacell['text'];
                        $worksheet->setCellValueByColumnAndRow($cell + 1, $row + 1, $datacell['text']);
                        $save = true;
                    }
                }
            }
        } else {
            //echo "Invalid JSON input.";
        }

        if ($save) {
            $writer = new Xlsx($spreadsheet);
            $writer->save($options['filename']);
        }

        $result = [
            'data' => $dataArray,
            'data2' => $update,
            'cambios' => $cambios
        ];





        // Print the array

    } catch (Exception $e) {
        if ($developermode) {
            $result = [
                'error' => $e->getMessage()
            ];
        } else {
            $esult = [
                'error' => 'error'
            ];
        }
    }
}
