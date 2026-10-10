<?php
require 'common.php';
$excel = new xspreadsheet(['filename' => __DIR__ . '/test.xlsx']);
?>
<div class="container">
	<?= docHeader('Hoja de cálculo', '<code>xspreadsheet</code> abre un archivo .xlsx del servidor en una hoja editable (<a href="https://github.com/myliang/x-spreadsheet" target="_blank">x-spreadsheet</a>). Los cambios se envían a <code>/nframework/xspreadsheet.php</code>, que los escribe en el mismo archivo con PhpSpreadsheet.') ?>
	<?= $excel ?>
	<?= docCode(<<<'PHP'
$excel = new xspreadsheet(['filename' => __DIR__ . '/test.xlsx']);
echo $excel;
PHP) ?>
	<p class="remark">La ruta del archivo solo se guarda en la sesión; el navegador recibe un identificador. Para generar archivos nuevos en lugar de editarlos vea <a href="exports.php">Excel, Word y PDF</a>.</p>
</div>
