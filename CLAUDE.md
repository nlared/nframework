# CLAUDE.md — nframework

Contexto para trabajar en **nframework** y en **sitios construidos sobre él**. Un proyecto basado en
nframework puede importar este archivo desde su propio `CLAUDE.md`:

```markdown
@/var/www/dev.nlared.local/CLAUDE.md
```

nframework es un framework PHP (sin namespaces, basado en variables globales) para aplicaciones web con
MongoDB, componentes de formulario Metro UI 5, formularios AJAX, DataTables, usuarios con 2FA, panel de
administración, exportaciones Excel/Word/PDF, colas de trabajo e integración con el SAT (CFDI / e.firma).

**La referencia viva es `docs/`** (abrir `/docs/` en el sitio de desarrollo): cada página muestra el
resultado junto al código. Antes de usar un componente que no aparece aquí, lea su página en `docs/` y la
clase en `includes/`.

## Dos tipos de proyecto

1. **El framework** (este repositorio, `github.com/nlared/nframework`): `includes/`, `nframework/`,
   `admin/`, `docs/`, `sat/`, `router.php`.
2. **Un sitio basado en nframework**: otro repositorio/document root con sus propias páginas `.php`. Usa el
   núcleo a través del `include_path` de PHP (que apunta a `includes/` del framework), así que sus páginas
   hacen `require 'include.php';` sin ruta. Suele tener su propio `common2.php` (sesión obligatoria, menú,
   `usecommon`, estilos) que todas sus páginas incluyen, y `crouter.php` para rutas propias.
   **En un sitio no modifique archivos del framework**: el código del sitio va en el sitio; los cambios
   genéricos se hacen en este repositorio.

## Comandos

```bash
cd includes && composer install      # dependencias de ejecución (viven en includes/, no en la raíz)
./run-tests.sh [unit|feature|coverage]
./includes/vendor/bin/phpunit tests/Unit/FunctionsTest.php
./includes/vendor/bin/pint            # estilo de código
php nframework/test.php --host=www.ejemplo.com   # instala/repara BD (usuarios, grupos, índices) y diagnostica
HTTP_HOST=www.ejemplo.com php includes/job_worker.php <cola> 5   # worker de la cola de trabajos
```

- PHP 8.3+ en ejecución; los tests (PHPUnit 13) requieren PHP 8.4+. No necesitan MongoDB.
- El `composer.json` de la raíz es solo el paquete Laravel (`src/`, `stubs/`: comandos
  `make:databindingajax` y `make:datatableajax`); no tiene nada que ver con las dependencias del framework.
- En CLI la variable de entorno `HTTP_HOST` elige el bloque de `includes/config.php`; sin ella se usa `localhost`.

## Anatomía de una página

```php
<?php
// Opcionales, ANTES del require (después no tienen efecto):
// $requiresession = true;          invitado → redirige a / y termina
// $nfshutdowndisable = true;       sin plantilla, sin JSON automático, sin estadísticas (descargas/streams)
// $nfjavaobfuscatedisable = true;  no captura la salida (va junto con la anterior)
require 'include.php';

$nframework->usecommon = true;            // envolver en la plantilla HTML completa (head, Metro UI, JS)
$nframework->metas['title'] = 'Título';
?>
<div class="container">...</div>
```

Variables globales tras `include.php`:

| Variable | Qué es |
|---|---|
| `$m` | `MongoDB\Client`. La base del sitio es **siempre** `$m->{$config['sitedb']}` (nunca un nombre fijo). |
| `$config` | `class_config` (ArrayAccess): `includes/config.php` + documento `{_id:'site'}` de `configs` (gana MongoDB). |
| `$user` | `User` actual; el invitado tiene `username = 'guest'`. |
| `$nframework` | `class_nframework`: CSS/JS (`csss`, `jss` ordenados por clave `'001'`…`'200'`), `metas`, idioma, exportaciones, caché HTTP. |
| `$javas` | Acumula JS para el final de la página. |
| `$ip` | IP real del cliente (respeta `trusted_proxies`). |
| `$result` | Respuesta de una petición AJAX. |

Dentro de funciones/closures (rutas, jobs) declare `global $m, $config, $user, $nframework, $javas, $result;`.

La salida se captura en un buffer; `nfshutdown()` (registrada al final de `include.php`) arma la respuesta:
plantilla si `usecommon`, `json_encode($result)` si es AJAX, empaqueta/ofusca el JS de `$javas` (desactivar
con `$noobfuscate = true`), envía cabeceras de seguridad y guarda estadísticas. Los `<script>` inline se
extraen y se mueven al final.

## Contrato AJAX

`$nframework->isAjax()` es `true` cuando llega `X-Requested-With: XMLHttpRequest`. La misma página sirve
el HTML (GET normal) y responde al AJAX:

