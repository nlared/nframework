<?
use Mezon\Router\Router;
use Mezon\Router\RouterInterface;
$developermode=true;

require __DIR__.'/nframework/router.php';

$uri=($_SERVER['QUERY_STRING']!=''?substr($_SERVER['REQUEST_URI'],0,-strlen($_SERVER['QUERY_STRING'])-1) : $_SERVER['REQUEST_URI']);





/*
$router->addRoute('/rest/', function(){}, 'POST'); // this handler will be called for POST requests
$router->addRoute('/rest/', function(){}, 'GET');  // this handler will be called for GET requests 
$router->addRoute('/rest/', function(){}, 'PUT');  // this handler will be called for PUT requests
$router->addRoute('/rest/', function(){}, 'DELETE');  // this handler will be called for DELETE requests
$router->addRoute('/rest/', function(){}, 'OPTION');  // this handler will be called for OPTION requests
$router->addRoute('/rest/', function(){}, 'PATCH');  // this handler will be called for PATCH requests
*/
/*
$router->addRoute('/rest/', function(){
	global $m,$config;
    $body = json_decode(file_get_contents('php://input'), true);
	$data=$m->{$config['sitedb']}->exampledata->insertOne($body);
	echo json_encode($data);
}, 'POST'); // this handler will be called for POST requests
$router->addRoute('/rest/', function(){
	global $m,$config;
	foreach ($m->{$config['sitedb']}->exampledata->find() as $d){
		$data[]=$d;
	}
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode($data);
}, 'GET');  // this handler will be called for GET requests 
$router->addRoute('/rest/', function(){
		
}, 'PUT');  // this handler will be called for PUT requests
$router->addRoute('/rest/', function(){
	$m->{$config['sitedb']}->exampledata->removeMany();
}, 'DELETE');  // this handler will be called for DELETE requests
//$router->addRoute('/rest/', function(){}, 'OPTION');  // this handler will be called for OPTION requests
//$router->addRoute('/rest/', function(){}, 'PATCH');  // this handler will be called for PATCH requests

$router->addRoute('/rest/[s:_id]', function($route,$args){
	global $m,$config;
	$data=$m->{$config['sitedb']}->exampledata->findOne(['_id'=>tomongoid($args['_id'])]);
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode($data);
}, 'GET');  // this handler will be called for GET requests 

$router->addRoute('/rest/[s:_id]', function($route,$args){
	global $m,$config;
    $body = json_decode(file_get_contents('php://input'), true);
	unset($data['_id']);
	$data=$m->{$config['sitedb']}->exampledata->updateOne(['_id'=>tomongoid($args['_id'])],['$set'=>$body]);
	echo json_encode($data);
}, 'PUT');  // this handler will be called for GET requests 
*/
if (file_exists($_SERVER['DOCUMENT_ROOT'].'/crouter.php')){
	include($_SERVER['DOCUMENT_ROOT'].'/crouter.php');
}
try{
	$router->callRoute($uri);
}catch(exception $e){
	//header('HTTP/1.0 404 Not Found');
	$page=$m->{$config['sitedb']}->pages->findOne(['path'=>'_404']);
	echo $page['html'];
}
