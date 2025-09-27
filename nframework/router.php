<?
require 'include.php';
$loader1 = new \Twig\Loader\FilesystemLoader(__DIR__.'/templates');
$loader2= new \Twig\Loader\FilesystemLoader(__DIR__.'/templates/panda');
$loader3= new \Twig\Loader\FilesystemLoader($nframework->include_path.'/i18n/'.$nframework->lang);
$loader = new \Twig\Loader\ChainLoader([$loader1, $loader2,$loader3]);
$twig = new \Twig\Environment($loader, [
    'cache' => false,//__DIR__.'/../compilation_cache',
    'debug' => true,
    'auto_reload' => true,
]);


use Intervention\Image\ImageManager;
//https://github.com/alexdodonov/mezon-router#routing--


$router = new \Mezon\Router\Router();

$router->addRoute('index', function($route, $variables){
	global $_SERVER,$twig,$nframework,$config,$m;
	
	$header=$m->{$config['sitedb']}->pages->findOne(['path'=>'_header']);
	$footer=$m->{$config['sitedb']}->pages->findOne(['path'=>'_footer']);
	$parallax=$m->{$config['sitedb']}->pages->findOne(['path'=>'_parallax']);
	$menu=$m->{$config['sitedb']}->menus->findOne(['name'=>'_nav']);
	
	$nframework->usecommon=true;
	$template = $twig->load('page.html');
	
	if($config['homepagetype']=='page'){
		$page=$m->{$config['sitedb']}->pages->findOne(['path'=>'_home']);
		$nframework->metas['description']=$page->description;
		$nframework->metas['title']=$page->title;
		$nframework->metas['keywords']=$page->keywords;
			
	}else{
		
	}
	
	echo $template->render([
		'theme'=>$config['theme'],
		'parallaxpage' =>$parallax?->html,
		'page' =>$page->html,
		'header'=>$header->html,
		'footer'=>$footer->html,
		'menu'=>$menu->code,
		'themeswitcher'=>(string)$themeswitcher,
		'route'=>'index.php'
	]);
},'GET');


$router->addRoute('/main.js', function(string $route,array $p){
	global $twig,$config;
	 header('Content-Type: text/javascript; charset=utf-8');
	$template = $twig->load('main.js');
	echo $template->render([
		'publicKey' => $config['notifications']['publicKey']
	]);
},'GET');

$router->addRoute('/account/login', function(string $route,array $p){
	global $twig,$config,$nframework,$themeswitcher;
	
	if (!empty($_POST['login'])){
		$login=$_POST['login'];
		$user= new User([
			'username'=> ['$regex' => trim($login['username']), '$options' => 'i'],
			'password'=>trim($login['password'])
			]);
			
		if(!empty($user->_id)){
			header('edtoy: sss1');	
			$_SESSION['user']=$user->_id;
			session_write_close();
	        if( $_SESSION['nframework']['loginpage']!='' && $_SESSION['nframework']['loginpage']!='/account/login.php'){
	            header('location: '.$_SESSION['nframework']['logiopage']);
	        }else{
	        	if($user->in('admins')){
	        		header('location: /admin/');
	        	}else{
	        		header('location: /');
	        	}
	        }
	        exit();
		}
		$msgError='Datos incorrectos';
	}
	
	if(!empty($_SESSION['nframework']['loginpage'])){
		$_SESSION['nframework']['loginerror']='Datos incorrectos';
		header('location: '.$_SESSION['nframework']['loginpage']);
	}
	
	
	$nframework->usecommon=true;
	$template = $twig->load('login.html');
	$oauths=[
		'google'=>$config['google_oauth_client_enable'],
		'facebook'=>$config['facebook_oauth_client_enable'],
	];
	


	echo $template->render([
		'lng' => $nframework->language(),
		'oauths'=>$oauths,
		'themeswitcher'=>(string)$themeswitcher,
	]);

	
	//print_r($config);
},['GET','POST']);

$router->addRoute('/account/signup', function(string $route,array $p){
	global $twig,$config,$nframework;
	$nframework->usecommon=true;
	$template = $twig->load('signup.html');
	echo $template->render([
		'lng' => $nframework->language()
	]);
},'GET');