```php
if ($nframework->isAjax()) {
    if (!csrfValidate()) {
        $result = ['error' => 'Formulario caducado, recargue la página.'];
    } elseif (($_POST['op'] ?? '') === 'guardar') {
        $result = ['error' => $dataset->save(), 'ids' => ['estado' => 'Guardado']];
    }
    return;   // NO hacer echo/exit: el framework envía json_encode($result)
}
?>
<?= secureform() ?>                      <!-- AJAX a la misma página + token CSRF; secureform($url) = POST normal -->
    <?= $campo ?>
    <button class="button primary secureop" value="guardar">Guardar</button>   <!-- value → $_POST['op'] -->
</form>
```

Claves de `$result` que entiende el navegador (`nAjaxFormDone`): `error` (`false` = aviso de éxito,
string = diálogo de error), `ids` (`['idElemento' => 'html']`), `js` (código a ejecutar).

## Componentes y databinding

Constructor con arreglo de opciones; se imprimen con `echo`/`<?= ?>` (`__toString`). Opciones comunes:
`name`, `caption`, `value`, `required`, `pattern`, `validate`, `prepend`, `dataset` + `field`.

- Texto/números: `inputText`, `inputNumber`, `inputSpinner`, `inputRating`, `inputColor`, `textArea`,
  `inputMCE` (TinyMCE), `inputHidden`, `label`.
- Fechas: `inputDate`, `inputTime`, `inputDateTime` (se guardan como `UTCDateTime`).
- Opciones: `Select` (`'options' => ['valor' => 'Texto']`, o `'ajax' => [db, collection, columns, label, value, pipeline]`
  para búsqueda en servidor), `SelectIcon`, `inputCheckBox`, `inputCheckBoxs`, `inputRadios`.
- Archivos: `inputFile`, `inputFiles` (`dir`, `path`, `accept`, `countlimit`, `sizelimit`, `preview`,
  `delete`, `download`, `extension` + `onupload`/`ondelete`, `allowext` para aceptar extensiones
  protegidas como `py`/`sh`; las de PHP solo fuera del document root). La carpeta destino la decide el servidor.
- e.firma/CSD del SAT: `Efirma` (`tipo` `fiel`|`csd`|`any`, `storage` `client`|`server`). En el navegador
  `nfEfirma[id].sign(cadena)`; con llave en servidor solo firma PHP: `Efirma::sign()` / `Efirma::credential()`
  (requiere `efirma_key` en `config.php`). Ver `docs/efirma.php`.
- Otros: `Signature_pad`, `mapmarker`, `inputaddress`, `Dialog`, `BreadCrumbs`, `Sidebar`, `TreeView`, `Icon`,
  `ThemeSwitcher`, `QRReader`, `xspreadsheet`.

**Databinding** — `dataset` representa un documento:

```php
$dataset = new dataset([
    'collection' => $m->{$config['sitedb']}->productos,
    '_id' => $_GET['_id'],   // ObjectId en texto; 'simpleid' => true para _id de texto libre (RFC, folio)
    'nameprefix' => 'data',  // los campos viajan como data[campo]
    // 'historic' => true    // guarda versiones previas en nfversions
]);
$nombre = new inputText(['dataset' => &$dataset, 'field' => 'nombre', 'required' => true]);
$sub    = new inputText(['dataset' => &$dataset, 'field' => 'direccion.calle']);  // notación con punto
$errores = $dataset->save();   // valida y convierte tipos; false = sin errores, string = errores
$dataset->estado = 'ok';       // asignar una propiedad hace updateOne con upsert inmediato
```

Patrón habitual para "nuevo registro": si no hay `_id` válido en la URL, redirigir a `?_id=<new ObjectId>`
antes del `require`. Arreglos dentro del documento: `embededArray` (ver `docs/arraylist.php`).
`dataset->mongo_session` permite transacciones.

**Tablas** — `Table` (DataTables):

```php
$t = new Table(['header' => '<th>A</th><th>B</th>', 'order' => [[1, 'desc']]]);
$t->data[] = [htmlspecialchars($a), htmlspecialchars($b)];   // celdas son HTML: escapar
echo $t;

$t = new Table();
$t->Ajax([   // paginado/búsqueda/orden en MongoDB vía /nframework/datatable.php; la consulta vive en sesión
    'id' => 'tabla1', 'db' => $config['sitedb'], 'collection' => 'productos',
    'header' => '<th>Nombre</th><th></th>', 'columns' => ['nombre', '_id'],
    'pipeline' => [['$match' => ['owner' => $user->_id]]],
    'columnDefs' => ['1' => ['render' => "'<a href=\"editar.php?_id='+data+'\">Editar</a>'"]],
]);
// JS: datatables['tabla1'].clearPipeline(); datatables['tabla1'].draw();
```

