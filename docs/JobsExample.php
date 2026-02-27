<?
// Despachar un job  
SendEmailJob::dispatch(['to' => 'user@example.com', 'subject' => 'Hola']);  
  
// Iniciar worker (CLI)  
php job_worker.php default 5