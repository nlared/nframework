<?php
/**
 * Endpoint de TablefIA (includes/class.TablefIA.php).
 * op: ask (pregunta → IA → pipeline validado → resultado, y se guarda), run (consulta guardada), list, delete,
 * export (GET: csv o xlsx de una consulta guardada). La configuración del componente vive en la sesión.
 */
set_time_limit(180);
require 'include.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$id = is_string($_REQUEST['id'] ?? null) ? $_REQUEST['id'] : '';
$token = is_string($_REQUEST['token'] ?? null) ? $_REQUEST['token'] : '';
$op = is_string($_REQUEST['op'] ?? null) ? $_REQUEST['op'] : '';
$cfg = $_SESSION['tablefia'][$id] ?? null;

if (empty($cfg) || $token === '' || !hash_equals($cfg['token'], $token)) {
	http_response_code(403);
	$result = ['error' => 'La sesión del componente caducó; recargue la página.'];
	return;
}
if ($cfg['requireauth'] && !$user->isLoggedIn()) {
	http_response_code(403);
	$result = ['error' => 'Inicie sesión.'];
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
	return $saved->findOne($filter, ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]);
}

/** Datos de una consulta guardada para el navegador. */
function tablefiaPublic(array $doc): array
{
	return [
		'_id' => (string) $doc['_id'],
		'prompt' => $doc['prompt'],
		'coleccion' => $doc['coleccion'],
		'pipeline' => tablefiaPretty((string) $doc['pipeline']),
		'columnas' => $doc['columnas'],
		'explicacion' => $doc['explicacion'],
	];
}

