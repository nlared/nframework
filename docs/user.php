<?php
require 'common.php';

$estado = [
	'isLoggedIn()' => $user->isLoggedIn(),
	'username' => $user->username,
	"in('admins')" => $user->in('admins'),
	"in('developers')" => $user->in('developers'),
	"can('reportes')" => $user->can('reportes'),
];
$filas = '';
foreach ($estado as $llamada => $valor) {
	$filas .= '<tr><td><code>$user->' . htmlspecialchars($llamada) . '</code></td><td><code>' . htmlspecialchars(var_export($valor, true)) . '</code></td></tr>';
}
?>
<div class="container">
	<?= docHeader('Usuarios y permisos', '<code>include.php</code> crea <code>$user</code> con el usuario de la sesión, o un invitado (<code>username = \'guest\'</code>) si no hay sesión. Los usuarios viven en la colección <code>users</code> y los grupos en <code>usersgroups</code>.') ?>

	<h3>Usted, en esta petición</h3>
	<table class="table striped compact"><tbody><?= $filas ?></tbody></table>

	<h3>Proteger una página</h3>
	<?= docCode(<<<'PHP'
require 'include.php';

$user->requireAuth();                  // sin sesión: guarda la URL y envía a /account/login
requireGroup('admins');                // sin el grupo: AJAX responde 403, si no redirige a /
requireGroup('admins', 'capturistas'); // basta con pertenecer a uno

// O antes del require: el invitado se envía a / sin cargar nada más.
$requiresession = true;
require 'include.php';
PHP) ?>

	<h3>Grupos y permisos</h3>
	<?= docCode(<<<'PHP'
if ($user->in('developers')) {           // ¿está en el grupo? (se consulta una vez por petición)
    echo 'Herramientas de desarrollo';
}

if ($user->can('reportes')) {            // permiso individual: campo permissions.reportes del usuario
    echo '<a href="/reportes/">Reportes</a>';
}
PHP) ?>
	<p>Un grupo es un documento <code>{name: 'admins', users: [ObjectId, ...]}</code> en <code>usersgroups</code>; se administra en <a href="/admin/usersgroups/">Admin → Grupos</a>. <code>can()</code> lee <code>permissions</code> del usuario y acepta <code>true</code>, <code>1</code>, <code>"on"</code>, <code>"true"</code>.</p>

	<h3>Leer y guardar datos del usuario</h3>
	<?= docCode(<<<'PHP'
echo htmlspecialchars($user->name);      // cualquier campo del documento
echo $user['email'];                     // también como arreglo
$user->telefono = '844 123 4567';        // se guarda de inmediato en MongoDB
unset($user->temporal);                  // $unset en MongoDB
// username y _id no se pueden cambiar así.
PHP) ?>

	<h3>Crear y autenticar</h3>
	<?= docCode(<<<'PHP'
// Crea el usuario con la contraseña cifrada (password_hash) y el username en minúsculas.
$nuevo = User::create(['username' => 'ana@ejemplo.com', 'name' => 'Ana', 'password' => $clave]);

// Verifica usuario (sin distinguir mayúsculas) y contraseña; null si no coinciden.
$valido = User::authenticate($_POST['usuario'] ?? null, $_POST['clave'] ?? null);

// Buscar por cualquier campo:
$otro = new User(['_id' => $id]);       // acepta texto o ObjectId
$otro = new User(['username' => 'ana@ejemplo.com']);
if (!empty($otro->_id)) { /* existe */ }
PHP) ?>

	<h3>Rutas de cuenta incluidas</h3>
	<p><code>/account/login</code>, <code>/account/signup</code> (con activación por correo), <code>/account/forgot</code> y <code>/account/reset</code>, <code>/account/twofa</code> (TOTP), <code>/account/profile</code>, <code>/account/sessions</code> y <code>/account/logout</code>. Ver <a href="routes.php">Rutas</a>.</p>
</div>