$router->addRoute('/account/logout', function(string $route,array $p){
	global $twig,$config,$nframework,$user,$m;
	
	
	$tmp=(array)$user->sessions;
	//unset($tmp[session_id()]);
	$tmp=array_diff($tmp, [session_id()]);
	
	$m->{$config['sitedb']}->endpoints->deleteOne(['_id'=>(string)session_id()]);
	$user->sessions=$tmp;
	
	unset($user);
	unset($_SESSION['user']);
	unset($_SESSION['emisor']);
	unset($_SESSION['primerinicio']);
	session_regenerate_id(true); 
	if (session_status() == PHP_SESSION_NONE) {
	    session_start();
	}
	session_destroy();
	header('Location: '.(isset($_GET['to'])?$_GET['to']:'/'));
	$nframework->usecommon=true;
	$template = $twig->load('logout.html');
	echo $template->render([
		'lng' => $nframework->language()
	]);
	
},'GET');

$router->addRoute('/login-google/oauth', function(string $route,array $p){
	global $twig,$config,$nframework,$m;
	require 'google/oauth.php';
},['GET','POST']);

$router->addRoute('/login-facebook/oauth', function(string $route,array $p){
	global $twig,$config,$nframework,$m;
	require 'facebook/oauth.php';
},['GET','POST']);

$router->addRoute('/login-microsoft/oauth', function(string $route,array $p){
	global $twig,$config,$nframework,$m;
	require 'ms/oauth.php';
},['GET','POST']);

$router->addRoute('/.well-known/microsoft-identity-association.json', function(string $route,array $p){
	global $config;
	header('Content-Type: application/json; charset=utf-8');
	echo '{
  "associatedApplications": [
    {
      "applicationId": "'.$config['microsoft_oauth_client_id'].'"
    }
  ]
}';
},['GET']);



//TODO:favicon.ico


$router->addRoute('/robots.txt', function($route, $variables){
	global $_SERVER;
	header('Content-Type: text/plain');
echo'User-agent: *
Disallow: 
Disallow: /nframework/
Disallow: /account/
Sitemap: http://'.$_SERVER['HTTP_HOST'].'/sitemap.xml';	
});


$router->addRoute('/sitemap.xml', function($route, $variables){
	global $m,$config;
	header("Content-type: text/xml; charset=utf-8");
	$urls=[];
	foreach($m->{$config['sitedb']} as $url){
	$urls[]='<url>
  <loc>'.$url['url'].'</loc>
  <lastmod>'.$url['lastmod'].'</lastmod>
  <priority>'.$url['prioridad'].'</priority>
</url>';
	}
	echo'<?xml version="1.0" encoding="UTF-8"?>
<urlset
      xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
      xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
      xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9
            http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">
<!-- created by nframework5 -->
'.implode("\n",$urls).'

</urlset>';	
},'GET');


$router->addRoute('/.well-known/acme-challenge/[s:filename]', function($route, $variables){
	global $m,$config;
    $client = new Api($config->letsencrypt_email, __DIR__ . '/__account');
    $account = $client->account()->get();

	
	try {
    	$client->domainValidation()->start($account, $validationStatus[0], AuthorizationChallengeEnum::HTTP);
    	$privateKey = \Rogierw\RwAcme\Support\OpenSsl::generatePrivateKey();
		$csr = \Rogierw\RwAcme\Support\OpenSsl::generateCsr(['example.com'], $privateKey);
		if ($order->isReady() && $client->domainValidation()->allChallengesPassed($order)) {
    		$client->order()->finalize($order, $csr);
		}
		if ($order->isFinalized()) {
		   $certificateBundle = $client->certificate()->getBundle($order);
		}
		$config->letsencryptvalidtruh=strtotime('+90 days');
		

	} catch (DomainValidationException $exception) {
	    // The local HTTP challenge test has been failed...
	}
	foreach($validationData as $vd){
		if($vd['identifier']==$_SERVER['HTTP_HOST']&&$vd['filename']==$variables['filename']){
			echo $vd['content'];
			exit();
		}
	}
});


$router->addRoute('/images/config/[i:size]/logo.png', function(string $route,array $p){
	global $m,$config;
	$logo=$_SERVER['DOCUMENT_ROOT'].'/img/nf/logo.png';
	$dir='img/nf/config/';
	$dst=$dir.'/logo_'.$p['size'].'.png';
	if(!file_exists($dst)||filemtime($dst)<filemtime($logo)){
		if(!file_exists($dir)){
			mkdir($dir,0777,true);
		}
		$manager = new ImageManager(array('driver' => 'gd'));
		$img = $manager->make($logo);
		$img->fit($p['size'],$p['size'], function ($constraint) {
		    $constraint->aspectRatio();
		   // $constraint->upsize();
		});
		$img->save($dst);
	}
	header('Content-Length: '.filesize($dst));
    header('Content-Type: image/png');
    echo file_get_contents($dst);
},'GET');

