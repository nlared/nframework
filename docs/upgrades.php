<?php
require 'common.php';

$migraciones = nfUpgrader::discover(nfUpgrader::defaultDirs(''));
?>
<div class="container">
	<?= docHeader('Actualizaciones y migraciones', 'Cuando el framework cambia, cada sitio necesita poner al día su base de datos y, a veces, su código. <code>nfUpgrader</code> lo hace con migraciones versionadas: archivos en <code>upgrades/</code> que se aplican una sola vez por sitio, en orden, y quedan registrados en la colección <code>nfmigrations</code>.') ?>

	<h3>Versión</h3>
	<p>La versión del framework está en el archivo <code>VERSION</code> de su raíz (año.número, igual que las etiquetas de git): esta copia es la <b><?= htmlspecialchars(nfUpgrader::frameworkVersion()) ?></b>. Cada sitio registra en <code>nfmigrations</code> la versión con la que se puso al día; Admin → Actualizar → Migraciones muestra las dos y lo pendiente.</p>

	<h3>Cómo se aplican</h3>
	<ul>
		<li><b>Automático:</b> Admin → Actualizar → Pull o Forzar actualización ejecutan al terminar las migraciones pendientes del sitio (casilla activada por omisión), en un proceso nuevo para usar el código recién descargado.</li>
		<li><b>Aviso:</b> mientras haya migraciones pendientes, todas las páginas de Admin lo avisan. En multisitio (un framework compartido) cada sitio tiene su base: al actualizar el framework desde un sitio, los demás muestran el aviso hasta que se apliquen.</li>
		<li><b>Terminal o cron</b>, por ejemplo para todos los sitios después de un despliegue:</li>
	</ul>
	<?= docCode(<<<'SH'
php /var/www/nframework/nframework/upgrade.php --host=www.ejemplo.com                       # aplica las pendientes
php /var/www/nframework/nframework/upgrade.php --host=www.otro.com --docroot=/var/www/otro   # multisitio
php /var/www/nframework/nframework/upgrade.php --host=www.ejemplo.com --dry-run            # solo lista
php /var/www/nframework/nframework/upgrade.php --host=www.ejemplo.com --code               # revisa el código del sitio
php /var/www/nframework/nframework/upgrade.php --host=www.ejemplo.com --apply-code         # aplica las correcciones automáticas
SH, 'bash') ?>
	<p>Código de salida: 0 = al día, 1 = error de uso o conexión, 2 = una migración falló. <code>nframework/test.php</code> (instalador) también las aplica.</p>

	<h3>Escribir una migración</h3>
	<p>Un archivo <code>upgrades/AAAAMMDD_nombre.php</code> que devuelve un arreglo. Las del framework van en su carpeta <code>upgrades/</code>; un sitio puede tener las suyas en <code>&lt;document root&gt;/upgrades/</code> (o en <code>$config['upgrades_dir']</code>), que corren después de las del framework.</p>
	<?= docCode(<<<'PHP'
<?php
// upgrades/20261201_clientes_rfc.php
return [
    'version' => '2026.2',                       // versión del framework (o del sitio) que la trae
    'description' => 'Clientes: RFC en mayúsculas e índice único',
    // Base del sitio, función para anotar en el registro y contexto (host, docroot, config, client).
    // Debe poder ejecutarse otra vez sin daño: si falla, la próxima ejecución empieza de nuevo por ella.
    'up' => function (MongoDB\Database $db, callable $log, array $ctx): void {
        $n = $db->clientes->updateMany(['rfc' => ['$type' => 'string']], [['$set' => ['rfc' => ['$toUpper' => '$rfc']]]])->getModifiedCount();
        $db->clientes->createIndex(['rfc' => 1], ['unique' => true, 'sparse' => true]);
        $log("$n cliente(s) actualizados");
    },
    // Opcional: reglas para el código de los sitios. Sin 'replace' solo se informan; con 'replace' se
    // corrigen desde Admin (o --apply-code) con respaldo en .nfupgrade/<fecha>/.
    'code' => [
        ['description' => 'clienteViejo() se llama ahora cliente()', 'pattern' => '/\bclienteViejo\(/', 'replace' => 'cliente('],
        ['description' => 'Revise los usos de $cliente["rfc"] en minúsculas', 'pattern' => '/strtolower\(\$cliente\[.rfc.\]\)/'],
    ],
];
PHP) ?>
	<ul>
		<li>Se ejecutan en orden de nombre; la primera que falla detiene las siguientes y se reintenta en la próxima ejecución.</li>
		<li>Un candado en <code>nfmigrations</code> impide que dos procesos migren el mismo sitio a la vez.</li>
		<li>La revisión de código recorre las páginas del sitio sin entrar a <code>vendor/</code>, a las ligas del multisitio (<code>admin/</code>, <code>nframework/</code>) ni, en instalación simple, a las carpetas del framework.</li>
	</ul>

	<h3>Al publicar una versión del framework</h3>
	<ol>
		<li>Agregue las migraciones de datos e índices en <code>upgrades/</code> y anote en <code>UPGRADING.md</code> lo que cambia para los sitios, con su regla de código si se puede detectar.</li>
		<li>Suba el número de <code>VERSION</code> y etiquete el commit con el mismo número (<code>git tag 2026.2</code>).</li>
	</ol>

	<h3>Migraciones de esta versión</h3>
	<table class="table compact striped">
		<thead><tr><th>Id</th><th>Versión</th><th>Descripción</th><th>Base de datos</th><th>Reglas de código</th></tr></thead>
		<tbody>
			<?php foreach ($migraciones as $mg) { ?>
				<tr>
					<td><code><?= htmlspecialchars($mg['id']) ?></code></td>
					<td><?= htmlspecialchars($mg['version']) ?></td>
					<td><?= htmlspecialchars($mg['description']) ?></td>
					<td><?= $mg['up'] ? 'sí' : '' ?></td>
					<td><?= count($mg['code']) ?: '' ?></td>
				</tr>
			<?php } ?>
		</tbody>
	</table>
</div>
