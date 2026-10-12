<?php
/**
 * Conversión de tipos de sat_comprobantes.
 *
 * Las clases XMLS (y por tanto importlib.php) leen todos los atributos del XML como texto. Antes de guardar
 * se convierten a tipos de MongoDB para poder filtrar, ordenar y sumar:
 *   - fechas (Fecha, FechaTimbrado, FechaPago, ...)  -> UTCDateTime, interpretadas en hora de México
 *   - importes, cantidades y tipos de cambio          -> double
 *   - NumParcialidad, NumDiasPagados                   -> int / double
 *   - '' en un campo numérico o de fecha               -> null
 * Las claves de catálogo (FormaPago '01', UsoCFDI, CP, Serie, Folio, RFC, ...) se quedan como texto.
 *
 * Cada documento convertido lleva tipos_version = CFDI_TIPOS_VERSION y, en tipos_errores, los valores que no se
 * pudieron interpretar. importlib.php lo aplica al importar; para documentos importados antes, cada sitio
 * puede llamar cfdiNormalizarPendientes() desde un script de migración (se puede correr varias veces).
 */

const CFDI_TIPOS_VERSION = 1;

// Zona en que se interpreta la fecha local del CFDI (el XML no trae zona horaria).
const CFDI_TIPOS_ZONA = 'America/Mexico_City';

// Campos por nivel del documento: 'n' = double, 'i' = int, 'f' = fecha.
const CFDI_TIPOS_COMPROBANTE = ['Fecha' => 'f', 'SubTotal' => 'n', 'Descuento' => 'n', 'Total' => 'n', 'TipoCambio' => 'n'];
const CFDI_TIPOS_CONCEPTO = ['Cantidad' => 'n', 'ValorUnitario' => 'n', 'Importe' => 'n', 'Descuento' => 'n'];
const CFDI_TIPOS_TIMBRE = ['FechaTimbrado' => 'f'];
const CFDI_TIPOS_PAGO = ['FechaPago' => 'f', 'Monto' => 'n', 'TipoCambioP' => 'n'];
const CFDI_TIPOS_PAGOS_TOTALES = [
	'TotalRetencionesIVA' => 'n', 'TotalRetencionesISR' => 'n', 'TotalRetencionesIEPS' => 'n',
	'TotalTrasladosBaseIVA16' => 'n', 'TotalTrasladosImpuestoIVA16' => 'n', 'TotalTrasladosBaseIVA8' => 'n',
	'TotalTrasladosImpuestoIVA8' => 'n', 'TotalTrasladosBaseIVA0' => 'n', 'TotalTrasladosImpuestoIVA0' => 'n',
	'TotalTrasladosBaseIVAExento' => 'n', 'MontoTotalPagos' => 'n',
];
const CFDI_TIPOS_DOCTO = [
	'EquivalenciaDR' => 'n', 'NumParcialidad' => 'i', 'ImpSaldoAnt' => 'n', 'ImpPagado' => 'n', 'ImpSaldoInsoluto' => 'n',
];
const CFDI_TIPOS_NOMINA = [
	'FechaPago' => 'f', 'FechaInicialPago' => 'f', 'FechaFinalPago' => 'f', 'NumDiasPagados' => 'n',
	'TotalPercepciones' => 'n', 'TotalDeducciones' => 'n', 'TotalOtrosPagos' => 'n',
];

/**
 * Convierte un valor según su tipo. Regresa [valor, ok]; ok = false si el texto no se pudo interpretar
 * (en ese caso el valor original se conserva).
 */
function cfdiTiposValor($valor, string $tipo): array
{
	if ($valor === null) {
		return [null, true];
	}
	if ($tipo === 'f') {
		if ($valor instanceof \MongoDB\BSON\UTCDateTime) {
			return [$valor, true];
		}
		$texto = trim((string) $valor);
		if ($texto === '') {
			return [null, true];
		}
		// AAAA-MM-DD o AAAA-MM-DDThh:mm:ss (CFDI 3.2 a 4.0, Pagos, Nómina).
		if (!preg_match('/^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2}:\d{2})?$/', $texto)) {
			return [$valor, false];
		}
		try {
			$fecha = new DateTimeImmutable($texto, new DateTimeZone(CFDI_TIPOS_ZONA));
		} catch (Exception $e) {
			return [$valor, false];
		}
		return [new \MongoDB\BSON\UTCDateTime($fecha), true];
	}
	if (is_int($valor) || is_float($valor)) {
		return [$tipo === 'i' ? (int) $valor : (float) $valor, true];
	}
	$texto = trim((string) $valor);
	if ($texto === '') {
		return [null, true];
	}
	if (!is_numeric($texto)) {
		return [$valor, false];
	}
	return [$tipo === 'i' ? (int) $texto : (float) $texto, true];
}

/**
 * Aplica los tipos a los campos de un nodo (arreglo asociativo). Los campos que no existen se omiten.
 * Los valores que no se pudieron convertir se agregan a $errores como 'ruta.campo=valor'.
 */
function cfdiTiposNodo(array $nodo, array $campos, string $ruta, array &$errores): array
{
	foreach ($campos as $campo => $tipo) {
		if (!array_key_exists($campo, $nodo)) {
			continue;
		}
		[$valor, $ok] = cfdiTiposValor($nodo[$campo], $tipo);
		if (!$ok) {
			$errores[] = ltrim($ruta . '.' . $campo, '.') . '=' . $nodo[$campo];
		}
		$nodo[$campo] = $valor;
	}
	return $nodo;
}

