<?

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$nftable = $m->{$config['table']}->findOne($id);


$importfile = new inputFile([
    'name' => 'importfile',
    'type' => 'file',
    'label' => 'Import File',
    'required' => true,
    'accept' => '.csv'
]);
$import_file_type = new select([
    'name' => 'import_file_type',
    'type' => 'select',
    'label' => 'File Type',
    'options' => [
        ['csv' => 'CSV'],
        ['xlsx' => 'Excel']
    ],
    'required' => true,
    'value' => 'csv'
]);
$import_file_separator = new inputText([
    'name' => 'import_file_separator',
    'type' => 'text',
    'label' => 'CSV Separator',
    'required' => true,
    'value' => ','
]);
$import_file_has_header = new select([
    'name' => 'import_file_has_header',
    'type' => 'select',
    'label' => 'CSV Has Header',
    'options' => [
        ['yes' => 'Yes'],
        ['no' => 'No']
    ],
    'required' => true,
    'value' => 'yes'
]);
$import_file_range = new inputText([
    'name' => 'import_file_range',
    'type' => 'text',
    'label' => 'Excel Range (e.g. A1:C10)',
    'required' => false,
    'value' => ''
]);





$headers = '';
$columns = [];
$filtertypes = [
    'string' => 'string',
    'integer' => 'integer',
    'date' => 'date',

];

$columns[] = '_id';
foreach ($tabla->nffields as $field) {
    $headers .= '<th>' . $field->short_description . '</th>';
    $columns[] = $field->field;
    $filters[] = [
        'id' => $field->field,
        'field' => $field->field,
        'label' => $field->short_description,
        'type' => $filtertypes[$field->type],
    ];
}

$datatable = new Table();
$datatable->Ajax([
    'id' => 'nftable_' . $tabla->nfcollection,
    'db' => $config['sitedb'],
    'collection' => $tabla->nfcollection . '_tmp',
    'header' => $headers,
    /*'pipeline'=>[[
    	'$addFields'=>[
    		'addfield'=>'add'
    		]
    	]],*/
    'columns' => $columns,
]);


if ($nframework->isAjax()) {
    if ($file) {
        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        if ($file_extension == 'xlsx') {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file['tmp_name']);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();
            // Process the rows as needed
            echo json_encode(['status' => 'success']);
        } else {
            $filename = $file['tmp_name'];
            $handle = fopen($filename, "r");
            $separator = $import_file_separator->getValue();
            $has_header = $import_file_has_header->getValue() === 'yes';
            if ($has_header) {
                $header = fgetcsv($handle, 1000, $separator);
            }
            while (($data = fgetcsv($handle, 1000, $separator)) !== FALSE) {
                // Process each row of the CSV file
                // For example, you can insert the data into a database
                // $data[0] will contain the first column, $data[1] the second column, etc.
            }
            fclose($handle);
            echo json_encode(['status' => 'success']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'File upload failed']);
    }
} else {
    $nframework->usecommon = true;

    $datatable = new Table();
    $datatable->Ajax([
        'id' => 'nftable_' . $tabla->nfcollection,
        'db' => $config['sitedb'],
        'collection' => $tabla->nfcollection,
        'header' => $headers,
        /*'pipeline'=>[[
    	'$addFields'=>[
    		'addfield'=>'add'
    		]
    	]],*/
        'columns' => $columns,
        'columnDefs' => [
            count($columns) - 1 => ['render' => "'<a href=\"/nftables/" . $tabla->nfcollection . "/'+data+'\" class=\"square button small primary\"><span class=\"mif-pencil\"></span></a>'+		
		'<a href=\"javascript:removeid(\\''+data+'\\');\" class=\"square small button alert\"><span class=\"mif-bin\"></span></a>'"], // data $row[0]
        ]
    ]);
?>
    <div class="container">
        <div class="box shadow-large">
            <div class="box-title">Import Data</div>
            <div class="grid">
                <div class="row">
                    <div class="cell">
                        <?= $importfile ?>
                    </div>
                </div>
                <div class="row">
                    <div class="cell">
                        <?= $import_file_type ?>
                    </div>
                    <div class="cell">
                        <?= $import_file_has_header ?>
                    </div>
                </div>
                <div class="row">
                    <div class="cell">
                        <?= $import_file_separator ?>
                    </div>
                </div>
                <div class="row">
                    <div class="cell">
                        <?= $import_file_range ?>
                    </div>
                </div>
                <div class="row">
                    <div class="cell">
                        <button class="button primary" id="import_button">Import</button>
                    </div>
                </div>
                <div>
                    <div class="row">
                        <div class="cell">
                            <?= $datatable ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        function previewImport() {
            var formData = new FormData();
            formData.append('importfile', document.que<div class="row">
				<div class="cell"><?=$nombre?></div>
			</div>rySelector('input[name="importfile"]').files[0]);
            formData.append('import_file_type', document.querySelector('select[name="import_file_type"]').value);
            formData.append('import_file_separator', document.querySelector('input[name="import_file_separator"]').value);
            formData.append('import_file_has_header', document.querySelector('select[name="import_file_has_header"]').value);
            formData.append('import_file_range', document.querySelector('input[name="import_file_range"]').value);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    datatable.ajax.reload(); // Refresh the datatable to show the imported data
                    // Optionally, refresh the datatable or perform other actions
                } else {
                    alert('Error importing file: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while importing the file.');
            });
        }
    </script>
}
