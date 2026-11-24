# Actualización de sitios basados en nframework

Cambios del framework que pueden notar los sitios que lo usan. Cada entrada dice qué cambió, a quién afecta
y qué revisar. Las más recientes van primero; cada versión (archivo `VERSION`, etiqueta de git) agrupa sus
entradas.

## Cómo actualizar un sitio

1. Actualice el código del framework: Admin → Actualizar → **Pull**. Si Pull no puede (rama sin upstream o
   historial distinto), use **Forzar actualización**.
2. Las **migraciones de base de datos** (`upgrades/*.php`) se aplican solas al terminar el paso 1. También se
   pueden aplicar en Admin → Actualizar → Migraciones, o con
   `php nframework/upgrade.php --host=<dominio> [--docroot=<dir>]`. Mientras falte alguna, todo Admin lo avisa.
   En multisitio cada sitio tiene su base: aplíquelas en cada uno (o con la terminal, un `--host` por sitio).
3. **Código del sitio:** use Migraciones → «Revisar código del sitio» (o `--code`) para ver qué páginas usan
   algo que cambió. Las correcciones marcadas como automáticas se aplican con un botón (o `--apply-code`) y
   respaldo en `.nfupgrade/<fecha>/`; las demás se revisan con las notas de abajo.
4. Si cambiaron dependencias: Composer → Install.

Ver `docs/upgrades.php`.

# 2026.3

## 2026-11-24 — Mi cuenta (/account/)

- Nueva página `/account/` con Perfil (`/account/profile`: nombre y foto), Contraseña (`/account/password`),
  Seguridad (`/account/security`) y Sesiones (`/account/sessions`: ver y cerrar dispositivos). Antes
  `/account/profile` y `/account/sessions` fallaban (no existían sus plantillas) y el menú de usuario enlazaba a
  `/account/myprofile.php` y `/account/cpassword.php`, que no existen; ahora enlaza a `/account/` y
  `/account/password`.
- A quién afecta: sitios con páginas propias en esas rutas o en `/account/` (un archivo existente gana a la
  ruta); revise si sobra alguna. Cambiar la contraseña desde `/account/password` cierra las demás sesiones del
  usuario y aplica `passwordmask` (Admin → Sitio).
- Cada sesión nueva guarda IP y navegador en `users.sessions_info`; las sesiones abiertas antes de actualizar
  aparecen como «Desconocido».

## 2026-11-24 — Verificación en dos pasos obligatoria

- Nueva página `/account/twofa-setup` para activar el 2FA (QR + código). Antes no había forma de activarlo
  y el formulario de `/account/twofa` no enviaba el código (apuntaba a `/account/signup`); ya funciona.
- Nueva página `/account/security` (enlace «Seguridad» en el menú de usuario): estado del 2FA, activarlo,
  desactivarlo si no es obligatorio y generar una llave de recuperación de un solo uso (se guarda su hash en
  `users.twofa_recovery`). Al activar el 2FA se genera y se muestra la llave. En `/account/twofa`, «¿Perdió su
  teléfono?» acepta la llave: entra, la gasta y desactiva el 2FA para volver a configurarlo.
- Admin → Sitio: «Requerir la verificación en dos pasos a todos los usuarios» (`twofa_required` en
  `configs/site`). Admin → Usuarios: lo mismo para un usuario (`users.twofa_required`) y «Restablecer 2FA».
- Quien la tenga obligatoria y sin activar es enviado a `/account/twofa-setup` desde cualquier página
  (también sesiones ya abiertas e inicios por OAuth); las peticiones AJAX reciben 403. Nada cambia si no se
  marca ninguna casilla.
- **Corrección de seguridad:** el inicio con Google, Microsoft o Facebook ya pide el código a quien tiene 2FA
  activo (antes entraba sin él). Si el sitio tiene su propio OAuth que asigna `$_SESSION['user']`, agregue
  `nfTwofaChallenge($_SESSION['user']);` justo después: busque `$_SESSION['user'] =` en el código del sitio.
- A quién afecta: sitios que la habiliten. Si el sitio usa otra plantilla que `panda`, agregue
  `twofasetup.html`, `security.html` y `recoverykey.html`. Las rutas propias que deban seguir abiertas (webhooks con sesión) no deberían depender
  de un usuario con sesión; revise `crouter.php`. `/account/totp-setup` (guarda `totp_secret`, que el login
  no usa) queda igual; no lo use para el 2FA.

## 2026-11-23 — Botón de Administración en el menú de usuario

- `$user->usermenu()` agrega un botón «Administración» (a `/admin/`) cuando el usuario está en el grupo
  `admins`. Los demás usuarios no ven cambios.
