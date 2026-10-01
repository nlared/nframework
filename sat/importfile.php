<?php
$isCli = PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg' || defined('STDIN');

if ($isCli) {
    require 'include.php';
    if (isset($argv[1])) {
        $file = $argv[1];
    } else {
        fwrite(STDERR, "Usage: php importfile.php <file>\n");
        exit(1);
    }
}


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
        $obj->{$attribute->nodeName} = $attribute->nodeValue;
    }
    return $obj;
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
}

function processExtractedFile($file)
{
    global $namespaceTranslations, $classTranslations;

    validateProcessingRequirements();
    validateXmlFile($file);

    echo "Processing file: " . $file . "\n";

    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = false;
    $dom->load($file);

    $cfdi = new SAT\Generated\cfdv40\Comprobante();
    hydrateCfdiFromDom($dom, $cfdi);


    $destinationPath = $_SERVER['DOCUMENT_ROOT'] . '/comprobantes/xmls/' . $cfdi->Emisor->Rfc . '/' . $cfdi->Complemento->TimbreFiscalDigital->Uuid . '.xml'; // Replace with the actual destination path
    echo "Extracted file will be saved to: " . $destinationPath . "\n";
    echo "Destination path: " . $destinationPath . "\n";
    if (!is_dir(dirname($destinationPath))) {
        mkdir(dirname($destinationPath), 0777, true);
    }
    file_put_contents($destinationPath, $dom->saveXML());
    $m->{$config['sitedb']}->sat_comprobantes->insertOne($cfdi);

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
try {
    validateProcessingRequirements();

    if (!isset($file) || trim((string)$file) === '') {
        throw new RuntimeException('No file was provided for processing.');
    }

    if (file_exists($file)) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'zip') {
            $zip = new ZipArchive;
            if ($zip->open($file) === TRUE) {
                $tmpdir = sys_get_temp_dir() . '/' . uniqid('zip_', true);
                mkdir($tmpdir);
                $zip->extractTo($tmpdir);
                $zip->close();
                echo "Zip file extracted successfully.";
                echo "Files extracted to: " . $tmpdir;
                glob($tmpdir . '/*');
                foreach (glob($tmpdir . '/*') as $file) {
                    echo "Processing extracted file: " . $file . "\n";
                    processExtractedFile($file);
                    unlink($file); // Delete the extracted file after processing
                }
                rmdir($tmpdir); // Delete the temporary directory after processing all files

                // You can now process the extracted files in $tmpdir as needed.

            } else {
                echo "Failed to open zip file.";
            }
            // Handle zip file
        } else {
            validateXmlFile($file);
            processExtractedFile($file);
        }
    } else {
        throw new RuntimeException('File does not exist: ' . $file);
    }
} catch (Exception $e) {
    if (isset($tmpdir) && is_dir($tmpdir)) {
        // Clean up the temporary directory if it exists
        foreach (glob($tmpdir . '/*') as $file) {
            unlink($file);
        }
        rmdir($tmpdir);
    }
    echo "An error occurred: " . $e->getMessage() . "\n";
    fwrite(STDERR, "An error occurred: " . $e->getMessage() . "\n");
    exit(1);
}
