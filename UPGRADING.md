# Actualización de sitios basados en nframework

Cambios del framework que pueden notar los sitios que lo usan. Cada entrada dice qué cambió, a quién afecta
y qué revisar. Las más recientes van primero.

## 2026-11-22 — `inputMCE`: las imágenes ya no llegan a la carpeta del framework

Antes, el editor subía las imágenes pegadas o arrastradas aunque el campo no tuviera `upload`. Si `mediadir`
faltaba o era inválido, terminaban en la carpeta del framework (`nframework/mceclip0.jpg`, `mceu_….jpg`). Además,
cada imagen pegada se llama igual (`mceclip0.jpg`) y sobrescribía a la anterior. Ahora:

- **Sin `upload`, o con un `mediadir` inválido:** el editor no sube ni incrusta imágenes. Con `upload` y un
  `mediadir` inválido se anota en el log `inputMCE …: mediadir inválido`.
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
