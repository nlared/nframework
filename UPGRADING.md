# Actualización de sitios basados en nframework

Cambios del framework que pueden notar los sitios que lo usan. Cada entrada dice qué cambió, a quién afecta
y qué revisar. Las más recientes van primero.

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