$router->addRoute('/images/frompdf/[s:id]/[i:w]/[i:h]/[i:p].png', function(string $route,array $p){
	$options = $_SESSION['frompdf'][$p['id']];
	if(file_exists($options['filename'])){
		$pdf = new \Spatie\PdfToImage\Pdf($options['filename']);
		mkdir($options['directory']);
		$pdf->format(\Spatie\PdfToImage\Enums\OutputFormat::Png);
		$pdf->selectPage($p['p'])->size($p['w'])->save($options['directory'].$p['p'].'.png');
		header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
		header("Cache-Control: post-check=0, pre-check=0", false);
		header("Pragma: no-cache");
		header('Content-Length: '.filesize($options['directory'].'/'.$p['p'].'.png'));
	    header('Content-Type: image/png');
	    echo file_get_contents($options['directory'].'/'.$p['p'].'.png');	
	    if(!empty($options['deletefile'])&&$options['deletefile']==true){
			unlink($options['directory'].'/'.$p['p'].'.png');
		}
		if(!empty($options['deletedirectory'])&&$options['deletedirectory']==true){
			rmdir($options['directory']);
		}
	}
	
},'GET');

$router->addRoute('/images/frompdf/[s:id]/info.json', function(string $route,array $p){
	global $nframework;
	$nframework->isAjax=false;
	$options = $_SESSION['frompdf'][$p['id']];
	if(file_exists($options['filename'])){
		$pdf = new \Spatie\PdfToImage\Pdf($options['filename']);
		$size = $pdf->getSize();
		$result=[
			'numberOfPages' => $pdf->pageCount(),
			'width' => $size->width,
			'height' => $size->height,
		];
		header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
		header("Cache-Control: post-check=0, pre-check=0", false);
		header("Pragma: no-cache");
		header('Content-Type: application/json');
		echo json_encode($result);
	}
	
},'GET');

$router->addRoute('/images/[s:id]/[i:w]/[i:h]/preview.png', function(string $route,array $p){
	$upload = $_SESSION['uploads4'][$p['id']];
	$filename=$upload['extensioninfo']['path'];
	$extension = pathinfo($filename, PATHINFO_EXTENSION);
	
	$dst=sys_get_temp_dir().'/'.uniqid('pdftopng',true);
	mkdir($dst);
	$pdf = new \Spatie\PdfToImage\Pdf($filename);
	$pdf->format(\Spatie\PdfToImage\Enums\OutputFormat::Png);
	$pdf->selectPage($p['p'])->size($p['w'])->save($dst);
	
/*	header('dst:'.$dst);
	header('dstf:'.$filename);
	header('dstp:'.$p['p']);//*/
	header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
	header("Cache-Control: post-check=0, pre-check=0", false);
	header("Pragma: no-cache");
	header('Content-Length: '.filesize($dst.'/'.$p['p'].'.png'));
    header('Content-Type: image/png');
    echo file_get_contents($dst.'/'.$p['p'].'.png');	
	unlink($dst.'/'.$p['p'].'.png');
	rmdir($dst);
	
},'GET');

$router->addRoute('/images/config/[i:w]/[i:h]/logo.png', function(string $route,array $p){
	global $m,$config;
	$dir='img/nf/config/';
	$dst=$dir.'/logo_'.$p['w'].'x'.$p['h'].'.png';
	if(!file_exists($dst)||filemtime($dst)<filemtime($config['image'])){
		if(!file_exists($dir)){
			mkdir($dir,0777,true);
		}
		$manager = new ImageManager(array('driver' => 'gd'));
		$img = $manager->make($config['image']);
		$img->fit($p['w'], $p['h'], function ($constraint) {
		    $constraint->aspectRatio();
		    //$constraint->upsize();
		});
		$img->save($dst);
	}
	header('Content-Length: '.filesize($dst));
    header('Content-Type: image/png');
    echo file_get_contents($dst);
},'GET');

