<?php

/**
 * Esquema de las tablas administrables (Admin → nftables, /nftables/<colección>/).
 *
 * Cada documento de la colección nftables describe una colección editable con `nffields`, una lista de campos.
 * Esta clase es la única que interpreta esa lista: el editor de Admin la usa para validar lo que se guarda y
 * /nftables/ para construir los controles, las columnas, los filtros y el pipeline de la tabla. Todo es
 * estático y puro (sin MongoDB), salvo lo que recibe en $context, para poder probarlo.
 *
 * Forma de un campo (lo que guarda el editor):
 *
 *   [
 *       'field' => 'cliente',                 // notación con punto para subcampos (direccion.calle)
 *       'type' => 'Select',                   // clase del framework (ver TYPES)
 *       'short_description' => 'Cliente',     // etiqueta y encabezado de la columna
 *       'long_description' => '',            // ayuda (title del control)
 *       'required' => true,
 *       'list' => true,                       // se muestra como columna de la tabla
 *       'options' => ['multiple' => false],   // propiedades del componente permitidas para su tipo
 *       'items' => [['value' => 'a', 'label' => 'Activo']],                 // lista fija (Select, inputRadios...)
 *       'ajax' => ['collection' => 'clientes', 'label' => ['nombre'], 'value' => '_id',
 *                  'columns' => ['nombre', 'rfc'], 'match' => '{"activo":true}', 'limit' => 20],  // Select en colección
 *       'suggest' => ['mode' => 'self'],      // inputText: list (items) | collection (+column) | self
 *       'subfields' => [...],                 // embededArray: campos de cada elemento (sin subniveles)
 *   ]
 *
 * Los documentos viejos (tipos en minúsculas como 'inputtext', 'inputcheckboxes', required "1") se aceptan:
 * normalizeField() los convierte al leerlos.
 *
 *   $fields = nfTableSchema::normalizeFields($tabla['nffields'] ?? []);
 *   foreach ($fields as $f) {
 *       $control = nfTableSchema::build($f, $dataset, ['collection' => 'productos', '_id' => $dataset->_id]);
 *   }
 */
class nfTableSchema
{
    /**
     * Tipos que se pueden elegir en Admin. Por tipo:
     * - label / group: texto en el editor;
     * - options: propiedades del componente que el administrador puede fijar y su tipo (string, bool, int,
     *   number, o lista de valores permitidos);
     * - source: 'items' (lista fija), 'items+ajax' (lista o colección), 'suggest' (sugerencias de texto libre);
     * - filter / phptype: tipo para el constructor de filtros (TableF) y conversión en datatablef.php;
     * - import: tipo por omisión al importar un archivo (nfimport.php);
     * - column: se muestra como columna por omisión;
     * - sub: se puede usar como campo de un arreglo embebido;
     * - files: necesita una carpeta en el servidor ($config['nftables_files']).
     */
    public const TYPES = [
        'inputText' => [
            'label' => 'Texto', 'group' => 'Texto', 'source' => 'suggest',
            'options' => ['placeholder' => 'string', 'pattern' => 'string', 'validate' => 'string', 'uppercase' => 'bool',
                'lowercase' => 'bool', 'prepend' => 'string', 'append' => 'string', 'default' => 'string', 'readonly' => 'bool'],
            'filter' => 'string', 'import' => 'string', 'column' => true, 'sub' => true,
        ],
        'textArea' => [
            'label' => 'Texto largo', 'group' => 'Texto',
            'options' => ['placeholder' => 'string', 'charscounter' => 'bool', 'uppercase' => 'bool', 'default' => 'string', 'readonly' => 'bool'],
            'filter' => 'string', 'import' => 'string', 'column' => true, 'sub' => true,
        ],
        'inputMCE' => [
            'label' => 'Texto con formato (HTML)', 'group' => 'Texto', 'options' => [],
            'filter' => 'string', 'import' => 'string', 'column' => false, 'sub' => false,
        ],
        'inputNumber' => [
            'label' => 'Número', 'group' => 'Números',
            'options' => ['min' => 'number', 'max' => 'number', 'step' => 'number', 'prepend' => 'string', 'append' => 'string',
                'default' => 'number', 'readonly' => 'bool'],
            'filter' => 'double', 'phptype' => 'number', 'import' => 'number', 'column' => true, 'sub' => true,
        ],
        'inputSpinner' => [
            'label' => 'Número con botones', 'group' => 'Números', 'options' => ['default' => 'number'],
            'filter' => 'double', 'phptype' => 'number', 'import' => 'number', 'column' => true, 'sub' => false,
        ],
        'inputRating' => [
            'label' => 'Calificación (estrellas)', 'group' => 'Números', 'options' => [],
            'filter' => 'double', 'phptype' => 'number', 'import' => 'number', 'column' => true, 'sub' => false,
        ],
        'inputDate' => [
            'label' => 'Fecha', 'group' => 'Fechas', 'options' => ['readonly' => 'bool'],
            'filter' => 'date', 'phptype' => 'date', 'import' => 'date', 'column' => true, 'sub' => true,
        ],
        'inputTime' => [
            'label' => 'Hora', 'group' => 'Fechas', 'options' => ['readonly' => 'bool'],
            'filter' => 'string', 'import' => 'string', 'column' => true, 'sub' => true,
        ],
        'inputDateTime' => [
            'label' => 'Fecha y hora', 'group' => 'Fechas', 'options' => ['readonly' => 'bool'],
            'filter' => 'datetime', 'phptype' => 'date', 'import' => 'datetime', 'column' => true, 'sub' => false,
        ],
        'Select' => [
            'label' => 'Lista de opciones', 'group' => 'Opciones', 'source' => 'items+ajax',
            'options' => ['multiple' => 'bool', 'canadd' => 'bool'],
            'filter' => 'string', 'import' => 'string', 'column' => true, 'sub' => true,
        ],
        'inputRadios' => [
            'label' => 'Botones de opción', 'group' => 'Opciones', 'source' => 'items', 'options' => [],
            'filter' => 'string', 'import' => 'string', 'column' => true, 'sub' => true,
        ],
        'inputCheckBoxs' => [
            'label' => 'Varias casillas', 'group' => 'Opciones', 'source' => 'items', 'options' => ['horizontal' => 'bool'],
            'filter' => 'string', 'import' => 'string', 'column' => true, 'sub' => false,
        ],
        'inputCheckBox' => [
            'label' => 'Casilla (sí/no)', 'group' => 'Opciones', 'options' => [],
            'filter' => 'boolean', 'import' => 'boolean', 'column' => true, 'sub' => true,
        ],
        'inputColor' => [
            'label' => 'Color', 'group' => 'Otros', 'options' => [],
            'filter' => 'string', 'import' => 'string', 'column' => true, 'sub' => false,
        ],
        'inputFile' => [
            'label' => 'Archivo', 'group' => 'Archivos', 'files' => true,
            'options' => ['accept' => 'string', 'mode' => ['input', 'drop', 'button']],
            'filter' => null, 'import' => null, 'column' => false, 'sub' => false,
        ],
        'inputFiles' => [
            'label' => 'Varios archivos', 'group' => 'Archivos', 'files' => true,
            'options' => ['accept' => 'string', 'countlimit' => 'int', 'sizelimit' => 'int', 'mode' => ['input', 'drop', 'button']],
            'filter' => null, 'import' => null, 'column' => false, 'sub' => false,
        ],
        'Signature_pad' => [
            'label' => 'Firma', 'group' => 'Archivos', 'files' => true, 'options' => ['width' => 'int', 'height' => 'int'],
            'filter' => null, 'import' => null, 'column' => false, 'sub' => false,
        ],
        'mapmarker' => [
            'label' => 'Ubicación en mapa', 'group' => 'Mapas', 'options' => [],
            'filter' => null, 'import' => null, 'column' => false, 'sub' => false,
        ],
        'mapshape' => [
            'label' => 'Geocerca o ruta', 'group' => 'Mapas', 'options' => ['shape' => ['polygon', 'polyline']],
            'filter' => null, 'import' => null, 'column' => false, 'sub' => false,
        ],
        'inputaddress' => [
            'label' => 'Domicilio', 'group' => 'Mapas', 'options' => [],
            'filter' => null, 'import' => null, 'column' => false, 'sub' => false,
        ],
        'inputHidden' => [
            'label' => 'Oculto', 'group' => 'Otros', 'options' => ['default' => 'string'],
            'filter' => 'string', 'import' => 'string', 'column' => false, 'sub' => true,
        ],
        'label' => [
            'label' => 'Solo lectura', 'group' => 'Otros', 'options' => [],
            'filter' => 'string', 'import' => null, 'column' => true, 'sub' => false,
        ],
        'embededArray' => [
            'label' => 'Lista de subdocumentos', 'group' => 'Datos embebidos', 'options' => [],
            'filter' => null, 'import' => null, 'column' => true, 'sub' => false,
        ],
    ];

