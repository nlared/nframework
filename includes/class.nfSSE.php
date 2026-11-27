<?php

/**
 * Server-Sent Events: el servidor empuja datos al navegador por una conexión abierta (EventSource), sin
 * WebSockets ni dependencias. Para tableros que hoy consultan cada N segundos (bgprocess, colas de trabajo).
 *
 *   // En la página, antes de imprimir nada (atiende ?stream=1):
 *   if (($_GET['stream'] ?? '') === '1') {
 *       nfSSE::stream(fn () => ['pendientes' => $m->{$config['sitedb']}->jobs->countDocuments()]);
 *       exit;
 *   }
 *   // Navegador:
 *   //   var es = new EventSource('?stream=1');
 *   //   es.addEventListener('update', e => mostrar(JSON.parse(e.data)));
 *   //   es.addEventListener('end', () => es.close());
 *
 * $tick se llama cada 'interval' segundos y devuelve los datos del momento: se envían como evento 'update' solo
 * si cambiaron (onlychanges), null no envía nada y false termina con un evento 'end' (el navegador debe cerrar;
 * si no, EventSource reconecta solo). También puede enviar eventos propios con $sse->send($datos, 'evento').
 *
 * Cada conexión ocupa un proceso de PHP-FPM mientras dura, así que el flujo termina a los 'maxtime' segundos
 * (30) y el navegador reconecta a los 'retry' ms: así una pestaña olvidada no aparta un proceso para siempre y
 * cada reconexión vuelve a revisar sesión y permisos. Con muchos usuarios mirando a la vez, suba
 * pm.max_children o baje maxtime. Antes de transmitir se cierra la sesión (session_write_close): las demás
 * peticiones del usuario no esperan, pero $_SESSION ya no se guarda. nginx no debe almacenar la respuesta
 * (se envía X-Accel-Buffering: no). Ver docs/sse.php.
 */
class nfSSE
{
    /** @var float Segundos entre llamadas a $tick. */
    public $interval = 1.0;
    /** @var int Segundos que dura la conexión antes de que el navegador reconecte. */
    public $maxtime = 30;
    /** @var int Milisegundos que espera el navegador para reconectar. */
    public $retry = 2000;
    /** @var int Segundos sin enviar nada tras los que se manda un comentario (detecta conexiones cerradas). */
    public $heartbeat = 15;
    /** @var bool Solo enviar 'update' cuando los datos cambian. */
    public $onlychanges = true;
    /** @var string Nombre del evento de los datos de $tick. */
    public $event = 'update';
    /** @var string Último id que recibió el navegador antes de reconectar (cabecera Last-Event-ID). */
    public $lastEventId = '';
    /** @var int Ticks ejecutados en esta conexión. */
    public $ticks = 0;

    /** @var string|null JSON del último 'update' enviado. */
    private $last = null;
    /** @var float Momento del último envío. */
    private $lastWrite = 0.0;
    /** @var int Bytes enviados. */
    private $bytes = 0;

    /**
     * @param array{interval?: float, maxtime?: int, retry?: int, heartbeat?: int, onlychanges?: bool, event?: string} $options
     */
    public function __construct($options = [])
    {
        foreach ((array) $options as $name => $value) {
            if (property_exists($this, $name) && !in_array($name, ['ticks', 'lastEventId'], true)) {
                $this->{$name} = $value;
            }
        }
        $this->interval = max(0.2, (float) $this->interval);
        $this->maxtime = max(1, min(600, (int) $this->maxtime));
        $this->retry = max(100, (int) $this->retry);
        $this->heartbeat = max(1, (int) $this->heartbeat);
        $last = $_SERVER['HTTP_LAST_EVENT_ID'] ?? '';
        $this->lastEventId = is_string($last) ? mb_substr($last, 0, 200) : '';
    }

    /**
     * Abre el flujo y llama a $tick hasta maxtime, hasta que devuelva false o el navegador cierre. Al volver,
     * termine la petición (exit) sin imprimir nada más.
     *
     * @param callable(nfSSE): mixed $tick Datos (arreglo/escalar serializable), null = nada, false = terminar.
     * @param array{interval?: float, maxtime?: int, retry?: int, heartbeat?: int, onlychanges?: bool, event?: string} $options
     * @return void
     */
    public static function stream(callable $tick, $options = [])
    {
        (new self($options))->run($tick);
    }

    /**
     * @param callable(nfSSE): mixed $tick
     * @return void
     */
    public function run(callable $tick)
    {
        $this->open();
        $start = microtime(true);
        while (true) {
            $this->ticks++;
            $data = $tick($this);
            if ($data === false) {
                $this->send(['done' => true], 'end');
                break;
            }
            if ($data !== null) {
                $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
                if (!$this->onlychanges || $json !== $this->last) {
                    $this->last = $json;
                    $this->write(self::format($json, $this->event, (string) $this->ticks));
                }
            }
            if (microtime(true) - $this->lastWrite >= $this->heartbeat) {
                $this->write(": ping\n\n");
            }
            if (connection_aborted() || microtime(true) - $start + $this->interval > $this->maxtime) {
                break;
            }
            usleep((int) ($this->interval * 1000000));
        }
    }

    /**
     * Envía un evento propio. $data se envía como JSON (un texto ya JSON pásese con $raw = true).
     *
     * @param mixed $data
     * @param string $event '' = evento 'message'.
     * @param bool $raw $data ya es texto para la línea data:.
     * @return void
     */
    public function send($data, $event = '', $raw = false)
    {
        $this->write(self::format($raw ? (string) $data : (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), $event));
    }

    /**
     * Texto de un evento SSE. Cada línea de $data va en su propia línea data:, y event/id no pueden tener
     * saltos de línea (se quitan).
     *
     * @param string $data
     * @param string|null $event
     * @param string|null $id
     * @return string
     */
    public static function format(string $data, ?string $event = null, ?string $id = null): string
    {
        $out = '';
        if ($id !== null && $id !== '') {
            $out .= 'id: ' . str_replace(["\r", "\n", "\0"], '', $id) . "\n";
        }
        if ($event !== null && $event !== '') {
            $out .= 'event: ' . str_replace(["\r", "\n"], '', $event) . "\n";
        }
        foreach (preg_split('/\r\n|\r|\n/', $data) as $line) {
            $out .= 'data: ' . $line . "\n";
        }
        return $out . "\n";
    }

    /**
     * Cierra la sesión, descarta el buffer del framework y envía las cabeceras del flujo.
     *
     * @global class_nframework $nframework
     * @return void
     */
    private function open(): void
    {
        global $nframework;
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        ignore_user_abort(false);
        @set_time_limit($this->maxtime + 15);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if ($nframework instanceof class_nframework) {
            $nframework->streamed = true;   // nfshutdown no arma plantilla ni JSON
        }
        if (!headers_sent()) {
            header('Content-Type: text/event-stream; charset=utf-8');
            header('Cache-Control: no-store');
            header('X-Accel-Buffering: no');
            header('X-Content-Type-Options: nosniff');
        }
        $this->write('retry: ' . $this->retry . "\n\n");
    }

    /**
     * @global class_nframework $nframework
     * @param string $text
     * @return void
     */
    private function write(string $text): void
    {
        global $nframework;
        echo $text;
        flush();
        $this->bytes += strlen($text);
        $this->lastWrite = microtime(true);
        if ($nframework instanceof class_nframework) {
            $nframework->streamedBytes = $this->bytes;
        }
    }
}
