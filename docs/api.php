<?php
require 'common.php';
?>
<div class="container">
	<?= docHeader('API REST', '<code>nfApi</code> declara rutas JSON versionadas bajo <code>/api/v1/…</code> en el <code>crouter.php</code> del sitio, con autenticación por token (<code>nfApiToken</code>), permisos por <em>scope</em>, límite de peticiones y respuestas uniformes. Los usuarios crean sus tokens en <a href="/account/apitokens">/account/apitokens</a>.') ?>

	<h3>1. Declarar las rutas</h3>
	<?= docCode(<<<'PHP'
<?php
// crouter.php (raíz del sitio)
$api = new nfApi('v1');                                    // rutas bajo /api/v1
$api->scope('notas:read', 'Leer sus notas')               // descripción que ve el usuario al crear el token
    ->scope('notas:write', 'Crear y borrar sus notas');

$api->get('/notas', function (array $p) {
    global $m, $config, $user;                             // $user = dueño del token
    $pg = nfApi::pagination(20, 100);                      // ?page=2&per_page=50
    $filter = ['owner' => toMongoId($user->_id)];
    $notas = $m->{$config['sitedb']}->notas;
    return nfApi::paginated(
        $notas->find($filter, ['skip' => $pg['skip'], 'limit' => $pg['limit'], 'sort' => ['_id' => -1]]),
        $notas->countDocuments($filter),
        $pg
    );
}, ['scope' => 'notas:read']);

$api->get('/notas/[s:id]', function (array $p) {
    global $m, $config, $user;
    if (!isValidObjectId($p['id'])) {
        throw new nfApiException(400, 'invalid_id', 'Id inválido.');
    }
    $nota = $m->{$config['sitedb']}->notas->findOne(['_id' => toMongoId($p['id']), 'owner' => toMongoId($user->_id)]);
    if (!$nota) {
        throw new nfApiException(404, 'not_found', 'La nota no existe.');
    }
    return $nota;                                          // 200 {"data": {...}}
}, ['scope' => 'notas:read']);

$api->post('/notas', function () {
    global $m, $config, $user;
    $in = nfApi::input();                                  // JSON o formulario
    $titulo = is_string($in['titulo'] ?? null) ? trim($in['titulo']) : '';   // forzar tipos: un JSON puede traer {"$ne": ...}
    if ($titulo === '') {
        throw new nfApiException(422, 'validation_failed', 'Revise los campos.', ['titulo' => 'Requerido']);
    }
    $doc = ['titulo' => $titulo, 'owner' => toMongoId($user->_id), 'fecha' => new MongoDB\BSON\UTCDateTime()];
    $doc['_id'] = $m->{$config['sitedb']}->notas->insertOne($doc)->getInsertedId();
    return new nfApiResult($doc, 201, [], ['Location' => '/api/v1/notas/' . $doc['_id']]);
}, ['scope' => 'notas:write']);

$api->delete('/notas/[s:id]', function (array $p) {
    // ... borrar ...
    return null;                                           // 204 sin cuerpo
}, ['scope' => 'notas:write', 'ratelimit' => [30, 60]]);   // límite propio de esta ruta

$api->get('/estado', fn () => ['ok' => true, 'version' => '1'], ['auth' => false]);   // pública
PHP) ?>
	<p>Opciones de cada ruta: <code>scope</code> (permiso que debe tener el token; sin él basta cualquier token válido), <code>auth</code> (<code>false</code> = pública) y <code>ratelimit</code> (<code>[peticiones, segundos]</code> por token; por defecto <code>$config['api_ratelimit']</code> o 600 por minuto). Los parámetros de ruta usan la sintaxis de <a href="routes.php">Mezon</a>.</p>

	<h3>2. Respuestas</h3>
	<table class="table compact">
		<thead><tr><th>El handler…</th><th>Respuesta</th></tr></thead>
		<tbody>
			<tr><td>devuelve datos (arreglo, documento, escalar)</td><td><code>200 {"data": …}</code></td></tr>
			<tr><td>devuelve <code>nfApiResult($datos, $estado, $meta, $cabeceras)</code></td><td>ese estado; <code>meta</code> si no está vacío</td></tr>
			<tr><td>devuelve <code>nfApi::paginated(...)</code></td><td><code>200 {"data": […], "meta": {"page", "per_page", "total", "pages"}}</code></td></tr>
			<tr><td>devuelve <code>null</code></td><td><code>204</code> sin cuerpo</td></tr>
			<tr><td>lanza <code>nfApiException($estado, $codigo, $mensaje, $detalles)</code></td><td><code>{"error": {"code", "message", "details"}}</code></td></tr>
			<tr><td>lanza otra excepción</td><td><code>500 internal_error</code> (el detalle va al log; en modo desarrollador, también en el mensaje)</td></tr>
		</tbody>
	</table>
	<p><code>ObjectId</code> sale como texto, <code>UTCDateTime</code> como ISO 8601 en UTC (<code>2026-11-27T18:30:00.000Z</code>) y los documentos de MongoDB como objetos (<code>nfApi::normalize()</code>). Una ruta <code>/api/*</code> que no existe responde <code>404 not_found</code> en el mismo formato.</p>
	<?= docCode(<<<'JSON'
// 401: falta el token o no es válido
{"error": {"code": "invalid_token", "message": "El token no existe, caducó o fue revocado."}}
// 403: el token no tiene el scope
{"error": {"code": "insufficient_scope", "message": "El token no tiene el permiso notas:write.", "details": {"required": "notas:write"}}}
// 429: límite de peticiones (cabecera Retry-After)
{"error": {"code": "rate_limited", "message": "Límite de 600 peticiones cada 60 s."}}
JSON, 'javascript') ?>

	<h3>3. Llamarla</h3>
	<?= docCode(<<<'BASH'
curl -H "Authorization: Bearer nfat_…" https://www.ejemplo.com/api/v1/notas?per_page=10
curl -X POST -H "Authorization: Bearer nfat_…" -H "Content-Type: application/json" \
     -d '{"titulo": "Comprar papel"}' https://www.ejemplo.com/api/v1/notas
BASH, 'bash') ?>

	<h3>Tokens</h3>
	<p>Cada usuario crea los suyos en <a href="/account/apitokens">/account/apitokens</a> (aparece en Mi cuenta cuando el sitio declara rutas): nombre, scopes de los que declaró el <code>crouter.php</code> (o todos), y caducidad de 30, 90 o 365 días o sin caducidad. El token (<code>nfat_</code> + 48 caracteres) se muestra una sola vez; en la colección <code>nfapitokens</code> solo queda su sha256, los últimos 4 caracteres, el último uso y la IP. Revocar es inmediato.</p>
	<p><code>$config['api_tokens_group']</code> limita quién puede crearlos (un grupo; vacío = cualquier usuario con sesión). Desde código:</p>
	<?= docCode(<<<'PHP'
$nuevo = nfApiToken::create($usuario->_id, 'Integración ERP', ['notas:read'], 90);   // ['token' => 'nfat_…', 'doc' => [...]]
nfApiToken::forUser($usuario->_id);       // sin hash
nfApiToken::revoke($usuario->_id);        // todos los del usuario (p. ej. al darlo de baja)
PHP) ?>

	<h3>Seguridad</h3>
	<ul>
		<li>Solo se acepta <code>Authorization: Bearer</code>; la cookie de sesión no autentica la API, así que no hay CSRF. La sesión de la petición se descarta sin guardarse.</li>
		<li>El token actúa con los permisos de su dueño: dentro del handler filtre por <code>$user</code> y use <code>nfApi::requireGroup('admins')</code> para operaciones privilegiadas. No use <code>$user->requireAuth()</code> ni <code>requireGroup()</code> del framework: redirigen en lugar de responder JSON.</li>
		<li>Un usuario deshabilitado deja de poder usar sus tokens. Más de 20 tokens inválidos desde una IP en 15 minutos la bloquean (429).</li>
		<li>Las peticiones de navegador desde otro dominio (CORS) no están contempladas: un POST con <code>Origin</code> ajeno recibe 403 del filtro CSRF por origen. La API está pensada para servidores y aplicaciones.</li>
		<li>Fuerce los tipos de lo que llega en <code>nfApi::input()</code> y en los parámetros, igual que con <code>$_POST</code> (ver <a href="security.php">Seguridad</a>).</li>
	</ul>
</div>