    /** Tipos guardados por versiones anteriores del editor (en minúsculas) que no coinciden con una clase. */
    public const ALIASES = ['inputcheckboxes' => 'inputCheckBoxs', 'checkbox' => 'inputCheckBox', 'text' => 'inputText'];

    /** Colecciones que no se pueden usar como origen de opciones (sesiones, credenciales). */
    public const BLOCKED_COLLECTIONS = ['sessions', 'nfsessions'];

    /** Campos que no se pueden mostrar ni buscar en un Select con colección. */
    public const BLOCKED_FIELDS = '/pass|secret|token|hash|salt|otp|2fa|totp/i';

    /** Ruta de un campo: segmentos de letras, números y _ separados por punto; sin $ ni _id. */
    public const FIELD_PATTERN = '/^[A-Za-z][A-Za-z0-9_]*(\.[A-Za-z][A-Za-z0-9_]*){0,4}$/';

    /** Campo de un subdocumento: sin punto (embeded.php solo acepta nombres simples). */
    public const SUBFIELD_PATTERN = '/^[A-Za-z][A-Za-z0-9_]*$/';

    /** Nombre de colección aceptado. */
    public const COLLECTION_PATTERN = '/^[A-Za-z0-9_][A-Za-z0-9_.-]{0,119}$/';

    public const MAX_FIELDS = 80;
    public const MAX_ITEMS = 300;
    public const MAX_SUBFIELDS = 20;

    /**
     * Nombre canónico del tipo (clase) o null si no es un tipo de TYPES. Acepta mayúsculas/minúsculas distintas y
     * los alias de versiones anteriores.
     *
     * @param mixed $type
     * @return string|null
     */
    public static function canonicalType($type): ?string
    {
        if (!is_string($type) || $type === '') {
            return null;
        }
        $lower = strtolower($type);
        if (isset(self::ALIASES[$lower])) {
            return self::ALIASES[$lower];
        }
        foreach (array_keys(self::TYPES) as $name) {
            if (strtolower($name) === $lower) {
                return $name;
            }
        }
        return null;
    }

    /**
     * Tipos agrupados para el editor: grupo => [clase => etiqueta].
     *
     * @param bool $sub true = solo los que se pueden usar dentro de un arreglo embebido.
     * @return array<string, array<string, string>>
     */
    public static function typeGroups(bool $sub = false): array
    {
        $groups = [];
        foreach (self::TYPES as $name => $def) {
            if ($sub && empty($def['sub'])) {
                continue;
            }
            $groups[$def['group']][$name] = $def['label'];
        }
        return $groups;
    }

    /**
     * Descripción de los tipos para el JS del editor: opciones permitidas y de dónde salen sus valores.
     *
     * @return array<string, array{label: string, options: array<string, string|list<string>>, source: string, files: bool, sub: bool, column: bool}>
     */
    public static function editorCatalog(): array
    {
        $out = [];
        foreach (self::TYPES as $name => $def) {
            $out[$name] = [
                'label' => $def['label'],
                'options' => $def['options'],
                'source' => $def['source'] ?? '',
                'files' => !empty($def['files']),
                'sub' => !empty($def['sub']),
                'column' => !empty($def['column']),
            ];
        }
        return $out;
    }

