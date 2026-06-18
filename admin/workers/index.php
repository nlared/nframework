<?
$supervisorConfig =
    '[program:nframework-worker]  
command=php /var/www/html/job_worker.php default 5  
directory=/var/www/html  
process_name=%(program_name)s_%(process_num)02d  
numprocs=1  
autostart=true  
autorestart=true  
user=www-data  
redirect_stderr=true  
stdout_logfile=/var/log/nframework/worker.log  
stopwaitsecs=3600  
environment=PATH="/usr/bin:/bin"';

$installSupervisorConfig = 'sudo supervisorctl reread  
sudo supervisorctl update  
sudo supervisorctl start nframework-worker:*';


$systemdService = '[Unit]  
Description=nFramework Queue Worker %i  
After=network.target mongodb.service  
  
[Service]  
Type=simple  
User=www-data  
Group=www-data  
WorkingDirectory=/var/www/html  
ExecStart=/usr/bin/php job_worker.php %i 5  
Restart=always  
RestartSec=10  
Environment=PATH=/usr/bin:/bin  
StandardOutput=append:/var/log/nframework/worker-%i.log  
StandardError=inherit  
  
[Install]  
WantedBy=multi-user.target';


$_SESSION['pids']['queue_worker'] = [
    'cmd' => 'php ' . __DIR__ . '/job_worker.php default 5',
    'logfile' => __DIR__ . '/logs/worker.log'
];

$workerLogRotation = '/var/log/nframework/worker*.log {  
    daily  
    missingok  
    rotate 30  
    compress  
    delaycompress  
    notifempty  
    create 644 www-data www-data  
    postrotate  
        supervisorctl restart nframework-worker:*  # o systemctl restart nframework-worker@*  
    endscript  
}';