$router->addRoute('/images/resize/[s:id]/[i:w]/[i:h]/[s:file]', function(string $route,array $p){
	global $nframework;
	if(isset($_SESSION['imagesresize'][$p['id']])){
	$conf=$_SESSION['imagesresize'][$p['id']];
		$filename=$p['file'];
		$pos = strrpos($filename, '.');
		$name=substr($filename,0,$pos);
		$ext=substr($filename,$pos);
		$dst=$conf['dst'].'/'.$name.'_'.$p['w'].'x'.$p['h'].$ext;
		$src=$conf['src'].'/'.$filename;
		//echo "$name  $ext $dst";
		
	
		if(!file_exists($dst)){
			if(!file_exists($conf['dst'])){
				mkdir($dir,0777,true);
			}
			$actualizar=true;
		}else{
			$lasttimedst=filemtime($dst);
			$lasttimesrc=filemtime($src);
			if($lasttimedst<$lasttimesrc){
				$actualizar=true;
			}
		}
		if($actualizar){	
			$manager = new ImageManager(array('driver' => 'gd'));
			if(!file_exists($src)){
				$src=$conf['default'];
			}
			$img = $manager->make($src);
			$img->fit($p['w'], $p['h'], function ($constraint) {
			    $constraint->aspectRatio();
			    //$constraint->upsize();
			});
			$img->save($dst);
			$lasttimedst=filemtime($dst);
		}
		
		$toetag.=$dst.$lasttimedst;
		$nframework->lastmodified=$lasttimedst;
		$nframework->etag = md5($toetag);
		
		//$nframework->expiretime=time() + (60 * 60 * 24);
		
		
		if(isset($_SERVER['HTTP_IF_NONE_MATCH'])){
			$id=trim($_SERVER['HTTP_IF_NONE_MATCH']);
			if (substr($id,0,2)=="W/"){
				$id=substr($id,2);
			}
			$id=str_replace('"','',$id);
			if($id==$toetag){
				header('ncache: 304');
				http_response_code(304);
				
				die();
			}
		}
		
		header('Content-Length: '.filesize($dst));
	    header('Content-Type: image/png');
	    echo file_get_contents($dst);//*/
	}
},'GET');



$router->addRoute('/nf.webmanifest', function(string $route,array $p){
	global $config;
	header('Content-Type: application/manifest+json; charset=utf-8');
echo '{
    "name": "'.$config['title'].'",
    "short_name": "'.$config['shortname'].'",
    "id": "'.$config['shortname'].'",
    "theme_color": "'.$config['manifest']['theme_color'].'",
    "background_color": "'.$config['manifest']['background_color'].'",
    "display": "standalone",
    "scope": "/",
    "start_url": "/",
    "description": "'.str_replace(array("\n", "\r"), '',$config['description']).'",
    "orientation": "any",
    "launch_handler": {
    	"client_mode": "auto"
	},
    "edge_side_panel": {
    	"preferred_width": 1
	},
	"categories": [
    "education"
  ],
  "dir": "auto",
  "lang": "es",
  "prefer_related_applications": false,
  "iarc_rating_id": "16+",
    "icons": [
        {
            "src": "https://'.$_SERVER['HTTP_HOST'].'/images/config/72/logo.png",
            "sizes": "72x72",
            "type": "image/png",
            "purpose":"any"
        },
        {
            "src": "https://'.$_SERVER['HTTP_HOST'].'/images/config/96/logo.png",
            "sizes": "96x96",
            "type": "image/png",
            "purpose":"any"
        },
        {
            "src": "https://'.$_SERVER['HTTP_HOST'].'/images/config/144/logo.png",
            "sizes": "144x144",
            "type": "image/png",
            "purpose":"any"
        },
        {
            "src": "https://'.$_SERVER['HTTP_HOST'].'/images/config/192/logo.png",
            "sizes": "192x192",
            "type": "image/png",
            "purpose":"any"
        },
        {
            "src": "https://'.$_SERVER['HTTP_HOST'].'/images/config/256/logo.png",
            "sizes": "256x256",
            "type": "image/png",
            "purpose":"any"
        },
        {
            "src": "https://'.$_SERVER['HTTP_HOST'].'/images/config/384/logo.png",
            "sizes": "384x384",
            "type": "image/png",
            "purpose":"any"
        },
        {
            "src": "https://'.$_SERVER['HTTP_HOST'].'/images/config/512/logo.png",
            "sizes": "512x512",
            "type": "image/png",
            "purpose":"any"
        },
        {
            "src": "https://'.$_SERVER['HTTP_HOST'].'/images/config/1024/logo.png",
            "sizes": "1024x1024",
            "type": "image/png",
            "purpose":"any"
        }
    ]
}';
//72, 96, 144, 192, 256, 384, 512

},'GET');

