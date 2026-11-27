<?php
/**
 * Endpoint de bgprocess (includes/class.bgprocess.php) para los procesos de la sesión ($_SESSION['pids']).
 * op: status (GET, JSON), stream (GET, Server-Sent Events con el mismo estado; ver nfSSE),
 * start y stop (POST con CSRFToken = csrfToken('/nframework/kernel.php')). Solo actúa sobre los procesos
 * que el servidor registró en la sesión: el navegador no puede indicar comandos.
 */
require_once __DIR__ . '/../includes/include.php';

$op = is_string($_REQUEST['op'] ?? null) ? $_REQUEST['op'] : 'status';
$pids = is_array($_SESSION['pids'] ?? null) ? $_SESSION['pids'] : [];

if ($op === 'stream') {
    // Copia de la sesión: nfSSE la cierra para no bloquear las demás peticiones del usuario. Iniciar o
    // detener desde el tablero reconecta el flujo para tomar el PID nuevo.
    nfSSE::stream(fn () => ['pids' => bgprocess::snapshot($pids)], ['interval' => 1, 'maxtime' => 30]);
    exit;
}

header('Content-Type: application/json');
$result = [];
try {
    if ($op === 'start' || $op === 'stop') {
        $id = is_string($_POST['pid'] ?? null) ? $_POST['pid'] : '';   // el tablero envía el id como pid
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrfValidate()) {
            http_response_code(403);
            $result['error'] = 'Petición caducada; recargue la página.';
        } elseif ($id === '' || !isset($pids[$id])) {
            $result['error'] = 'Proceso desconocido.';
        } else {
            $bg = new bgprocess(['id' => $id, 'cmd' => $pids[$id]['cmd'] ?? '', 'logfile' => $pids[$id]['logfile'] ?? '']);
            if ($op === 'start') {
                if ($bg->isRunning()) {
                    $result['error'] = 'El proceso ya está corriendo.';
                } else {
                    $bg->start();
                    $result = ['status' => 'started', 'pid' => $bg->pid];
                }
            } else {
                $result['status'] = $bg->stop() ? 'stopped' : 'not running';
            }
        }
    } else {
        $result['pids'] = bgprocess::snapshot($pids);
    }
} catch (Exception $e) {
    $result['error'] = $e->getMessage();
}

echo json_encode($result);
