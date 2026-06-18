<?php
require_once __DIR__ . '/../includes/include.php';

header('Content-Type: application/json');

$op = $_REQUEST['op'] ?? 'status';
$result = [];

// Initialize pids session if not exists
if (!isset($_SESSION['pids'])) {
    $_SESSION['pids'] = [];
}

try {
    switch ($op) {
        case 'start':
            $id = $_REQUEST['pid'] ?? ''; // In JS it sends pid=id
            if ($id && isset($_SESSION['pids'][$id])) {
                $config = $_SESSION['pids'][$id];
                $bg = new bgprocess([
                    'id' => $id,
                    'cmd' => $config['cmd'],
                    'logfile' => $config['logfile']
                ]);
                $bg->start();
                $result['status'] = 'started';
                $result['pid'] = $bg->pid;
            } else {
                $result['error'] = 'Invalid ID';
            }
            break;

        case 'stop':
            $id = $_REQUEST['pid'] ?? '';
            if ($id && isset($_SESSION['pids'][$id])) {
                $config = $_SESSION['pids'][$id];
                $bg = new bgprocess([
                    'id' => $id,
                    'cmd' => $config['cmd'] ?? '',
                    'logfile' => $config['logfile'] ?? ''
                ]);
                $bg->stop();
                $result['status'] = 'stopped';
            }
            break;

        case 'status':
        default:
            $pidsObj = [];
            foreach ($_SESSION['pids'] as $id => $data) {
                // Check if running
                $isRunning = false;
                if (!empty($data['pid'])) {
                    // Try to recreate object to check status
                    // Note: ensure cmd/logfile are available if needed by constructor
                    $bg = new bgprocess([
                        'id' => $id,
                        'cmd' => $data['cmd'] ?? '',
                        'logfile' => $data['logfile'] ?? ''
                    ]);
                    $isRunning = $bg->isRunning();
                }
                $pidsObj[$id] = [
                    'running' => $isRunning,
                    'pid' => $data['pid'] ?? null
                ];
            }
            $result['pids'] = $pidsObj;
            break;
    }
} catch (Exception $e) {
    $result['error'] = $e->getMessage();
}

echo json_encode($result);
