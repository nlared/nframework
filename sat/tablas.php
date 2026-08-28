<?php


$tablasyear = 2026;
$tablas = [];
$tablas['salarios'] = [
    2012 => ['A' => 62.33, 'B' => 60.57, 'C' => 59.08],
    2013 => ['A' => 67.29, 'B' => 63.77],
    2014 => ['A' => 67.29, 'B' => 63.77],
    2015 => ['A' => 67.29, 'B' => 63.77],
    2016 => ['A' => 73.04, 'B' => 73.04, 'C' => 73.04],
    2018 => ['A' => 88.36],
    2019 => ['A' => 84.49],
    2020 => ['A' => 86.88],
    2021 => ['A' => 86.88],
    2024 => ['A' => 248.93],
    2025 => ['A' => 278.80],
    2026 => ['A' => 315.04],
];

$tablas['uma'] = [
    2019 => 84.49,
    2020 => 86.88,
    2021 => 86.88,
    2024 => 108.57,
    2025 => 113.14,
    2026 => 117.31,
];

$tablas['recargos'] = [
    2024 => 1.47,
];

$riesgoBase2012 = [
    1 => 0.54355,
    2 => 1.13065,
    3 => 2.5984,
    4 => 4.65325,
    5 => 7.58875,
];

$riesgoBase2024 = [
    1 => 0.5,
    2 => 1.13065,
    3 => 2.5984,
    4 => 4.65325,
    5 => 7.58875,
];

$tablas['riesgo'] = [
    2012 => $riesgoBase2012,
    2024 => $riesgoBase2024,
    2025 => $riesgoBase2024,
    2026 => $riesgoBase2024,
];

$tablas['cuotas_base'] = [
    'cuotaadicional' => ['patron' => 1.1, 'trabajador' => 0.4],
    'Pensionados' => ['patron' => 1.05, 'trabajador' => 0.375],
    'En Dinero' => ['patron' => 0.70, 'trabajador' => 0.25],
    'Invalidez y Vida' => ['patron' => 1.75, 'trabajador' => 0.625],
    'Retiro' => ['patron' => 2, 'trabajador' => 0],
    'CEAV' => ['patron' => 3.15, 'trabajador' => 1.125],
    'Guarderias y Prestaciones Sociales' => ['patron' => 1, 'trabajador' => 0],
    'INFONAVIT' => ['patron' => 5, 'trabajador' => 0],
];

$tablas['vigencias'] = [
    'isr' => [
        '2012-01-01' => 2012,
        '2014-01-01' => 2014,
        '2017-01-01' => 2017,
        '2018-01-01' => 2018,
        '2021-01-01' => 2021,
        '2024-01-01' => 2024,
    ],
    'subisr' => [
        '2012-01-01' => 2012,
        '2017-01-01' => 2017,
        '2018-01-01' => 2018,
    ],
    'riesgo' => [
        '2012-01-01' => 2012,
        '2024-01-01' => 2024,
    ],
    'uma' => [
        '2019-01-01' => 2019,
        '2020-01-01' => 2020,
        '2024-01-01' => 2024,
        '2025-01-01' => 2025,
        '2026-01-01' => 2026,
    ],
    'salarios' => [
        '2012-01-01' => 2012,
        '2013-01-01' => 2013,
        '2016-01-01' => 2016,
        '2018-01-01' => 2018,
        '2019-01-01' => 2019,
        '2020-01-01' => 2020,
        '2024-01-01' => 2024,
        '2025-01-01' => 2025,
        '2026-01-01' => 2026,
    ],
];

$isr2012 = '0.01	496.07	0.00	1.92
496.08	4,210.41	9.52	6.40
4,210.42	7,399.42	247.23	10.88
7,399.43	8,601.50	594.24	16.00
8,601.51	10,298.35	786.55	17.92
10,298.36	20,770.29	1,090.62	21.36
20,770.30	32,736.83	3,327.42	23.52
32,736.84	En adelante	6,141.95	30.00';

$subisr2012 = '0.01	1,768.96	407.02
1,768.97	2,653.38	406.83
2,653.39	3,472.84	406.62
3,472.85	3,537.87	392.77
3,537.88	4,446.15	382.46
4,446.16	4,717.18	354.23
4,717.19	5,335.42	324.87
5,335.43	6,224.67	294.63
6,224.68	7,113.90	253.54
7,113.91	7,382.33	217.61
7,382.34	En adelante	0';

