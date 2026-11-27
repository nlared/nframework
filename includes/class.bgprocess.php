<?php

use Cocur\BackgroundProcess\BackgroundProcess;

/**
 * Comando largo en segundo plano (cocur/background-process). El PID se guarda en $_SESSION['pids'][id], así que
 * la misma página puede consultar o detener el proceso en peticiones siguientes. Ver docs/backgroundps.php.
 *
 *   $p = new bgprocess(['id' => 'respaldo', 'cmd' => 'mongodump ...', 'logfile' => '/tmp/respaldo.log']);
 *   if (!$p->isRunning()) { $p->start(); }
 *   echo $p->renderDashboard();
 */
class bgprocess
{
    /** @var string Comando de shell (escape los argumentos con escapeshellarg()). */
    public $cmd;
    /** @var int|null */
    public $pid;
    /** @var string Archivo donde se escribe la salida. */
    public $logfile;
    /** @var string Identificador del proceso en la sesión. */
    public $id;
    /** @var string|null JS del tablero al cambiar el estado. */
    public $onChange;
    /** @var string|null JS del tablero al terminar. */
    public $onComplete;

    /**
     * @param array{id: string, cmd?: string, logfile?: string, onChange?: string, onComplete?: string} $options
     */
    public function __construct($options)
    {
        foreach ($options as $nam => $op) {
            $this->{$nam} = $op;
        }
        if (empty($_SESSION['pids'][$this->id]['pid'])) {
            $_SESSION['pids'][$this->id] = [
                'logfile' => $this->logfile,
                'cmd' => $this->cmd
            ];
        } else {
            $this->pid = $_SESSION['pids'][$this->id]['pid'];
        }
    }
    /**
     * Lanza el comando y guarda el PID en la sesión.
     *
     * @return void
     */
    public function start()
    {
        $proc = new BackgroundProcess($this->cmd);
        $proc->run($this->logfile);
        $this->pid = $proc->getPid();
        $_SESSION['pids'][$this->id]['pid'] = $this->pid;        
    }

    /**
     * @return array{data: string|false, isRunning: bool} Contenido del log y si sigue corriendo.
     */
    public function status()
    {
        $process = BackgroundProcess::createFromPID($this->pid);
        return
            $result = [
                'data' => file_get_contents($this->logfile),
                'isRunning' => $process->isRunning()
            ];
    }
    /**
     * @return bool
     */
    public function isRunning()
    {
        if (!empty($_SESSION['pids'][$this->id]['pid'])) {
            $process = BackgroundProcess::createFromPID($_SESSION['pids'][$this->id]['pid']);
            return    $process->isRunning();
        } else {
            return false;
        }
    }
    /**
     * @return bool true si estaba corriendo y se detuvo.
     */
    public function stop()
    {
        $process = BackgroundProcess::createFromPID($this->pid);
        if ($process->isRunning()) {
            $process->stop();
            $_SESSION['pids'][$this->id]['pid'] = null;
            return true;
        } else {
            return false;
        }
    }


    /**
     * Estado de los procesos de la sesión: si corre, su PID y el final de su log. Lo envían
     * /nframework/kernel.php?op=status y el flujo SSE ?op=stream que usa renderDashboard().
     *
     * @param array<string, array{pid?: int|null, logfile?: string, cmd?: string}> $pids $_SESSION['pids'].
     * @param int $tail Bytes finales del log que se incluyen.
     * @param callable(int): bool|null $isRunning Para pruebas; null = BackgroundProcess.
     * @return array<string, array{running: bool, pid: int|null, log: string}>
     */
    public static function snapshot(array $pids, int $tail = 4000, ?callable $isRunning = null): array
    {
        $isRunning ??= fn (int $pid) => BackgroundProcess::createFromPID($pid)->isRunning();
        $out = [];
        foreach ($pids as $id => $data) {
            $pid = !empty($data['pid']) ? (int) $data['pid'] : null;
            $log = '';
            $file = (string) ($data['logfile'] ?? '');
            if ($file !== '' && is_file($file) && is_readable($file)) {
                $size = (int) @filesize($file);
                $log = (string) @file_get_contents($file, false, null, max(0, $size - $tail));
                if ($size > $tail) {
                    // Sin la primera línea, que puede quedar cortada (o con un carácter UTF-8 a medias).
                    $log = (string) preg_replace('/^[^\n]*\n/', '', $log, 1);
                }
                $log = mb_convert_encoding($log, 'UTF-8', 'UTF-8');
            }
            $out[(string) $id] = ['running' => $pid !== null && $isRunning($pid), 'pid' => $pid, 'log' => $log];
        }
        return $out;
    }

