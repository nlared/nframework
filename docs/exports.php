<?php
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\TemplateProcessor;

$descarga = $_GET['descargar'] ?? '';
if ($descarga !== '') {
	// Descargas: sin plantilla ni buffer del framework para no alterar el archivo binario.
	$nfshutdowndisable = true;
	$nfjavaobfuscatedisable = true;
	require 'include.php';

	$ventas = [['Región', 'Vendedor', 'Importe'], ['Norte', 'Ana', 1250.5], ['Sur', 'Luis', 980], ['Centro', 'María', 2310.75]];

	if ($descarga === 'xlsx' || $descarga === 'xlsxpdf') {
		$libro = new Spreadsheet();
		$hoja = $libro->getActiveSheet();
		$hoja->setTitle('Ventas');
		$hoja->fromArray($ventas, null, 'A1');
		$hoja->setCellValue('B5', 'Total');
		$hoja->setCellValue('C5', '=SUM(C2:C4)');
		$hoja->getStyle('A1:C1')->getFont()->setBold(true);
		$hoja->getStyle('C2:C5')->getNumberFormat()->setFormatCode('#,##0.00');
		foreach (['A', 'B', 'C'] as $columna) {
			$hoja->getColumnDimension($columna)->setAutoSize(true);
		}
		$descarga === 'xlsx'
			? $nframework->excelOut($libro, 'ventas')                         // ventas.xlsx
			: $nframework->excelOutPdf($libro, 'ventas', 'Dompdf', 'inline');  // ventas.pdf en el navegador
	} elseif ($descarga === 'docx' || $descarga === 'docxpdf') {
		$documento = new PhpWord();
		$seccion = $documento->addSection();
		$seccion->addTitle('Reporte de ventas', 1);
		$seccion->addText('Generado el ' . date('d/m/Y H:i'));
		$tabla = $seccion->addTable(['borderSize' => 6, 'cellMargin' => 60]);
		foreach ($ventas as $fila) {
			$tabla->addRow();
			foreach ($fila as $celda) {
				$tabla->addCell(2500)->addText((string) $celda);
			}
		}
		if ($descarga === 'docx') {
			$nframework->wordOut($documento, 'reporte');                     // reporte.docx
		} else {
			$config['word_pdf_converter'] = 'Dompdf';                        // esta demo no requiere LibreOffice
			$nframework->wordOutPdf($documento, 'reporte');                  // reporte.pdf
		}
	} elseif ($descarga === 'plantilla') {
		$plantilla = __DIR__ . '/tmp/plantilla.docx';
		if (!is_file($plantilla)) {
			// Plantilla de ejemplo; normalmente se diseña en Word con ${variables}.
			$base = new PhpWord();
			$base->addSection()->addText('Estimado(a) ${nombre}: su folio ${folio} quedó registrado el ${fecha}.');
			$base->save($plantilla);
		}
		$tp = new TemplateProcessor($plantilla);
		$tp->setValue('nombre', 'Ana Pérez');
		$tp->setValue('folio', 'A-' . rand(1000, 9999));
		$tp->setValue('fecha', date('d/m/Y'));
		header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
		header('Content-Disposition: attachment; filename="constancia.docx"');
		$tp->saveAs('php://output');
	}
	exit();
}

require 'common.php';
?>
<div class="container">
	<?= docHeader('Excel, Word y PDF', '<code>$nframework</code> tiene métodos para enviar al navegador documentos creados con <a href="https://phpspreadsheet.readthedocs.io" target="_blank">PhpSpreadsheet</a> y <a href="https://phpword.readthedocs.io" target="_blank">PhpWord</a>, como archivo o convertidos a PDF.') ?>

	<div class="mb-4">
		<a class="button" href="?descargar=xlsx"><span class="mif-file-excel"></span> Excel</a>
		<a class="button" href="?descargar=xlsxpdf" target="_blank"><span class="mif-file-pdf"></span> Excel → PDF</a>
		<a class="button" href="?descargar=docx"><span class="mif-file-word"></span> Word</a>
		<a class="button" href="?descargar=docxpdf" target="_blank"><span class="mif-file-pdf"></span> Word → PDF</a>
		<a class="button" href="?descargar=plantilla"><span class="mif-file-text"></span> Plantilla Word</a>
	</div>

	<h3>Excel</h3>
	<?= docCode(<<<'PHP'
use PhpOffice\PhpSpreadsheet\Spreadsheet;

$libro = new Spreadsheet();
$hoja = $libro->getActiveSheet();
$hoja->fromArray([['Región', 'Importe'], ['Norte', 1250.5], ['Sur', 980]], null, 'A1');
$hoja->setCellValue('B4', '=SUM(B2:B3)');

$nframework->excelOut($libro, 'ventas');                          // descarga ventas.xlsx
$nframework->excelOutPdf($libro, 'ventas', 'Dompdf', 'inline');   // PDF con Dompdf
$nframework->excelOutPdf($libro, 'ventas', 'unoconv', 'attachment'); // PDF con LibreOffice (unoconv)
PHP) ?>

	<h3>Word</h3>
	<?= docCode(<<<'PHP'
use PhpOffice\PhpWord\PhpWord;

$documento = new PhpWord();
$seccion = $documento->addSection();
$seccion->addTitle('Reporte de ventas', 1);
$seccion->addText('Generado el ' . date('d/m/Y'));

$nframework->wordOut($documento, 'reporte');      // reporte.docx
$nframework->wordOutPdf($documento, 'reporte');   // reporte.pdf
PHP) ?>
	<p>El convertidor a PDF se elige con <code>$config['word_pdf_converter']</code>: <code>'Dompdf'</code> (por defecto, puro PHP), <code>'unoconv'</code> o cualquier otro valor para usar un servidor de LibreOffice en el puerto 2002. LibreOffice respeta mucho mejor el formato.</p>

	<h3>Plantillas de Word</h3>
	<p>Diseñe el documento en Word con marcadores <code>${variable}</code> y llénelo desde PHP:</p>
	<?= docCode(<<<'PHP'
use PhpOffice\PhpWord\TemplateProcessor;

$tp = new TemplateProcessor(__DIR__ . '/plantillas/constancia.docx');
$tp->setValue('nombre', $alumno['nombre']);
$tp->setValue('folio', $alumno['folio']);
$tp->cloneRow('materia', count($materias));          // repetir una fila de tabla
foreach ($materias as $i => $materia) {
    $tp->setValue('materia#' . ($i + 1), $materia['nombre']);
    $tp->setValue('calificacion#' . ($i + 1), $materia['calificacion']);
}
$nframework->wordTemplateOutPdf($tp, 'constancia');   // PDF (unoconv salvo word_pdf_converter = 'Dompdf')
PHP) ?>

	<h3>Descargar un archivo existente</h3>
	<?= docCode(<<<'PHP'
$user->requireAuth();
$archivo = '/var/data/recibos/' . basename($_GET['f'] ?? '');   // basename: impide ../
$nframework->downloadfrom($archivo);   // cabeceras de descarga + readfile; excepción si no existe
PHP) ?>
	<p class="remark">Las descargas deben generarse antes de imprimir HTML. Si la página usa la plantilla del sitio, defina <code>$nfshutdowndisable</code> y <code>$nfjavaobfuscatedisable</code> antes de <code>require 'include.php'</code> (ver el código de esta página y <a href="vars.php">Variables especiales</a>).</p>
	<?= docSource(__FILE__) ?>
</div>