$isr2014 = '0.01	496.07	0	1.92%
496.08	4,210.41	9.52	6.40%
4,210.42	7,399.42	247.24	10.88%
7,399.43	8,601.50	594.21	16.00%
8,601.51	10,298.35	786.54	17.92%
10,298.36	20,770.29	1,090.61	21.36%
20,770.30	32,736.83	3,327.42	23.52%
32,736.84	62,500.00	6,141.95	30.00%
62,500.01	83,333.33	15,070.90	32.00%
83,333.34	250,000.00	21,737.57	34.00%
250,000.01	En adelante	78,404.23	35.00%';

$isr2017 = '0.01	496.07	0.00	1.92%
496.08	4,210.41	9.52	6.40%
4,210.42	7,399.42	247.23	10.88%
7,399.43	8,601.50	594.24	16.00%
8,601.51	10,298.35	786.55	17.92%
10,298.36	20,770.29	1,090.62	21.36%
20,770.30	32,736.83	3,327.42	23.52%
32,736.84	62,500.00	6,141.95	30.00%
62,500.01	83,333.33	15,070.90	32.00%
83,333.34	250,000.00	21,737.57	34.00%
250,000.01	En adelante	78,404.23	35.00%';

$subisr2017 = '0.01	1,768.96	407.02
1,768.97	1,978.70	406.83
1,978.71	2,653.38	359.84
2,653.39	3,472.84	343.60
3,472.85	3,537.87	310.00
3,537.88	4,446.00	298.44
4,446.01	4,717.18	354.23
4,717.19	5,335.42	324.87
5,335.43	6,224.67	294.63
6,224.68	7,113.90	253.54
7,113.91	7,382.33	217.61
7,382.34	En adelante	0.00';

$isr2018 = '0.01	578.52	0.00	1.92%
578.53	4,910.18	11.11	6.40%
4,910.19	8,629.20	288.33	10.88%
8,629.21	10,031.07	692.96	16.00%
10,031.08	12,009.94	917.26	17.92%
12,009.95	24,222.31	1,271.87	21.36%
24,222.32	38,177.69	3,880.44	23.52%
38,177.70	72,887.50	7,162.74	30.00%
72,887.51	97,183.33	17,575.69	32.00%
97,183.34	291,550.00	25,350.35	34.00%
291,550.01	En adelante	91,435.02	35.00%';

$subisr2018 = '0.01	1,768.96	407.02
1,768.97	2,653.38	406.83
2,653.39	3,472.84	406.62
3,472.85	3,537.87	392.77
3,537.88	4,446.15	382.46
4,446.16	4,717.18	354.23
4,717.19	5,335.42	324.87
5,335.43	6,224.67	294.63
6,224.68	7,113.90	253.54
7,113.91	7,382.33	217.61
7,382.34	En adelante	0.00';

$isr2021 = '0.01	644.58	0.00	1.92%
644.59	5,470.92	12.38	6.40%
5,470.93	9,614.66	321.26	10.88%
9,614.67	11,176.62	772.10	16.00%
11,176.63	13,381.47	1,022.01	17.92%
13,381.48	26,988.50	1,417.12	21.36%
26,988.51	42,537.58	4,323.58	23.52%
42,537.59	81,211.25	7,980.73	30.00%
81,211.26	108,281.67	19,582.83	32.00%
108,281.68	324,845.01	28,245.36	34.00%
324,845.02	En adelante	101,876.90	35.00%';

$isr2024 = '0.01	746.04	0.00	1.92%
746.05	6,332.05	14.32	6.40%
6,332.06	11,128.01	371.83	10.88%
11,128.02	12,935.82	893.63	16.00%
12,935.83	15,487.71	1,182.88	17.92%
15,487.72	31,236.49	1,640.18	21.36%
31,236.50	49,233.00	5,004.12	23.52%
49,233.01	93,993.90	9,236.89	30.00%
93,993.91	125,325.20	22,665.17	32.00%
125,325.21	375,975.61	32,691.18	34.00%
375,975.62	En adelante	117,912.32	35.00%';

$tablas[2012]['isr']['str'] = $isr2012;
$tablas[2012]['subisr']['str'] = $subisr2012;

foreach ([2014, 2015, 2016] as $ano) {
    $tablas[$ano]['isr']['str'] = $isr2014;
    $tablas[$ano]['subisr']['str'] = $subisr2012;
}

$tablas[2017]['isr']['str'] = $isr2017;
$tablas[2017]['subisr']['str'] = $subisr2017;