/**
 * importlib.php guarda un nodo repetido como objeto si aparece una vez y como lista si aparece varias.
 * Aplica $fn a cada elemento respetando la forma original.
 */
function cfdiTiposCadaUno($valor, callable $fn)
{
	if (!is_array($valor)) {
		return $valor;
	}
	if (array_is_list($valor)) {
		return array_map(fn($elemento) => is_array($elemento) ? $fn($elemento) : $elemento, $valor);
	}
	return $fn($valor);
}

/**
 * Calcula el $set para un documento de sat_comprobantes leído con typeMap de arreglos.
 *
 * @return array{set: array, errores: string[]}
 */
function cfdiTiposDocumento(array $doc): array
{
	$errores = [];
	// Función que aplica los tipos de $campos a un nodo y acumula los errores con la ruta indicada.
	$aplicar = function (array $campos, string $ruta) use (&$errores): callable {
		return function (array $nodo) use ($campos, $ruta, &$errores): array {
			return cfdiTiposNodo($nodo, $campos, $ruta, $errores);
		};
	};

	$set = $aplicar(CFDI_TIPOS_COMPROBANTE, '')(array_intersect_key($doc, CFDI_TIPOS_COMPROBANTE));

	if (isset($doc['Conceptos']['Concepto']) && is_array($doc['Conceptos']['Concepto'])) {
		$set['Conceptos.Concepto'] = cfdiTiposCadaUno($doc['Conceptos']['Concepto'], $aplicar(CFDI_TIPOS_CONCEPTO, 'Conceptos.Concepto'));
	}

	$complemento = $doc['Complemento'] ?? null;
	if (is_array($complemento)) {
		if (isset($complemento['TimbreFiscalDigital'])) {
			$complemento['TimbreFiscalDigital'] = cfdiTiposCadaUno($complemento['TimbreFiscalDigital'], $aplicar(CFDI_TIPOS_TIMBRE, 'TimbreFiscalDigital'));
		}
		if (isset($complemento['Pagos'])) {
			$complemento['Pagos'] = cfdiTiposCadaUno($complemento['Pagos'], function (array $pagos) use ($aplicar): array {
				if (isset($pagos['Totales'])) {
					$pagos['Totales'] = cfdiTiposCadaUno($pagos['Totales'], $aplicar(CFDI_TIPOS_PAGOS_TOTALES, 'Pagos.Totales'));
				}
				if (isset($pagos['Pago'])) {
					$pagos['Pago'] = cfdiTiposCadaUno($pagos['Pago'], function (array $pago) use ($aplicar): array {
						$pago = $aplicar(CFDI_TIPOS_PAGO, 'Pagos.Pago')($pago);
						if (isset($pago['DoctoRelacionado'])) {
							$pago['DoctoRelacionado'] = cfdiTiposCadaUno($pago['DoctoRelacionado'], $aplicar(CFDI_TIPOS_DOCTO, 'Pagos.Pago.DoctoRelacionado'));
						}
						return $pago;
					});
				}
				return $pagos;
			});
		}
		if (isset($complemento['Nomina'])) {
			$complemento['Nomina'] = cfdiTiposCadaUno($complemento['Nomina'], $aplicar(CFDI_TIPOS_NOMINA, 'Nomina'));
		}
		$set['Complemento'] = $complemento;
	}

	$set['tipos_version'] = CFDI_TIPOS_VERSION;
	$set['tipos_errores'] = $errores;
	return ['set' => $set, 'errores' => $errores];
}

/**
 * Convierte los documentos que todavía no tienen tipos_version = CFDI_TIPOS_VERSION.
 *
 * @param callable|null $log  function(string $mensaje): void
 * @return array{revisados:int, actualizados:int, con_errores:int}
 */
function cfdiNormalizarPendientes(\MongoDB\Collection $coleccion, ?callable $log = null, bool $simular = false, array $filtroExtra = []): array
{
	$log ??= fn(string $mensaje) => null;
	$filtro = array_merge(['tipos_version' => ['$ne' => CFDI_TIPOS_VERSION]], $filtroExtra);
	$cursor = $coleccion->find($filtro, [
		'typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array'],
		'noCursorTimeout' => true,
	]);

	$resumen = ['revisados' => 0, 'actualizados' => 0, 'con_errores' => 0];
	$lote = [];
	$enviar = function () use (&$lote, &$resumen, $coleccion, $simular) {
		if ($lote && !$simular) {
			$resumen['actualizados'] += $coleccion->bulkWrite($lote, ['ordered' => false])->getModifiedCount();
		}
		$lote = [];
	};

	foreach ($cursor as $doc) {
		$resumen['revisados']++;
		$calculo = cfdiTiposDocumento($doc);
		if ($calculo['errores']) {
			$resumen['con_errores']++;
			$uuid = $doc['Complemento']['TimbreFiscalDigital']['UUID'] ?? (string) $doc['_id'];
			$log('No se pudieron convertir en ' . $uuid . ': ' . implode(', ', $calculo['errores']));
		}
		$lote[] = ['updateOne' => [['_id' => $doc['_id']], ['$set' => $calculo['set']]]];
		if (count($lote) >= 500) {
			$enviar();
			$log('Revisados ' . $resumen['revisados'] . '...');
		}
	}
	$enviar();
	if ($simular) {
		$resumen['actualizados'] = 0;
	}
	return $resumen;
}