- A quién afecta: sitios cuya plantilla usa `usermenu()` (lo hace `Sidebar`). Si el sitio ya tenía su propio
  enlace al panel, quedará repetido: busque `/admin` en `common2.php` y en las plantillas del sitio.

# 2026.2

## 2026-11-23 — Antivirus ClamAV en las subidas

- Admin → Antivirus configura `clamd` por TCP (servidor, puerto, tiempo de espera, tamaño máximo y qué hacer si
  clamd falla) y tiene un botón para probar la conexión. Se guarda en `configs/site` como `$config['clamav']`.
- **Solo si se habilita**, `nframework/uploadfile.php` (`inputFile`/`inputFiles`, archivos de nftables),
  `tinymceupload.php` (imágenes de `inputMCE`) y `nfimport.php` analizan cada archivo antes de guardarlo o
  leerlo. Un archivo infectado se borra y se rechaza. Mientras no se habilite, nada cambia.
- A quién afecta: sitios que lo habiliten. Con «Rechazar» (por omisión), si clamd no responde no se puede
  subir ningún archivo; y los archivos mayores al «Tamaño máximo» se rechazan aunque `inputFiles` permita más
  (`sizelimit`). Busque `sizelimit` en el sitio y ajuste el tamaño máximo (y `StreamMaxLength` de clamd).
- Código propio del sitio que reciba archivos: `ClamAV::uploadError($tmp, $nombre)` (ver `docs/clamav.php`).

## 2026-11-23 — Constructor de consultas de Tablef

- Se corrigió el error «QueryBuilder is not defined» que detenía los scripts de las páginas con `Tablef` y
  los operadores «empieza con» / «termina con». No requiere cambios en los sitios.

# 2026.1

## 2026-11-22 — Versionado y migraciones automáticas

- El framework tiene versión (`VERSION`, 2026.1) y migraciones (`upgrades/`). Cada sitio registra lo aplicado
  en la colección nueva `nfmigrations`.
- Pull y Forzar actualización aplican las migraciones al terminar (casilla «Aplicar las migraciones…»).
- La normalización de las consultas de TablefIA pasó de `nframework/test.php` a la migración
  `20261121_tablefia_promptnorm`. La migración `20261122_nftables_campos` convierte a arreglo las listas embebidas
  de nftables guardadas como objeto `{"0": …}` y guarda los tipos de campo con su nombre actual.
- Un sitio puede tener sus propias migraciones en `<document root>/upgrades/` (o `$config['upgrades_dir']`).

## 2026-11-22 — `inputMCE`: las imágenes ya no llegan a la carpeta del framework

Antes, el editor subía las imágenes pegadas o arrastradas aunque el campo no tuviera `upload`. Si `mediadir`
faltaba o era inválido, terminaban en la carpeta del framework (`nframework/mceclip0.jpg`, `mceu_….jpg`). Además,
cada imagen pegada se llama igual (`mceclip0.jpg`) y sobrescribía a la anterior. Ahora:

- **Sin `upload`, o con `mediadir`/`baseurl` faltantes o inválidos:** el editor no sube ni incrusta imágenes. Si
  alguien pega o arrastra una imagen, el editor muestra un aviso en lugar de descartarla en silencio.
- **Con `upload` pero mal configurado:** aparece un aviso bajo el editor y se anota el motivo en el log
  (`inputMCE::uploadProblem()`). Con `developermode`, el aviso de pantalla incluye el motivo y la ruta.
  `baseurl` ahora es obligatorio para subir imágenes.
- **`mediadir` válido:** debe ser una ruta absoluta que no sea la raíz del framework ni esté dentro de
  `includes/`, `nframework/`, `admin/` o `sat/`. Se revisa también siguiendo ligas simbólicas; la validación está en
  `inputMCE::uploadDir()`.
- **Nombres repetidos:** si ya existe un archivo con ese nombre, la imagen se guarda como `nombre-1.jpg`,
  `nombre-2.jpg`…

**Revise:**
- Los editores con `'upload' => true` deben tener un `mediadir` absoluto, por ejemplo
  `$_SERVER['DOCUMENT_ROOT'] . '/media/'`, y su `baseurl`.
- Las imágenes `mceclip*` y `mceu_*` que quedaron sueltas en `nframework/`: muévalas a la carpeta de medios del
  sitio si algún contenido las usa.

```bash
grep -rn "new inputMCE\|new inputmce" --include=*.php .
ls nframework/mceclip* nframework/mceu_* 2>/dev/null
```

`.gitignore` ahora excluye `/sat/certs/` y `/comprobantes/`, que son datos del sitio y no del framework.

