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
	<h3>Mi cuenta (/account/)</h3>
	<p>Enlace «Mi cuenta» del menú de usuario. Reúne <code>/account/profile</code> (nombre en <code>name</code> y foto: se recorta a 512×512 y se guarda como <code>{_id}.png</code> en <code>User::picturesDir()</code>, analizada con <a href="clamav.php">ClamAV</a> si está habilitado), <code>/account/password</code> (pide la actual salvo cuentas de OAuth sin contraseña, aplica <code>NF_PASSWORD_MIN_LENGTH</code> y <code>$config['passwordmask']</code>, y cierra las demás sesiones), <code>/account/security</code> y <code>/account/sessions</code> (dispositivo, IP, inicio y último uso; cerrar una o todas las demás). El dispositivo se guarda en <code>users.sessions_info.{id}</code> al registrar la sesión; el id real nunca llega al navegador (<code>User::sessionToken()</code>). Las plantillas son <code>account.html</code>, <code>profile.html</code>, <code>password.html</code>, <code>sessions.html</code>, <code>security.html</code> y <code>recoverykey.html</code> de <code>nframework/templates/panda</code>.</p>

	<h3>Verificación en dos pasos (2FA)</h3>
	<p>El usuario la activa en <code>/account/twofa-setup</code>: escanea un QR con una app de autenticación y escribe un código; solo entonces se guarda su secreto en <code>twofa_secret</code>. Con secreto, <code>/account/login</code> y los inicios por OAuth piden el código en <code>/account/twofa</code>; un inicio de sesión propio del sitio debe llamar <code>nfTwofaChallenge($_SESSION['user'])</code> después de asignar la sesión.</p>
	<p><code>/account/security</code> (enlace «Seguridad» del menú de usuario) muestra el estado, permite activarla, desactivarla si no es obligatoria y generar una <b>llave de recuperación</b> (también se genera al activarla). La llave se muestra una sola vez y se guarda solo su hash (<code>twofa_recovery</code>, ver <code>User::recoveryKeyGenerate()</code> / <code>recoveryKeyMatches()</code>); en <code>/account/twofa</code> permite entrar una vez sin el código y desactiva el 2FA para configurarlo con el teléfono nuevo. Regenerar o desactivar piden un código vigente de la app.</p>
	<p>Se puede exigir a todos (Admin → Sitio, <code>$config['twofa_required']</code>) o a un usuario (Admin → Usuarios, campo <code>twofa_required</code>; ahí también se restablece si pierde el teléfono). Mientras <code>$user->twofaPending()</code> sea <code>true</code>, <code>include.php</code> lo envía a <code>/account/twofa-setup</code> desde cualquier página (las peticiones AJAX reciben 403); solo puede abrir esa página, salir y las imágenes (<code>User::twofaExemptPath()</code>).</p>
</div>
