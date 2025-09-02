<?
$developermode=true;
$usecommon=true;
if(empty($nframework)){
	require_once 'include.php';
}

if (!$nframework->isAjax()){
$nframework->usecommon=true;
$nframework->css['998']='https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/default.min.css';
$nframework->jss['998']='https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js';

$nframework->csss['101']='/css/index.css?d='.date('Ymhis');
function tocode($filetocode,$all=false) :string
{
    $code=file_get_contents($filetocode);
    if($all){
    	$pos=strlen($code);
    }else{
    	$pos=strpos($code, '<pre class="stay-on"><code');
    }
    return htmlentities(substr($code, 0, $pos));
}
$sidebar=new Sidebar([
    'title'=> 'nf5',
    'sidemenu'=> '<li class="item-header">Introducction</li>
	            <li>
	                <a href="/" class="side-menu__item">
	                    <span class="icon"><span class="mif-featured-play-list"></span></span>
	                    <span class="caption">Features</span>
	                </a>
	            </li>
	            <li>
	                <a href="/docs/intro.php" class="side-menu__item">
	                    <span class="icon"><span class="mif-apps"></span></span>
	                    <span class="caption">Getting started</span>
	                </a>
	            </li>
	            <li>
	                <a href="/download/" class="side-menu__item">
	                    <span class="icon"><span class="mif-download"></span></span>
	                    <span class="caption">Download</span>
	                </a>
	            </li>
	            <li class="item-header">Base Components</li>
	            <li>
	                <a href="/docs/inputs.php" class="side-menu__item">
	                    <span class="icon"><span class="mif-widgets"></span></span>
	                    <span class="caption">Inputs</span>
	                </a>
	            </li>
	            <li>
	                <a href="/docs/files.php" class="side-menu__item">
	                    <span class="icon"><span class="mif-widgets"></span></span>
	                    <span class="caption">InputFiles</span>
	                </a>
	            </li>
	            <li>
	                <a href="/docs/datetime.php" class="side-menu__item">
	                    <span class="icon"><span class="mif-calendar"></span></span>
	                    <span class="caption">Datetime</span>
	                </a>
	            </li>
	            <li>
	                <a href="/docs/webcamshow.php" class="side-menu__item">
	                    <span class="icon"><span class="mif-camera"></span></span>
	                    <span class="caption">Webcam</span>
	                </a>
	            </li>
	            <li>
	                <a href="/docs/databinding.php" class="side-menu__item">
	                    <span class="icon"><span class="mif-database"></span></span>
	                    <span class="caption">Databinding</span>
	                </a>
	            </li>
	            <li>
	                <a href="/docs/datatable.php" class="side-menu__item">
	                    <span class="icon"><span class="mif-table"></span></span>
	                    <span class="caption">DataTable</span>
	                </a>
	            </li>
	            <li class="item-header category-name">Ajax</li>
	            <li>
	                <a href="/docs/databindingajax.php?id='.session_id().'" class="side-menu__item">
	                    <span class="icon"><span class="mif-database"></span></span>
	                    <span class="caption">Ajax Databinding</span>
	                </a>
	            </li>
	            
	            <li>
	                <a href="/docs/datatableajax.php" class="side-menu__item">
	                    <span class="icon"><span class="mif-table"></span></span>
	                    <span class="caption">Ajax DataTable</span>
	                </a>
	            </li>
	            <li class="item-header">Backend Components</li>
	            <li>
	                <a href="#" class="side-menu__item">
	                    <span class="icon"><span class="mif-gamepad"></span></span>
	                    <span class="caption">DataSession</span>
	                </a>
	            </li>
	            <li>
	                <a href="/docs/user.php" class="side-menu__item">
	                    <span class="icon"><span class="mif-user"></span></span>
	                    <span class="caption">User</span>
	                </a>
	            </li>
	            <li>
	                <a href="/docs/javas.php" class="side-menu__item">
	                    <span class="icon"><span class="mif-file-code"></span></span>
	                    <span class="caption">Javas</span>
	                </a>
	            </li>
	            <li>
	                <a href="/docs/functions.php" class="side-menu__item">
	                    <span class="icon"><span class="mif-code"></span></span>
	                    <span class="caption">Functions</span>
	                </a>
	            </li>
	            <li>
	                <a href="/docs/vars.php" class="side-menu__item">
	                    <span class="icon"><span class="mif-money"></span></span>
	                    <span class="caption">Special vars</span>
	                </a>
	            </li>
	            <li>
	                <a href="#" class="side-menu__item">
	                    <span class="icon"><span class="mif-file-image"></span></span>
	                    <span class="caption">Cache/Imagen</span>
	                </a>
	            </li>
	            <li>
	                <a href="/docs/authors.php" class="side-menu__item">
	                    <span class="icon"><span class="mif-user"></span></span>
	                    <span class="caption">Authors</span>
	                </a>
	            </li>
	'
    ]);
   echo $sidebar;
}