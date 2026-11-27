<?php
/**
 * Estado de la cola de trabajos (colección jobs) y de los procesos job_worker.php del servidor. Lo usan
 * Admin → Workers y el diagnóstico de Admin → Inicio.
 *
 *   echo (new JobDashboard())->render();                 // pendientes, atrasados y fallidos por cola
 *   $workers = JobDashboard::workers();                  // procesos job_worker.php (lee /proc)
 *   $deEste = JobDashboard::workersForHost($workers, $_SERVER['HTTP_HOST']);
 */
class JobDashboard
{
    /** Segundos tras los que un trabajo pendiente se considera atrasado (nadie lo tomó). */
    public const OVERDUE = 300;

    /**
     * Pendientes, atrasados y fallidos por cola.
     *
     * @global MongoDB\Client $m
     * @global class_config $config
     * @return array<string, array{pending: int, overdue: int, failed: int}> cola => contadores
     */
    public static function queues(): array
    {
        global $m, $config;
        $overdue = new MongoDB\BSON\UTCDateTime((time() - self::OVERDUE) * 1000);
        $stats = $m->{$config['sitedb']}->jobs->aggregate([
            ['$group' => [
                '_id' => '$queue',
                'pending' => ['$sum' => ['$cond' => [['$eq' => [['$ifNull' => ['$failed_at', null]], null]], 1, 0]]],
                'overdue' => ['$sum' => ['$cond' => [['$and' => [
                    ['$eq' => [['$ifNull' => ['$failed_at', null]], null]],
                    ['$lt' => ['$delay_until', $overdue]],
                ]], 1, 0]]],
                'failed' => ['$sum' => ['$cond' => [['$eq' => [['$ifNull' => ['$failed_at', null]], null]], 0, 1]]],
            ]],
            ['$sort' => ['_id' => 1]],
        ]);
        $queues = [];
        foreach ($stats as $s) {
            $queues[(string) $s['_id']] = ['pending' => (int) $s['pending'], 'overdue' => (int) $s['overdue'], 'failed' => (int) $s['failed']];
        }
        return $queues;
    }

    /**
     * Tabla HTML con los contadores de queues(). Con $stream (URL de un flujo nfSSE que envía
     * ['queues' => JobDashboard::queues()] en sus eventos 'update') los contadores se actualizan solos;
     * una cola nueva aparece como fila al final.
     *
     *   if (($_GET['stream'] ?? '') === 'jobs') { nfSSE::stream(fn () => ['queues' => JobDashboard::queues()]); exit; }
     *   echo (new JobDashboard())->render('?stream=jobs');
     *
     * @param string $stream URL del flujo; '' = tabla fija.
     * @return string
     */
    public function render($stream = '')
    {
        $id = 'nfjobs_' . substr(md5(uniqid('', true)), 0, 8);
        $html = '<table class="table striped compact" id="' . $id . '"><thead><tr><th>Cola</th><th>Pendientes</th><th>Atrasados</th><th>Fallidos</th></tr></thead><tbody>';
        foreach (self::queues() as $queue => $s) {
            $html .= self::row($queue, $s);
        }
        $html .= '</tbody></table>';
        if ($stream !== '') {
            $html .= '<script>' . self::liveScript($id, (string) $stream) . '</script>';
        }
        return $html;
    }

    /**
     * Fila de una cola (también la arma el navegador con los mismos estilos al llegar una cola nueva).
     *
     * @param string $queue
     * @param array{pending: int, overdue: int, failed: int} $s
     * @return string
     */
    public static function row(string $queue, array $s): string
    {
        return '<tr data-queue="' . htmlspecialchars($queue) . '"><td><code>' . htmlspecialchars($queue) . '</code></td>'
            . '<td data-k="pending">' . (int) $s['pending'] . '</td>'
            . '<td data-k="overdue">' . ($s['overdue'] ? '<span class="fg-red">' . (int) $s['overdue'] . '</span>' : '0') . '</td>'
            . '<td data-k="failed">' . ($s['failed'] ? '<span class="fg-orange">' . (int) $s['failed'] . '</span>' : '0') . '</td></tr>';
    }

