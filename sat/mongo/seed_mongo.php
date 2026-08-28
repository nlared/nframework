<?php

if (!class_exists('MongoDB\\Client')) {
    fwrite(STDERR, "Falta la extension mongodb de PHP.\n");
    exit(1);
}

$config = ['sitedb' => 'sat'];
$m = (object) [
    'sat' => (object) [
        'prestaciones' => [],
    ],
];

require_once __DIR__ . '/../tablas.php';

$uri = $argv[1] ?? 'mongodb://127.0.0.1:27017';
$databaseName = $argv[2] ?? 'sat';

$client = new MongoDB\Client($uri);
$database = $client->selectDatabase($databaseName);

function upsertOne(MongoDB\Collection $collection, array $filter, array $document): void
{
    $collection->updateOne(
        $filter,
        ['$set' => $document],
        ['upsert' => true]
    );
}

function parseTablaMongo(string $raw, int $columnas): array
{
    $resultado = [];
    $lineas = preg_split('/\R+/', trim($raw));

    foreach ($lineas as $linea) {
        $partes = preg_split('/\t+/', trim($linea));
        if (count($partes) < $columnas) {
            continue;
        }

        $fila = [];
        for ($i = 0; $i < $columnas; $i++) {
            $valor = str_replace([' ', ',', '%'], '', trim($partes[$i]));
            if (strcasecmp($valor, 'Enadelante') === 0) {
                $fila[] = null;
                continue;
            }

            $fila[] = (float) $valor;
        }

        $resultado[] = $fila;
    }

    return $resultado;
}

$vigencias = [
    ['tipo' => 'isr', 'anio' => 2012, 'vigente_desde' => new MongoDB\BSON\UTCDateTime(strtotime('2012-01-01') * 1000)],
    ['tipo' => 'isr', 'anio' => 2014, 'vigente_desde' => new MongoDB\BSON\UTCDateTime(strtotime('2014-01-01') * 1000)],
    ['tipo' => 'isr', 'anio' => 2017, 'vigente_desde' => new MongoDB\BSON\UTCDateTime(strtotime('2017-01-01') * 1000)],
    ['tipo' => 'isr', 'anio' => 2018, 'vigente_desde' => new MongoDB\BSON\UTCDateTime(strtotime('2018-01-01') * 1000)],
    ['tipo' => 'isr', 'anio' => 2021, 'vigente_desde' => new MongoDB\BSON\UTCDateTime(strtotime('2021-01-01') * 1000)],
    ['tipo' => 'isr', 'anio' => 2024, 'vigente_desde' => new MongoDB\BSON\UTCDateTime(strtotime('2024-01-01') * 1000)],
    ['tipo' => 'subisr', 'anio' => 2012, 'vigente_desde' => new MongoDB\BSON\UTCDateTime(strtotime('2012-01-01') * 1000)],
    ['tipo' => 'subisr', 'anio' => 2017, 'vigente_desde' => new MongoDB\BSON\UTCDateTime(strtotime('2017-01-01') * 1000)],
    ['tipo' => 'subisr', 'anio' => 2018, 'vigente_desde' => new MongoDB\BSON\UTCDateTime(strtotime('2018-01-01') * 1000)],
    ['tipo' => 'riesgo', 'anio' => 2012, 'vigente_desde' => new MongoDB\BSON\UTCDateTime(strtotime('2012-01-01') * 1000)],
    ['tipo' => 'riesgo', 'anio' => 2024, 'vigente_desde' => new MongoDB\BSON\UTCDateTime(strtotime('2024-01-01') * 1000)],
    ['tipo' => 'uma', 'anio' => 2019, 'vigente_desde' => new MongoDB\BSON\UTCDateTime(strtotime('2019-01-01') * 1000)],
    ['tipo' => 'uma', 'anio' => 2020, 'vigente_desde' => new MongoDB\BSON\UTCDateTime(strtotime('2020-01-01') * 1000)],
    ['tipo' => 'uma', 'anio' => 2024, 'vigente_desde' => new MongoDB\BSON\UTCDateTime(strtotime('2024-01-01') * 1000)],
    ['tipo' => 'uma', 'anio' => 2025, 'vigente_desde' => new MongoDB\BSON\UTCDateTime(strtotime('2025-01-01') * 1000)],
    ['tipo' => 'uma', 'anio' => 2026, 'vigente_desde' => new MongoDB\BSON\UTCDateTime(strtotime('2026-01-01') * 1000)],
    ['tipo' => 'salarios', 'anio' => 2012, 'vigente_desde' => new MongoDB\BSON\UTCDateTime(strtotime('2012-01-01') * 1000)],
    ['tipo' => 'salarios', 'anio' => 2013, 'vigente_desde' => new MongoDB\BSON\UTCDateTime(strtotime('2013-01-01') * 1000)],
    ['tipo' => 'salarios', 'anio' => 2016, 'vigente_desde' => new MongoDB\BSON\UTCDateTime(strtotime('2016-01-01') * 1000)],
    ['tipo' => 'salarios', 'anio' => 2018, 'vigente_desde' => new MongoDB\BSON\UTCDateTime(strtotime('2018-01-01') * 1000)],
    ['tipo' => 'salarios', 'anio' => 2019, 'vigente_desde' => new MongoDB\BSON\UTCDateTime(strtotime('2019-01-01') * 1000)],
    ['tipo' => 'salarios', 'anio' => 2020, 'vigente_desde' => new MongoDB\BSON\UTCDateTime(strtotime('2020-01-01') * 1000)],
    ['tipo' => 'salarios', 'anio' => 2024, 'vigente_desde' => new MongoDB\BSON\UTCDateTime(strtotime('2024-01-01') * 1000)],
    ['tipo' => 'salarios', 'anio' => 2025, 'vigente_desde' => new MongoDB\BSON\UTCDateTime(strtotime('2025-01-01') * 1000)],
    ['tipo' => 'salarios', 'anio' => 2026, 'vigente_desde' => new MongoDB\BSON\UTCDateTime(strtotime('2026-01-01') * 1000)],
];