`TablefIA` (`docs/tablefia.php`): el usuario pregunta en lenguaje natural y Claude genera el pipeline (requiere
`anthropic_api_key`). Declare `collections` (con `match` obligatorio y `hidden`) y `relations` (únicos `$lookup`
permitidos); el servidor valida el pipeline y guarda cada consulta en `nftablesfia` por `uso` para reutilizarla.

**JavaScript desde PHP**: `$javas->addjs($codigo, 'general'|'ready'|'resize'|'scroll')`. Pase datos con
`json_encode()`, nunca interpolando strings. `notify($titulo, $texto)` y `speak($texto)` son atajos.
En el navegador están jQuery, Metro UI 5 (`Metro.*`), SweetAlert2 (`Swal.fire`) y `toast()`.

## Usuarios y permisos

```php
$user->requireAuth();                   // sin sesión → guarda URL y va a /account/login
requireGroup('admins', 'capturistas');  // basta uno; AJAX → 403, si no redirige a /
$user->isLoggedIn(); $user->in('developers'); $user->can('reportes');   // permissions.reportes
$user->telefono = '...';                // se guarda de inmediato; unset($user->campo) hace $unset
User::create([...]); User::authenticate($usuario, $clave);
new User(['_id' => $id]); new User(['username' => $correo]);
```

Colecciones: `users`, `usersgroups` (`{name, users: [ObjectId]}`), `sessions` (handler de sesión en MongoDB).
Grupos con significado especial: `admins` (panel `/admin/`, nftables), `developers` (activa `$developermode`:
tabla de errores en pantalla en lugar de solo `errorlog`).

## Rutas

Orden: archivo `.php` existente → rutas integradas (`nframework/router.php`: `/account/*`, OAuth,
`/images/*`, `/sitemap.xml`, `/robots.txt`, `/sw.js`, `/nftables/*`…) → páginas de Admin → Páginas (paths que
empiezan con `_` son fragmentos: `_header`, `_footer`, `_home`, `_404`) → `crouter.php` del sitio → `_404`.

```php
// crouter.php (raíz del sitio). Parámetros Mezon: [i:] entero, [s:] texto sin '/', [a:], [date:], [fp:], [il:]
$router->addRoute('/api/notas/[s:id]', function (string $route, array $p) {
    global $m, $config, $user;
    $user->requireAuth();
    if (!isValidObjectId($p['id'])) { http_response_code(400); return; }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($m->{$config['sitedb']}->notas->findOne(['_id' => toMongoId($p['id']), 'owner' => $user->_id]));
}, 'GET');   // o ['GET', 'POST']
```

Dentro de rutas está además `$twig` (plantillas de `nframework/templates`). **Se define en
`nframework/router.php`**: no existe en páginas `.php` servidas directamente ni en `job_worker.php`.

## Seguridad — reglas obligatorias

- Todo POST/PUT/PATCH/DELETE con `Origin`/`Referer` de otro dominio recibe 403 automáticamente
  (excepciones: `csrf_trusted_origins`, `csrf_exempt_paths`). Aun así, los formularios usan `secureform()`
  y el handler llama `csrfValidate()`.
- **Inyección NoSQL**: PHP convierte `?x[$ne]=1` en arreglo. Fuerce tipos de todo `$_GET`/`$_POST`
  (`is_string(...) ? ... : ''`, `(int)`), valide ids con `isValidObjectId()` y convierta con
  `toMongoId()`/`toMongoIds()`. Para filtros complejos del cliente: `nfSanitizeMongoQuery()`. Escape regex
  con `preg_quote()`.
- Escape la salida: `htmlspecialchars()` en HTML, `urlencode()` en URLs, `json_encode()` en JS. Las celdas
  de `Table` y el contenido de `Dialog` son HTML crudo.
- Redirecciones con datos del usuario: `header('Location: ' . nfSafeRedirect($url));`.
- Rate limit: `nflogAttempt('clave:' . $ip, 5, 900)` / `nflogReset(...)`.
- Contraseñas: `nfPasswordHash()` / `nfPasswordVerify()` (acepta hashes sha512 antiguos). Mínimo `NF_PASSWORD_MIN_LENGTH`.
- Rutas de archivo desde la petición: `basename()` y un directorio fijo. Use `$nframework->downloadfrom()`
  o `serveFile()` para entregar archivos.
- URLs absolutas del sitio (correos, sitemap): `nfSiteHost()` / `$config['url']`, nunca `$_SERVER['HTTP_HOST']`.
- No forzar `$developermode = true` en producción.

## Otros servicios del framework