/** El pipeline con sangría para mostrarlo; se guarda tal como lo generó la IA. */
function tablefiaPretty(string $json): string
{
	// Sin true: los objetos vacíos ({}) siguen siendo objetos al volver a codificar.
	$data = json_decode($json);
	return $data === null ? $json : json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/** Valida de nuevo (la configuración pudo cambiar) y ejecuta una consulta guardada. */
function tablefiaExecute($db, array $cfg, array $doc, int $limit): array
{
	$stages = TablefIA::parsePipeline($doc['pipeline']);
	$pipeline = TablefIA::buildPipeline($cfg, $doc['coleccion'], $stages, $limit + 1);
	$rows = TablefIA::run($db->{$doc['coleccion']}, $pipeline, $doc['columnas']);
	$truncated = count($rows) > $limit;
	return [array_slice($rows, 0, $limit), $truncated];
}

try {
	switch ($op) {
		case 'ask':
			$prompt = trim(is_string($_POST['prompt'] ?? null) ? $_POST['prompt'] : '');
			if ($prompt === '' || mb_strlen($prompt) > 2000) {
				$result = ['error' => 'Escriba una pregunta (máximo 2000 caracteres).'];
				break;
			}
			$hash = TablefIA::configHash($cfg);

			// La misma pregunta con la misma configuración: se reutiliza sin llamar a la IA.
			$filter = ['uso' => $cfg['uso'], 'prompt' => $prompt, 'confighash' => $hash];
			if (!$cfg['shared']) {
				$filter['usuario'] = $userId;
			}
			$doc = $saved->findOne($filter, ['sort' => ['fecha' => -1], 'typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]);
			if ($doc) {
				[$rows, $truncated] = tablefiaExecute($db, $cfg, $doc, $cfg['limit']);
				$saved->updateOne(['_id' => $doc['_id']], ['$inc' => ['ejecuciones' => 1], '$set' => ['ultimaejecucion' => new MongoDB\BSON\UTCDateTime()]]);
				$result = ['error' => false, 'query' => tablefiaPublic($doc), 'rows' => $rows, 'truncated' => $truncated, 'reused' => true];
				break;
			}

			[$limitCount, $limitSeconds] = $cfg['ratelimit'];
			if (!nflogAttempt('tablefia:' . ($user->_id ?: $ip), (int) $limitCount, (int) $limitSeconds)) {
				$result = ['error' => 'Alcanzó el límite de consultas con IA; intente más tarde.'];
				break;
			}

			$fields = [];
			foreach ($cfg['collections'] as $name => $options) {
				$fields[$name] = TablefIA::describeFields($db->{$name}, $options);
			}
			$system = TablefIA::systemPrompt($cfg, $fields);

			// Un segundo intento si el pipeline no pasa la validación o MongoDB lo rechaza.
			$previous = null;
			$usage = ['input' => 0, 'output' => 0, 'cache' => 0];
			for ($attempt = 1; $attempt <= 2; $attempt++) {
				$answer = TablefIA::askClaude($cfg, $system, $prompt, $previous);
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
					$rows = TablefIA::run($db->{$answer['coleccion']}, $pipeline, $columns);
					break;
				} catch (InvalidArgumentException | MongoDB\Driver\Exception\CommandException $e) {
					if ($attempt === 2) {
						throw new RuntimeException('No se pudo generar una consulta válida: ' . $e->getMessage());
					}
					$previous = ['pipeline' => (string) $answer['pipeline'], 'error' => $e->getMessage()];
				}
			}

			$truncated = count($rows) > $cfg['limit'];
			$rows = array_slice($rows, 0, $cfg['limit']);
			$now = new MongoDB\BSON\UTCDateTime();
			$doc = [
				'uso' => $cfg['uso'],
				'prompt' => $prompt,
				'coleccion' => (string) $answer['coleccion'],
				// Texto JSON: los nombres con $ y las fechas {"$date"} se guardan tal cual los generó la IA.
				'pipeline' => (string) $answer['pipeline'],
				'columnas' => $columns,
				'explicacion' => (string) $answer['explicacion'],
				'confighash' => $hash,
				'modelo' => $answer['modelo'] ?? $cfg['model'],
				'tokens' => $usage,
				'usuario' => $userId,
				'username' => $user->username,
				'fecha' => $now,
				'ultimaejecucion' => $now,
				'ejecuciones' => 1,
				'filas' => count($rows) + ($truncated ? 1 : 0),
				'muestra' => array_slice($rows, 0, max(0, $cfg['sample'])),
			];
			$doc['_id'] = $saved->insertOne($doc)->getInsertedId();
			$result = ['error' => false, 'query' => tablefiaPublic($doc), 'rows' => $rows, 'truncated' => $truncated, 'reused' => false];
			break;

		case 'run':
			$doc = tablefiaFind($saved, $cfg, $userId, $_POST['query'] ?? null);
			if (!$doc) {
				$result = ['error' => 'La consulta no existe.'];
				break;
			}
			[$rows, $truncated] = tablefiaExecute($db, $cfg, $doc, $cfg['limit']);
			$saved->updateOne(['_id' => $doc['_id']], ['$inc' => ['ejecuciones' => 1], '$set' => ['ultimaejecucion' => new MongoDB\BSON\UTCDateTime()]]);
			$result = ['error' => false, 'query' => tablefiaPublic($doc), 'rows' => $rows, 'truncated' => $truncated, 'reused' => true];
			break;

		case 'list':
			$filter = ['uso' => $cfg['uso']];
			if (!$cfg['shared']) {
				$filter['usuario'] = $userId;
			}
			$queries = [];
			$admin = $user->in('admins');
			foreach ($saved->find($filter, ['sort' => ['ultimaejecucion' => -1], 'limit' => 200, 'projection' => ['muestra' => 0, 'pipeline' => 0]]) as $doc) {
				$queries[] = [
					'_id' => (string) $doc['_id'],
					'prompt' => $doc['prompt'],
					'explicacion' => $doc['explicacion'],
					'usuario' => $doc['username'] ?? '',
					'fecha' => $doc['fecha']->toDateTime()->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i'),
					'ejecuciones' => $doc['ejecuciones'] ?? 0,
					'puedeborrar' => $admin || ($userId !== null && $doc['usuario'] == $userId),
				];
			}
			$result = ['error' => false, 'queries' => $queries];
			break;

		case 'delete':
			$doc = tablefiaFind($saved, $cfg, $userId, $_POST['query'] ?? null);
			if (!$doc || !($user->in('admins') || ($userId !== null && $doc['usuario'] == $userId))) {
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
					fputcsv($out, $row, ',', '"', '');
				}
				fclose($out);
			} else {
				$spreadsheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();
				$spreadsheet->getActiveSheet()->fromArray(array_merge([$doc['columnas']], $rows), null, 'A1', true);
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
}
