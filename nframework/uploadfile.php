<?php
set_time_limit(0);
require 'include.php';
header('Content-Type: application/json');
function isValidFilename(string $filename): bool
{
	$forbidden = [
		'.php',
		'.asp',
		'.exe',
		'.bat',
		'.cmd',
		'.sh',
		'?',
		'[',
		']',
		'/',
		'\\',
		'=',
		'<',
		'>',
		':',
		';',
		"'",
		'"',
		'$',
		'#',
		'*',
		'|',
		'~',
		'`',
		'!',
		'{',
		'}',
		'%',
		'+',
		"\0"
	];

	foreach ($forbidden as $char) {
		if (strpos($filename, $char) !== false) {
			return false;
		}
	}
	return true;
}

function sanitizeFilename(string $filename): string
{
	$encoding = mb_detect_encoding($filename, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
	if ($encoding && $encoding !== 'UTF-8') {
		$filename = mb_convert_encoding($filename, 'UTF-8', $encoding);
	}
	return $filename;
}

function handleFileUpload(array $upload): array
{
	$error = '';
	$onresult = [];
	$directorio = $upload['dir'];

	try {
		$currentTime = time();
		if ($currentTime < $upload['limit_time_start'] || $currentTime > $upload['limit_time_end']) {
			throw new Exception("Fuera de tiempo límite");
		}

		if (!empty($upload['extension']) && file_exists($upload['extension'])) {
			require $upload['extension'];
		}

		if (!empty($_POST['delete'])) {
			return handleFileDelete($upload);
		}

		if (!isset($_FILES[$upload['formname']]['tmp_name'])) {
			throw new Exception('Form not found: ' . $upload['formname']);
		}

		return handleFileUploadProcess($upload);
	} catch (Exception $e) {
		return ['error' => $e->getMessage(), 'onresult' => []];
	}
}

function handleFileDelete(array $upload): array
{
	if (!$upload['delete']) {
		return ['error' => 'No permitido eliminar', 'onresult' => []];
	}

	$filename = sanitizeFilename(rawurldecode($_POST['file']));

	if (!isValidFilename($filename)) {
		return ['error' => 'Nombre de archivo inválido', 'onresult' => []];
	}

	$fullPath = rtrim($upload['dir'], '/') . '/' . $filename;
	if (!empty($upload['ondelete'])) {
		if (!is_callable($upload['ondelete'])) {
			return ['error' => 'ondelete no es una función válida', 'onresult' => []];
		}
		$onresult[] = call_user_func($upload['ondelete'], $fullPath, $upload);
		return ['error' => '', 'onresult' => $onresult];
	} else {
		if (file_exists($fullPath) && unlink($fullPath)) {
			$onresult = [];
			return ['error' => '', 'onresult' => $onresult];
		}
	}

	return ['error' => 'No se pudo eliminar el archivo', 'onresult' => []];
}

function handleFileUploadProcess(array $upload): array
{
	$directorio = $upload['dir'];
	$ufile = $_FILES[$upload['formname']];

	if (empty($ufile['tmp_name']) || $ufile['error'] !== UPLOAD_ERR_OK) {
		return ['error' => 'Error en la subida del archivo', 'onresult' => []];
	}

	// Check file count limit
	if ($upload['countlimit'] > 0) {
		if (!empty($upload['oncountcheck'])) {
			if (!is_callable($upload['oncountcheck'])) {
				return ['error' => 'oncountcheck no es una función válida', 'onresult' => []];
			}
			$canUpload = call_user_func($upload['oncountcheck'], $upload);
			if (!$canUpload) {
				unlink($ufile['tmp_name']);
				return ['error' => 'Límite de archivos alcanzado', 'onresult' => []];
			}
		} else {
			$existingFiles = array_diff(scandir($directorio) ?: [], ['.', '..']);
			if (count($existingFiles) >= $upload['countlimit']) {
				unlink($ufile['tmp_name']);
				return ['error' => 'Límite de archivos alcanzado', 'onresult' => []];
			}
		}
	}
	$filename = sanitizeFilename(rawurldecode($ufile['name']));
	if (!isValidFilename($filename)) {
		unlink($ufile['tmp_name']);
		return ['error' => 'Nombre de archivo inválido', 'onresult' => []];
	}

	// Create directory if needed
	if (!file_exists($directorio) && $upload['create_dir']) {
		if (!mkdir($directorio, 0755, true)) {
			return ['error' => 'No se pudo crear el directorio', 'onresult' => []];
		}
	}

	$fullPath = rtrim($directorio, '/') . '/' . $filename;

	if (!move_uploaded_file($ufile['tmp_name'], $fullPath)) {
		return ['error' => 'No se pudo mover el archivo ' . $ufile['tmp_name'] . ' a ' . $fullPath, 'onresult' => []];
	}

	$onresult = [];
	if (!empty($upload['onupload'])) {
		if (!is_callable($upload['onupload'])) {
			return ['error' => 'onupload no es una función válida', 'onresult' => []];
		}
		$onresult[] = call_user_func($upload['onupload'], $fullPath, $upload);
	}

	return ['error' => '', 'onresult' => $onresult];
}

function getFileList(array $upload): array
{
	if (!empty($upload['onlist'])) {
		if (!is_callable($upload['onlist'])) {
			return ['error' => 'onlist no es una función válida', 'onresult' => []];
		}
		return call_user_func($upload['onlist'], $upload);
	}

	$directorio = $upload['dir'];
	if (empty($directorio) || !is_dir($directorio)) {
		return [];
	}

	$files = array_diff(scandir($directorio) ?: [], ['.', '..']);
	$result = [];

	foreach ($files as $index => $file) {
		$fullPath = $directorio . '/' . $file;
		$result[] = [
			'id' => $index,
			'name' => $file,
			'length' => file_exists($fullPath) ? filesize($fullPath) : 0
		];
	}
	return $result;
}

// Main execution
if (!isset($_POST['mid']) || !isset($_SESSION['uploads4'][$_POST['mid']])) {
	echo json_encode(['error' => 'Sesión inválida', 'session' => session_id()]);
	exit;
}

$upload = $_SESSION['uploads4'][$_POST['mid']];
$uploadResult = handleFileUpload($upload);

$result = [
	'conf' => $upload,
	'delete' => $upload['delete'],
	'download' => $upload['download'],
	'preview' => !empty($upload['preview']),
	'files' => getFileList($upload),
	'onresult' => $uploadResult['onresult'],
	'error' => $uploadResult['error']
];
//echo json_encode($response);