## 2026-11-22 — nftables: todos los componentes, Select AJAX, autocompletar y datos embebidos

### Admin → nftables y `/nftables/`

El editor de Admin → nftables se rehízo y los campos (`nffields`) ahora los interpreta `nfTableSchema`:

- **Tipos:** cualquier componente del framework (ver `nfTableSchema::TYPES`), con las propiedades que cada uno
  permite. Los documentos guardados antes (`inputtext`, `inputcheckboxes`, `required: "1"`) se siguen leyendo igual.
  Antes, `inputcheckboxes` daba un error fatal (la clase es `inputCheckBoxs`) y `select` se mostraba sin opciones.
- **Validación al guardar:** el editor ya no guarda campos inválidos (nombres con `$`, repetidos, Select sin opciones)
  ni tablas sobre colecciones del framework (`users`, `usersgroups`, `configs`, `jobs`, `pages`, `menus`, `nftables`…).
- **Celdas:** las celdas de `/nftables/<colección>/` se escapan. Si guardaba HTML a propósito, ahora se verá como texto.
- **Guardar un registro:** valida el token CSRF.
- **Archivos y firmas:** requieren la nueva clave `nftables_files` en `includes/config.php`. Sin ella, esos campos
  se muestran con un aviso.

### `embededArray`: cómo se guardan los elementos (afecta a todos los sitios que lo usan)

- **Elemento nuevo:** se agrega con `$push`. Antes se usaba `$set` en `campo.N.subcampo`, y si el arreglo no
  existía MongoDB creaba un objeto `{"0": {...}}` en lugar de un arreglo.
- **Casilla (`inputCheckBox`):** una casilla desmarcada se guarda como `false`. Antes no se podía desmarcar.
  Al editar, la casilla se marca según el valor guardado.
- **Número (`inputNumber`):** se guarda como número (`int`/`float`). Antes se guardaba el texto; un valor vacío
  ahora se guarda como `null`.

**Revise:** consultas o comparaciones del sitio que esperen como texto los números de un arreglo embebido
(`'partidas.cantidad' => '3'`), y documentos viejos con el objeto `{"0": ...}`, que conviene convertir a arreglo.

```bash
grep -rn "new embededArray" --include=*.php .
```

### Otros

- `inputHidden` con valor `null` ya no genera un aviso de función obsoleta.
- La primera firma de una página (`Signature_pad`) ya no genera el warning `Undefined array key "Signature_pad"`.
- `inputFile` e `inputFiles` ya tienen los textos "Arrastre el archivo aquí…" que les faltaban en `es-MX`/`en-US`.

## 2026-11-21 — TablefIA: más contexto para la IA y reutilización por colección

### La IA ahora recibe valores reales de muestra (privacidad)

Hasta ahora `TablefIA` enviaba a la API de Anthropic solo nombres y tipos de campos. Ahora, para las colecciones
sin `fields`, también envía lo que encuentra en una muestra de documentos (que ya cumplen su `match` y sin los
campos de `hidden`):

- **por omisión (`'stats' => true`)**: los valores de los campos categóricos (hasta 12 valores distintos que se
  repiten, p. ej. `estado: "pagado", "pendiente"`) y el rango de números y fechas (`importe: de 150 a 8999`);
- **solo con `'values' => true`**: tres ejemplos de los campos de texto libre (nombres, correos…).

**Revise:** colecciones cuyos valores categóricos o rangos no deban salir del servidor (diagnósticos, montos
confidenciales). Desactívelo por colección con `'stats' => false`, declare `fields` a mano (con `fields` no se
muestrea nada) u oculte el campo con `hidden`.

```bash
grep -rn "new TablefIA" --include=*.php .
```

### Ejemplos de consultas guardadas (`fewshot`)

La IA recibe hasta 5 consultas guardadas del mismo `uso` (favoritas y más ejecutadas, revalidadas con la
configuración actual) como ejemplo. Con `'shared' => false` solo las del propio usuario. `'fewshot' => 0` lo
desactiva. Las preguntas de los usuarios llegan así al prompt de otros usuarios del mismo `uso`: el pipeline se
sigue validando en el servidor, pero si las preguntas pueden contener datos sensibles, use `0`.

### Reutilización: cambiar una colección ya no invalida las consultas de las demás

Las consultas nuevas guardan `colecciones` (base + `$lookup`) y su `confighash` cubre solo esas colecciones, sus
relaciones e `instructions`. Las consultas guardadas antes se siguen reutilizando con la regla anterior (toda la
configuración); agregar `notes`, `stats` o `values` no las invalida. No hay migración ni índices nuevos.

### `notes` en lugar de `fields` para dar significado