    /**
     * JS que conecta la tabla $id al flujo SSE $url.
     *
     * @param string $id
     * @param string $url
     * @return string
     */
    private static function liveScript(string $id, string $url): string
    {
        $id = json_encode($id);
        $url = json_encode($url);
        return <<<JS
        $(function () {
            if (!window.EventSource) { return; }
            var table = $('#' + {$id}), es = new EventSource({$url});
            function cell(n, cls) { return n ? $('<span>').addClass(cls).text(n) : document.createTextNode('0'); }
            es.addEventListener('update', function (e) {
                var data = JSON.parse(e.data), seen = {};
                $.each(data.queues || {}, function (q, s) {
                    seen[q] = true;
                    var tr = table.find('tbody tr').filter(function () { return $(this).attr('data-queue') === q; });
                    if (!tr.length) {
                        tr = $('<tr>').attr('data-queue', q).append($('<td>').append($('<code>').text(q)),
                            $('<td data-k="pending">'), $('<td data-k="overdue">'), $('<td data-k="failed">'));
                        table.find('tbody').append(tr);
                    }
                    tr.find('[data-k=pending]').text(s.pending);
                    tr.find('[data-k=overdue]').empty().append(cell(s.overdue, 'fg-red'));
                    tr.find('[data-k=failed]').empty().append(cell(s.failed, 'fg-orange'));
                });
                // Una cola que se vació por completo ya no sale en queues(): queda en ceros.
                table.find('tbody tr').each(function () {
                    if (!seen[$(this).attr('data-queue')]) { $(this).find('[data-k]').text('0'); }
                });
                table.trigger('nf:jobs-update', [data]);
            });
        });
JS;
    }

    /**
     * Procesos job_worker.php que corren en este servidor. El host (bloque de config.php) se lee de la variable
     * HTTP_HOST del proceso; solo es visible para procesos del mismo usuario (o con root), si no queda null.
     *
     * @param string $proc Raíz de procfs (en los tests, una carpeta falsa).
     * @return list<array{pid: int, queue: string, lease: int, host: string|null, uid: int|null, rss: int|null, started: int|null}>
     */
    public static function workers(string $proc = '/proc'): array
    {
        $workers = [];
        foreach (glob($proc . '/[0-9]*', GLOB_ONLYDIR) ?: [] as $dir) {
            $cmdline = @file_get_contents($dir . '/cmdline');
            if (!is_string($cmdline) || !str_contains($cmdline, 'job_worker.php')) {
                continue;
            }
            $info = self::parseWorker($cmdline, @file_get_contents($dir . '/environ') ?: null);
            if ($info === null) {
                continue;
            }
            $status = (string) @file_get_contents($dir . '/status');
            $uid = @fileowner($dir);
            $workers[] = ['pid' => (int) basename($dir)] + $info + [
                'uid' => $uid === false ? null : $uid,
                'rss' => preg_match('/^VmRSS:\s+(\d+)\s+kB/m', $status, $mm) ? (int) $mm[1] * 1024 : null,
                'started' => ($t = @filectime($dir)) === false ? null : $t,
            ];
        }
        return $workers;
    }

    /**
     * Cola, tiempo de reserva y host de un proceso a partir de /proc/PID/cmdline y environ (separados por \0).
     * null si no es un worker (p. ej. un editor que abrió job_worker.php).
     *
     * @param string $cmdline
     * @param string|null $environ null = no legible.
     * @return array{queue: string, lease: int, host: string|null}|null
     */
    public static function parseWorker(string $cmdline, ?string $environ): ?array
    {
        $args = explode("\0", rtrim($cmdline, "\0"));
        // El proceso debe ser PHP (php, php8.3...): así no cuentan sudo, un editor o grep con el mismo argumento.
        if (!preg_match('/^php[0-9.]*$/', basename($args[0]))) {
            return null;
        }
        $script = null;
        foreach ($args as $i => $arg) {
            if ($i > 0 && basename($arg) === 'job_worker.php') {
                $script = $i;
                break;
            }
        }
        if ($script === null) {
            return null;
        }
        $positional = array_values(array_filter(array_slice($args, $script + 1), fn ($a) => !str_starts_with($a, '--')));
        $host = null;
        if ($environ !== null && preg_match('/(?:^|\0)HTTP_HOST=([^\0]*)/', $environ, $mm)) {
            $host = $mm[1];
        }
        return ['queue' => $positional[0] ?? 'default', 'lease' => (int) ($positional[1] ?? 5), 'host' => $host];
    }

    /**
     * Workers de un sitio: los que tienen ese HTTP_HOST, más los de host desconocido (no se pudo leer environ).
     *
     * @param list<array{host: string|null}> $workers De workers().
     * @param string $host
     * @return list<array<string, mixed>>
     */
    public static function workersForHost(array $workers, string $host): array
    {
        return array_values(array_filter($workers, fn ($w) => $w['host'] === null || strcasecmp($w['host'], $host) === 0));
    }
}
