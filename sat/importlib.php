<?php
require_once __DIR__ . '/classes/cfdv40/Comprobante.php';
require_once __DIR__ . '/classes/cfdv40/ComprobanteComplemento.php';
require_once __DIR__ . '/classes/cfdv40/ComprobanteEmisor.php';
require_once __DIR__ . '/classes/cfdv40/ComprobanteReceptor.php';
require_once __DIR__ . '/classes/cfdv40/ComprobanteConceptos.php';
require_once __DIR__ . '/classes/cfdv40/ComprobanteConceptosConcepto.php';
require_once __DIR__ . '/classes/nomina12/Nomina.php';
require_once __DIR__ . '/classes/nomina12/NominaPercepciones.php';
require_once __DIR__ . '/classes/nomina12/NominaPercepcionesPercepcion.php';
require_once __DIR__ . '/classes/nomina12/NominaDeducciones.php';
require_once __DIR__ . '/classes/nomina12/NominaDeduccionesDeduccion.php';




$classTranslations = [
    '\\Complemento' => '\\SAT\\Generated\\cfdv40\\ComprobanteComplemento',
    '\\Emisor' => '\\SAT\\Generated\\cfdv40\\ComprobanteEmisor',
    '\\Receptor' => '\\SAT\\Generated\\cfdv40\\ComprobanteReceptor',
    '\\Conceptos' => '\\SAT\\Generated\\cfdv40\\ComprobanteConceptos',
    '\\Concepto' => '\\SAT\\Generated\\cfdv40\\ComprobanteConceptosConcepto',
    '\\Percepciones' => '\\SAT\\Generated\\nomina12\\NominaPercepciones',
    '\\Percepcion' => '\\SAT\\Generated\\nomina12\\NominaPercepcionesPercepcion',
    '\\Deducciones' => '\\SAT\\Generated\\nomina12\\NominaDeducciones',
    '\\Deduccion' => '\\SAT\\Generated\\nomina12\\NominaDeduccionesDeduccion',
];

$namespaceTranslations = [
    '\\cfdi' => '\\SAT\\Generated\\cfdv40',
    '\\nomina12' => '\\SAT\\Generated\\nomina12',
];

function validateProcessingRequirements(): void
{
    if (!class_exists('DOMDocument')) {
        throw new RuntimeException('DOMDocument extension is required to process CFDI XML files.');
    }

    if (!class_exists('ZipArchive') && defined('PHP_SAPI')) {
        // ZipArchive is only required when ZIP files are being processed.
    }

    $requiredClasses = [
        'SAT\\Generated\\cfdv40\\Comprobante',
        'SAT\\Generated\\cfdv40\\ComprobanteEmisor',
        'SAT\\Generated\\cfdv40\\ComprobanteReceptor',
        'SAT\\Generated\\cfdv40\\ComprobanteConceptos',
        'SAT\\Generated\\cfdv40\\ComprobanteConceptosConcepto',
    ];

    foreach ($requiredClasses as $className) {
        if (!class_exists($className)) {
            throw new RuntimeException('Missing required CFDI class: ' . $className);
        }
    }

    if (isset($GLOBALS['user'])) {
        if (!isset($GLOBALS['user']->username) || trim((string)$GLOBALS['user']->username) === '') {
            throw new RuntimeException('A valid user must exist before processing CFDI files.');
        }
    }
}

function validateXmlFile(string $file): void
{
    if (!is_file($file)) {
        throw new RuntimeException('File does not exist: ' . $file);
    }

    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = false;

    if (!$dom->load($file)) {
        throw new RuntimeException('Invalid XML file: ' . $file);
    }

    if (!$dom->documentElement) {
        throw new RuntimeException('The XML file has no root element: ' . $file);
    }

    $rootName = $dom->documentElement->localName ?? $dom->documentElement->nodeName;
    if ($rootName !== 'Comprobante') {
        throw new RuntimeException('The XML root element is not a CFDI Comprobante: ' . $dom->documentElement->nodeName);
    }
}