foreach ([2018, 2019, 2020] as $ano) {
    $tablas[$ano]['isr']['str'] = $isr2018;
    $tablas[$ano]['subisr']['str'] = $subisr2018;
}

$tablas[2021]['isr']['str'] = $isr2021;
$tablas[2021]['subisr']['str'] = $subisr2018;

foreach ([2024, 2025, 2026] as $ano) {
    $tablas[$ano]['isr']['str'] = $isr2024;
    $tablas[$ano]['subisr']['str'] = $subisr2018;
    $tablas[$ano]['despensap'] = 40;
    $tablas[$ano]['puntualidadp'] = 10;
    $tablas[$ano]['asistenciap'] = 10;
}

foreach($m->{$config['sitedb']}->prestaciones as $prestacion) {
    $ano = (int)$prestacion->anio;
    $tablas[$ano]['despensap'] = (float)$prestacion->despensa;
    $tablas[$ano]['puntualidadp'] = (float)$prestacion->puntualidad;
    $tablas[$ano]['asistenciap'] = (float)$prestacion->asistencia;
}


$tablas[2020]['despensap'] = 40;
$tablas[2020]['puntualidadp'] = 10;
$tablas[2020]['asistenciap'] = 10;

function resolverVigencia(array $vigenciasPorFecha, ?string $fecha = null): int
{
    $fechaBase = $fecha ? strtotime($fecha) : time();
    ksort($vigenciasPorFecha);

    $vigente = reset($vigenciasPorFecha);
    foreach ($vigenciasPorFecha as $fechaInicio => $valor) {
        if (strtotime($fechaInicio) <= $fechaBase) {
            $vigente = $valor;
            continue;
        }
        break;
    }

    return (int) $vigente;
}

function anoVigenteRubro(string $rubro, ?string $fecha = null): int
{
    global $tablas;
    if (empty($tablas['vigencias'][$rubro])) {
        throw new InvalidArgumentException('Rubro sin vigencias: ' . $rubro);
    }

    return resolverVigencia($tablas['vigencias'][$rubro], $fecha);
}

function anoVigenteEnCatalogo(array $catalogo, int $ano): int
{
    $anios = array_keys($catalogo);
    sort($anios, SORT_NUMERIC);

    $vigente = $anios[0];
    foreach ($anios as $anio) {
        if ($anio <= $ano) {
            $vigente = $anio;
            continue;
        }
        break;
    }

    return $vigente;
}

function construirFijaYRiesgoHastaAno(int $anoObjetivo): void
{
    global $tablas;

    for ($ano = 2012; $ano <= $anoObjetivo; $ano++) {
        $anoRiesgo = anoVigenteEnCatalogo($tablas['riesgo'], $ano);
        $tablas['riesgo'][$ano] = $tablas['riesgo'][$anoRiesgo];

        if (isset($tablas['uma'][$ano])) {
            $tablas['fija'][$ano] = 0.204 * $tablas['uma'][$ano];
            continue;
        }

        $anoSalario = anoVigenteEnCatalogo($tablas['salarios'], $ano);
        $tablas['fija'][$ano] = 0.204 * $tablas['salarios'][$anoSalario]['A'];
    }
}

construirFijaYRiesgoHastaAno($tablasyear);

function limpiarNumeroTabla(string $valor): float
{
    $normalizado = str_replace([' ', ',', '%'], '', trim($valor));
    $normalizado = str_replace('Enadelante', 'INF', $normalizado);

    if (strcasecmp($normalizado, 'INF') === 0) {
        return INF;
    }

    return (float) $normalizado;
}

function parsearTabla(string $raw, int $columnas): array
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
            $fila[] = limpiarNumeroTabla($partes[$i]);
        }
        $resultado[] = $fila;
    }

    return $resultado;
}

function obtenerTablaParseada(string $rubro, int $ano): array
{
    global $tablas;
    static $cache = ['isr' => [], 'subisr' => []];

    if ($rubro !== 'isr' && $rubro !== 'subisr') {
        throw new InvalidArgumentException('Rubro no soportado en parseo: ' . $rubro);
    }

    if (!isset($tablas[$ano][$rubro]['str'])) {
        $ano = anoVigenteRubro($rubro, $ano . '-12-31');
    }

    if (isset($cache[$rubro][$ano])) {
        return $cache[$rubro][$ano];
    }

    $columnas = $rubro === 'isr' ? 4 : 3;
    $cache[$rubro][$ano] = parsearTabla($tablas[$ano][$rubro]['str'], $columnas);

    return $cache[$rubro][$ano];
}

