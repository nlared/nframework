<?

use Cocur\BackgroundProcess\BackgroundProcess;

class bgprocess
{
    public $cmd;
    public $pid;
    public $logfile;
    public $id;
    public $onChange;
    public $onComplete;

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
    public function start()
    {
        $proc = new BackgroundProcess($this->cmd);
        $proc->run($this->logfile);
        $this->pid = $proc->getPid();
        $_SESSION['pids'][$this->id]['pid'] = $this->pid;        
    }

    public function status()
    {
        $process = BackgroundProcess::createFromPID($this->pid);
        return
            $result = [
                'data' => file_get_contents($this->logfile),
                'isRunning' => $process->isRunning()
            ];
    }
    public function isRunning()
    {
        if (!empty($_SESSION['pids'][$this->id]['pid'])) {
            $process = BackgroundProcess::createFromPID($_SESSION['pids'][$this->id]['pid']);
            return    $process->isRunning();
        } else {
            return false;
        }
    }
    public function stop()
    {
        $process = BackgroundProcess::createFromPID($this->pid);
        if ($process->isRunning()) {
            $process->stop();
        }
    }
    public function __toString()
    {
        global $javas;
        $javas->addjs('
	$(".bg_process").click(function() {
		var icon = $(this).find("span");
		if (icon.hasClass("mif-play")){
			icon.removeClass("mif-play");
			
			var id = $(this).attr("id").substring(10);
			
			$.ajax({
				url: "/nframework/kernel.php?op=start&pid="+id, 
				success: function(result){
				icon.addClass("mif-stop");
				}
			});
		}
	
	});
');


        $s = ($this->isRunning() ? 'stop' : 'play');
        return '<div class="bg_process" id="bgprocess_' . $this->id . '" ><span class="mif-' . $s . '" ></span>s</div>';
    }
}