function mapElementAttributesToObject(DOMElement $element): stdClass
{
    $obj = new stdClass();
    foreach ($element->attributes as $attribute) {
        $attributeName = $attribute->localName ?? $attribute->nodeName;
        $obj->{$attributeName} = $attribute->nodeValue;
    }
    return $obj;
}

function mapElementRecursivelyToObject(DOMElement $element): stdClass
{
    $obj = mapElementAttributesToObject($element);

    foreach ($element->childNodes as $childNode) {
        if (!($childNode instanceof DOMElement)) {
            continue;
        }

        $childName = $childNode->localName ?? $childNode->nodeName;
        $childValue = mapElementRecursivelyToObject($childNode);

        if (property_exists($obj, $childName)) {
            if (!is_array($obj->{$childName})) {
                $obj->{$childName} = [$obj->{$childName}];
            }
            $obj->{$childName}[] = $childValue;
            continue;
        }

        $obj->{$childName} = $childValue;
    }

    return $obj;
}

function extractCfdiUuid(SAT\Generated\cfdv40\Comprobante $cfdi): string
{
    if (!isset($cfdi->Complemento) || !is_object($cfdi->Complemento) || !isset($cfdi->Complemento->TimbreFiscalDigital)) {
        return '';
    }

    $timbre = $cfdi->Complemento->TimbreFiscalDigital;
    if (is_array($timbre)) {
        $timbre = $timbre[0] ?? null;
    }

    if (!is_object($timbre)) {
        return '';
    }

    if (isset($timbre->UUID) && trim((string)$timbre->UUID) !== '') {
        return trim((string)$timbre->UUID);
    }

    if (isset($timbre->Uuid) && trim((string)$timbre->Uuid) !== '') {
        return trim((string)$timbre->Uuid);
    }

    return '';
}

function extractEmisorRfc(SAT\Generated\cfdv40\Comprobante $cfdi): string
{
    if (!isset($cfdi->Emisor) || !is_object($cfdi->Emisor) || !isset($cfdi->Emisor->Rfc)) {
        return '';
    }

    $rfc = trim((string)$cfdi->Emisor->Rfc);
    return $rfc;
}

function findFirstLocalNameElement(DOMElement $parent, string $tagName): ?DOMElement
{
    foreach ($parent->childNodes as $node) {
        if ($node instanceof DOMElement && $node->localName === $tagName) {
            return $node;
        }
    }
    return null;
}

function hydrateCfdiFromDom(DOMDocument $dom, SAT\Generated\cfdv40\Comprobante $cfdi): void
{
    $root = $dom->documentElement;
    if (!$root) {
        return;
    }

    foreach ($root->attributes as $attribute) {
        $name = $attribute->nodeName;
        if (property_exists($cfdi, $name)) {
            $cfdi->{$name} = $attribute->nodeValue;
        }
    }

    $emisorNode = findFirstLocalNameElement($root, 'Emisor');
    if ($emisorNode instanceof DOMElement) {
        $cfdi->Emisor = mapElementAttributesToObject($emisorNode);
    }

    $receptorNode = findFirstLocalNameElement($root, 'Receptor');
    if ($receptorNode instanceof DOMElement) {
        $cfdi->Receptor = mapElementAttributesToObject($receptorNode);
    }

    $conceptosNode = findFirstLocalNameElement($root, 'Conceptos');
    if ($conceptosNode instanceof DOMElement) {
        $conceptos = [];
        foreach ($conceptosNode->childNodes as $node) {
            if ($node instanceof DOMElement && $node->localName === 'Concepto') {
                $conceptos[] = mapElementAttributesToObject($node);
            }
        }
        $cfdi->Conceptos = (object) ['Concepto' => $conceptos];
    }

    $complementoNode = findFirstLocalNameElement($root, 'Complemento');
    if ($complementoNode instanceof DOMElement) {
        $cfdi->Complemento = mapElementRecursivelyToObject($complementoNode);
    }
}

