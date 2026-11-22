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
     * Tablero con botones iniciar/detener y el log en vivo.
     *
     * @global class_nframework $nframework
     * @return string HTML.
     */
    public function renderDashboard()
    {
        global $nframework;
        
        $dashboardId = 'bgdashboard_' . uniqid();
        
        // Inject JS for dashboard
        $js = <<<JS
        function updateBgDashboard() {
            $.ajax({
                url: "/nframework/kernel.php?op=status",
                dataType: "json",
                success: function(data) {
                    if (data.pids) {
                        for (const [id, info] of Object.entries(data.pids)) {
                            const row = $('#bgrow_' + id);
                            const icon = row.find('.status-icon');
                            const btnStart = row.find('.btn-start');
                            const btnStop = row.find('.btn-stop');
                            
                            if (info.running) {
                                icon.removeClass('mif-stop fg-red').addClass('mif-play fg-green');
                                row.addClass('bg-lightGreen');
                                btnStart.attr('disabled', true);
                                btnStop.attr('disabled', false);
                            } else {
                                icon.removeClass('mif-play fg-green').addClass('mif-stop fg-red');
                                row.removeClass('bg-lightGreen');
                                btnStart.attr('disabled', false);
                                btnStop.attr('disabled', true);
                            }
                        }
                    }
                }
            });
        }
        
        $(document).on('click', '.btn-bg-action', function() {
            var action = $(this).data('action');
            var pid = $(this).data('pid');
            
            $.ajax({
                url: "/nframework/kernel.php",
                data: { op: action, pid: pid },
                dataType: "json",
                success: function(res) {
                    if(res.error) {
                        alert(res.error);
                    } else {
                        updateBgDashboard();
                    }
                }
            });
        });

        setInterval(updateBgDashboard, 3000); // Poll every 3 seconds
        updateBgDashboard(); // Initial call
JS;

        
        $html = '<script>' . $js . '</script>';
        $html .= '<table class="table striped hovered cell-hover" id="' . $dashboardId . '">';
        $html .= '<thead><tr><th>ID</th><th>Command</th><th>Status</th><th>Actions</th></tr></thead>';
        $html .= '<tbody>';
        
        if (!empty($_SESSION['pids'])) {
            foreach ($_SESSION['pids'] as $pid => $data) {
                 $html .= '<tr id="bgrow_' . $pid . '">';
                 $html .= '<td>' . htmlspecialchars($pid) . '</td>';
                 $html .= '<td>' . htmlspecialchars($data['cmd']) . '</td>';
                 $html .= '<td><span class="status-icon mif-stop"></span></td>';
                 $html .= '<td>
                    <button class="button small success btn-bg-action btn-start" data-action="start" data-pid="' . $pid . '"><span class="mif-play"></span> Start</button>
                    <button class="button small alert btn-bg-action btn-stop" data-action="stop" data-pid="' . $pid . '"><span class="mif-stop"></span> Stop</button>
                 </td>';
                 $html .= '</tr>';
            }
        } else {
            $html .= '<tr><td colspan="4">No processes configured.</td></tr>';
        }        
        $html .= '</tbody></table>';        
        return $html;
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
