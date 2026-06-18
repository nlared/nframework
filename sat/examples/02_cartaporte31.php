<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/class.XMLS.php';
require_once __DIR__ . '/../classes/CartaPorte31/Cartaporte.php';
require_once __DIR__ . '/../classes/CartaPorte31/CartaporteUbicaciones.php';
require_once __DIR__ . '/../classes/CartaPorte31/CartaporteUbicacionesUbicacion.php';
require_once __DIR__ . '/../classes/CartaPorte31/CartaporteUbicacionesUbicacionDomicilio.php';
require_once __DIR__ . '/../classes/CartaPorte31/CartaporteMercancias.php';
require_once __DIR__ . '/../classes/CartaPorte31/CartaporteMercanciasMercancia.php';

use SAT\Generated\CartaPorte31\Cartaporte;
use SAT\Generated\CartaPorte31\CartaporteMercancias;
use SAT\Generated\CartaPorte31\CartaporteMercanciasMercancia;
use SAT\Generated\CartaPorte31\CartaporteUbicaciones;
use SAT\Generated\CartaPorte31\CartaporteUbicacionesUbicacion;
use SAT\Generated\CartaPorte31\CartaporteUbicacionesUbicacionDomicilio;

$cartaPorte = new Cartaporte([
    'Version' => '3.1',
    'IdCCP' => 'CCC9C3F2-6F9A-4F31-BE20-111111111111',
    'TranspInternac' => 'No',
    'TotalDistRec' => '25.4',
]);

$origen = date('Y-m-d\\TH:i:s');
$destino = date('Y-m-d\\TH:i:s', time() + 3600);

$cartaPorte->Ubicaciones = new CartaporteUbicaciones();

$ubicacionOrigen = new CartaporteUbicacionesUbicacion([
    'TipoUbicacion' => 'Origen',
    'IDUbicacion' => 'OR000001',
    'RFCRemitenteDestinatario' => 'AAA010101AAA',
    'FechaHoraSalidaLlegada' => $origen,
]);
$ubicacionOrigen->Domicilio = new CartaporteUbicacionesUbicacionDomicilio([
    'Estado' => 'NL',
    'Pais' => 'MEX',
    'CodigoPostal' => '64000',
]);

$ubicacionDestino = new CartaporteUbicacionesUbicacion([
    'TipoUbicacion' => 'Destino',
    'IDUbicacion' => 'DE000001',
    'RFCRemitenteDestinatario' => 'XAXX010101000',
    'FechaHoraSalidaLlegada' => $destino,
]);
$ubicacionDestino->Domicilio = new CartaporteUbicacionesUbicacionDomicilio([
    'Estado' => 'NL',
    'Pais' => 'MEX',
    'CodigoPostal' => '64000',
]);

$cartaPorte->Ubicaciones->Ubicacion[] = $ubicacionOrigen;
$cartaPorte->Ubicaciones->Ubicacion[] = $ubicacionDestino;

$cartaPorte->Mercancias = new CartaporteMercancias([
    'PesoBrutoTotal' => '120.5',
    'UnidadPeso' => 'KGM',
    'NumTotalMercancias' => '1',
]);

$cartaPorte->Mercancias->Mercancia[] = new CartaporteMercanciasMercancia([
    'BienesTransp' => '01010101',
    'Descripcion' => 'Material promocional',
    'Cantidad' => '10',
    'ClaveUnidad' => 'H87',
    'PesoEnKg' => '120.5',
]);

header('Content-Type: application/xml; charset=UTF-8');
echo (string)$cartaPorte;