function processExtractedFile($file)
{
    global $namespaceTranslations, $classTranslations, $config, $m;

    validateProcessingRequirements();
    validateXmlFile($file);

    echo "Processing file: " . $file . "\n";

    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = false;
    $dom->load($file);

    $cfdi = new SAT\Generated\cfdv40\Comprobante();
    hydrateCfdiFromDom($dom, $cfdi);
    $uuid = extractCfdiUuid($cfdi);
    $emisorRfc = extractEmisorRfc($cfdi);

    if ($uuid === '') {
        throw new RuntimeException('No se encontro UUID en el Complemento/TimbreFiscalDigital del CFDI.');
    }

    if ($emisorRfc === '') {
        throw new RuntimeException('No se encontro RFC del Emisor en el CFDI.');
    }


    // En CLI (worker de descargasat) DOCUMENT_ROOT viene vacío, por eso se usa la raíz del sitio relativa a este archivo
    $documentRoot = !empty($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : dirname(__DIR__);
    $destinationPath = $documentRoot . '/comprobantes/xmls/' . $emisorRfc . '/' . $uuid . '.xml';
    echo "Extracted file will be saved to: " . $destinationPath . "\n";
    echo "Destination path: " . $destinationPath . "\n";
    if (!is_dir(dirname($destinationPath))) {
        mkdir(dirname($destinationPath), 0777, true);
    }
    file_put_contents($destinationPath, $dom->saveXML());

    if ($m->{$config['sitedb']}->sat_comprobantes->findOne([
        '$or' => [
            ['Complemento.TimbreFiscalDigital.UUID' => $uuid],
            ['Complemento.TimbreFiscalDigital.Uuid' => $uuid],
        ],
    ]) === null) {
        $m->{$config['sitedb']}->sat_comprobantes->insertOne($cfdi);
    }

    $conceptCount = 0;
    if (isset($cfdi->Conceptos) && is_object($cfdi->Conceptos) && isset($cfdi->Conceptos->Concepto) && is_array($cfdi->Conceptos->Concepto)) {
        $conceptCount = count($cfdi->Conceptos->Concepto);
    }

    echo "Lectura OK\n";
    echo 'Serie: ' . ($cfdi->Serie ?? '') . "\n";
    echo 'Folio: ' . ($cfdi->Folio ?? '') . "\n";
    echo 'Total: ' . ($cfdi->Total ?? '') . "\n";
    echo 'Emisor RFC: ' . ($cfdi->Emisor->Rfc ?? '') . "\n";
    echo 'Receptor RFC: ' . ($cfdi->Receptor->Rfc ?? '') . "\n";
    echo 'Conceptos: ' . $conceptCount . "\n\n";

    echo "XML reconstruido:\n";
    echo (string)$cfdi . "\n";
}

/**
 * Importa un XML o un ZIP de XMLs a sat_comprobantes.
 * Un CFDI inválido dentro del ZIP no detiene la importación del resto.
 * Lanza RuntimeException si el archivo no existe o no se puede abrir.
 *
 * @return array{imported:int, errors:array<string,string>}
 */
function satImportFile(string $file): array
{
    validateProcessingRequirements();

    if (trim($file) === '' || !file_exists($file)) {
        throw new RuntimeException('File does not exist: ' . $file);
    }

    $result = ['imported' => 0, 'errors' => []];

    if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'zip') {
        processExtractedFile($file);
        $result['imported']++;
        return $result;
    }

    $zip = new ZipArchive;
    if ($zip->open($file) !== true) {
        throw new RuntimeException('Failed to open zip file: ' . $file);
    }

    $tmpdir = sys_get_temp_dir() . '/' . uniqid('zip_', true);
    mkdir($tmpdir);
    try {
        $zip->extractTo($tmpdir);
        $zip->close();
        echo "Zip file extracted successfully. Files extracted to: " . $tmpdir . "\n";

        foreach (glob($tmpdir . '/*') as $extractedFile) {
            echo "Processing extracted file: " . $extractedFile . "\n";
            try {
                processExtractedFile($extractedFile);
                $result['imported']++;
            } catch (Throwable $e) {
                $result['errors'][basename($extractedFile)] = $e->getMessage();
                echo "Error processing " . basename($extractedFile) . ": " . $e->getMessage() . "\n";
            }
        }
    } finally {
        foreach (glob($tmpdir . '/*') ?: [] as $extractedFile) {
            @unlink($extractedFile);
        }
        @rmdir($tmpdir);
    }

    return $result;
}
