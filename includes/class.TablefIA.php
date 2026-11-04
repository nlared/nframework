<?php

use Anthropic\Client as AnthropicClient;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\RateLimitException;

/**
 * TablefIA: consultas a MongoDB en lenguaje natural.
 *
 * Como TableF, pero en lugar del constructor de filtros el usuario escribe una pregunta; Claude la convierte
 * en un pipeline de aggregate que el servidor valida, ejecuta y muestra en una tabla. Cada consulta (prompt,
 * pipeline, columnas, explicación y una muestra del resultado) se guarda en la colección nftablesfia con el
 * campo `uso`, que identifica dónde se usa el componente, para listarla y reutilizarla sin volver a llamar a
 * la IA.
 *
 * Seguridad: el pipeline de la IA se trata como entrada del usuario (se puede manipular con el prompt).
 * Solo se aceptan etapas de lectura, $lookup únicamente en las relaciones declaradas, y antes de las etapas
 * de la IA se aplican el $match obligatorio y el $unset de campos ocultos de cada colección.
 *
 *   echo new TablefIA([
 *       'id' => 'ventasia',
 *       'uso' => 'reportes.ventas',
 *       'collections' => [
 *           'pedidos' => ['description' => 'Pedidos de venta', 'match' => ['empresa' => $empresaId]],
 *           'clientes' => ['description' => 'Clientes', 'hidden' => ['rfc_password']],
 *       ],
 *       'relations' => [
 *           ['from' => 'pedidos', 'localField' => 'cliente', 'to' => 'clientes', 'foreignField' => '_id', 'as' => 'cliente'],
 *       ],
 *   ]);
 */
class TablefIA
{
    /** Etapas que la IA puede usar. */
    public const STAGES = [
        '$match', '$group', '$sort', '$project', '$addFields', '$set', '$unset', '$unwind',
        '$limit', '$skip', '$count', '$sortByCount', '$lookup', '$replaceRoot', '$replaceWith',
    ];

    /** Operadores rechazados dentro de las etapas (en minúsculas): ejecutan código, escriben o leen fuera de lo permitido. */
    public const FORBIDDEN = [
        '$where', '$function', '$accumulator', '$graphlookup', '$lookup', '$unionwith', '$merge', '$out',
        '$facet', '$documents', '$collstats', '$indexstats', '$currentop', '$listsessions', '$listlocalsessions',
        '$plancachestats', '$changestream', '$search', '$searchmeta', '$vectorsearch', '$querysettings',
    ];

    public const MAX_STAGES = 30;
    /** $lookup y $unwind multiplican documentos antes del $limit final: se limita cuántos puede usar la IA. */
    public const MAX_LOOKUPS = 4;
    public const MAX_UNWINDS = 4;
    /** Profundidad y tamaño máximos del pipeline (nodos del árbol JSON). */
    public const MAX_DEPTH = 16;
    public const MAX_NODES = 3000;
    /** Longitud máxima de una expresión regular generada. */
    public const MAX_REGEX = 200;
    /** Validez del token del componente en la sesión; se renueva con cada uso. */
    public const TOKEN_TTL = 28800;

    public $id = 'tablefia';
    public $uso = '';
    public $db;
    /** ['coleccion' => ['description', 'fields' => ['campo' => 'descripción'], 'match' => [...], 'hidden' => [...]]] o lista de nombres. */
    public $collections = [];
    /** [['from', 'localField', 'to', 'foreignField', 'as', 'description'], ...] */
    public $relations = [];
    public $instructions = '';
    public $limit = 1000;
    public $exportlimit = 50000;
    public $sample = 20;
    /** Documentos de muestra para inferir los campos cuando no se declaran en 'fields'. */
    public $fieldsample = 200;
    /** Intentos con la IA cuando el pipeline no pasa la validación o MongoDB lo rechaza. */
    public $attempts = 3;
    /** Tiempo máximo de ejecución del pipeline en MongoDB (ms). */
    public $maxtime = 30000;
    public $model = 'claude-opus-5-5';
    public $effort = 'medium';
    public $requireauth = true;
    /** Llamadas a la IA permitidas por usuario: [cantidad, segundos]. */
    public $ratelimit = [30, 3600];
    /** true: el listado muestra las consultas de todos los usuarios para este uso; false: solo las propias. */
    public $shared = true;
    public $caption = 'Pregunte en lenguaje natural';
    public $placeholder = 'Ej. Total vendido por cliente en el último mes, de mayor a menor';

