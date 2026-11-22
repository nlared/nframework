<?php
require 'include.php';
$nframework->usecommon = true;
if (!$user->in('admins')) {
    header('Location: /');
    exit();
}

// Migraciones pendientes del sitio (el framework se actualizó pero esta base no): aviso en todo el panel.
// Se cachea 5 minutos; un POST de un admin a /admin/ vacía la caché.
if (!$nframework->isAjax()) {
    try {
        $nfPendientes = nfCacheRemember('nfupgrader:' . $config['sitedb'], 300, function () use ($m, $config) {
            $docroot = (string) ($_SERVER['DOCUMENT_ROOT'] ?? '');
            return count((new nfUpgrader($m->{$config['sitedb']}, nfUpgrader::defaultDirs($docroot), $docroot))->status()['pending']);
        });
        if ($nfPendientes > 0 && !str_starts_with((string) ($_SERVER['REQUEST_URI'] ?? ''), '/admin/update')) {
            notify('Actualización pendiente', $nfPendientes . ' migración(es) de base de datos sin aplicar: Admin → Actualizar → Migraciones.', ['timeout' => 10000]);
        }
    } catch (Throwable $e) {
        error_log('nfUpgrader: ' . $e->getMessage());
    }
}


$sidemenu .= '
	<li class="slide">
		<a href="/" class="side-menu__item">
	        <span class="icon"><span class="mif-home"></span></span>
	        <span class="caption">' . $nframework->language['home'] . '</span>
	    </a>
    </li>
    <li class="slide">
    <a href="/admin/pages"  class="side-menu__item">
        <span class="icon"><span class="mif-files-empty"></span></span>
        <span class="caption">' . $nframework->language['pages'] . '</span>
    </a></li>
    <li class="slide"><a href="/admin/menus" >
        <span class="icon"><span class="mif-dropdown-menu"></span></span>
        <span class="caption">' . $nframework->language['menus'] . '</span>
    </a></li>
    <li class="slide"><a href="/admin/nftables" >
        <span class="icon"><span class="mif-table"></span></span>
        <span class="caption">nftables</span>
    </a></li>
    <li class="slide"><a href="/admin/site" >
        <span class="icon"><span class="mif-cog"></span></span>
        <span class="caption">' . $nframework->language['site'] . '</span>
    </a></li>
	<li class="slide"><a href="/admin/site/auths.php" >
        <span class="icon"><span class="mif-user-secret"></span></span>
        <span class="caption">' . $nframework->language['auths'] . '</span>
    </a></li> 
    <li class="slide"><a href="/admin/site/security.php" >
        <span class="icon"><span class="mif-security"></span></span>
        <span class="caption">' . $nframework->language['security'] . '</span>
    </a></li> 
    <li class="slide"><a href="/admin/sat/" >
        <span class="icon"><span class="mif-key"></span></span>
        <span class="caption">SAT</span>
    </a></li>
    <li class="slide"><a href="/admin/theme/tconfig.php" >
        <span class="icon"><span class="mif-insert-template"></span></span>
        <span class="caption">' . $nframework->language['theme'] . '</span>
    </a></li>
    
    <li class="slide"><a href="/admin/users" >
        <span class="icon"><span class="mif-account"></span></span>
        <span class="caption">' . $nframework->language['users'] . '</span>
    </a></li>
    <li class="slide"><a href="/admin/usersgroups" >
        <span class="icon"><span class="mif-connected-users"></span></span>
        <span class="caption">' . $nframework->language['groups'] . '</span>
    </a></li>
    <li class="slide"><a href="/admin/filemanager/" >
        <span class="icon"><span class="mif-folder-tree"></span></span>
        <span class="caption">' . $nframework->language['filemanager'] . '</span>
    </a></li>
    <li class="slide"><a href="/admin/statistics/" >
        <span class="icon"><span class="mif-chart-bars"></span></span>
        <span class="caption">' . $nframework->language['statistics'] . '</span>
    </a></li>
    <li class="slide"><a href="/admin/update/" >
        <span class="icon"><span class="mif-cloud-download"></span></span>
        <span class="caption">' . $nframework->language['updates'] . '</span>
    </a></li>
    <li class="slide"><a href="/admin/errorlog" >
        <span class="icon"><span class="mif-bug"></span></span>
        <span class="caption">' . $nframework->language['logs'] . '</span>
    </a></li>
	';


if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/admins/common2.php')) {
    include $_SERVER['DOCUMENT_ROOT'] . '/admins/common2.php';
}


$sidebar = new Sidebar([
    //'color'=> '',
    'darkcolor' => 'black',
    'focuscolor' => 'grayMouse',
    'title' => 'Admin',
    'sidemenu' => $sidemenu
]);
if (!$nframework->isAjax()) {
    echo $sidebar;
    // En línea: no depende de cómo el sitio exponga la carpeta /admin.
    echo '<style>' . file_get_contents(__DIR__ . '/admin.css') . '</style>';
}
