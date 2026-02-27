<?php
require 'include.php';
header('Content-Type: application/json');  
  
switch ($_GET['op'] ?? '') {  
    case 'status':  
        $pids = $_SESSION['pids'] ?? [];  
        $running = array_filter($pids, fn($p) => (new BackgroundProcess())->createFromPID($p['pid'])->isRunning());  
        echo json_encode(['pids' => $running]);  
        break;  
    case 'start':  
        $pid = $_GET['pid'] ?? null;  
        if ($pid && isset($_SESSION['pids'][$pid])) {  
            $proc = new BackgroundProcess($_SESSION['pids'][$pid]['cmd']);  
            $proc->run($_SESSION['pids'][$pid]['logfile']);  
            $_SESSION['pids'][$pid]['pid'] = $proc->getPid();  
        }  
        break;  
    case 'stop':  
        $pid = $_GET['pid'] ?? null;  
        if ($pid && isset($_SESSION['pids'][$pid])) {  
            BackgroundProcess::createFromPID($_SESSION['pids'][$pid]['pid'])->stop();  
            $_SESSION['pids'][$pid]['pid'] = null;  
        }  
        break;  
}