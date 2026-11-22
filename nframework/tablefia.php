<?php
/**
 * Endpoint de TablefIA (includes/class.TablefIA.php).
 * op: ask (pregunta → IA → pipeline validado → resultado, y se guarda; con base = ajuste de una consulta guardada),
 * progress (etapa de un ask en curso), run (consulta guardada), list (búsqueda y paginación), rename, favorite,
 * delete, export (GET: csv o xlsx de una consulta guardada). La configuración del componente vive en la sesión.
 */
set_time_limit(180);
require 'include.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

const TABLEFIA_PAGE_SIZE = 20;
const TABLEFIA_TYPEMAP = ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']];

$id = is_string($_REQUEST['id'] ?? null) ? $_REQUEST['id'] : '';
$token = is_string($_REQUEST['token'] ?? null) ? $_REQUEST['token'] : '';
$op = is_string($_REQUEST['op'] ?? null) ? $_REQUEST['op'] : '';

try {
	$cfg = TablefIA::sessionCfg($id, $token);
} catch (RuntimeException $e) {
	http_response_code(403);
	$result = ['error' => $e->getMessage()];
	return;
}

$db = $m->{$cfg['db']};
$saved = $db->nftablesfia;
$userId = isValidObjectId($user->_id) ? toMongoId($user->_id) : null;

/** Consulta guardada de este uso (y del usuario si el listado no es compartido). */
function tablefiaFind($saved, array $cfg, $userId, $queryId)
{
	if (!is_string($queryId) || !isValidObjectId($queryId)) {
		return null;
	}
	$filter = ['_id' => toMongoId($queryId), 'uso' => $cfg['uso']];
	if (!$cfg['shared']) {
		$filter['usuario'] = $userId;
	}
	return $saved->findOne($filter, TABLEFIA_TYPEMAP);
}

/** Puede renombrar o eliminar: el autor o un administrador. */
function tablefiaCanEdit(array $doc, $userId): bool
{
	global $user;
	return $user->in('admins') || ($userId !== null && ($doc['usuario'] ?? null) == $userId);
}

/** Datos de una consulta guardada para el navegador. */
function tablefiaPublic(array $doc): array
{
	return [
		'_id' => (string) $doc['_id'],
		'prompt' => $doc['prompt'],
		'titulo' => $doc['titulo'] ?? '',
		'coleccion' => $doc['coleccion'],
		'pipeline' => tablefiaPretty((string) $doc['pipeline']),
		'columnas' => $doc['columnas'],
		'explicacion' => $doc['explicacion'],
		'tokens' => $doc['tokens'] ?? null,
		'intentos' => $doc['intentos'] ?? 1,
		'modelo' => $doc['modelo'] ?? '',
	];
}