$router->addRoute('/getPayload', function(string $route,array $p){
	global $m,$config;
	if(!empty($_GET['endpoint'])){
		if($_GET['endpoint']!='null'){
		$m->{$config['sitedb']}->endpoints->updateOne([['_id'=>(string)session_id()]],
			[
			'$set'=>[
				'endpoint'=>json_decode($_GET['endpoint'])
				]
			],['upsert'=> true]);
		}else{
			$m->{$config['sitedb']}->endpoints->deleteOne([['_id'=>(string)session_id()]]);
		}
		echo "ok";
	}
});

$router->addRoute('/privacy', function(string $route,array $p){
	global $nframework,$twig,$config,$m;
	$nframework->usecommon=true;
	$page=$m->{$config['sitedb']}->pages->findOne(['title'=>'Privacidad']);
	if(empty($page)){
		$template = $twig->load('privacy.html' );
		$body= $template->render(['config'=>$config]);
		echo $body;
	}else{
		echo $page['html'];
	}
},'GET');

$router->addRoute('/terms', function(string $route,array $p){
	global $nframework,$twig,$config,$m;
	$nframework->usecommon=true;
	$page=$m->{$config['sitedb']}->pages->findOne(['title'=>'Terms']);
	if(empty($page)){
		$template = $twig->load('terms.html' );
		$body= $template->render(['config'=>$config]);
		echo $body;
	}else{
		echo $page['html'];
	}
},'GET');

$router->addRoute('/righttoforget', function(string $route,array $p){
	global $nframework,$twig,$config,$m;
	$nframework->usecommon=true;
	$page=$m->{$config['sitedb']}->pages->findOne(['title'=>'righttoforget']);
	if(empty($page)){
		$template = $twig->load('righttoforget.html' );
		$body= $template->render(['config'=>$config]);
		echo $body;
	}else{
		echo $page['html'];
	}
},'GET');



$router->addRoute('/privacidad', function(string $route,array $p){
	global $nframework,$twig,$config,$m;
	$page=$m->{$config['sitedb']}->pages->findOne(['title'=>'Privacidad']);
	echo $page['html'];
},'GET');

$router->addRoute('/sw.js', function(string $route,array $p){
	global $nframework,$twig,$config;
	$template = $twig->load('sw.js');
	header('Content-Type: application/javascript; charset=utf-8');
	echo $template->render([
		'publicKey' => $config['notifications']['publicKey'],
		'tocache'=>array_values(array_merge($nframework->csss,$nframework->jss)),
		'csss'=>implode ("','",$nframework->csss),
		'jss'=>implode ("','",$nframework->jss)
	]);
},'GET');

foreach($m->{$config['sitedb']}->pages->distinct('path') as $d){
	if(!empty($d)){
		$router->addRoute($d, function($route,$arg){
			global $m,$config,$nframework,$twig;
			$page=$m->{$config['sitedb']}->pages->findOne(['path'=>$route]);
			$nframework->metas['description']=$page->description;
			$nframework->metas['title']=$page->title;
			$nframework->metas['keywords']=$page->keywords;
			
			$menud=$m->{$config['sitedb']}->menus->findOne(['name'=>'_nav']);
			if($menud){
				$menu=nfMetroMenu($menu->code);
			}
			
			$header=$m->{$config['sitedb']}->pages->findOne(['path'=>'_header']);
			$footer=$m->{$config['sitedb']}->pages->findOne(['path'=>'_footer']);
			$nframework->usecommon=true;
			$template = $twig->load('page.html');
			
			
			echo $template->render([
				'theme'=>$config['theme'],
				'page' => $page->html,
				'header'=>renderEmbeddedFunctions($header->html),
				'footer'=>$footer->html,
				'route' => $route,
			]);
			//echo $page->html;
		}, 'GET'); // this handler will be called for POST requests
	}
}
$router->addRoute('/cachetest.png', function($route,$arg){
	global $m;
	//$developermode=true;
	$cache=new cache(__DIR__.'/profilepict.png');
	$cache->contentType='image/png';
	$cache->cache();
	
});
