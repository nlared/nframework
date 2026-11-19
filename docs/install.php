<?php
require 'common.php';
?>
<div class="container">
	<?= docHeader('Instalación', 'Requisitos, el instalador <code>install.sh</code> y sus dos modos: <b>sitio único</b> (el repositorio es el document root) o <b>multisitio</b> (un framework compartido y un document root por vhost con ligas simbólicas).') ?>

	<h3>Requisitos</h3>
	<ul>
		<li>PHP <b>8.3+</b> (8.4+ para correr los tests) con las extensiones <code>mongodb</code>, <code>intl</code>, <code>gd</code>, <code>curl</code>, <code>zip</code>, <code>mbstring</code>, <code>openssl</code>, <code>xml</code>, <code>pdo_sqlite</code> e <code>imagick</code>.</li>
		<li>Servidor MongoDB (replica set si usa transacciones). El instalador no lo instala.</li>
		<li>nginx (o Apache) con PHP-FPM.</li>
		<li>Opcional: Ghostscript (PDF → imagen), <code>pdftk</code>, LibreOffice/<code>unoconv</code> (Word/Excel → PDF).</li>
	</ul>

	<h3>¿Qué modo elegir?</h3>
	<table class="table striped">
		<thead><tr><th></th><th>Sitio único</th><th>Multisitio</th></tr></thead>
		<tbody>
			<tr><td>Dónde queda el framework</td><td>En el document root, junto a las páginas del sitio</td><td>En una carpeta propia (p. ej. <code>/opt/nframework</code>)</td></tr>
			<tr><td>Document root del vhost</td><td>El repositorio clonado</td><td>Una carpeta por sitio con ligas a <code>/admin</code>, <code>/nframework</code> y <code>/router.php</code></td></tr>
			<tr><td><code>includes/</code>, <code>vendor/</code>, <code>config.php</code></td><td>Del sitio</td><td>Compartidos: un bloque <code>case</code> por dominio en <code>config.php</code>, una base de datos por sitio</td></tr>
			<tr><td>Actualizar</td><td><code>git pull</code> en el sitio</td><td>Un <code>git pull</code> actualiza todos los sitios a la vez</td></tr>
			<tr><td>Recomendado para</td><td>Un servidor con un solo sitio, desarrollo del framework</td><td>Varios sitios en el mismo servidor; repositorios de sitio limpios</td></tr>
		</tbody>
	</table>

	<h3 id="sitio-unico">Sitio único</h3>
	<p>Clone el repositorio <b>en la carpeta que será el document root</b> y ejecute el instalador desde ahí:</p>
	<?= docCode(<<<'BASH'
sudo mkdir -p /var/www/www.ejemplo.com && sudo chown $USER:www-data /var/www/www.ejemplo.com
git clone https://github.com/nlared/nframework.git /var/www/www.ejemplo.com
cd /var/www/www.ejemplo.com
./install.sh --mode=single --host=www.ejemplo.com
BASH, 'bash') ?>
	<p>Las páginas del sitio (<code>index.php</code>, <code>crouter.php</code>, <code>common2.php</code>...) se agregan en la misma carpeta. Si el sitio tiene su propio repositorio, considere el modo multisitio: así no se mezcla con el del framework.</p>

	<h3 id="multisitio">Multisitio</h3>
	<p>El framework se clona una sola vez fuera de los document roots; por cada sitio se ejecuta el instalador con su dominio y su carpeta:</p>
	<?= docCode(<<<'BASH'
sudo git clone https://github.com/nlared/nframework.git /opt/nframework
sudo chown -R $USER:www-data /opt/nframework
cd /opt/nframework

./install.sh --mode=multi --host=www.ejemplo.com --docroot=/var/www/www.ejemplo.com
./install.sh --mode=multi --host=tienda.ejemplo.com --docroot=/var/www/tienda.ejemplo.com --db=tienda
BASH, 'bash') ?>
	<p>Cada ejecución deja el document root así (las ligas existentes que ya apuntan al framework se respetan; un archivo real con el mismo nombre nunca se sobrescribe):</p>
	<?= docCode(<<<'TXT'
/var/www/www.ejemplo.com/
├── admin       -> /opt/nframework/admin
├── nframework  -> /opt/nframework/nframework
├── router.php  -> /opt/nframework/router.php
├── img/
│   ├── bg-1.jpg -> /opt/nframework/img/bg-1.jpg   (fondos de login/registro; reemplazables)
│   └── bg-2.jpg -> /opt/nframework/img/bg-2.jpg
├── index.php   (de ejemplo, solo si no había uno)
└── crouter.php, common2.php, páginas del sitio...
TXT, 'text') ?>
	<p>Con <code>--docs</code> también liga <code>/docs</code> (y <code>/css/index.css</code>) para tener esta documentación en el sitio; no lo haga en producción. Las páginas del sitio hacen <code>require 'include.php';</code> sin ruta: el <code>include_path</code> de PHP apunta a <code>/opt/nframework/includes</code>, compartido por todos los sitios. <code>crouter.php</code>, <code>img/nf/</code> (logo, fotos de usuarios) y los archivos subidos son de cada sitio porque se resuelven con <code>$_SERVER['DOCUMENT_ROOT']</code>.</p>
	<p>nginx sigue las ligas simbólicas por omisión (<code>disable_symlinks off</code>). Si PHP-FPM usa <code>open_basedir</code>, incluya en él tanto el document root como la carpeta del framework.</p>

	<h3>Qué hace <code>install.sh</code></h3>
	<p>Cada paso se salta si ya está hecho, así que puede ejecutarse de nuevo para reparar o para agregar un sitio. Pregunta antes de usar <code>apt-get</code>, escribir en <code>/etc/php</code> o modificar <code>config.php</code> (<code>--yes</code> responde por usted; sin <code>--mode</code> y con <code>--yes</code> usa sitio único).</p>
	<ol>
		<li><b>Paquetes del sistema</b>: extensiones de PHP, Ghostscript, <code>git</code> y <code>unzip</code> con <code>apt-get</code> (<code>--php=8.4</code> elige la versión, <code>--no-apt</code> omite el paso).</li>
		<li><b>Composer</b>: descarga <code>includes/composer.phar</code> verificando la firma del instalador oficial.</li>
		<li><b>Dependencias</b>: <code>php composer.phar install --ignore-platform-reqs</code> en <code>includes/</code> (<code>--no-dev</code> sin PHPUnit/Pint).</li>
		<li><b>config.php</b>: si no existe lo crea con un bloque para <code>--host</code>; si existe y no tiene ese dominio, agrega el bloque antes del <code>default:</code> (deja una copia en <code>config.php.bak</code>). <code>--db=</code> y <code>--mongo=</code> eligen la base y la cadena de conexión; por omisión, el dominio sin puntos y <code>mongodb://127.0.0.1</code>.</li>
		<li><b>include_path</b>: agrega <code>includes/</code> en <code>/etc/php/X.Y/{cli,fpm,apache2}/conf.d/99-nframework.ini</code> y reinicia PHP-FPM.</li>
		<li><b>Document root</b> (multisitio): crea la carpeta, las ligas y un <code>index.php</code> de ejemplo.</li>
		<li><b>Base de datos y diagnóstico</b>: <code>php nframework/test.php --host=... --docroot=...</code> crea usuarios, grupos e índices y lista lo que falte.</li>
	</ol>
	<p>Al terminar imprime un bloque <code>server</code> de nginx con el <code>root</code> y el socket de PHP-FPM ya puestos.</p>
	<?= docCode(<<<'TXT'
--mode=single|multi   modo de instalación (si se omite, se pregunta)
--host=<dominio>      dominio del sitio: bloque de config.php y base de datos
--docroot=<dir>       multisitio: document root del vhost
--docs                multisitio: ligar también /docs
--db=<nombre>         base de MongoDB para un bloque nuevo de config.php
--mongo=<cadena>      cadena de conexión para un bloque nuevo de config.php
--php=<X.Y>           versión de PHP para apt
--no-apt              no instalar paquetes del sistema
--no-dev              composer install --no-dev
--yes, -y             no pedir confirmación
TXT, 'text') ?>

	<h3>Servidor web</h3>
	<p>El bloque que imprime el instalador incluye las reglas de <a href="security.php#servidor-web">Seguridad → Servidor web</a>: <code>includes/</code>, archivos ocultos, <code>vendor/</code>, logs y <code>composer.*</code> nunca se sirven, y todo lo que no sea un archivo existente va a <code>/router.php</code>.</p>
	<?= docCode(<<<'NGINX'
server {
    listen 80;
    server_name www.ejemplo.com;
    root /var/www/www.ejemplo.com;
    index index.php index.html;

    location ^~ /.well-known/ { try_files $uri /router.php$is_args$args; }
    location ~ /\.                { return 404; }
    location ~ ^/includes(/|$)   { return 404; }
    location ~ /vendor/           { return 404; }
    location ~* (\.log|\.sh|/composer\.[^/]*|/phpunit\.xml[^/]*)$ { return 404; }

    location / {
        try_files $uri $uri/ /router.php$is_args$args;
    }
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }
}
NGINX, 'nginx') ?>
	<?= docCode(<<<'BASH'
sudo nano /etc/nginx/sites-available/www.ejemplo.com      # pegue el bloque
sudo ln -s /etc/nginx/sites-available/www.ejemplo.com /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo certbot --nginx -d www.ejemplo.com                   # HTTPS (opcional)
BASH, 'bash') ?>
	<p>Configure también el <code>error_log</code> de PHP fuera del document root.</p>

	<h3>Después de instalar</h3>
	<ul>
		<li>Abra el sitio e inicie sesión con el usuario administrador que reportó <code>test.php</code>; cambie su contraseña.</li>
		<li>Abra <code>/nframework/test.php</code> en el navegador: PHP-FPM puede cargar extensiones distintas que el CLI.</li>
		<li>Ajuste en Admin → Sitio / Seguridad / SAT lo que no va en <code>config.php</code> (SMTP, logo, reglas).</li>
		<li>Para revisar un sitio multisitio en CLI: <code>php /opt/nframework/nframework/test.php --host=www.ejemplo.com --docroot=/var/www/www.ejemplo.com</code>.</li>
	</ul>

	<h3>Actualizar</h3>
	<?= docCode(<<<'BASH'
cd /opt/nframework            # o el document root en sitio único
git pull
cd includes && php composer.phar install --ignore-platform-reqs && cd ..
php nframework/test.php --host=www.ejemplo.com --docroot=/var/www/www.ejemplo.com   # índices nuevos, por sitio
BASH, 'bash') ?>
	<p>En multisitio la actualización aplica a todos los sitios a la vez; ejecute <code>test.php</code> para cada dominio.</p>

	<h3>Instalación manual</h3>
	<p>Sin Debian/Ubuntu, o para hacer cada paso a mano:</p>
	<?= docCode(<<<'BASH'
# 1. Framework (sitio único: en el document root; multisitio: en /opt/nframework)
git clone https://github.com/nlared/nframework.git /opt/nframework && cd /opt/nframework

# 2. Composer y dependencias (viven en includes/)
cd includes
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php && php -r "unlink('composer-setup.php');"
php composer.phar install --ignore-platform-reqs
cd ..

# 3. Configuración: un bloque case por dominio (todas las opciones en config.example.php)
cp includes/config.example.php includes/config.php && chmod 640 includes/config.php

# 4. include_path de PHP (CLI y FPM)
echo 'include_path = ".:/opt/nframework/includes:/usr/share/php"' | sudo tee /etc/php/8.3/fpm/conf.d/99-nframework.ini
sudo cp /etc/php/8.3/fpm/conf.d/99-nframework.ini /etc/php/8.3/cli/conf.d/
sudo systemctl restart php8.3-fpm

# 5. Solo multisitio: document root con ligas
mkdir -p /var/www/www.ejemplo.com/img
ln -s /opt/nframework/admin /opt/nframework/nframework /opt/nframework/router.php /var/www/www.ejemplo.com/
ln -s /opt/nframework/img/bg-1.jpg /opt/nframework/img/bg-2.jpg /var/www/www.ejemplo.com/img/

# 6. Base de datos y diagnóstico
php nframework/test.php --host=www.ejemplo.com --docroot=/var/www/www.ejemplo.com
BASH, 'bash') ?>
</div>