    /**
     * Convierte lo que llega de un formulario o de MongoDB ("1", "on", "true", 1) a bool.
     *
     * @param mixed $value
     * @return bool
     */
    public static function toBool($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return $value != 0;
        }
        return is_string($value) && in_array(strtolower(trim($value)), ['1', 'on', 'true', 'yes', 'si', 'sí'], true);
    }

    /**
     * Opciones fijas a partir de texto ("valor=Texto" o "Texto" por renglón) o de una lista ya armada
     * (['valor' => 'Texto'], [['value' => .., 'label' => ..]]).
     *
     * @param mixed $raw
     * @return list<array{value: string, label: string}>
     */
    public static function parseItems($raw): array
    {
        if (is_object($raw)) {
            $raw = function_exists('mongoToArray') ? mongoToArray($raw) : (array) $raw;
        }
        $pairs = [];
        if (is_string($raw)) {
            foreach (preg_split('/\R/', $raw) as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $pos = strpos($line, '=');
                $pairs[] = $pos === false ? [$line, $line] : [trim(substr($line, 0, $pos)), trim(substr($line, $pos + 1))];
            }
        } elseif (is_array($raw)) {
            foreach ($raw as $key => $item) {
                if (is_array($item) || is_object($item)) {
                    $item = (array) $item;
                    $value = $item['value'] ?? $item['label'] ?? '';
                    $pairs[] = [$value, $item['label'] ?? $value];
                } elseif (is_scalar($item)) {
                    $pairs[] = array_is_list($raw) ? [$item, $item] : [$key, $item];
                }
            }
        }
        $items = [];
        $seen = [];
        foreach ($pairs as [$value, $label]) {
            $value = trim((string) $value);
            $label = trim((string) $label);
            if ($value === '' || isset($seen[$value])) {
                continue;
            }
            $seen[$value] = true;
            $items[] = ['value' => $value, 'label' => $label === '' ? $value : $label];
            if (count($items) >= self::MAX_ITEMS) {
                break;
            }
        }
        return $items;
    }

    /**
     * Lista de nombres de campo a partir de texto separado por comas o de un arreglo.
     *
     * @param mixed $raw
     * @return list<string>
     */
    private static function fieldList($raw): array
    {
        if (is_string($raw)) {
            $raw = explode(',', $raw);
        }
        if (is_object($raw)) {
            $raw = (array) $raw;
        }
        $out = [];
        foreach ((array) $raw as $item) {
            $item = is_scalar($item) ? trim((string) $item) : '';
            if ($item !== '' && !in_array($item, $out, true)) {
                $out[] = $item;
            }
        }
        return $out;
    }

    /**
     * Convierte el valor de una opción a su tipo; null si no es válido (la opción se omite).
     *
     * @param string|list<string> $kind
     * @param mixed $value
     * @return string|int|float|bool|null
     */
    private static function castOption($kind, $value)
    {
        if (is_array($kind)) {
            return is_string($value) && in_array($value, $kind, true) ? $value : null;
        }
        if ($value === null || $value === '' || is_array($value) || is_object($value)) {
            return null;
        }
        switch ($kind) {
            case 'bool':
                return self::toBool($value) ?: null;   // false = omitir (es el valor por omisión)
            case 'int':
                return is_numeric($value) ? (int) $value : null;
            case 'number':
                return is_numeric($value) ? $value + 0 : null;
            default:
                $value = trim((string) $value);
                return $value === '' ? null : mb_substr($value, 0, 500);
        }
    }

    /**
     * Valida y normaliza un campo. Los errores se acumulan en $errors (con el nombre del campo) y el campo se
     * devuelve igual con lo que sí es válido; null si no se puede usar (sin nombre o tipo).
     *
     * @param mixed $raw Arreglo, BSONDocument u objeto.
     * @param list<string> $errors
     * @param bool $sub true = campo de un arreglo embebido (nombre simple, solo tipos con 'sub').
     * @param bool $strict false = documento ya guardado: un tipo desconocido se lee como inputText.
     * @return array<string, mixed>|null
     */
    public static function normalizeField($raw, array &$errors = [], bool $sub = false, bool $strict = true): ?array
    {
        if (is_object($raw)) {
            $raw = function_exists('mongoToArray') ? mongoToArray($raw) : (array) $raw;
        }
        if (!is_array($raw)) {
            $errors[] = 'Campo con formato inválido.';
            return null;
        }
        $name = is_string($raw['field'] ?? null) ? trim($raw['field']) : '';
        $where = $name === '' ? 'Campo sin nombre' : 'Campo ' . $name;
        if ($name === '') {
            $errors[] = 'Hay un campo sin nombre.';
            return null;
        }
        if (!preg_match($sub ? self::SUBFIELD_PATTERN : self::FIELD_PATTERN, $name)) {
            $errors[] = $where . ': el nombre solo admite letras, números y _' . ($sub ? '' : ' (y punto para subcampos)') . ', y debe empezar con letra.';
            return null;
        }

        $type = self::canonicalType($raw['type'] ?? null);
        if ($type === null) {
            if ($strict) {
                $errors[] = $where . ': tipo desconocido.';
                return null;
            }
            $type = 'inputText';
        }
        if ($sub && empty(self::TYPES[$type]['sub'])) {
            $errors[] = $where . ': el tipo ' . self::TYPES[$type]['label'] . ' no se puede usar dentro de una lista de subdocumentos.';
            if ($strict) {
                return null;
            }
        }
        $def = self::TYPES[$type];

        $field = [
            'field' => $name,
            'type' => $type,
            'short_description' => mb_substr(trim((string) (is_scalar($raw['short_description'] ?? null) ? $raw['short_description'] : '')), 0, 120),
            'long_description' => mb_substr(trim((string) (is_scalar($raw['long_description'] ?? null) ? $raw['long_description'] : '')), 0, 1000),
            'required' => self::toBool($raw['required'] ?? false),
            'list' => array_key_exists('list', $raw) ? self::toBool($raw['list']) : !empty($def['column']),
        ];
        if ($field['short_description'] === '') {
            $field['short_description'] = $name;
        }

        $options = [];
        $rawOptions = is_array($raw['options'] ?? null) ? $raw['options'] : [];
        foreach ($def['options'] as $option => $kind) {
            // Compatibilidad: la opción también puede venir en la raíz del campo.
            $value = $rawOptions[$option] ?? $raw[$option] ?? null;
            $value = self::castOption($kind, $value);
            if ($value !== null) {
                $options[$option] = $value;
            }
        }
        if (isset($options['pattern']) && @preg_match('/' . str_replace('/', '\/', $options['pattern']) . '/', '') === false) {
            $errors[] = $where . ': el patrón no es una expresión regular válida.';
            unset($options['pattern']);
        }
        if (isset($options['min'], $options['max']) && $options['min'] > $options['max']) {
            $errors[] = $where . ': el mínimo es mayor que el máximo.';
        }
        if ($type === 'mapshape' && !isset($options['shape'])) {
            $options['shape'] = 'polygon';
        }
        $field['options'] = $options;

        $source = $def['source'] ?? '';
        if ($source === 'items' || $source === 'items+ajax') {
            $ajax = $source === 'items+ajax' && !$sub ? self::normalizeAjax($raw['ajax'] ?? null, $where, $errors) : null;
            if ($ajax !== null) {
                $field['ajax'] = $ajax;
            } else {
                $field['items'] = self::parseItems($raw['items'] ?? $raw['options_text'] ?? []);
                if ($field['items'] === [] && $strict) {
                    $errors[] = $where . ': agregue las opciones' . ($source === 'items+ajax' && !$sub ? ' o elija una colección' : '') . '.';
                }
            }
        } elseif ($source === 'suggest' && !$sub) {
            $suggest = self::normalizeSuggest($raw['suggest'] ?? null, $where, $errors);
            if ($suggest !== null) {
                $field['suggest'] = $suggest;
            }
        }

        if ($type === 'embededArray') {
            $subErrors = [];
            $field['subfields'] = self::normalizeFields($raw['subfields'] ?? [], $subErrors, true, $strict);
            foreach ($subErrors as $e) {
                $errors[] = $where . ' → ' . $e;
            }
            if ($field['subfields'] === [] && $strict) {
                $errors[] = $where . ': agregue al menos un campo a la lista.';
            }
            $field['required'] = false;
        }
        return $field;
    }

    /**
     * Normaliza una lista de campos (nffields); descarta los inválidos o repetidos y anota por qué.
     *
     * @param mixed $raw Lista de campos (arreglo, BSONArray o JSON).
     * @param list<string> $errors
     * @param bool $sub
     * @param bool $strict
     * @return list<array<string, mixed>>
     */
    public static function normalizeFields($raw, array &$errors = [], bool $sub = false, bool $strict = false): array
    {
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        if (is_object($raw)) {
            $raw = function_exists('mongoToArray') ? mongoToArray($raw) : (array) $raw;
        }
        $fields = [];
        $names = [];
        foreach (is_array($raw) ? array_values($raw) : [] as $item) {
            $field = self::normalizeField($item, $errors, $sub, $strict);
            if ($field === null) {
                continue;
            }
            $root = explode('.', $field['field'])[0];
            if (isset($names[$field['field']])) {
                $errors[] = 'El campo ' . $field['field'] . ' está repetido.';
                continue;
            }
            // 'a' y 'a.b' a la vez: guardar uno borraría el otro.
            foreach (array_keys($names) as $other) {
                if (str_starts_with($other, $field['field'] . '.') || str_starts_with($field['field'], $other . '.')) {
                    $errors[] = 'Los campos ' . $other . ' y ' . $field['field'] . ' se encimarían: use solo uno.';
                    continue 2;
                }
            }
            $names[$field['field']] = $root;
            $fields[] = $field;
            if (count($fields) >= ($sub ? self::MAX_SUBFIELDS : self::MAX_FIELDS)) {
                $errors[] = 'Se alcanzó el máximo de campos.';
                break;
            }
        }
        if (!$sub) {
            // Los controles de la página usan el id data_<campo>: un subcampo no puede llamarse como otro control.
            $ids = [];
            foreach ($fields as $f) {
                $ids[str_replace('.', '_', $f['field'])][] = $f['field'];
                foreach ($f['subfields'] ?? [] as $s) {
                    $ids[$s['field']][] = $f['field'] . ' → ' . $s['field'];
                }
            }
            foreach ($ids as $owners) {
                if (count($owners) > 1) {
                    $errors[] = 'Nombres que chocan en el formulario: ' . implode(', ', $owners) . '. Cambie uno.';
                }
            }
        }
        return $fields;
    }

    /**
     * true si un nombre de colección se puede usar como origen de datos.
     *
     * @param mixed $name
     * @return bool
     */
    public static function validCollection($name): bool
    {
        return is_string($name) && preg_match(self::COLLECTION_PATTERN, $name) === 1 && !str_starts_with($name, 'system.')
            && !in_array(strtolower($name), self::BLOCKED_COLLECTIONS, true);
    }

    /**
     * Configuración de un Select que busca en una colección. null si no se eligió colección.
     *
     * @param mixed $raw ['collection', 'label' (campos), 'value', 'columns', 'match' (JSON), 'limit']
     * @param string $where Prefijo de los errores.
     * @param list<string> $errors
     * @return array{collection: string, label: list<string>, value: string, columns: list<string>, match: string, limit: int}|null
     */
    public static function normalizeAjax($raw, string $where, array &$errors): ?array
    {
        if (is_object($raw)) {
            $raw = (array) $raw;
        }
        if (!is_array($raw) || trim((string) ($raw['collection'] ?? '')) === '') {
            return null;
        }
        $collection = trim((string) $raw['collection']);
        if (!self::validCollection($collection)) {
            $errors[] = $where . ': la colección "' . $collection . '" no se puede usar.';
            return null;
        }
        $label = self::fieldList($raw['label'] ?? []);
        $columns = self::fieldList($raw['columns'] ?? []);
        $value = trim((string) (is_scalar($raw['value'] ?? null) ? $raw['value'] : ''));
        $value = $value === '' ? '_id' : $value;
        if ($label === []) {
            $errors[] = $where . ': indique el campo que se muestra de ' . $collection . '.';
            return null;
        }
        foreach (array_merge($label, $columns, [$value]) as $f) {
            if ($f !== '_id' && !preg_match(self::FIELD_PATTERN, $f)) {
                $errors[] = $where . ': "' . $f . '" no es un nombre de campo válido.';
                return null;
            }
            if (preg_match(self::BLOCKED_FIELDS, $f)) {
                $errors[] = $where . ': el campo "' . $f . '" no se puede mostrar ni buscar.';
                return null;
            }
        }
        $match = trim((string) (is_scalar($raw['match'] ?? null) ? $raw['match'] : ''));
        if ($match !== '') {
            $decoded = json_decode($match, true);
            if (!is_array($decoded) || ($decoded !== [] && array_is_list($decoded))) {
                $errors[] = $where . ': el filtro debe ser un objeto JSON, p. ej. {"activo": true}.';
                $match = '';
            } else {
                $clean = function_exists('nfSanitizeMongoQuery') ? nfSanitizeMongoQuery($decoded) : $decoded;
                $match = $clean === [] ? '' : json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }
        return [
            'collection' => $collection,
            'label' => $label,
            'value' => $value,
            'columns' => $columns === [] ? array_values(array_filter($label, fn($f) => $f !== '_id')) : $columns,
            'match' => $match,
            'limit' => max(1, min(100, (int) ($raw['limit'] ?? 20) ?: 20)),
        ];
    }

    /**
     * Sugerencias de un inputText. null = sin sugerencias.
     *
     * @param mixed $raw ['mode' => list|collection|self, 'items', 'collection', 'column', 'limit']
     * @param string $where
     * @param list<string> $errors
     * @return array{mode: string, items?: list<string>, collection?: string, column?: string, limit: int}|null
     */
    public static function normalizeSuggest($raw, string $where, array &$errors): ?array
    {
        if (is_object($raw)) {
            $raw = (array) $raw;
        }
        $mode = is_array($raw) && is_string($raw['mode'] ?? null) ? $raw['mode'] : '';
        $limit = max(1, min(50, (int) ($raw['limit'] ?? 20) ?: 20));
        if ($mode === 'list') {
            $items = array_map(fn($i) => $i['label'], self::parseItems($raw['items'] ?? []));
            if ($items === []) {
                $errors[] = $where . ': escriba las sugerencias (una por renglón).';
                return null;
            }
            return ['mode' => 'list', 'items' => $items, 'limit' => $limit];
        }
        if ($mode === 'self') {
            return ['mode' => 'self', 'limit' => $limit];
        }
        if ($mode === 'collection') {
            $collection = trim((string) ($raw['collection'] ?? ''));
            $column = trim((string) ($raw['column'] ?? ''));
            if (!self::validCollection($collection) || !preg_match(self::FIELD_PATTERN, $column) || preg_match(self::BLOCKED_FIELDS, $column)) {
                $errors[] = $where . ': indique una colección y un campo válidos para las sugerencias.';
                return null;
            }
            return ['mode' => 'collection', 'collection' => $collection, 'column' => $column, 'limit' => $limit];
        }
        return null;
    }

    /**
     * Expresión de agregación para la etiqueta de un Select con colección: un campo o varios unidos con " · ".
     *
     * @param list<string> $fields
     * @return string|array<string, mixed>
     */
    public static function labelExpression(array $fields)
    {
        $refs = array_map(fn($f) => $f === '_id' ? ['$toString' => '$_id'] : '$' . $f, $fields);
        if (count($refs) === 1) {
            return is_string($refs[0]) ? ['$toString' => $refs[0]] : $refs[0];
        }
        $parts = [];
        foreach ($refs as $i => $ref) {
            if ($i > 0) {
                $parts[] = ' · ';
            }
            $parts[] = ['$ifNull' => [['$toString' => $ref], '']];
        }
        return ['$concat' => $parts];
    }

    /**
     * Opciones de 'ajax' para Select (ver nfTomSelectAjax()). Con value '_id' el valor viaja como texto y el
     * Select lo guarda como ObjectId (format mongoid).
     *
     * @param array{collection: string, label: list<string>, value: string, columns: list<string>, match: string, limit: int} $ajax
     * @param string $db
     * @return array{db: string, collection: string, columns: list<string>, label: mixed, value: mixed, pipeline: list<array<string, mixed>>, limit: int}
     */
    public static function selectAjaxOptions(array $ajax, string $db): array
    {
        $pipeline = [];
        $match = $ajax['match'] !== '' ? json_decode($ajax['match'], true) : null;
        if (is_array($match) && $match !== []) {
            $pipeline[] = ['$match' => function_exists('nfSanitizeMongoQuery') ? nfSanitizeMongoQuery($match) : $match];
        }
        $pipeline[] = ['$sort' => [$ajax['label'][0] => 1]];
        return [
            'db' => $db,
            'collection' => $ajax['collection'],
            'columns' => $ajax['columns'],
            'label' => self::labelExpression($ajax['label']),
            'value' => $ajax['value'] === '_id' ? ['$toString' => '$_id'] : ['$toString' => '$' . $ajax['value']],
            'pipeline' => $pipeline,
            'limit' => $ajax['limit'],
        ];
    }

    /**
     * Carpeta (absoluta, con / final) de los archivos de un campo de un documento, o null si el sitio no
     * configuró $config['nftables_files'].
     *
     * @param array{files?: string|null, collection?: string, _id?: mixed} $context
     * @param string $field
     * @return string|null
     */
    public static function filesDir(array $context, string $field): ?string
    {
        $base = (string) ($context['files'] ?? '');
        $id = (string) ($context['_id'] ?? '');
        if ($base === '' || !preg_match('/^[A-Za-z0-9_-]{1,64}$/', $id) || !self::validCollection($context['collection'] ?? null)) {
            return null;
        }
        return rtrim($base, '/') . '/' . $context['collection'] . '/' . $id . '/' . str_replace('.', '_', $field) . '/';
    }

    /**
     * Opciones del constructor del componente de un campo (sin dataset/nfembeded), o null si el campo no se
     * puede dibujar (p. ej. archivos sin carpeta configurada).
     *
     * @param array<string, mixed> $field Campo normalizado.
     * @param array{db?: string, collection?: string, _id?: mixed, files?: string|null} $context
     * @return array<string, mixed>|null
     */
    public static function componentOptions(array $field, array $context = []): ?array
    {
        $type = $field['type'];
        $def = self::TYPES[$type];
        $opts = [
            'field' => $field['field'],
            'caption' => htmlspecialchars($field['short_description'], ENT_QUOTES),
            'required' => !empty($field['required']),
        ];
        if ($field['long_description'] !== '') {
            $opts['title'] = $field['long_description'];
        }
        foreach ($field['options'] as $option => $value) {
            if ($option === 'readonly') {
                $opts['readonly'] = true;
                $opts['backreadonly'] = true;   // el servidor tampoco acepta cambios
            } elseif ($option === 'validate') {
                $opts['validate'] = preg_replace('/[^A-Za-z0-9_=\s-]/', '', (string) $value);
            } else {
                $opts[$option] = $value;
            }
        }
        if ($type === 'inputNumber' && (isset($opts['step']) && floor((float) $opts['step']) != (float) $opts['step'])) {
            $opts['data_validate'] = 'float';
        }

        if (isset($field['ajax'])) {
            $opts['ajax'] = self::selectAjaxOptions($field['ajax'], (string) ($context['db'] ?? ''));
            if ($field['ajax']['value'] === '_id') {
                $opts['format'] = Select::formatMongoID;
            }
        } elseif (isset($field['items'])) {
            $opts['options'] = array_column($field['items'], 'label', 'value');
        }

        if (isset($field['suggest'])) {
            $s = $field['suggest'];
            if ($s['mode'] === 'list') {
                $opts['suggest'] = ['items' => $s['items'], 'limit' => $s['limit']];
            } else {
                $collection = $s['mode'] === 'self' ? (string) ($context['collection'] ?? '') : $s['collection'];
                $column = $s['mode'] === 'self' ? $field['field'] : $s['column'];
                if (self::validCollection($collection)) {
                    $opts['suggest'] = [
                        'db' => (string) ($context['db'] ?? ''),
                        'collection' => $collection,
                        'columns' => [$column],
                        'value' => '$' . $column,
                        'distinct' => true,
                        'limit' => $s['limit'],
                    ];
                }
            }
        }

        if (!empty($def['files'])) {
            $dir = self::filesDir($context, $field['field']);
            if ($dir === null) {
                return null;
            }
            $opts['dir'] = $dir;
            $opts['create_dir'] = true;
            if ($type === 'inputFile') {
                $opts['path'] = $dir . 'archivo';
            } elseif ($type === 'Signature_pad') {
                $opts['path'] = $dir . 'firma.png';
                unset($opts['dir'], $opts['create_dir']);
            }
        }
        if ($type === 'inputCheckBoxs') {
            $opts['multiple'] = true;
        }
        return $opts;
    }

    /**
     * Crea el componente de un campo ligado a $dataset (o a un embededArray con $embedded). Un embededArray
     * se devuelve como arreglo ['list' => embededArray, 'dialog' => Dialog, 'controls' => [...]]: la página
     * imprime el contenedor y el diálogo por separado.
     *
     * @param array<string, mixed> $field Campo normalizado.
     * @param dataset $dataset
     * @param array{db?: string, collection?: string, _id?: mixed, files?: string|null} $context
     * @param embededArray|null $embedded
     * @return baseInput|array{list: embededArray, dialog: Dialog, controls: list<baseInput>, containerid: string}|null
     */
    public static function build(array $field, &$dataset, array $context = [], $embedded = null)
    {
        if ($field['type'] === 'embededArray') {
            return self::buildEmbedded($field, $dataset, $context);
        }
        $opts = self::componentOptions($field, $context);
        if ($opts === null) {
            return null;
        }
        $class = $field['type'];
        if ($embedded !== null) {
            unset($opts['ajax'], $opts['suggest'], $opts['backreadonly']);
            $opts['nfembeded'] = &$embedded;
        } else {
            $opts['dataset'] = &$dataset;
        }
        return new $class($opts);
    }

    /**
     * id de HTML a partir de un nombre de campo.
     *
     * @param string $field
     * @return string
     */
    public static function htmlId(string $field): string
    {
        return 'nf_' . preg_replace('/[^A-Za-z0-9_]/', '_', $field);
    }

    /**
     * Plantilla Twig de la lista de un arreglo embebido: una tabla con los subcampos marcados como columna.
     *
     * @param array<string, mixed> $field Campo embededArray normalizado.
     * @return string
     */
    public static function embeddedTemplate(array $field): string
    {
        $cols = array_values(array_filter($field['subfields'], fn($s) => !empty($s['list']))) ?: array_slice($field['subfields'], 0, 4);
        $head = '';
        $row = '';
        foreach ($cols as $s) {
            // Los textos del administrador van como literales de Twig (nunca como código) con |e: Twig no
            // escapa las constantes por su cuenta.
            $head .= '<th>{{ ' . self::twigString($s['short_description']) . '|e }}</th>';
            $key = 'item.' . $s['field'];
            if (isset($s['items'])) {
                $cell = '';
                foreach ($s['items'] as $n => $item) {
                    $cell .= ($n === 0 ? '{% if ' : '{% elseif ') . $key . ' == ' . self::twigString($item['value']) . ' %}{{ ' . self::twigString($item['label']) . '|e }}';
                }
                $row .= '<td>' . ($cell === '' ? '{{ ' . $key . ' }}' : $cell . '{% else %}{{ ' . $key . ' }}{% endif %}') . '</td>';
            } elseif ($s['type'] === 'inputCheckBox') {
                $row .= '<td>{{ ' . $key . ' ? "Sí" : "No" }}</td>';
            } else {
                $row .= '<td>{{ ' . $key . ' }}</td>';
            }
        }
        return '{% if items|length > 0 %}<table class="table compact striped"><thead><tr>' . $head . '<th></th></tr></thead><tbody>'
            . '{% for key,item in items %}<tr>' . $row
            . '<td class="text-right"><button type="button" class="button square small primary" onclick="{{function_get}}(\'{{key}}\')"><span class="mif-pencil"></span></button> '
            . '<button type="button" class="button square small alert" onclick="{{function_delete}}(\'{{key}}\')"><span class="mif-bin"></span></button></td></tr>{% endfor %}'
            . '</tbody></table>{% else %}<div class="text-muted">Sin elementos</div>{% endif %}';
    }

    /**
     * Literal de cadena de Twig (comillas simples: sin interpolación).
     *
     * @param string $text
     * @return string
     */
    public static function twigString(string $text): string
    {
        return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $text) . "'";
    }

    /**
     * @param array<string, mixed> $field
     * @param dataset $dataset
     * @param array<string, mixed> $context
     * @return array{list: embededArray, dialog: Dialog, controls: list<baseInput>, containerid: string}
     */
    private static function buildEmbedded(array $field, &$dataset, array $context): array
    {
        $htmlId = self::htmlId($field['field']);
        $dialog = new Dialog(['title' => htmlspecialchars($field['short_description'], ENT_QUOTES), 'id' => $htmlId . '_dialog']);
        $list = new embededArray([
            'dataset' => &$dataset,
            'field' => $field['field'],
            'containerid' => $htmlId . '_list',
            'dialogid' => $dialog->id,
            'id' => $htmlId . '_array',
            'template' => self::embeddedTemplate($field),
        ]);
        $controls = [];
        foreach ($field['subfields'] as $sub) {
            $control = self::build($sub, $dataset, $context, $list);
            if ($control instanceof baseInput) {
                $controls[] = $control;
            }
        }
        $html = '<div class="row">';
        foreach ($controls as $control) {
            $html .= '<div class="cell-md-6">' . $control . '</div>';
        }
        $dialog->content = $html . '</div>';
        return ['list' => $list, 'dialog' => $dialog, 'controls' => $controls, 'containerid' => $htmlId . '_list'];
    }

    /**
     * Columnas de la tabla de /nftables/: nombre de la columna en el resultado del pipeline, encabezado y
     * expresión JS para dibujar la celda (recibe data; siempre escapa con nfEsc()).
     *
     * @param list<array<string, mixed>> $fields Campos normalizados.
     * @return list<array{column: string, field: string, title: string, render: string}>
     */
    public static function columns(array $fields): array
    {
        $out = [];
        foreach ($fields as $i => $f) {
            if (empty($f['list'])) {
                continue;
            }
            $column = $f['field'];
            if (isset($f['ajax']) || str_contains($column, '.')) {
                $column = 'nfc_' . $i;   // valor calculado en pipeline()
            }
            $multiple = !empty($f['options']['multiple']) || $f['type'] === 'inputCheckBoxs';
            if (isset($f['items'])) {
                $map = json_encode(array_column($f['items'], 'label', 'value'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?: '{}';
                $render = 'nfEsc(nfList(data).map(function(v){var m=' . $map . ';return m[v]!==undefined?m[v]:v;}).join(", "))';
            } elseif ($f['type'] === 'inputCheckBox') {
                $render = "(data===true?'Sí':(data===false?'No':''))";
            } elseif ($f['type'] === 'embededArray') {
                $render = "(Array.isArray(data)?data.length+(data.length===1?' elemento':' elementos'):'')";
            } elseif ($f['type'] === 'inputDate') {
                $render = 'nfEsc(nfDate(data,false))';
            } elseif ($f['type'] === 'inputDateTime') {
                $render = 'nfEsc(nfDate(data,true))';
            } elseif ($f['type'] === 'inputColor') {
                $render = "(data?'<span style=\"display:inline-block;width:1em;height:1em;vertical-align:middle;background:'+nfEsc(data)+'\"></span> '+nfEsc(data):'')";
            } elseif ($multiple) {
                $render = 'nfEsc(nfList(data).join(", "))';
            } else {
                $render = 'nfEsc(data)';
            }
            $out[] = ['column' => $column, 'field' => $f['field'], 'title' => $f['short_description'], 'render' => $render];
        }
        return $out;
    }

    /**
     * Etapas que se agregan al pipeline de la tabla: subcampos con punto como columna plana y la etiqueta de
     * los Select con colección ($lookup), para mostrarla y poder buscar por ella.
     *
     * @param list<array<string, mixed>> $fields Campos normalizados.
     * @return list<array<string, mixed>>
     */
    public static function pipeline(array $fields): array
    {
        $stages = [];
        $add = [];
        foreach ($fields as $i => $f) {
            if (empty($f['list'])) {
                continue;
            }
            if (isset($f['ajax'])) {
                $a = $f['ajax'];
                $tmp = 'nfl_' . $i;
                $stages[] = ['$lookup' => [
                    'from' => $a['collection'],
                    'localField' => $f['field'],
                    'foreignField' => $a['value'],
                    'as' => $tmp,
                    'pipeline' => [['$project' => ['_id' => 0, 'label' => self::labelExpression($a['label'])]]],
                ]];
                $add['nfc_' . $i] = ['$map' => ['input' => '$' . $tmp, 'as' => 'x', 'in' => '$$x.label']];
                if (empty($f['options']['multiple'])) {
                    $add['nfc_' . $i] = ['$ifNull' => [['$first' => $add['nfc_' . $i]], '$' . $f['field']]];
                }
            } elseif (str_contains($f['field'], '.')) {
                $add['nfc_' . $i] = '$' . $f['field'];
            }
        }
        if ($add !== []) {
            $stages[] = ['$addFields' => $add];
        }
        return $stages;
    }

    /**
     * Filtros del constructor de consultas (TableF) para los campos que se pueden filtrar.
     *
     * @param list<array<string, mixed>> $fields Campos normalizados.
     * @return list<array<string, mixed>>
     */
    public static function filters(array $fields): array
    {
        $out = [];
        foreach ($fields as $i => $f) {
            $def = self::TYPES[$f['type']];
            if (empty($def['filter'])) {
                continue;
            }
            $id = isset($f['ajax']) ? 'nfc_' . $i : $f['field'];
            if (isset($f['ajax']) && empty($f['list'])) {
                continue;   // la etiqueta solo existe si la columna está en la tabla
            }
            $filter = [
                'id' => $id,
                'field' => $id,
                'label' => $f['short_description'],
                'type' => $def['filter'],
                'phptype' => $def['phptype'] ?? 'string',
            ];
            if (isset($f['items'])) {
                $filter['input'] = 'select';
                $filter['values'] = array_column($f['items'], 'label', 'value');
                $filter['operators'] = ['equal', 'not_equal', 'in', 'not_in', 'is_null', 'is_not_null'];
            } elseif ($f['type'] === 'inputCheckBox') {
                $filter['input'] = 'radio';
                $filter['values'] = ['true' => 'Sí', 'false' => 'No'];
                $filter['operators'] = ['equal'];
            }
            $out[] = $filter;
        }
        return $out;
    }

    /**
     * Tipo por omisión al importar un archivo (nfimport.php): string, number, integer, date, datetime o boolean.
     *
     * @param mixed $type Tipo del campo (acepta los nombres viejos).
     * @return string
     */
    public static function importType($type): string
    {
        $canonical = self::canonicalType($type);
        $import = $canonical === null ? 'string' : (self::TYPES[$canonical]['import'] ?? 'string');
        return $import === 'number' ? 'integer' : ($import ?? 'string');
    }

    /**
     * Propone campos a partir de documentos de muestra (botón "Detectar campos"): el tipo sale del valor más
     * común de cada campo; los subdocumentos se expanden con punto y los arreglos de subdocumentos se vuelven
     * listas embebidas.
     *
     * @param iterable<array<string, mixed>|object> $docs
     * @param int $max
     * @return list<array<string, mixed>>
     */
    public static function inferFields(iterable $docs, int $max = 40): array
    {
        $seen = [];
        $count = 0;
        foreach ($docs as $doc) {
            $doc = is_object($doc) ? (function_exists('mongoToArray') ? mongoToArray($doc) : (array) $doc) : $doc;
            if (!is_array($doc)) {
                continue;
            }
            $count++;
            self::collectTypes($doc, '', $seen, 0);
        }
        // Textos cortos que se repiten mucho (estado, tipo): mejor como lista de opciones.
        foreach ($seen as &$info) {
            if (($info['types']['inputText'] ?? 0) >= 6 && count($info['values']) >= 2 && count($info['values']) <= 8
                && array_sum($info['values']) >= 3 * count($info['values'])) {
                $info['types']['Select'] = $info['types']['inputText'] + 1;
            }
        }
        unset($info);
        $fields = [];
        foreach ($seen as $path => $info) {
            if (preg_match(self::BLOCKED_FIELDS, $path) || !preg_match(self::FIELD_PATTERN, $path)) {
                continue;
            }
            arsort($info['types']);
            $type = array_key_first($info['types']);
            $field = [
                'field' => $path,
                'type' => $type,
                'short_description' => self::caption($path),
                'required' => false,
                'list' => !empty(self::TYPES[$type]['column']),
            ];
            if (($info['ids'] ?? 0) > 0) {
                $field['long_description'] = 'Contiene ids de otra colección: cámbielo a «Lista de opciones» → «Buscar en una colección».';
            }
            if ($type === 'embededArray') {
                $sub = [];
                foreach ($info['sub'] as $subPath => $subTypes) {
                    arsort($subTypes);
                    $subType = array_key_first($subTypes);
                    if (!preg_match(self::SUBFIELD_PATTERN, $subPath) || preg_match(self::BLOCKED_FIELDS, $subPath) || empty(self::TYPES[$subType]['sub'])) {
                        continue;
                    }
                    $sub[] = ['field' => $subPath, 'type' => $subType, 'short_description' => self::caption($subPath), 'required' => false, 'list' => true];
                }
                if ($sub === []) {
                    continue;
                }
                $field['subfields'] = array_slice($sub, 0, self::MAX_SUBFIELDS);
            }
            if ($type === 'Select') {
                $field['items'] = self::parseItems(array_slice(array_keys($info['values']), 0, self::MAX_ITEMS));
            }
            $fields[] = $field;
            if (count($fields) >= $max) {
                break;
            }
        }
        return $fields;
    }

    /**
     * @param array<string, mixed> $doc
     * @param string $prefix
     * @param array<string, array{types: array<string, int>, sub: array<string, array<string, int>>, values: array<string, int>, ids: int}> $seen
     * @param int $depth
     * @return void
     */
    private static function collectTypes(array $doc, string $prefix, array &$seen, int $depth): void
    {
        foreach ($doc as $key => $value) {
            if (!is_string($key) || $key === '_id' || str_starts_with($key, '$')) {
                continue;
            }
            $path = $prefix . $key;
            if (is_array($value) && !array_is_list($value) && $depth < 2 && !isset($value['type'], $value['coordinates'])) {
                self::collectTypes($value, $path . '.', $seen, $depth + 1);
                continue;
            }
            $seen[$path] ??= ['types' => [], 'sub' => [], 'values' => [], 'ids' => 0];
            if ($value instanceof \MongoDB\BSON\ObjectId) {
                $seen[$path]['ids']++;
            }
            $type = self::typeOf($value);
            $seen[$path]['types'][$type] = ($seen[$path]['types'][$type] ?? 0) + 1;
            if ($type === 'embededArray') {
                foreach ($value as $item) {
                    foreach ((array) $item as $k => $v) {
                        if (is_string($k)) {
                            $t = self::typeOf($v);
                            $seen[$path]['sub'][$k][$t] = ($seen[$path]['sub'][$k][$t] ?? 0) + 1;
                        }
                    }
                }
            } elseif (is_string($value) && mb_strlen($value) <= 60) {
                $seen[$path]['values'][$value] = ($seen[$path]['values'][$value] ?? 0) + 1;
            }
        }
    }

    /**
     * Tipo de control que corresponde a un valor guardado.
     *
     * @param mixed $value
     * @return string
     */
    private static function typeOf($value): string
    {
        if (is_bool($value)) {
            return 'inputCheckBox';
        }
        if (is_int($value) || is_float($value) || $value instanceof \MongoDB\BSON\Decimal128 || $value instanceof \MongoDB\BSON\Int64) {
            return 'inputNumber';
        }
        if ($value instanceof \MongoDB\BSON\UTCDateTime) {
            $d = $value->toDateTime();
            return $d->format('H:i:s') === '00:00:00' ? 'inputDate' : 'inputDateTime';
        }
        if (is_array($value) && isset($value['type'], $value['coordinates'])) {
            return in_array($value['type'], ['Polygon', 'LineString', 'MultiPolygon'], true) ? 'mapshape' : 'mapmarker';
        }
        if (is_array($value) && array_is_list($value)) {
            $docs = array_filter($value, fn($v) => is_array($v) && !array_is_list($v));
            return $value !== [] && count($docs) === count($value) ? 'embededArray' : 'inputCheckBoxs';
        }
        if (is_string($value) && (mb_strlen($value) > 120 || str_contains($value, "\n"))) {
            return preg_match('/<\/?(p|div|br|b|strong|ul|li)\b/i', $value) ? 'inputMCE' : 'textArea';
        }
        if (is_string($value) && preg_match('/^#[0-9a-f]{6}$/i', $value)) {
            return 'inputColor';
        }
        return 'inputText';
    }

    /**
     * Etiqueta legible a partir del nombre de un campo (direccion.codigo_postal → "Direccion codigo postal").
     *
     * @param string $path
     * @return string
     */
    public static function caption(string $path): string
    {
        $text = trim(str_replace(['.', '_'], ' ', preg_replace('/([a-z])([A-Z])/', '$1 $2', $path)));
        return mb_strtoupper(mb_substr($text, 0, 1)) . mb_strtolower(mb_substr($text, 1));
    }

    /**
     * Código PHP equivalente a un campo, para copiarlo a una página propia del sitio (botón "Código PHP").
     *
     * @param array<string, mixed> $field Campo normalizado.
     * @param string $collection Colección de la tabla (para sugerencias 'self').
     * @return string
     */
    public static function toPhp(array $field, string $collection = ''): string
    {
        $context = ['db' => '__SITEDB__', 'collection' => $collection, '_id' => '__ID__', 'files' => '__FILES__'];
        if ($field['type'] === 'embededArray') {
            $code = "\$dialog = new Dialog(['title' => " . var_export($field['short_description'], true) . "]);\n"
                . "\$lista = new embededArray([\n    'dataset' => &\$dataset,\n    'field' => " . var_export($field['field'], true) . ",\n"
                . "    'dialogid' => \$dialog->id,\n    'containerid' => 'lista_" . preg_replace('/\W/', '_', $field['field']) . "',\n"
                . "    'template' => " . var_export(self::embeddedTemplate($field), true) . ",\n]);\n";
            $controls = [];
            foreach ($field['subfields'] as $sub) {
                $var = '$' . preg_replace('/\W/', '_', $sub['field']);
                $opts = self::componentOptions($sub, $context) ?? [];
                unset($opts['ajax'], $opts['suggest'], $opts['backreadonly']);
                $code .= $var . ' = new ' . $sub['type'] . '(' . self::exportOptions(['nfembeded' => '&$lista'] + $opts) . ");\n";
                $controls[] = $var;
            }
            return $code . "\$dialog->content = " . implode(" . ", $controls ?: ["''"]) . ";\necho \$dialog;\necho \$lista;   // dibuja la lista en <div id=\"lista_...\">\n";
        }
        $opts = self::componentOptions($field, $context);
        if ($opts === null) {
            return "// Configure \$config['nftables_files'] para usar campos de archivo.\n";
        }
        if (isset($opts['format'])) {
            $opts['format'] = '__FORMAT__';
        }
        $var = '$' . preg_replace('/\W/', '_', $field['field']);
        return $var . ' = new ' . $field['type'] . '(' . self::exportOptions(['dataset' => '&$dataset'] + $opts) . ");\n";
    }

    /**
     * Arreglo PHP con sintaxis corta para toPhp(); reemplaza los marcadores por expresiones.
     *
     * @param array<string, mixed> $opts
     * @return string
     */
    private static function exportOptions(array $opts): string
    {
        $code = self::export($opts, 0);
        return strtr($code, [
            "'&\$dataset'" => '&$dataset',
            "'&\$lista'" => '&$lista',
            "'__SITEDB__'" => "\$config['sitedb']",
            "'__FORMAT__'" => 'Select::formatMongoID',
            "'__FILES__/" => "\$config['nftables_files'] . '/",
            "/__ID__/" => "/' . \$dataset->_id . '/",
        ]);
    }

    /**
     * @param mixed $value
     * @param int $level
     * @return string
     */
    private static function export($value, int $level): string
    {
        if (!is_array($value)) {
            return var_export($value, true);
        }
        if ($value === []) {
            return '[]';
        }
        $list = array_is_list($value);
        $scalars = array_filter($value, fn($v) => !is_array($v)) === $value;
        if ($scalars) {
            // Arreglos cortos de valores simples en una línea: ['nombre', 'rfc'], ['$toString' => '$_id'].
            $parts = [];
            foreach ($value as $k => $v) {
                $parts[] = ($list ? '' : var_export($k, true) . ' => ') . var_export($v, true);
            }
            $inline = '[' . implode(', ', $parts) . ']';
            if (strlen($inline) <= 80 && !str_contains($inline, "\n")) {
                return $inline;
            }
        }
        $pad = str_repeat('    ', $level + 1);
        $lines = [];
        foreach ($value as $k => $v) {
            $lines[] = $pad . ($list ? '' : var_export($k, true) . ' => ') . self::export($v, $level + 1) . ',';
        }
        return "[\n" . implode("\n", $lines) . "\n" . str_repeat('    ', $level) . ']';
    }
}
