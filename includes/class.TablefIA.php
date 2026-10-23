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

    // ------------------------------------------------------------------------------------------- prompt

    /**
     * Campos de una colección: los declarados en 'fields' o los que aparecen en una muestra de documentos.
     */
    public static function describeFields(MongoDB\Collection $collection, array $options): array
    {
        if (is_array($options['fields'])) {
            $fields = $options['fields'];
        } else {
            $key = 'tablefia:' . $collection->getDatabaseName() . '.' . $collection->getCollectionName();
            $fields = nfCacheRemember($key, 3600, function () use ($collection) {
                $found = [];
                $docs = $collection->aggregate([['$sample' => ['size' => 50]]], [
                    'typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array'],
                    'maxTimeMS' => 10000,
                ]);
                foreach ($docs as $doc) {
                    self::collectFields($doc, '', $found, 0);
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
     * $previous: ['pipeline' => string, 'error' => string] de un intento rechazado, para que lo corrija.
     */
    public static function askClaude(array $cfg, string $systemPrompt, string $question, ?array $previous = null): array
    {
        global $config;
        $apiKey = $config['anthropic_api_key'] ?? getenv('ANTHROPIC_API_KEY');
        if (empty($apiKey)) {
            throw new RuntimeException('Falta configurar anthropic_api_key en config.php.');
        }
        $client = new AnthropicClient(apiKey: $apiKey, requestOptions: ['timeout' => 120]);

        $content = 'Fecha actual: ' . date('Y-m-d') . ' (zona horaria ' . date_default_timezone_get() . ").\n\nPregunta: " . $question;
        if ($previous !== null) {
            $content .= "\n\nUn intento anterior produjo este pipeline:\n" . $previous['pipeline']
                . "\n\nFue rechazado por: " . $previous['error'] . "\nCorrígelo respetando las reglas.";
        }

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
        foreach ($stages as $i => $stage) {
            if (!is_array($stage) || count($stage) !== 1) {
                throw new InvalidArgumentException("la etapa {$i} debe tener exactamente un operador");
            }
            $operator = (string) array_key_first($stage);
            if (!in_array($operator, self::STAGES, true)) {
                throw new InvalidArgumentException("la etapa {$operator} no está permitida");
            }
            $body = $stage[$operator];
            self::assertNoForbidden($body);
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

    private static function assertNoForbidden($value): void
    {
        if (!is_array($value)) {
            return;
        }
        foreach ($value as $key => $item) {
            if (is_string($key) && in_array(strtolower($key), self::FORBIDDEN, true)) {
                throw new InvalidArgumentException("el operador {$key} no está permitido");
            }
            self::assertNoForbidden($item);
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
     * Ejecuta el pipeline y devuelve las filas como texto, en el orden de $columns.
     */
    public static function run(MongoDB\Collection $collection, array $pipeline, array $columns): array
    {
        $rows = [];
        $cursor = $collection->aggregate($pipeline, [
            'typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array'],
            'maxTimeMS' => 30000,
        ]);
        foreach ($cursor as $doc) {
            $row = [];
            foreach ($columns as $column) {
                $row[] = self::formatValue($doc[$column] ?? null);
            }
            $rows[] = $row;
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
        $_SESSION['tablefia'][$this->id] = $this->sessionConfig() + ['token' => bin2hex(random_bytes(16))];
        addVarToGarbage('tablefia\\' . $this->id, time() + 60 * 60 * 8);
        $nframework->csss['006'] = 'https://cdn.datatables.net/v/dt/dt-1.13.8/r-2.5.0/sc-2.2.0/sl-1.7.0/datatables.min.css';
        $nframework->jss['006'] = 'https://cdn.datatables.net/v/dt/dt-1.13.8/r-2.5.0/sc-2.2.0/sl-1.7.0/datatables.min.js';

        $id = htmlspecialchars($this->id);
        $js = json_encode($this->id);
        $token = json_encode($_SESSION['tablefia'][$this->id]['token']);
        $javas->addjs(<<<JS
            (function(){
                var id = {$js}, token = {$token}, url = '/nframework/tablefia.php', table = null, current = null;
                var \$root = $('#' + id + '_tablefia');
                // Por id: Metro copia las clases del textarea al <div> que lo envuelve, y .val() de ese div es ''.
                var \$prompt = $('#' + id + '_prompt');
                function post(data) {
                    return $.post(url, $.extend({id: id, token: token}, data), null, 'json');
                }
                function busy(on) {
                    \$root.find('.tablefia-busy').toggle(on);
                    \$root.find('button').prop('disabled', on);
                }
                // Mensaje del servidor aunque la respuesta sea 403/500 (jQuery la manda a .fail()).
                function xhrError(xhr) {
                    return xhr && xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : null;
                }
                function fail(msg) {
                    Swal.fire({icon: 'error', title: 'Consulta', text: msg || 'No se pudo completar la consulta.'});
                }
                function show(r) {
                    current = r.query._id;
                    \$root.find('.tablefia-explicacion').text(r.query.explicacion || '');
                    \$root.find('.tablefia-pipeline').text(r.query.pipeline || '');
                    \$root.find('.tablefia-info').text(r.rows.length + ' fila(s)' + (r.truncated ? ' (se muestran las primeras ' + r.rows.length + ')' : '') + (r.reused ? ' · consulta reutilizada' : ''));
                    \$root.find('.tablefia-meta, .tablefia-export').show();
                    if (table) { table.destroy(); \$root.find('.tablefia-result').empty(); }
                    var \$t = $('<table class="table striped compact display"></table>').appendTo(\$root.find('.tablefia-result'));
                    table = \$t.DataTable({
                        data: r.rows,
                        columns: r.query.columnas.map(function(c){ return {title: $('<div>').text(c).html(), render: $.fn.dataTable.render.text()}; }),
                        pageLength: 25, responsive: true, language: {url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-MX.json'}
                    });
                    list();
                }
                function list() {
                    var \$tb = \$root.find('.tablefia-list tbody');
                    function listError(msg) {
                        \$tb.empty().append($('<tr>').append($('<td colspan="5" class="text-muted">').text(msg || 'No se pudo cargar el listado.')));
                    }
                    post({op: 'list'}).fail(function(xhr){ listError(xhrError(xhr)); }).done(function(r){
                        \$tb.empty();
                        if (r.error) { listError(r.error); return; }
                        $.each(r.queries, function(i, q){
                            var \$tr = $('<tr>').toggleClass('bg-light', q._id === current);
                            $('<td>').text(q.fecha).appendTo(\$tr);
                            $('<td>').text(q.prompt).attr('title', q.explicacion).appendTo(\$tr);
                            $('<td>').text(q.usuario).appendTo(\$tr);
                            $('<td class="text-right">').text(q.ejecuciones).appendTo(\$tr);
                            var \$a = $('<td class="text-right">').appendTo(\$tr);
                            $('<button class="button small primary" title="Ejecutar"><span class="mif-play"></span></button>').on('click', function(){ run(q._id); }).appendTo(\$a);
                            $('<button class="button small ml-1" title="Usar como base"><span class="mif-pencil"></span></button>').on('click', function(){ \$prompt.val(q.prompt).trigger('input').focus(); }).appendTo(\$a);
                            if (q.puedeborrar) {
                                $('<button class="button small alert ml-1" title="Eliminar"><span class="mif-bin"></span></button>').on('click', function(){
                                    if (confirm('¿Eliminar esta consulta guardada?')) { post({op: 'delete', query: q._id}).done(list); }
                                }).appendTo(\$a);
                            }
                            \$tb.append(\$tr);
                        });
                    });
                }
                function run(q) {
                    busy(true);
                    post({op: 'run', query: q}).done(function(r){ r.error ? fail(r.error) : show(r); }).fail(function(xhr){ fail(xhrError(xhr)); }).always(function(){ busy(false); });
                }
                \$root.find('.tablefia-ask').on('click', function(e){
                    e.preventDefault();
                    var prompt = $.trim(\$prompt.val());
                    if (prompt === '') {
                        Swal.fire({icon: 'info', title: 'Consulta', text: 'Escriba una pregunta.'});
                        return;
                    }
                    busy(true);
                    post({op: 'ask', prompt: prompt}).done(function(r){ r.error ? fail(r.error) : show(r); }).fail(function(xhr){ fail(xhrError(xhr)); }).always(function(){ busy(false); });
                });
                \$root.find('.tablefia-export a[data-type]').on('click', function(e){
                    e.preventDefault();
                    if (current) { window.open(url + '?op=export&id=' + encodeURIComponent(id) + '&token=' + token + '&query=' + current + '&type=' + $(this).data('type')); }
                });
                \$root.find('.tablefia-chart').on('click', function(e){
                    e.preventDefault();
                    if (current) { window.open('/nframework/tablefg.php?source=tablefia&id=' + encodeURIComponent(id) + '&token=' + token + '&query=' + current); }
                });
                list();
            })();
        JS, 'ready');

        $caption = htmlspecialchars($this->caption);
        $placeholder = htmlspecialchars($this->placeholder);
        return <<<HTML
            <div id="{$id}_tablefia" class="tablefia">
                <div class="grid">
                    <div class="row">
                        <div class="cell-md-10">
                            <label>{$caption}</label>
                            <textarea id="{$id}_prompt" class="tablefia-prompt" data-role="textarea" maxlength="2000" placeholder="{$placeholder}"></textarea>
                        </div>
                        <div class="cell-md-2 d-flex flex-column flex-justify-end">
                            <button class="button primary w-100 tablefia-ask"><span class="mif-search"></span>&nbsp;Consultar</button>
                            <div class="tablefia-busy text-center mt-2" style="display:none"><span class="mif-spinner2 ani-spin"></span> Generando…</div>
                        </div>
                    </div>
                    <div class="row tablefia-meta" style="display:none">
                        <div class="cell-md-10">
                            <p class="tablefia-explicacion text-leader2 mb-1"></p>
                            <small class="tablefia-info text-muted"></small>
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
                        <div class="cell">
                            <h6>Consultas guardadas</h6>
                            <table class="table compact striped tablefia-list">
                                <thead><tr><th>Fecha</th><th>Pregunta</th><th>Usuario</th><th class="text-right">Usos</th><th></th></tr></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        HTML;
    }
}