    /**
     * Tablero con botones iniciar/detener y el log en vivo. El estado llega por Server-Sent Events
     * (/nframework/kernel.php?op=stream, ver nfSSE); sin EventSource consulta cada 3 s. Iniciar y detener
     * van por POST con token CSRF.
     *
     * @return string HTML.
     */
    public function renderDashboard()
    {
        $dashboardId = 'bgdashboard_' . uniqid();
        $csrf = json_encode(csrfToken('/nframework/kernel.php'));

        // Un solo juego de manejadores aunque la página tenga varios tableros.
        $js = <<<JS
        (function () {
            if (window.nfBgDashboard) { return; }
            var url = '/nframework/kernel.php', csrf = {$csrf}, es = null, timer = null;
            window.nfBgDashboard = true;
            function apply(data) {
                $.each((data && data.pids) || {}, function (id, info) {
                    $('.nf-bgrow').filter(function () { return $(this).attr('data-bgid') === id; }).each(function () {
                        var row = $(this), log = $('.nf-bglog').filter(function () { return $(this).attr('data-bgid') === id; });
                        row.find('.status-icon').toggleClass('mif-play fg-green', info.running).toggleClass('mif-stop fg-red', !info.running);
                        row.find('.status-text').text(info.running ? 'Corriendo (PID ' + info.pid + ')' : 'Detenido');
                        row.find('.btn-start').prop('disabled', info.running);
                        row.find('.btn-stop').prop('disabled', !info.running);
                        var pre = log.find('pre');
                        var bottom = pre.length && pre[0].scrollHeight - pre.scrollTop() - pre.outerHeight() < 20;
                        pre.text(info.log || '');
                        log.toggleClass('d-none', !info.log);
                        if (bottom && pre.length) { pre.scrollTop(pre[0].scrollHeight); }
                    });
                });
            }
            function poll() {
                $.getJSON(url, {op: 'status'}, apply);
                timer = setTimeout(poll, 3000);
            }
            function connect() {
                if (es) { es.close(); }
                clearTimeout(timer);
                if (!window.EventSource) { poll(); return; }
                // Al terminar (maxtime) el navegador reconecta solo.
                es = new EventSource(url + '?op=stream');
                es.addEventListener('update', function (e) { apply(JSON.parse(e.data)); });
            }
            $(document).on('click', '.btn-bg-action', function () {
                var b = $(this).prop('disabled', true);
                $.post(url, {op: b.data('action'), pid: b.attr('data-bgid'), CSRFToken: csrf}, null, 'json').done(function (res) {
                    if (res && res.error) { Swal.fire({icon: 'error', title: 'Proceso', text: res.error}); }
                }).always(function () { b.prop('disabled', false); connect(); });
            });
            $(connect);
        })();
JS;

        $html = '<script>' . $js . '</script>';
        $html .= '<table class="table striped compact" id="' . $dashboardId . '">';
        $html .= '<thead><tr><th>Proceso</th><th>Comando</th><th>Estado</th><th></th></tr></thead><tbody>';
        if (!empty($_SESSION['pids'])) {
            foreach ($_SESSION['pids'] as $id => $data) {
                $id = htmlspecialchars((string) $id);
                $html .= '<tr class="nf-bgrow" data-bgid="' . $id . '">'
                    . '<td>' . $id . '</td>'
                    . '<td><code>' . htmlspecialchars((string) ($data['cmd'] ?? '')) . '</code></td>'
                    . '<td><span class="status-icon mif-stop fg-red"></span> <span class="status-text">…</span></td>'
                    . '<td class="text-nowrap">'
                    . '<button type="button" class="button small success btn-bg-action btn-start" data-action="start" data-bgid="' . $id . '"><span class="mif-play"></span> Iniciar</button> '
                    . '<button type="button" class="button small alert btn-bg-action btn-stop" data-action="stop" data-bgid="' . $id . '" disabled><span class="mif-stop"></span> Detener</button>'
                    . '</td></tr>'
                    . '<tr class="nf-bglog d-none" data-bgid="' . $id . '"><td colspan="4"><pre class="m-0 p-2" style="max-height:240px;overflow:auto;white-space:pre-wrap;font-size:.8rem"></pre></td></tr>';
            }
        } else {
            $html .= '<tr><td colspan="4">No hay procesos configurados.</td></tr>';
        }
        return $html . '</tbody></table>';
    }

    /**
     * @return string Icono simple de estado (compatibilidad).
     */
    public function __toString()
    {
        // Backward compatibility simple widget
        $s = ($this->isRunning() ? 'stop' : 'play');
        // We removed the global JS injection to avoid conflicts; users should use the dashboard or rely on specific pages ensuring JS is loaded.
        // Or we can add a minimal self-contained script for this widget.
        return '<div class="bg_process" id="bgprocess_' . $this->id . '" ><span class="mif-' . $s . '" ></span></div>';
    }
}