- **Correo**: `nfMailer()` devuelve un PHPMailer configurado con `$config['smtp']` (host, port, auth, username,
  password, secure `ssl`|`tls`, fromemail, fromname); disponible en páginas, rutas y jobs.
- **Caché local**: `nfCacheRemember($clave, $ttl, fn() => ...)`, `nfCacheForget($clave|null)`, `nfCacheTtl()`.
  Config, reglas, páginas y menús ya se cachean; un POST de un admin a `/admin/` vacía todo.
- **Caché HTTP**: `$nframework->serveFile($ruta, $mime, $maxAge)`, `serveContent($str, $mime)`, o
  `etag` + `lastmodified` + `testcache()`.
- **Exportaciones**: `$nframework->excelOut($spreadsheet, 'nombre')`, `excelOutPdf()`, `wordOut()`,
  `wordOutPdf()`, `wordTemplateOutPdf($templateProcessor, 'nombre')`.
- **Colas**: clase `class.<Nombre>.php` en el `include_path` que extiende `Job` (`$queue`, `$maxAttempts`,
  `$delay`, `handle()`); `MiJob::dispatch($payload)`. Una excepción en `handle()` reintenta. Colección `jobs`.
- **Procesos largos**: `bgprocess(['id', 'cmd', 'logfile'])` → `start()`, `isRunning()`, `status()`, `stop()`, `renderDashboard()`.
- **Menús y páginas del CMS**: `nfMetroMenu('_nav')`, `nfPage('_header')`, `renderEmbeddedFunctions($html)`.
- **i18n**: `$nframework->lang` (`es-MX`/`en-US`), `$nframework->language[...]` (textos en `includes/i18n/<lang>.php`).
- **Tablas administrables**: Admin → nftables define colecciones editables en `/nftables/<colección>/`.
- **Utilidades**: `mongoToArray()`, `mongoDateToReadable()`, `readableToMongoDate()`, `getNestedValue()`,
  `assignArrayByPath()`, `clean_filename()`, `formatSize()`, `delTree()` (ver `includes/functions.php` y `docs/functions.php`).
- **Autoload**: `new Foo` carga `class.Foo.php` de `includes/` o de cualquier directorio del `include_path`.

## Configuración

`includes/config.php` (no versionado; copia de `config.example.php`, documentado línea por línea) elige un
bloque por `HTTP_HOST`; hosts desconocidos → 400. Obligatorias: `mongo_connection_string`, `sitedb`,
`cookie_domain`, `url`. Lo editable por el administrador vive en `configs/{_id:'site'}` (Admin → Sitio /
Seguridad / SAT). `trusted_proxies` y `allowed_redirect_hosts` se **suman** entre archivo y BD.

## Estructura del framework

```
router.php              front controller (incluye nframework/router.php y crouter.php)
includes/include.php    bootstrap: proxies/IP, config, MongoDB, listas negras, CSRF por origen, estadísticas,
                        errores, autoload, sesión, idioma, $user, $javas, nfshutdown(), secureform/csrf*
includes/functions.php  helpers (caché, seguridad, Mongo, redirecciones, archivos)
includes/class.Base.php componentes de formulario, dataset, datasetArray, datasetpdo, Icon, TreeView
includes/class.*.php    User, Table, Dialog, Javas, Job, bgprocess, embededArray, XMLS, Sidebar, UIManager…
nframework/             endpoints internos: router, uploadfile, datatable, select_ajax, nfdialog/nftable,
                        OAuth (google/, facebook/, microsoft/), templates/ (Twig: basic, panda), test.php
admin/                  panel (cada sección en su carpeta; usa admin/common2.php)
docs/                   documentación ejecutable; menú en $docsMenu de docs/common2.php
sat/                    CFDI, e.firma, XSD (XMLS), catálogos
tests/                  PHPUnit: bootstrap.php simula $config y $m = null
```

## Convenciones al modificar el framework

- Comentarios, textos de UI y documentación en **español**; mensajes de commit en inglés con formato
  Conventional Commits y scope: `feat(admin): ...`, `fix(base): ...`, `refactor(router): ...`.
- `includes/` usa 4 espacios; `docs/` y varias páginas usan tabuladores. Respete el del archivo.
- Mantenga el estilo global (sin namespaces, `global` en funciones); no introduzca contenedores DI ni
  frameworks nuevos.
- Al cambiar o añadir un componente, actualice/añada su ejemplo en `docs/` (y regístrelo en `$docsMenu`) y
  agregue tests en `tests/Unit` cuando no dependan de MongoDB.
- Cambios de esquema o índices van en `nframework/test.php` (instalador idempotente).
- Ignorados por git: `includes/vendor/`, `includes/config.php`, `composer.lock`, `*.log`, `.github/`, `.vscode/`, `.idea/`.
- No suba ni muestre `includes/config.php` (credenciales).