    public function __construct(array $options = [])
    {
        global $config;
        foreach ($options as $option => $value) {
            $this->{$option} = $value;
        }
        $this->db ??= $config['sitedb'];
        if ($this->uso === '') {
            $this->uso = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) . '#' . $this->id;
        }
        $this->collections = self::normalizeCollections($this->collections);
    }

    /**
     * Acepta ['pedidos', 'clientes'] o ['pedidos' => [...]] y devuelve siempre la forma con opciones.
     */
    public static function normalizeCollections(array $collections): array
    {
        $normal = [];
        foreach ($collections as $name => $options) {
            if (is_int($name)) {
                $name = $options;
                $options = [];
            }
            $normal[(string) $name] = (array) $options + ['description' => '', 'fields' => null, 'match' => [], 'hidden' => []];
        }
        return $normal;
    }

    /**
     * Configuración que se guarda en la sesión para /nframework/tablefia.php (el navegador no la puede cambiar).
     */
    public function sessionConfig(): array
    {
        return [
            'uso' => $this->uso,
            'db' => $this->db,
            'collections' => $this->collections,
            'relations' => array_values($this->relations),
            'instructions' => $this->instructions,
            'limit' => (int) $this->limit,
            'exportlimit' => (int) $this->exportlimit,
            'sample' => (int) $this->sample,
            'fieldsample' => (int) $this->fieldsample,
            'attempts' => max(1, min(5, (int) $this->attempts)),
            'maxtime' => max(1000, (int) $this->maxtime),
            'model' => $this->model,
            'effort' => $this->effort,
            'requireauth' => (bool) $this->requireauth,
            'ratelimit' => $this->ratelimit,
            'shared' => (bool) $this->shared,
        ];
    }

    /**
     * Huella de colecciones y relaciones: una consulta guardada con otra configuración se vuelve a validar
     * y no se reutiliza automáticamente para el mismo prompt.
     */
    public static function configHash(array $cfg): string
    {
        return hash('sha256', json_encode([$cfg['collections'], $cfg['relations'], $cfg['instructions']]));
    }

    /**
     * Configuración del componente $id guardada en la sesión, si el token coincide, no caducó y pertenece al
     * usuario actual. Renueva la caducidad. Lanza RuntimeException si no es válida.
     */
    public static function sessionCfg(string $id, string $token): array
    {
        global $user;
        $cfg = $_SESSION['tablefia'][$id] ?? null;
        if (empty($cfg) || $token === '' || !hash_equals((string) $cfg['token'], $token)
            || ($cfg['expires'] ?? 0) < time() || ($cfg['userid'] ?? '') !== (string) $user->_id) {
            throw new RuntimeException('La sesión del componente caducó; recargue la página.');
        }
        if ($cfg['requireauth'] && !$user->isLoggedIn()) {
            throw new RuntimeException('Inicie sesión.');
        }
        $_SESSION['tablefia'][$id]['expires'] = time() + self::TOKEN_TTL;
        addVarToGarbage('tablefia\\' . $id, time() + self::TOKEN_TTL);
        return $cfg;
    }

    /**
     * Clave para reutilizar consultas: minúsculas, sin acentos (la ñ se conserva), sin signos de puntuación
     * y con los espacios colapsados. Los operadores (<, >, =, %) se conservan porque cambian el significado.
     */
    public static function normalizePrompt(string $prompt): string
    {
        $text = mb_strtolower($prompt, 'UTF-8');
        $text = strtr($text, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u']);
        $text = preg_replace('/[¿?¡!.,;:"\'«»“”‘’()\[\]{}…]+/u', ' ', $text);
        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    // ------------------------------------------------------------------------------------------- prompt

    /**
     * Campos de una colección: los declarados en 'fields' o los inferidos de los documentos que cumplen su
     * 'match': una muestra aleatoria de $sample documentos (campos anidados y tipos) más un $group de las claves
     * de primer nivel de los 5000 más recientes, para que aparezcan los campos poco frecuentes.
     */
    public static function describeFields(MongoDB\Collection $collection, array $options, int $sample = 200): array
    {
        if (is_array($options['fields'])) {
            $fields = $options['fields'];
        } else {
            $match = !empty($options['match']) ? [['$match' => $options['match']]] : [];
            $sample = max(10, min(5000, $sample));
            $key = 'tablefia:' . $collection->getDatabaseName() . '.' . $collection->getCollectionName() . ':' . $sample . ':' . md5(serialize($match));
            $fields = nfCacheRemember($key, 3600, function () use ($collection, $match, $sample) {
                $typeMap = ['root' => 'array', 'document' => 'array', 'array' => 'array'];
                $found = [];
                $docs = $collection->aggregate(array_merge($match, [['$sample' => ['size' => $sample]]]), [
                    'typeMap' => $typeMap,
                    'maxTimeMS' => 10000,
                ]);
                foreach ($docs as $doc) {
                    self::collectFields($doc, '', $found, 0);
                }
                try {
                    $keys = $collection->aggregate(array_merge($match, [
                        ['$sort' => ['_id' => -1]],
                        ['$limit' => 5000],
                        ['$project' => ['_id' => 0, 'kv' => ['$objectToArray' => '$$ROOT']]],
                        ['$unwind' => '$kv'],
                        ['$group' => ['_id' => '$kv.k', 'tipos' => ['$addToSet' => ['$type' => '$kv.v']]]],
                        ['$limit' => 500],
                    ]), ['typeMap' => $typeMap, 'maxTimeMS' => 10000]);
                    self::mergeKeys($found, iterator_to_array($keys, false));
                } catch (MongoDB\Driver\Exception\Exception $e) {
                    error_log('TablefIA: no se pudieron leer las claves de ' . $collection->getCollectionName() . ': ' . $e->getMessage());
                }
                ksort($found);
                return $found;
            });
        }
        foreach ((array) $options['hidden'] as $hidden) {
            foreach (array_keys($fields) as $field) {
                if ($field === $hidden || str_starts_with($field, $hidden . '.')) {
                    unset($fields[$field]);
                }
            }
        }
        return $fields;
    }

    /**
     * Agrega a $found las claves de primer nivel ([['_id' => 'campo', 'tipos' => ['string', ...]]]) que la
     * muestra no encontró. Un subdocumento ya descrito por sus campos anidados no se repite.
     */
    public static function mergeKeys(array &$found, array $keys): void
    {
        $names = ['long' => 'int', 'binData' => 'binary', 'missing' => 'null'];
        foreach ($keys as $key) {
            $field = (string) ($key['_id'] ?? '');
            if ($field === '' || isset($found[$field])) {
                continue;
            }
            foreach (array_keys($found) as $known) {
                if (str_starts_with($known, $field . '.')) {
                    continue 2;
                }
            }
            $types = array_unique(array_map(fn($t) => $names[$t] ?? $t, (array) ($key['tipos'] ?? [])));
            sort($types);
            $found[$field] = $types === [] ? 'desconocido' : implode('|', $types);
        }
    }

    private static function collectFields(array $doc, string $prefix, array &$found, int $depth): void
    {
        foreach ($doc as $key => $value) {
            $path = $prefix . $key;
            if (is_array($value) && $value !== [] && !array_is_list($value) && $depth < 3) {
                self::collectFields($value, $path . '.', $found, $depth + 1);
                continue;
            }
            if (is_array($value) && array_is_list($value) && isset($value[0]) && is_array($value[0]) && !array_is_list($value[0]) && $depth < 3) {
                $found[$path] ??= 'arreglo de documentos';
                self::collectFields($value[0], $path . '.', $found, $depth + 1);
                continue;
            }
            $found[$path] ??= self::bsonTypeName($value);
        }
    }

    public static function bsonTypeName($value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => 'bool',
            is_int($value) => 'int',
            is_float($value) => 'double',
            is_string($value) => 'string',
            $value instanceof MongoDB\BSON\UTCDateTime => 'date',
            $value instanceof MongoDB\BSON\ObjectId => 'objectId',
            $value instanceof MongoDB\BSON\Decimal128 => 'decimal',
            is_array($value) => 'array',
            default => 'object',
        };
    }

    /**
     * Prompt de sistema: colecciones con sus campos, relaciones y reglas del pipeline.
     * $fields: ['coleccion' => ['campo' => 'tipo o descripción']].
     */
    public static function systemPrompt(array $cfg, array $fields): string
    {
        $text = "Eres un asistente que convierte preguntas en español sobre una base de datos MongoDB en un pipeline de aggregate.\n\n"
            . "## Colecciones disponibles\n";
        foreach ($cfg['collections'] as $name => $options) {
            $text .= "\n### {$name}" . ($options['description'] !== '' ? " — {$options['description']}" : '') . "\n";
            foreach ($fields[$name] ?? [] as $field => $type) {
                $text .= "- {$field}: {$type}\n";
            }
        }
        if (!empty($cfg['relations'])) {
            $text .= "\n## Relaciones (las únicas permitidas en \$lookup)\n";
            foreach ($cfg['relations'] as $relation) {
                $text .= "- {$relation['from']}.{$relation['localField']} → {$relation['to']}.{$relation['foreignField']}"
                    . (!empty($relation['as']) ? " (as: \"{$relation['as']}\")" : '')
                    . (!empty($relation['description']) ? " — {$relation['description']}" : '') . "\n";
            }
        }
        $text .= "\n## Reglas\n"
            . "- `coleccion` es la colección sobre la que se ejecuta el pipeline; debe ser una de las de arriba.\n"
            . "- Usa solo las etapas " . implode(', ', self::STAGES) . ".\n"
            . "- \$lookup solo con la forma simple {from, localField, foreignField, as} y solo para las relaciones listadas"
            . " (en cualquiera de los dos sentidos). Sin `pipeline` ni `let` dentro de \$lookup.\n"
            . "- `pipeline` es un arreglo JSON de etapas en MongoDB Extended JSON: fechas como {\"\$date\": \"ISO-8601\"},"
            . " ObjectId como {\"\$oid\": \"...\"}.\n"
            . "- La última etapa debe ser un \$project que deje solo campos planos (sin subdocumentos) con exactamente los nombres de"
            . " `columnas`, en el mismo orden, y \"_id\": 0 salvo que el _id sea una de las columnas. Los nombres de columna deben"
            . " ser legibles para el usuario (en español, sin puntos ni \$).\n"
            . "- No agregues \$limit salvo que la pregunta pida una cantidad: el sistema limita las filas.\n"
            . "- `explicacion`: una o dos frases en español para el usuario final sobre qué muestra la consulta y qué supuestos hiciste.\n"
            . "- Si la pregunta no se puede responder con estos datos, devuelve `pipeline` \"[]\", `columnas` vacío y explica por qué.\n";
        if (trim((string) $cfg['instructions']) !== '') {
            $text .= "\n## Indicaciones del sitio\n" . trim((string) $cfg['instructions']) . "\n";
        }
        return $text;
    }

    /**
     * Pide a Claude el pipeline. Devuelve ['coleccion', 'pipeline' (string JSON), 'columnas', 'explicacion', 'usage'].
     * $previous: intentos rechazados [['coleccion', 'pipeline', 'error'], ...] (o uno solo), para que los corrija.
     * $base: consulta guardada ['prompt', 'coleccion', 'pipeline', 'columnas'] que la pregunta pide ajustar.
     */
    public static function askClaude(array $cfg, string $systemPrompt, string $question, ?array $previous = null, ?array $base = null): array
    {
        global $config;
        $apiKey = $config['anthropic_api_key'] ?? getenv('ANTHROPIC_API_KEY');
        if (empty($apiKey)) {
            throw new RuntimeException('Falta configurar anthropic_api_key en config.php.');
        }
        $client = new AnthropicClient(apiKey: $apiKey, requestOptions: ['timeout' => 120]);

        $content = self::userContent($question, $previous, $base);

        try {
            $message = $client->beta->messages->create(
                model: $cfg['model'],
                maxTokens: 16000,
                system: [['type' => 'text', 'text' => $systemPrompt, 'cacheControl' => ['type' => 'ephemeral']]],
                messages: [['role' => 'user', 'content' => $content]],
                outputConfig: [
                    'effort' => $cfg['effort'],
                    'format' => [
                        'type' => 'json_schema',
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'coleccion' => ['type' => 'string', 'enum' => array_keys($cfg['collections'])],
                                'pipeline' => ['type' => 'string'],
                                'columnas' => ['type' => 'array', 'items' => ['type' => 'string']],
                                'explicacion' => ['type' => 'string'],
                            ],
                            'required' => ['coleccion', 'pipeline', 'columnas', 'explicacion'],
                            'additionalProperties' => false,
                        ],
                    ],
                ],
                // Si el modelo declina por políticas, el servidor reintenta con su modelo de respaldo.
                fallbacks: 'default',
                betas: ['server-side-fallback-2026-07-01'],
            );
        } catch (AuthenticationException $e) {
            error_log('TablefIA: ' . $e->getMessage());
            throw new RuntimeException('La llave de la API de Anthropic no es válida.');
        } catch (RateLimitException $e) {
            throw new RuntimeException('El servicio de IA está saturado; intente en unos minutos.');
        } catch (APIStatusException $e) {
            error_log('TablefIA: ' . $e->getMessage());
            throw new RuntimeException('El servicio de IA respondió con un error.');
        } catch (APIConnectionException $e) {
            error_log('TablefIA: ' . $e->getMessage());
            throw new RuntimeException('No se pudo conectar con el servicio de IA.');
        }

        if ($message->stopReason === 'refusal') {
            throw new RuntimeException('La IA no respondió esta pregunta. Reformúlela.');
        }
        if ($message->stopReason !== 'end_turn') {
            throw new RuntimeException('La IA no terminó la respuesta (' . $message->stopReason . ').');
        }
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $data = json_decode($block->text, true);
                if (!is_array($data)) {
                    break;
                }
                $data['usage'] = [
                    'input' => $message->usage->inputTokens,
                    'output' => $message->usage->outputTokens,
                    'cache' => (int) $message->usage->cacheReadInputTokens,
                ];
                $data['modelo'] = $message->model;
                return $data;
            }
        }
        throw new RuntimeException('La IA devolvió una respuesta vacía.');
    }

    /** Mensaje del usuario para la IA: fecha, consulta a ajustar, pregunta e intentos rechazados. */
    public static function userContent(string $question, ?array $previous = null, ?array $base = null): string
    {
        $content = 'Fecha actual: ' . date('Y-m-d') . ' (zona horaria ' . date_default_timezone_get() . ").\n\n";
        if ($base !== null) {
            $content .= "Consulta anterior del usuario: " . $base['prompt'] . "\n"
                . "Colección: " . $base['coleccion'] . "\nPipeline:\n" . $base['pipeline'] . "\n"
                . "Columnas: " . implode(', ', (array) $base['columnas']) . "\n\n"
                . "El usuario pide este ajuste (devuelve la consulta completa ya ajustada): " . $question;
        } else {
            $content .= 'Pregunta: ' . $question;
        }
        if ($previous !== null) {
            $previous = isset($previous['pipeline']) ? [$previous] : $previous;
            foreach (array_values($previous) as $i => $attempt) {
                $content .= "\n\nIntento " . ($i + 1) . ' (colección ' . ($attempt['coleccion'] ?? '?') . "):\n" . $attempt['pipeline']
                    . "\nFue rechazado por: " . $attempt['error'];
            }
            $content .= "\n\nCorrige la consulta respetando las reglas y sin repetir los errores anteriores.";
        }
        return $content;
    }

    // ---------------------------------------------------------------------------------------- validación

    /**
     * Convierte el JSON de la IA (Extended JSON) en etapas PHP. Lanza InvalidArgumentException si no es válido.
     */
    public static function parsePipeline(string $json): array
    {
        try {
            $doc = MongoDB\BSON\Document::fromJSON('{"p":' . $json . '}');
        } catch (Throwable $e) {
            throw new InvalidArgumentException('el pipeline no es JSON válido');
        }
        $pipeline = $doc->toPHP(['root' => 'array', 'document' => 'array', 'array' => 'array'])['p'] ?? null;
        if (!is_array($pipeline) || !array_is_list($pipeline)) {
            throw new InvalidArgumentException('el pipeline debe ser un arreglo de etapas');
        }
        return $pipeline;
    }

    /**
     * Valida las etapas de la IA y devuelve el pipeline completo que se ejecuta sobre $base:
     * restricciones de la colección + etapas (con $lookup restringidos) + $limit.
     */
    public static function buildPipeline(array $cfg, string $base, array $stages, int $limit): array
    {
        if (!isset($cfg['collections'][$base])) {
            throw new InvalidArgumentException("la colección {$base} no está permitida");
        }
        if (count($stages) > self::MAX_STAGES) {
            throw new InvalidArgumentException('el pipeline tiene demasiadas etapas');
        }
        $pipeline = self::restrictions($cfg['collections'][$base]);
        $nodes = 0;
        $counts = ['$lookup' => 0, '$unwind' => 0];
        foreach ($stages as $i => $stage) {
            if (!is_array($stage) || count($stage) !== 1) {
                throw new InvalidArgumentException("la etapa {$i} debe tener exactamente un operador");
            }
            $operator = (string) array_key_first($stage);
            if (!in_array($operator, self::STAGES, true)) {
                throw new InvalidArgumentException("la etapa {$operator} no está permitida");
            }
            $body = $stage[$operator];
            self::assertNoForbidden($body, 0, $nodes);
            if (isset($counts[$operator]) && ++$counts[$operator] > ($operator === '$lookup' ? self::MAX_LOOKUPS : self::MAX_UNWINDS)) {
                throw new InvalidArgumentException("el pipeline tiene demasiadas etapas {$operator}");
            }
            if ($operator === '$lookup') {
                $body = self::restrictLookup($cfg, $base, $body);
            } elseif (in_array($operator, ['$limit', '$skip'], true) && (!is_int($body) || $body < 0)) {
                throw new InvalidArgumentException("{$operator} debe ser un entero positivo");
            }
            $pipeline[] = [$operator => $body];
        }
        $pipeline[] = ['$limit' => max(1, $limit)];
        return $pipeline;
    }

    /** $match obligatorio y $unset de campos ocultos de una colección. */
    private static function restrictions(array $options): array
    {
        $stages = [];
        if (!empty($options['match'])) {
            $stages[] = ['$match' => $options['match']];
        }
        if (!empty($options['hidden'])) {
            $stages[] = ['$unset' => array_values((array) $options['hidden'])];
        }
        return $stages;
    }

    /**
     * Revisa claves y valores: operadores prohibidos, profundidad y tamaño del árbol, expresiones regulares
     * costosas ($regex, $regexMatch/Find/FindAll, /…/ de Extended JSON) y $range con rangos grandes.
     */
    private static function assertNoForbidden($value, int $depth = 0, int &$nodes = 0): void
    {
        if (++$nodes > self::MAX_NODES) {
            throw new InvalidArgumentException('el pipeline es demasiado grande');
        }
        if ($value instanceof MongoDB\BSON\Regex) {
            self::assertSafeRegex($value->getPattern());
            return;
        }
        if (!is_array($value)) {
            return;
        }
        if ($depth > self::MAX_DEPTH) {
            throw new InvalidArgumentException('el pipeline tiene demasiados niveles de anidación');
        }
        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $lower = strtolower($key);
                if (in_array($lower, self::FORBIDDEN, true)) {
                    throw new InvalidArgumentException("el operador {$key} no está permitido");
                }
                if ($lower === '$regex' && is_string($item)) {
                    self::assertSafeRegex($item);
                } elseif (in_array($lower, ['$regexmatch', '$regexfind', '$regexfindall'], true) && is_string($item['regex'] ?? null)) {
                    self::assertSafeRegex($item['regex']);
                } elseif ($lower === '$range' && !(is_array($item) && is_int($item[0] ?? null) && is_int($item[1] ?? null) && abs($item[1] - $item[0]) <= 1000)) {
                    throw new InvalidArgumentException('$range solo se acepta con números fijos y hasta 1000 elementos');
                }
            }
            self::assertNoForbidden($item, $depth + 1, $nodes);
        }
    }

    /** Rechaza patrones largos, con referencias hacia atrás o cuantificadores anidados ((a+)+): retroceso exponencial. */
    public static function assertSafeRegex(string $pattern): void
    {
        if (strlen($pattern) > self::MAX_REGEX) {
            throw new InvalidArgumentException('la expresión regular es demasiado larga');
        }
        if (preg_match('/\\\\[1-9]|\\\\k</', $pattern) || preg_match('/\((?:[^()\\\\]|\\\\.)*[*+}](?:[^()\\\\]|\\\\.)*\)\s*(?:[*+]|\{\d*,)/', $pattern)) {
            throw new InvalidArgumentException('la expresión regular tiene cuantificadores anidados o referencias');
        }
    }

    /**
     * Un $lookup solo es válido si corresponde a una relación declarada, en cualquier sentido, partiendo de la
     * colección base. Se le agregan las restricciones de la colección destino.
     */
    private static function restrictLookup(array $cfg, string $base, $body): array
    {
        if (!is_array($body) || array_diff(array_keys($body), ['from', 'localField', 'foreignField', 'as']) !== []
            || !isset($body['from'], $body['localField'], $body['foreignField'], $body['as'])) {
            throw new InvalidArgumentException('$lookup debe tener solo from, localField, foreignField y as');
        }
        foreach (['from', 'localField', 'foreignField', 'as'] as $key) {
            if (!is_string($body[$key]) || $body[$key] === '' || str_starts_with($body[$key], '$')) {
                throw new InvalidArgumentException("\$lookup.{$key} no es válido");
            }
        }
        $allowed = false;
        foreach ($cfg['relations'] as $relation) {
            // Directo: base.localField → to.foreignField. Inverso: base.foreignField ← from.localField.
            $direct = $relation['from'] === $base && $relation['to'] === $body['from']
                && $relation['localField'] === $body['localField'] && $relation['foreignField'] === $body['foreignField'];
            $inverse = $relation['to'] === $base && $relation['from'] === $body['from']
                && $relation['foreignField'] === $body['localField'] && $relation['localField'] === $body['foreignField'];
            if ($direct || $inverse) {
                $allowed = true;
                break;
            }
        }
        if (!$allowed || !isset($cfg['collections'][$body['from']])) {
            throw new InvalidArgumentException("\$lookup a {$body['from']} no corresponde a una relación permitida");
        }
        $restrictions = self::restrictions($cfg['collections'][$body['from']]);
        if ($restrictions !== []) {
            $body['pipeline'] = $restrictions;
        }
        return $body;
    }

    // ----------------------------------------------------------------------------------------- ejecución

    /**
     * Ejecuta el pipeline y devuelve las filas en el orden de $columns con tipos nativos (ver cellValue()).
     * Sin allowDiskUse: un $sort/$group que exceda la memoria de MongoDB falla en lugar de escribir a disco.
     * $seen recibe las columnas que aparecieron en al menos un documento.
     */
    public static function run(MongoDB\Collection $collection, array $pipeline, array $columns, int $maxTimeMS = 30000, ?array &$seen = null): array
    {
        $rows = [];
        $seen = [];
        $cursor = $collection->aggregate($pipeline, [
            'typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array'],
            'maxTimeMS' => $maxTimeMS,
            'allowDiskUse' => false,
        ]);
        foreach ($cursor as $doc) {
            $row = [];
            foreach ($columns as $column) {
                if (array_key_exists($column, $doc)) {
                    $seen[$column] = true;
                }
                $row[] = self::cellValue($doc[$column] ?? null);
            }
            $rows[] = $row;
        }
        $seen = array_keys($seen);
        return $rows;
    }

    /**
     * Valor de una celda conservando el tipo: null, int, float, string o DateTimeImmutable (fechas, en la zona
     * del sitio). Así la tabla ordena números y fechas correctamente y Excel recibe valores nativos.
     */
    public static function cellValue($value)
    {
        return match (true) {
            $value === null, is_int($value), is_string($value) => $value,
            is_float($value) => is_finite($value) ? $value : (string) $value,
            is_bool($value) => $value ? 'Sí' : 'No',
            $value instanceof MongoDB\BSON\UTCDateTime => DateTimeImmutable::createFromMutable($value->toDateTime())->setTimezone(new DateTimeZone(date_default_timezone_get())),
            $value instanceof MongoDB\BSON\Decimal128 => is_numeric((string) $value) ? (float) (string) $value : (string) $value,
            $value instanceof MongoDB\BSON\Int64 => (int) (string) $value,
            default => self::formatValue($value),
        };
    }

    /** Filas para JSON: las fechas como 'Y-m-d H:i:s' (se ordenan bien como texto); números y null tal cual. */
    public static function jsonRows(array $rows): array
    {
        foreach ($rows as &$row) {
            foreach ($row as &$value) {
                if ($value instanceof DateTimeInterface) {
                    $value = $value->format('Y-m-d H:i:s');
                }
            }
        }
        return $rows;
    }

    public static function formatValue($value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'Sí' : 'No',
            $value instanceof MongoDB\BSON\UTCDateTime => $value->toDateTime()->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i:s'),
            $value instanceof MongoDB\BSON\ObjectId, $value instanceof MongoDB\BSON\Decimal128 => (string) $value,
            is_float($value) => rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.'),
            is_scalar($value) => (string) $value,
            // Arreglos y subdocumentos: Extended JSON sin el envoltorio {"v": ...}.
            default => trim(substr(MongoDB\BSON\Document::fromPHP(['v' => $value])->toRelaxedExtendedJSON(), 7, -2)),
        };
    }

    // -------------------------------------------------------------------------------------------- vista

    public function __toString()
    {
        global $javas, $nframework, $user;
        if ($this->requireauth && !$user->isLoggedIn()) {
            return '<div class="remark warning">Inicie sesión para usar las consultas con IA. '
                . '<a href="/account/login">Iniciar sesión</a></div>';
        }
        // Token nuevo en cada render, ligado al usuario y con caducidad que se renueva con cada uso (sessionCfg()).
        $_SESSION['tablefia'][$this->id] = $this->sessionConfig() + [
            'token' => bin2hex(random_bytes(16)),
            'userid' => (string) $user->_id,
            'expires' => time() + self::TOKEN_TTL,
        ];
        addVarToGarbage('tablefia\\' . $this->id, time() + self::TOKEN_TTL);
        $nframework->csss['006'] = 'https://cdn.datatables.net/v/dt/dt-1.13.8/r-2.5.0/sc-2.2.0/sl-1.7.0/datatables.min.css';
        $nframework->jss['006'] = 'https://cdn.datatables.net/v/dt/dt-1.13.8/r-2.5.0/sc-2.2.0/sl-1.7.0/datatables.min.js';

        $id = htmlspecialchars($this->id);
        $options = json_encode([
            'id' => $this->id,
            'token' => $_SESSION['tablefia'][$this->id]['token'],
            'placeholder' => $this->placeholder,
            'canfav' => isValidObjectId($user->_id),
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
        $javas->addjs('(' . self::script() . ')(' . $options . ');', 'ready');

        $caption = htmlspecialchars($this->caption);
        $placeholder = htmlspecialchars($this->placeholder);
        return <<<HTML
            <div id="{$id}_tablefia" class="tablefia">
                <div class="grid">
                    <div class="row">
                        <div class="cell-md-10">
                            <label>{$caption}</label>
                            <div class="tablefia-base remark info mt-1 mb-1 p-2" style="display:none">
                                <span class="mif-wrench"></span> Ajustando: <strong class="tablefia-base-text"></strong>
                                <button class="button small flat ml-2 tablefia-base-clear" title="Hacer una pregunta nueva"><span class="mif-cross"></span></button>
                            </div>
                            <textarea id="{$id}_prompt" class="tablefia-prompt" data-role="textarea" maxlength="2000" placeholder="{$placeholder}"></textarea>
                        </div>
                        <div class="cell-md-2 d-flex flex-column flex-justify-end">
                            <button class="button primary w-100 tablefia-ask" title="Ctrl+Enter"><span class="mif-search"></span>&nbsp;Consultar</button>
                            <div class="tablefia-busy text-center mt-2" style="display:none">
                                <span class="mif-spinner2 ani-spin"></span> <span class="tablefia-step"></span>
                                <small class="tablefia-elapsed text-muted d-block"></small>
                                <button class="button small flat tablefia-cancel" title="Deja de esperar la respuesta">Cancelar</button>
                            </div>
                        </div>
                    </div>
                    <div class="row tablefia-meta" style="display:none">
                        <div class="cell-md-10">
                            <p class="tablefia-explicacion text-leader2 mb-1"></p>
                            <small class="tablefia-info text-muted"></small> · <small class="tablefia-origen"></small>
                            <details class="mt-1"><summary class="c-pointer">Ver pipeline generado</summary><pre class="tablefia-pipeline border bd-default p-2 mt-1" style="font-size:12px; max-height:360px; overflow:auto; white-space:pre-wrap; word-break:break-word"></pre></details>
                        </div>
                        <div class="cell-md-2 tablefia-export" style="display:none">
                            <a href="#" data-type="csv" class="button w-100"><span class="mif-csv-file"></span>&nbsp;Csv</a>
                            <a href="#" data-type="xlsx" class="button w-100 mt-1"><span class="mif-xls-file"></span>&nbsp;Excel</a>
                            <a href="#" class="button w-100 mt-1 tablefia-chart"><span class="mif-bar-chart"></span>&nbsp;Gráfica</a>
                        </div>
                    </div>
                    <div class="row"><div class="cell tablefia-result"></div></div>
                    <div class="row mt-4">
                        <div class="cell-md-8"><h6>Consultas guardadas</h6></div>
                        <div class="cell-md-4"><input id="{$id}_search" type="text" data-role="input" data-clear-button="true" data-nf-noautofill placeholder="Buscar consultas…"></div>
                    </div>
                    <div class="row">
                        <div class="cell">
                            <table class="table compact striped tablefia-list">
                                <thead><tr><th></th><th>Pregunta</th><th>Fecha / usuario</th><th class="text-right">Usos</th><th></th></tr></thead>
                                <tbody></tbody>
                            </table>
                            <div class="tablefia-pager d-flex flex-justify-end flex-align-center">
                                <button class="button small tablefia-prev" title="Anterior"><span class="mif-chevron-left"></span></button>
                                <small class="tablefia-page mx-2 text-muted"></small>
                                <button class="button small tablefia-next" title="Siguiente"><span class="mif-chevron-right"></span></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        HTML;
    }

    /** JS del componente: function(o) con o = {id, token, placeholder, canfav}. */
    private static function script(): string
    {
        return <<<'JS'
            function(o){
                var id = o.id, token = o.token, url = '/nframework/tablefia.php';
                var table = null, current = null, base = null, page = 0, search = '', xhr = null, timer = null, poller = null, searchTimer = null;
                var $root = $('#' + id + '_tablefia');
                // Por id: Metro copia las clases del textarea/input al <div> que lo envuelve, y .val() de ese div es ''.
                var $prompt = $('#' + id + '_prompt'), $search = $('#' + id + '_search');
                function esc(s) { return $('<div>').text(s === null || s === undefined ? '' : String(s)).html(); }
                function num(n) { return (n || 0).toLocaleString('es-MX'); }
                function post(data) { return $.post(url, $.extend({id: id, token: token}, data), null, 'json'); }
                // Mensaje del servidor aunque la respuesta sea 403/500 (jQuery la manda a .fail()).
                function xhrError(x) { return x && x.responseJSON && x.responseJSON.error ? x.responseJSON.error : null; }
                function fail(msg) { Swal.fire({icon: 'error', title: 'Consulta', text: msg || 'No se pudo completar la consulta.'}); }
                function randomId() {
                    var a = new Uint8Array(16);
                    window.crypto.getRandomValues(a);
                    return Array.prototype.map.call(a, function(b){ return ('0' + b.toString(16)).slice(-2); }).join('');
                }
                // Mientras espera: segundos transcurridos y, si hay id de progreso, la etapa que reporta el servidor.
                function busy(on, progress) {
                    var $b = $root.find('.tablefia-busy');
                    $root.find('button').not('.tablefia-cancel').prop('disabled', on);
                    clearInterval(timer); clearInterval(poller); timer = poller = null;
                    $b.toggle(on);
                    if (!on) { return; }
                    var start = Date.now();
                    $b.find('.tablefia-step').text(progress ? 'Preparando…' : 'Ejecutando…');
                    $b.find('.tablefia-elapsed').text('');
                    timer = setInterval(function(){ $b.find('.tablefia-elapsed').text(Math.round((Date.now() - start) / 1000) + ' s'); }, 1000);
                    if (progress) {
                        poller = setInterval(function(){
                            post({op: 'progress', progress: progress}).done(function(r){ if (poller && r && r.texto) { $b.find('.tablefia-step').text(r.texto); } });
                        }, 1500);
                    }
                }
                // Números como número (DataTables los ordena numéricamente); se formatean solo para mostrarse.
                function cell(d, type) {
                    if (d === null || d === undefined) { return ''; }
                    if (type === 'display') { return esc(typeof d === 'number' ? d.toLocaleString('es-MX', {maximumFractionDigits: 6}) : d); }
                    return type === 'filter' ? String(d) : d;
                }
                function setBase(q) {
                    base = q;
                    var $b = $root.find('.tablefia-base').toggle(!!q);
                    if (q) {
                        $b.find('.tablefia-base-text').text(q.titulo || q.prompt);
                        $prompt.val('').attr('placeholder', 'Describa el ajuste, p. ej. «solo los de 2025» o «agregar el total por mes»').trigger('input').focus();
                    } else {
                        $prompt.attr('placeholder', o.placeholder);
                    }
                }
                function show(r) {
                    current = r.query._id;
                    $root.find('.tablefia-explicacion').text(r.query.explicacion || '');
                    $root.find('.tablefia-pipeline').text(r.query.pipeline || '');
                    $root.find('.tablefia-info').text(r.rows.length + ' fila(s)' + (r.truncated ? ' (se muestran las primeras ' + r.rows.length + ')' : ''));
                    var $origen = $root.find('.tablefia-origen').removeClass('fg-green fg-cyan');
                    if (r.reused) {
                        $origen.addClass('fg-green').text('Consulta guardada: sin costo de IA');
                    } else {
                        var t = r.query.tokens || {};
                        $origen.addClass('fg-cyan').text('Generada por IA' + (r.query.intentos > 1 ? ' en ' + r.query.intentos + ' intentos' : '')
                            + ': ' + num(t.input) + ' tokens de entrada' + (t.cache ? ' (' + num(t.cache) + ' de caché)' : '') + ' y ' + num(t.output) + ' de salida');
                    }
                    $root.find('.tablefia-meta, .tablefia-export').show();
                    if (table) { table.destroy(); $root.find('.tablefia-result').empty(); }
                    var $t = $('<table class="table striped compact display"></table>').appendTo($root.find('.tablefia-result'));
                    table = $t.DataTable({
                        data: r.rows,
                        columns: r.query.columnas.map(function(c, i){
                            var numeric = r.rows.some(function(row){ return typeof row[i] === 'number'; })
                                && r.rows.every(function(row){ return row[i] === null || typeof row[i] === 'number'; });
                            return {title: esc(c), render: cell, className: numeric ? 'dt-right' : ''};
                        }),
                        pageLength: 25, responsive: true, language: {url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-MX.json'}
                    });
                    list();
                }
                function list() {
                    var $tb = $root.find('.tablefia-list tbody');
                    function listError(msg) {
                        $tb.empty().append($('<tr>').append($('<td colspan="5" class="text-muted">').text(msg || 'No se pudo cargar el listado.')));
                    }
                    post({op: 'list', q: search, page: page}).fail(function(x){ listError(xhrError(x)); }).done(function(r){
                        $tb.empty();
                        if (r.error) { listError(r.error); return; }
                        var pages = Math.max(1, Math.ceil(r.total / r.size));
                        $root.find('.tablefia-page').text('Página ' + (r.page + 1) + ' de ' + pages + ' · ' + r.total + ' consulta(s)');
                        $root.find('.tablefia-prev').prop('disabled', r.page === 0);
                        $root.find('.tablefia-next').prop('disabled', r.page + 1 >= pages);
                        if (!r.queries.length) { listError(search ? 'Sin coincidencias.' : 'Aún no hay consultas guardadas.'); }
                        $.each(r.queries, function(i, q){
                            var $tr = $('<tr>').toggleClass('bg-light', q._id === current);
                            var $fav = $('<button class="button small flat">').attr('title', q.favorita ? 'Quitar de favoritas' : 'Marcar como favorita')
                                .append($('<span>').addClass(q.favorita ? 'mif-star-full fg-orange' : 'mif-star-empty'))
                                .prop('disabled', !o.canfav)
                                .on('click', function(){ post({op: 'favorite', query: q._id, on: q.favorita ? 0 : 1}).done(list); });
                            $('<td>').append($fav).appendTo($tr);
                            var $p = $('<td>').attr('title', q.explicacion).append($('<span>').text(q.titulo || q.prompt)).appendTo($tr);
                            if (q.titulo) { $p.append($('<br>'), $('<small class="text-muted">').text(q.prompt)); }
                            $('<td>').append($('<small>').text(q.fecha), $('<br>'), $('<small class="text-muted">').text(q.usuario)).appendTo($tr);
                            $('<td class="text-right">').text(q.ejecuciones).appendTo($tr);
                            var $a = $('<td class="text-right" style="white-space:nowrap">').appendTo($tr);
                            var btn = function(cls, icon, title, fn) {
                                $('<button class="button small ml-1">').addClass(cls).attr('title', title).append($('<span>').addClass(icon)).on('click', fn).appendTo($a);
                            };
                            btn('primary', 'mif-play', 'Ejecutar', function(){ run(q._id); });
                            btn('', 'mif-wrench', 'Ajustar con IA a partir de esta consulta', function(){ setBase(q); });
                            btn('', 'mif-pencil', 'Copiar la pregunta para escribir una nueva', function(){ setBase(null); $prompt.val(q.prompt).trigger('input').focus(); });
                            if (q.puedeeditar) {
                                btn('', 'mif-tag', 'Renombrar', function(){
                                    Swal.fire({title: 'Nombre de la consulta', input: 'text', inputValue: q.titulo || q.prompt, inputAttributes: {maxlength: 200},
                                        showCancelButton: true, confirmButtonText: 'Guardar', cancelButtonText: 'Cancelar'}).then(function(res){
                                        if (res.isConfirmed) { post({op: 'rename', query: q._id, titulo: res.value}).done(function(x){ x.error ? fail(x.error) : list(); }); }
                                    });
                                });
                                btn('alert', 'mif-bin', 'Eliminar', function(){
                                    Swal.fire({icon: 'warning', title: '¿Eliminar esta consulta guardada?', showCancelButton: true, confirmButtonText: 'Eliminar', cancelButtonText: 'Cancelar'}).then(function(res){
                                        if (res.isConfirmed) { post({op: 'delete', query: q._id}).done(list); }
                                    });
                                });
                            }
                            $tb.append($tr);
                        });
                    });
                }
                function run(q) {
                    busy(true);
                    post({op: 'run', query: q}).done(function(r){ r.error ? fail(r.error) : show(r); }).fail(function(x){ fail(xhrError(x)); }).always(function(){ busy(false); });
                }
                function ask() {
                    var prompt = $.trim($prompt.val());
                    if (prompt === '') {
                        Swal.fire({icon: 'info', title: 'Consulta', text: base ? 'Describa el ajuste.' : 'Escriba una pregunta.'});
                        return;
                    }
                    var progress = randomId();
                    busy(true, progress);
                    xhr = post({op: 'ask', prompt: prompt, progress: progress, base: base ? base._id : ''});
                    xhr.done(function(r){ if (r.error) { fail(r.error); } else { setBase(null); show(r); } })
                        .fail(function(x, status){ if (status !== 'abort') { fail(xhrError(x)); } })
                        .always(function(){ xhr = null; busy(false); });
                }
                $root.find('.tablefia-ask').on('click', function(e){ e.preventDefault(); ask(); });
                $prompt.on('keydown', function(e){ if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); ask(); } });
                // Solo deja de esperar: el servidor puede terminar y guardar la consulta, que aparecerá en el listado.
                $root.find('.tablefia-cancel').on('click', function(e){ e.preventDefault(); if (xhr) { xhr.abort(); } setTimeout(list, 500); });
                $root.find('.tablefia-base-clear').on('click', function(e){ e.preventDefault(); setBase(null); });
                $search.on('input change', function(){
                    clearTimeout(searchTimer);
                    searchTimer = setTimeout(function(){ search = $.trim($search.val()); page = 0; list(); }, 350);
                });
                $root.find('.tablefia-prev').on('click', function(e){ e.preventDefault(); if (page > 0) { page--; list(); } });
                $root.find('.tablefia-next').on('click', function(e){ e.preventDefault(); page++; list(); });
                $root.find('.tablefia-export a[data-type]').on('click', function(e){
                    e.preventDefault();
                    if (current) { window.open(url + '?op=export&id=' + encodeURIComponent(id) + '&token=' + token + '&query=' + current + '&type=' + $(this).data('type')); }
                });
                $root.find('.tablefia-chart').on('click', function(e){
                    e.preventDefault();
                    if (current) { window.open('/nframework/tablefg.php?source=tablefia&id=' + encodeURIComponent(id) + '&token=' + token + '&query=' + current); }
                });
                list();
            }
        JS;
    }
}