`notes` (`['importe' => 'total en pesos']`) agrega el significado a los campos inferidos, sin perder la
inferencia de tipos y valores. `fields` sigue funcionando igual (lista completa a mano, sin muestreo).

## 2026-11-21 — correcciones del núcleo

Ninguna firma pública cambió: el código que ya funcionaba sigue funcionando. Lo que cambia es que varias
operaciones que antes fallaban en silencio (o con error) ahora hacen lo que dicen.

### `$user['campo'] = $valor` y `unset($user['campo'])` ahora sí guardan en MongoDB

`User::offsetSet()` / `offsetUnset()` filtraban por el `_id` en texto, así que el `updateOne` no encontraba al
usuario: el cambio solo quedaba en memoria. Ahora se comportan igual que `$user->campo = $valor` (que siempre
funcionó) y, como esa forma, ignoran `username` y `_id`.

**Revise:** código que use la forma de arreglo para cambiar datos del usuario "solo para esta petición". Desde
ahora esos cambios se guardan. Si necesita un valor temporal, use una variable propia.

```bash
grep -rnE '\$user\[[^]]+\]\s*=[^=]|unset\(\$user\[' --include=*.php .
```

### `unset($dataset->campo)` ahora funciona

Enviaba el `$unset` envuelto en una lista (MongoDB lo interpretaba como pipeline de agregación y fallaba). Ahora
quita el campo. Si su sitio atrapaba esa excepción, ya no ocurrirá.

### `dataset` con `allUpper` / `allLower` ahora convierte a mayúsculas / minúsculas

Los controles buscaban `allUppercase` / `allLowercase`, que `dataset` no declara, así que las opciones no
hacían nada. Ahora se aceptan ambos nombres.

**Revise:** datasets creados con `'allUpper' => true` o `'allLower' => true`: sus `inputText` y `textArea`
empezarán a convertir el texto.

```bash
grep -rnE "allUpper|allLower" --include=*.php .
```

### `dataset::refresh()` con un documento borrado

Antes lanzaba `TypeError` (`count(null)`); ahora deja `info` como `['_id' => ...]`, igual que el constructor.
Además `info` queda como arreglo (antes era un `BSONDocument` tras `refresh()`).

### `dataset::tonfTables()` registra los campos en Admin → nftables

Antes armaba un update inválido. Ahora crea la tabla administrable de la colección si no existe y agrega a
`nffields` solo los controles que aún no están (`type`, `field`, `short_description`, `required`); lo que el
administrador ya editó se conserva. El parámetro `$id` sigue sin usarse y ahora es opcional.

### `TreeViewItem` / `TreeView`

`TreeViewItem::__toString()` llamaba a `Icon::data()`, que no existe: cualquier árbol terminaba en error fatal.
Ahora genera el marcado de Metro UI (`data-icon` y `data-caption` escapados, hijos en `<ul>`). `TreeView`
acepta opciones en el constructor (`children`, `id`); sin opciones conserva el id `tree_add_leaf_example`.

### `TableF`: operadores sin distinguir mayúsculas

El JS que registra `begins_withi`, `containsi` y `ends_withi` en QueryBuilder se agregaba a una sección
inexistente (`'reaady'`) y nunca llegaba al navegador. Ahora se emite antes de crear el constructor de filtros.

**Revise:** si su sitio definía esos operadores por su cuenta para suplir la falla, quite la copia.

### `BreadCrumbs`

- Respeta las opciones `root` y `self` (antes se ignoraban).
- Los enlaces llevan la ruta completa (`/a/b`); antes cada uno solo llevaba la carpeta anterior.
- Escapa textos y URLs: `PHP_SELF` puede traer texto del usuario (PATH_INFO).
- Corrige el `<li"` mal formado.

### `notify($titulo, $texto, $opciones)`

Antes ignoraba `$titulo` y `$opciones`. Ahora un título no vacío se muestra en negritas (escapado) sobre el texto,
y `['timeout' => ms]` controla cuánto dura el aviso. El valor por omisión de `$titulo` pasó de `'nlared.com'`
(que nunca se mostraba) a `''`, así que las llamadas sin título se ven igual que antes.

**Revise:** llamadas que pasan un título: ahora aparecerá.

```bash
grep -rnE "notify\(\s*['\"][^'\"]" --include=*.php .
```

### Documentación para agentes

`CLAUDE.md` del framework ahora solo importa `AGENTS.md`. Los sitios que importan
`@/var/www/dev.nlared.local/CLAUDE.md` no necesitan cambios; para herramientas que no son Claude, agregue al
`AGENTS.md` del sitio: "Este sitio usa nframework: antes de trabajar lea /var/www/dev.nlared.local/AGENTS.md".
