<?
require 'include.php';
$nframework->usecommon=true;
if(!$user->in('admins')){
	header('Location: /');
	exit();
}


$sidemenu.='
	<li class="slide">
		<a href="/" class="side-menu__item">
	        <span class="icon"><span class="mif-home"></span></span>
	        <span class="caption">'.$nframework->language['home'].'</span>
	    </a>
    </li>
    <li class="slide">
    <a href="/admin/pages"  class="side-menu__item">
        <span class="icon"><span class="mif-files-empty"></span></span>
        <span class="caption">'.$nframework->language['pages'].'</span>
    </a></li>
    <li class="slide"><a href="/admin/menus" >
        <span class="icon"><span class="mif-dropdown-menu"></span></span>
        <span class="caption">'.$nframework->language['menus'].'</span>
    </a></li>
    <li class="slide"><a href="/admin/site" >
        <span class="icon"><span class="mif-cog"></span></span>
        <span class="caption">'.$nframework->language['site'].'</span>
    </a></li>
	<li class="slide"><a href="/admin/site/auths.php" >
        <span class="icon"><span class="mif-user-secret"></span></span>
        <span class="caption">'.$nframework->language['site'].' Auths</span>
    </a></li> 
    <li class="slide"><a href="/admin/site/security.php" >
        <span class="icon"><span class="mif-security"></span></span>
        <span class="caption">'.$nframework->language['security'].' Auths</span>
    </a></li> 
    <li class="slide"><a href="/admin/theme/tconfig.php" >
        <span class="icon"><span class="mif-insert-template"></span></span>
        <span class="caption">'.$nframework->language['theme'].'</span>
    </a></li>
    
    <li class="slide"><a href="/admin/users" >
        <span class="icon"><span class="mif-account"></span></span>
        <span class="caption">'.$nframework->language['users'].'</span>
    </a></li>
    <li class="slide"><a href="/admin/usersgroups" >
        <span class="icon"><span class="mif-connected-users"></span></span>
        <span class="caption">'.$nframework->language['groups'].'</span>
    </a></li>
    <li class="slide"><a href="/admin/filemanager/" >
        <span class="icon"><span class="mif-folder-tree"></span></span>
        <span class="caption">'.$nframework->language['filemanager'].'</span>
    </a></li>
    <li class="slide"><a href="/admin/errorlog" >
        <span class="icon"><span class="mif-bug"></span></span>
        <span class="caption">'.$nframework->language['logs'].'</span>
    </a></li>
	';


if(file_exists($_SERVER['DOCUMENT_ROOT'].'/admins/common2.php')){
	include $_SERVER['DOCUMENT_ROOT'].'/admins/common2.php';
}


$sidebar=new Sidebar([
//'color'=> '',
'darkcolor'=> 'black',
'focuscolor'=> 'grayMouse',
'title'=>'Admin',
'sidemenu'=>$sidemenu]);
if (!$nframework->isAjax()) {
echo $sidebar;

}