function buscarFilaPorValor(array $filas, float $valor, int $mes = 1): ?array
{
    $lo = 0;
    $hi = count($filas) - 1;
    $seleccion = null;

    while ($lo <= $hi) {
        $mid = intdiv($lo + $hi, 2);
        $fila = $filas[$mid];
        $inferior = $fila[0] * $mes;
        $superior = is_infinite($fila[1]) ? INF : $fila[1] * $mes;

        if ($valor < $inferior) {
            $hi = $mid - 1;
            continue;
        }

        $seleccion = $fila;
        if ($valor <= $superior) {
            break;
        }
        $lo = $mid + 1;
    }

    return $seleccion;
}

function calculoimss(float $sueldo, int $ano, int $claseRiesgo = 1): array
{
    global $tablas;

    $anoRiesgo = anoVigenteEnCatalogo($tablas['riesgo'], $ano);
    $primaRiesgo = $tablas['riesgo'][$anoRiesgo][$claseRiesgo] ?? $tablas['riesgo'][$anoRiesgo][1];

    $cuotas = $tablas['cuotas_base'];
    $cuotas['Riesgos de Trabajo'] = ['patron' => $primaRiesgo, 'trabajador' => 0];

    $resultado = [];
    foreach ($cuotas as $nombre => $porcentaje) {
        $resultado[$nombre] = [
            'patron' => $sueldo * $porcentaje['patron'],
            'trabajador' => $sueldo * $porcentaje['trabajador'],
            'patronp' => $porcentaje['patron'],
            'trabajadorp' => $porcentaje['trabajador'],
        ];
    }

    return $resultado;
}

function buscarisrentablas(int $ano, float $valor, int $mes = 1, float $factor = 1): array
{
    $valorAjustado = $valor * $factor;
    $filas = obtenerTablaParseada('isr', $ano);
    $fila = buscarFilaPorValor($filas, $valorAjustado, $mes);

    if ($fila === null) {
        return [];
    }

    $limite = $fila[0] * $mes;
    $superior = is_infinite($fila[1]) ? INF : $fila[1] * $mes;
    $fija = $fila[2] * $mes;
    $tasa = $fila[3];
    $excedente = max(0, $valorAjustado - $limite);
    $isr = ($excedente * $tasa / 100) + $fija;

    return [
        'limite' => $limite,
        'limitesuperior' => $superior,
        'fija' => $fija,
        'tasa' => $tasa,
        'excedente' => $excedente,
        'isr' => $isr,
        'tasaefectiva' => $valorAjustado > 0 ? ($isr * 100 / $valorAjustado) : 0,
    ];
}

function buscarsubisrentablas(int $ano, float $valor): array
{
    $filas = obtenerTablaParseada('subisr', $ano);
    $fila = buscarFilaPorValor($filas, $valor, 1);

    if ($fila === null) {
        return [];
    }

    return [
        'inferior' => $fila[0],
        'superior' => $fila[1],
        'subsidio' => $fila[2],
    ];
}
function obtenerSalarioMinimo(int $ano, string $zona = 'A'): float
{
    global $tablas;

    if (!isset($tablas['salarios'][$ano][$zona])) {
        throw new InvalidArgumentException("No se encontro salario minimo para el anio {$ano} y zona {$zona}");
    }

    return $tablas['salarios'][$ano][$zona];
}
function obtenerUMA(int $ano): float
{
    global $tablas;

    if (!isset($tablas['uma'][$ano])) {
        throw new InvalidArgumentException("No se encontro UMA para el anio {$ano}");
    }

    return $tablas['uma'][$ano];
}
function obtenerRecargo(int $ano): float
{
    global $tablas;

    if (!isset($tablas['recargos'][$ano])) {
        throw new InvalidArgumentException("No se encontro recargo para el anio {$ano}");
    }

    return $tablas['recargos'][$ano];
}

function obtenerFija(int $ano): float
{
    global $tablas;

    if (!isset($tablas['fija'][$ano])) {
        throw new InvalidArgumentException("No se encontro fija para el anio {$ano}");
    }

    return $tablas['fija'][$ano];
}
function factordeintegracion(int $ano, string $zona = 'A'): float
{
    $salarioMinimo = obtenerSalarioMinimo($ano, $zona);
    $uma = obtenerUMA($ano);

    return ($salarioMinimo + $uma) / $salarioMinimo;
}