$collectionVigencias = $database->selectCollection('catalogos_vigentes');
foreach ($vigencias as $documento) {
    upsertOne($collectionVigencias, ['tipo' => $documento['tipo'], 'anio' => $documento['anio']], $documento);
}

$collectionTramos = $database->selectCollection('tramos_impuesto');
$mapaTramos = [
    'isr' => [
        2012 => $isr2012,
        2014 => $isr2014,
        2017 => $isr2017,
        2018 => $isr2018,
        2021 => $isr2021,
        2024 => $isr2024,
    ],
    'subisr' => [
        2012 => $subisr2012,
        2017 => $subisr2017,
        2018 => $subisr2018,
    ],
];

foreach ($mapaTramos as $tipo => $porAnio) {
    foreach ($porAnio as $anio => $raw) {
        $columnas = $tipo === 'isr' ? 4 : 3;
        $filas = parseTablaMongo($raw, $columnas);
        $tramos = [];

        foreach ($filas as $fila) {
            if ($tipo === 'isr') {
                $tramos[] = [
                    'limite_inferior' => $fila[0],
                    'limite_superior' => $fila[1],
                    'cuota_fija' => $fila[2],
                    'tasa' => $fila[3],
                ];
                continue;
            }

            $tramos[] = [
                'limite_inferior' => $fila[0],
                'limite_superior' => $fila[1],
                'subsidio' => $fila[2],
            ];
        }

        upsertOne(
            $collectionTramos,
            ['tipo' => $tipo, 'anio' => $anio],
            [
                'tipo' => $tipo,
                'anio' => $anio,
                'vigente_desde' => new MongoDB\BSON\UTCDateTime(strtotime($anio . '-01-01') * 1000),
                'tramos' => $tramos,
            ]
        );
    }
}

$collectionSalarios = $database->selectCollection('salarios_minimos');
foreach ($tablas['salarios'] as $anio => $zonas) {
    upsertOne(
        $collectionSalarios,
        ['anio' => $anio],
        [
            'anio' => $anio,
            'vigente_desde' => new MongoDB\BSON\UTCDateTime(strtotime($anio . '-01-01') * 1000),
            'zonas' => $zonas,
        ]
    );
}

$collectionUma = $database->selectCollection('uma');
foreach ($tablas['uma'] as $anio => $valor) {
    upsertOne(
        $collectionUma,
        ['anio' => $anio],
        [
            'anio' => $anio,
            'vigente_desde' => new MongoDB\BSON\UTCDateTime(strtotime($anio . '-01-01') * 1000),
            'valor' => (float) $valor,
        ]
    );
}

$collectionRecargos = $database->selectCollection('recargos');
foreach ($tablas['recargos'] as $anio => $valor) {
    upsertOne(
        $collectionRecargos,
        ['anio' => $anio],
        [
            'anio' => $anio,
            'vigente_desde' => new MongoDB\BSON\UTCDateTime(strtotime($anio . '-01-01') * 1000),
            'valor' => (float) $valor,
        ]
    );
}

$collectionRiesgo = $database->selectCollection('riesgo_trabajo');
foreach ($tablas['riesgo'] as $anio => $clases) {
    if (!is_array($clases)) {
        continue;
    }

    $clasesMongo = [];
    foreach ($clases as $clase => $prima) {
        $clasesMongo[] = [
            'clase' => (int) $clase,
            'prima' => (float) $prima,
        ];
    }

    upsertOne(
        $collectionRiesgo,
        ['anio' => $anio],
        [
            'anio' => $anio,
            'vigente_desde' => new MongoDB\BSON\UTCDateTime(strtotime($anio . '-01-01') * 1000),
            'clases' => $clasesMongo,
        ]
    );
}

$collectionCuotas = $database->selectCollection('cuotas_base');
foreach ($tablas['cuotas_base'] as $concepto => $valores) {
    upsertOne(
        $collectionCuotas,
        ['concepto' => $concepto],
        [
            'concepto' => $concepto,
            'patron' => (float) $valores['patron'],
            'trabajador' => (float) $valores['trabajador'],
        ]
    );
}

$collectionPrestaciones = $database->selectCollection('prestaciones');
foreach ([2020 => ['despensa' => 40, 'puntualidad' => 10, 'asistencia' => 10], 2024 => ['despensa' => 40, 'puntualidad' => 10, 'asistencia' => 10], 2025 => ['despensa' => 40, 'puntualidad' => 10, 'asistencia' => 10], 2026 => ['despensa' => 40, 'puntualidad' => 10, 'asistencia' => 10]] as $anio => $valores) {
    upsertOne(
        $collectionPrestaciones,
        ['anio' => $anio],
        [
            'anio' => $anio,
            'vigente_desde' => new MongoDB\BSON\UTCDateTime(strtotime($anio . '-01-01') * 1000),
            'despensa' => (float) $valores['despensa'],
            'puntualidad' => (float) $valores['puntualidad'],
            'asistencia' => (float) $valores['asistencia'],
        ]
    );
}

echo "MongoDB poblado correctamente en base {$databaseName}.\n";