/** El pipeline con sangría para mostrarlo; se guarda tal como lo generó la IA. */
function tablefiaPretty(string $json): string
{
	// Sin true: los objetos vacíos ({}) siguen siendo objetos al volver a codificar.
	$data = json_decode($json);
	return $data === null ? $json : json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/** Valida de nuevo (la configuración pudo cambiar) y ejecuta una consulta guardada. Filas con tipos nativos. */
function tablefiaExecute($db, array $cfg, array $doc, int $limit): array
{
	$stages = TablefIA::parsePipeline($doc['pipeline']);
	$pipeline = TablefIA::buildPipeline($cfg, $doc['coleccion'], $stages, $limit + 1);
	$rows = TablefIA::run($db->{$doc['coleccion']}, $pipeline, $doc['columnas'], $cfg['maxtime'] ?? 30000);
	$truncated = count($rows) > $limit;
	return [array_slice($rows, 0, $limit), $truncated];
}

/** Respuesta de una ejecución (la usa también ask al reutilizar). */
function tablefiaRunResult($saved, $db, array $cfg, array $doc): array
{
	[$rows, $truncated] = tablefiaExecute($db, $cfg, $doc, $cfg['limit']);
	$saved->updateOne(['_id' => $doc['_id']], ['$inc' => ['ejecuciones' => 1], '$set' => ['ultimaejecucion' => new MongoDB\BSON\UTCDateTime()]]);
	return ['error' => false, 'query' => tablefiaPublic($doc), 'rows' => TablefIA::jsonRows($rows), 'truncated' => $truncated, 'reused' => true];
}

/**
 * Archivo con la etapa de un ask en curso, para op=progress. Va en la caché local, ligado a la sesión y al
 * componente; el id lo genera el navegador.
 */
function tablefiaProgressFile(string $id, $progress): ?string
{
	if (!is_string($progress) || !preg_match('/^[a-f0-9]{32}$/', $progress)) {
		return null;
	}
	$dir = nfCacheDir('tablefia');
	return $dir === null ? null : $dir . '/' . hash('sha256', session_id() . '|' . $id . '|' . $progress) . '.json';
}

function tablefiaProgress(?string $file, string $text): void
{
	if ($file !== null) {
		@file_put_contents($file, json_encode(['texto' => $text], JSON_UNESCAPED_UNICODE), LOCK_EX);
	}
}

try {
	switch ($op) {
		case 'ask':
			$prompt = trim(is_string($_POST['prompt'] ?? null) ? $_POST['prompt'] : '');
			if ($prompt === '' || mb_strlen($prompt) > 2000) {
				$result = ['error' => 'Escriba una pregunta (máximo 2000 caracteres).'];
				break;
			}
			$progressFile = tablefiaProgressFile($id, $_POST['progress'] ?? null);

			// Ajuste de una consulta guardada: se guarda como «pregunta original · ajuste».
			$base = null;
			if (($_POST['base'] ?? '') !== '') {
				$base = tablefiaFind($saved, $cfg, $userId, $_POST['base']);
				if (!$base) {
					$result = ['error' => 'La consulta que quiere ajustar ya no existe.'];
					break;
				}
			}
			$fullPrompt = $base ? mb_substr($base['prompt'] . ' · ' . $prompt, 0, 4000) : $prompt;
			$normalized = TablefIA::normalizePrompt($fullPrompt);

			// La misma pregunta (normalizada) cuyas colecciones no cambiaron: se reutiliza sin llamar a la IA.
			// Cambiar otra colección del componente ya no impide reutilizarla (TablefIA::reusable()).
			tablefiaProgress($progressFile, 'Buscando una consulta guardada igual…');
			$filter = ['uso' => $cfg['uso'], '$or' => [['promptnorm' => $normalized], ['prompt' => $fullPrompt]]];
			if (!$cfg['shared']) {
				$filter['usuario'] = $userId;
			}
			$doc = null;
			foreach ($saved->find($filter, ['sort' => ['fecha' => -1], 'limit' => 20] + TABLEFIA_TYPEMAP) as $candidate) {
				if (TablefIA::reusable($cfg, $candidate)) {
					$doc = $candidate;
					break;
				}
			}
			if ($doc) {
				tablefiaProgress($progressFile, 'Ejecutando la consulta guardada…');
				$result = tablefiaRunResult($saved, $db, $cfg, $doc);
				break;
			}

			[$limitCount, $limitSeconds] = $cfg['ratelimit'];
			if (!nflogAttempt('tablefia:' . ($user->_id ?: $ip), (int) $limitCount, (int) $limitSeconds)) {
				$result = ['error' => 'Alcanzó el límite de consultas con IA; intente más tarde.'];
				break;
			}

			tablefiaProgress($progressFile, 'Leyendo los campos de las colecciones…');
			$fields = [];
			foreach ($cfg['collections'] as $name => $options) {
				$fields[$name] = TablefIA::describeFields($db->{$name}, $options, $cfg['fieldsample'] ?? 200);
			}
			// Ejemplos para la IA: consultas de este uso, primero las favoritas y las más ejecutadas.
			$examples = [];
			if (($cfg['fewshot'] ?? 0) > 0) {
				$exampleFilter = ['uso' => $cfg['uso']];
				if (!$cfg['shared']) {
					$exampleFilter['usuario'] = $userId;
				}
				$examples = TablefIA::fewShotExamples($cfg, $saved->find($exampleFilter, [
					'sort' => ['fijada' => -1, 'ejecuciones' => -1, 'fecha' => -1],
					'limit' => 40,
					'projection' => ['prompt' => 1, 'coleccion' => 1, 'columnas' => 1, 'pipeline' => 1, 'confighash' => 1, 'colecciones' => 1],
				] + TABLEFIA_TYPEMAP), (int) $cfg['fewshot'], $normalized);
			}
			$system = TablefIA::systemPrompt($cfg, $fields, $examples);

			// Hasta 'attempts' intentos: cada uno recibe los pipelines rechazados antes y por qué.
			$attempts = $cfg['attempts'] ?? 3;
			$previous = [];
			$usage = ['input' => 0, 'output' => 0, 'cache' => 0];
			for ($attempt = 1; $attempt <= $attempts; $attempt++) {
				tablefiaProgress($progressFile, $attempt === 1
					? 'Generando la consulta con IA…'
					: "Corrigiendo la consulta (intento {$attempt} de {$attempts})…");
				$answer = TablefIA::askClaude($cfg, $system, $prompt, $previous ?: null, $base);
				foreach ($usage as $k => $v) {
					$usage[$k] = $v + (int) ($answer['usage'][$k] ?? 0);
				}
				$columns = array_values(array_filter((array) ($answer['columnas'] ?? []), 'is_string'));
				try {
					$stages = TablefIA::parsePipeline((string) $answer['pipeline']);
					if ($stages === [] || $columns === []) {
						$result = ['error' => $answer['explicacion'] ?: 'La pregunta no se puede responder con estos datos.'];
						break 2;
					}
					$pipeline = TablefIA::buildPipeline($cfg, (string) $answer['coleccion'], $stages, $cfg['limit'] + 1);
					tablefiaProgress($progressFile, 'Ejecutando en la base de datos…');
					$rows = TablefIA::run($db->{$answer['coleccion']}, $pipeline, $columns, $cfg['maxtime'] ?? 30000, $seen);
					// Hay filas pero ninguna trae alguna columna: el $project final no coincide con 'columnas'.
					$missing = $rows === [] ? [] : array_diff($columns, $seen);
					if ($missing !== [] && $attempt < $attempts) {
						throw new InvalidArgumentException('el resultado no tiene las columnas ' . implode(', ', $missing)
							. '; el $project final debe producir exactamente esas claves');
					}
					break;
				} catch (InvalidArgumentException | MongoDB\Driver\Exception\CommandException $e) {
					$error = $e instanceof MongoDB\Driver\Exception\CommandException
						? 'MongoDB (código ' . $e->getCode() . '): ' . $e->getMessage()
						: $e->getMessage();
					if ($attempt === $attempts) {
						throw new RuntimeException('No se pudo generar una consulta válida: ' . $error);
					}
					$previous[] = ['coleccion' => (string) $answer['coleccion'], 'pipeline' => (string) $answer['pipeline'], 'error' => $error];
				}
			}

			$truncated = count($rows) > $cfg['limit'];
			$rows = TablefIA::jsonRows(array_slice($rows, 0, $cfg['limit']));
			$now = new MongoDB\BSON\UTCDateTime();
			$involved = TablefIA::involvedCollections((string) $answer['coleccion'], $stages);
			$doc = [
				'uso' => $cfg['uso'],
				'prompt' => $fullPrompt,
				'promptnorm' => $normalized,
				'coleccion' => (string) $answer['coleccion'],
				// Texto JSON: los nombres con $ y las fechas {"$date"} se guardan tal cual los generó la IA.
				'pipeline' => (string) $answer['pipeline'],
				'columnas' => $columns,
				'explicacion' => (string) $answer['explicacion'],
				// Huella solo de las colecciones que usa: cambiar otras no impide reutilizarla.
				'colecciones' => $involved,
				'confighash' => TablefIA::queryHash($cfg, $involved),
				'ejemplos' => count($examples),
				'modelo' => $answer['modelo'] ?? $cfg['model'],
				'tokens' => $usage,
				'intentos' => $attempt,
				'base' => $base ? $base['_id'] : null,
				'usuario' => $userId,
				'username' => $user->username,
				'fecha' => $now,
				'ultimaejecucion' => $now,
				'ejecuciones' => 1,
				// Sin favoritos: la limpieza por antigüedad (índice TTL de nframework/test.php) puede borrarla.
				'favoritos' => [],
				'fijada' => false,
				'filas' => count($rows) + ($truncated ? 1 : 0),
				'muestra' => array_slice($rows, 0, max(0, $cfg['sample'])),
			];
			$doc['_id'] = $saved->insertOne($doc)->getInsertedId();
			$result = ['error' => false, 'query' => tablefiaPublic($doc), 'rows' => $rows, 'truncated' => $truncated, 'reused' => false];
			break;

		case 'progress':
			$file = tablefiaProgressFile($id, $_POST['progress'] ?? null);
			$data = $file !== null && is_file($file) ? json_decode((string) @file_get_contents($file), true) : null;
			$result = ['error' => false, 'texto' => is_array($data) ? (string) ($data['texto'] ?? '') : ''];
			break;

		case 'run':
			$doc = tablefiaFind($saved, $cfg, $userId, $_POST['query'] ?? null);
			$result = $doc ? tablefiaRunResult($saved, $db, $cfg, $doc) : ['error' => 'La consulta no existe.'];
			break;

		case 'list':
			$filter = ['uso' => $cfg['uso']];
			if (!$cfg['shared']) {
				$filter['usuario'] = $userId;
			}
			$search = trim(is_string($_POST['q'] ?? null) ? $_POST['q'] : '');
			if ($search !== '') {
				$regex = new MongoDB\BSON\Regex(preg_quote(mb_substr($search, 0, 200)), 'i');
				$filter['$or'] = [['prompt' => $regex], ['titulo' => $regex], ['explicacion' => $regex]];
			}
			$total = $saved->countDocuments($filter);
			$page = max(0, (int) ($_POST['page'] ?? 0));
			$page = min($page, max(0, (int) ceil($total / TABLEFIA_PAGE_SIZE) - 1));
			// Primero las favoritas del usuario, luego las de uso más reciente.
			$cursor = $saved->aggregate([
				['$match' => $filter],
				['$project' => ['prompt' => 1, 'titulo' => 1, 'explicacion' => 1, 'username' => 1, 'usuario' => 1, 'fecha' => 1, 'ejecuciones' => 1, 'ultimaejecucion' => 1,
					'favorita' => ['$in' => [$userId, ['$ifNull' => ['$favoritos', []]]]]]],
				['$sort' => ['favorita' => -1, 'ultimaejecucion' => -1, '_id' => -1]],
				['$skip' => $page * TABLEFIA_PAGE_SIZE],
				['$limit' => TABLEFIA_PAGE_SIZE],
			], TABLEFIA_TYPEMAP);
			$queries = [];
			foreach ($cursor as $doc) {
				$queries[] = [
					'_id' => (string) $doc['_id'],
					'prompt' => $doc['prompt'],
					'titulo' => $doc['titulo'] ?? '',
					'explicacion' => $doc['explicacion'],
					'usuario' => $doc['username'] ?? '',
					'fecha' => $doc['fecha']->toDateTime()->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i'),
					'ejecuciones' => $doc['ejecuciones'] ?? 0,
					'favorita' => $userId !== null && !empty($doc['favorita']),
					'puedeeditar' => tablefiaCanEdit($doc, $userId),
				];
			}
			$result = ['error' => false, 'queries' => $queries, 'total' => $total, 'page' => $page, 'size' => TABLEFIA_PAGE_SIZE];
			break;

		case 'rename':
			$doc = tablefiaFind($saved, $cfg, $userId, $_POST['query'] ?? null);
			if (!$doc || !tablefiaCanEdit($doc, $userId)) {
				$result = ['error' => 'No puede renombrar esta consulta.'];
				break;
			}
			$titulo = trim(is_string($_POST['titulo'] ?? null) ? $_POST['titulo'] : '');
			if (mb_strlen($titulo) > 200) {
				$result = ['error' => 'El nombre admite hasta 200 caracteres.'];
				break;
			}
			// Vacío o igual a la pregunta: se quita el nombre y se muestra la pregunta.
			$saved->updateOne(['_id' => $doc['_id']], $titulo === '' || $titulo === $doc['prompt']
				? ['$unset' => ['titulo' => '']]
				: ['$set' => ['titulo' => $titulo]]);
			$result = ['error' => false];
			break;

		case 'favorite':
			$doc = tablefiaFind($saved, $cfg, $userId, $_POST['query'] ?? null);
			if (!$doc || $userId === null) {
				$result = ['error' => 'No puede marcar esta consulta.'];
				break;
			}
			// Favoritas por usuario; 'fijada' (alguien la marcó) la excluye de la limpieza por antigüedad.
			$list = ['$ifNull' => ['$favoritos', []]];
			$favoritos = !empty($_POST['on']) ? ['$setUnion' => [$list, [$userId]]] : ['$setDifference' => [$list, [$userId]]];
			$saved->updateOne(['_id' => $doc['_id']], [
				['$set' => ['favoritos' => $favoritos]],
				['$set' => ['fijada' => ['$gt' => [['$size' => '$favoritos'], 0]]]],
			]);
			$result = ['error' => false];
			break;

		case 'delete':
			$doc = tablefiaFind($saved, $cfg, $userId, $_POST['query'] ?? null);
			if (!$doc || !tablefiaCanEdit($doc, $userId)) {
				$result = ['error' => 'No puede eliminar esta consulta.'];
				break;
			}
			$saved->deleteOne(['_id' => $doc['_id']]);
			$result = ['error' => false];
			break;

		case 'export':
			$doc = tablefiaFind($saved, $cfg, $userId, $_GET['query'] ?? null);
			if (!$doc) {
				http_response_code(404);
				echo 'La consulta no existe.';
				break;
			}
			[$rows] = tablefiaExecute($db, $cfg, $doc, $cfg['exportlimit']);
			$filename = clean_filename('consulta_' . date('Y-m-d_H-i-s'));
			if (($_GET['type'] ?? '') === 'csv') {
				header('Content-Type: text/csv; charset=utf-8');
				header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
				$out = fopen('php://output', 'w');
				fwrite($out, "\xEF\xBB\xBF");   // BOM: Excel abre el CSV como UTF-8
				fputcsv($out, $doc['columnas'], ',', '"', '');
				foreach ($rows as $row) {
					// Fechas ISO y decimales sin notación científica: el CSV se puede volver a leer sin ambigüedad.
					fputcsv($out, array_map(fn($v) => match (true) {
						$v instanceof DateTimeInterface => $v->format('Y-m-d H:i:s'),
						is_float($v) => TablefIA::formatValue($v),
						default => (string) $v,
					}, $row), ',', '"', '');
				}
				fclose($out);
			} else {
				// Valores nativos: números como número, fechas como fecha de Excel y el texto siempre como texto
				// (un folio "00123" no pierde los ceros).
				$spreadsheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();
				$sheet = $spreadsheet->getActiveSheet();
				$sheet->fromArray([$doc['columnas']], null, 'A1', true);
				$sheet->getStyle([1, 1, max(1, count($doc['columnas'])), 1])->getFont()->setBold(true);
				$dateColumns = [];
				foreach ($rows as $r => $row) {
					foreach ($row as $c => $value) {
						if ($value === null) {
							continue;
						}
						$cell = $sheet->getCell([$c + 1, $r + 2]);
						if ($value instanceof DateTimeInterface) {
							$cell->setValue(PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($value));
							$dateColumns[$c] = true;
						} elseif (is_int($value) || is_float($value)) {
							$cell->setValueExplicit($value, PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
						} else {
							$cell->setValueExplicit((string) $value, PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
						}
					}
				}
				foreach (array_keys($dateColumns) as $c) {
					$sheet->getStyle([$c + 1, 2, $c + 1, count($rows) + 1])->getNumberFormat()->setFormatCode('yyyy-mm-dd hh:mm:ss');
				}
				$nframework->excelOut($spreadsheet, $filename);
			}
			break;

		default:
			$result = ['error' => 'Operación no válida.'];
	}
} catch (RuntimeException | InvalidArgumentException $e) {
	$result = ['error' => $e->getMessage()];
} catch (MongoDB\Driver\Exception\Exception $e) {
	error_log('TablefIA: ' . $e->getMessage());
	$result = ['error' => 'MongoDB rechazó la consulta: ' . $e->getMessage()];
} finally {
	if (!empty($progressFile)) {
		@unlink($progressFile);
	}
}
