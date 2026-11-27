<?php

use Anthropic\Client as AnthropicClient;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\RateLimitException;

/**
 * Proveedores de IA del sitio y una sola llamada para pedirles JSON con un esquema: Anthropic (SDK oficial),
 * OpenAI, Google Gemini y cualquier servidor compatible con la API de OpenAI (Ollama, LM Studio, vLLM,
 * OpenRouter, Groq, Mistral, DeepSeek...). Lo usa TablefIA; sirve para cualquier extracción estructurada.
 *
 * El administrador da de alta los proveedores en Admin → IA (configs/site → ai_providers) a partir del
 * catálogo de catalog(); uno es el predeterminado. Sin proveedores configurados se usa anthropic_api_key de
 * config.php (o ANTHROPIC_API_KEY), como antes.
 *
 *   $p = nfAI::provider();                     // predeterminado; nfAI::provider('ollama') = uno en particular
 *   $r = nfAI::json($p, 'Extrae los datos de la factura.', $texto, [
 *       'type' => 'object',
 *       'properties' => ['rfc' => ['type' => 'string'], 'total' => ['type' => 'number']],
 *       'required' => ['rfc', 'total'],
 *       'additionalProperties' => false,
 *   ]);
 *   $r['data']['rfc']; $r['usage']['input']; $r['model'];
 *
 * Llaves: cada proveedor guarda la suya cifrada (AES-256-GCM) con $config['ai_key'], que va solo en
 * config.php: un respaldo de la base no basta para usarlas. También acepta 'env:VARIABLE' para leerla del
 * entorno sin guardarla. Ver docs/ai.php.
 *
 * Proveedor: {id, name, preset, type: anthropic|openai|gemini|openai_compatible, base_url, model, api_key
 * (cifrada, 'env:...' o ''), effort, json_mode: schema|object|prompt, max_tokens, timeout, default}.
 */
class nfAI
{
    /** Tipos de API que sabe llamar. */
    public const TYPES = ['anthropic', 'openai', 'gemini', 'openai_compatible'];
    /** Cómo se pide el JSON: esquema estricto, objeto JSON libre o solo instrucciones en el prompt. */
    public const JSON_MODES = ['schema', 'object', 'prompt'];

    /**
     * Configuraciones disponibles para dar de alta un proveedor (Admin → IA). Los modelos son sugerencias:
     * escriba el que tenga contratado o instalado.
     *
     * @return array<string, array{type: string, label: string, base_url: string, models: list<string>, key: string, json_mode: string, json_modes: list<string>, effort: list<string>, help: string}>
     */
    public static function catalog(): array
    {
        $compat = ['type' => 'openai_compatible', 'models' => [], 'json_mode' => 'schema', 'json_modes' => self::JSON_MODES, 'effort' => []];
        return [
            'anthropic' => ['type' => 'anthropic', 'label' => 'Anthropic Claude', 'base_url' => 'https://api.anthropic.com',
                'models' => ['claude-opus-5-5', 'claude-sonnet-5-5', 'claude-haiku-5-5'], 'key' => 'required',
                'json_mode' => 'schema', 'json_modes' => ['schema'], 'effort' => ['low', 'medium', 'high', 'xhigh', 'max'],
                'help' => 'Llave en platform.claude.com → API keys. Salida estructurada con esquema y respaldo automático si el modelo declina.'],
            'openai' => ['type' => 'openai', 'label' => 'OpenAI', 'base_url' => 'https://api.openai.com/v1',
                'models' => [], 'key' => 'required', 'json_mode' => 'schema', 'json_modes' => self::JSON_MODES,
                'effort' => ['low', 'medium', 'high'],
                'help' => 'Llave en platform.openai.com → API keys. Usa /chat/completions con response_format json_schema; el esfuerzo solo aplica a modelos de razonamiento.'],
            'gemini' => ['type' => 'gemini', 'label' => 'Google Gemini', 'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
                // «Objeto» por omisión: responseMimeType JSON está documentado; responseJsonSchema (modo «esquema») no
                // está claro en la documentación de generateContent y su Schema propio no admite additionalProperties.
                'models' => [], 'key' => 'required', 'json_mode' => 'object', 'json_modes' => self::JSON_MODES, 'effort' => [],
                'help' => 'Llave en aistudio.google.com → API keys. Usa generateContent con respuesta JSON y el esquema en las instrucciones; pruebe el modo «esquema estricto» (responseJsonSchema) antes de usarlo.'],
            'ollama' => $compat + ['label' => 'Ollama (local)', 'base_url' => 'http://127.0.0.1:11434/v1', 'key' => 'none',
                'help' => 'Modelos locales: ollama pull <modelo>. No necesita llave. Elija un modelo con buen soporte de JSON.'],
            'lmstudio' => $compat + ['label' => 'LM Studio (local)', 'base_url' => 'http://127.0.0.1:1234/v1', 'key' => 'none',
                'help' => 'Active el servidor en LM Studio → Developer. No necesita llave.'],
            'vllm' => $compat + ['label' => 'vLLM (servidor propio)', 'base_url' => 'http://127.0.0.1:8000/v1', 'key' => 'optional',
                'help' => 'vllm serve <modelo>; llave solo si arrancó con --api-key.'],
            'openrouter' => $compat + ['label' => 'OpenRouter', 'base_url' => 'https://openrouter.ai/api/v1', 'key' => 'required',
                'help' => 'Un solo endpoint para modelos de varios proveedores; el modelo va como proveedor/modelo.'],
            'groq' => $compat + ['label' => 'Groq', 'base_url' => 'https://api.groq.com/openai/v1', 'key' => 'required',
                'help' => 'API compatible con OpenAI de Groq.'],
            'mistral' => $compat + ['label' => 'Mistral AI', 'base_url' => 'https://api.mistral.ai/v1', 'key' => 'required',
                'help' => 'API de Mistral (compatible con /chat/completions).'],
            'deepseek' => ['json_mode' => 'object'] + $compat + ['label' => 'DeepSeek', 'base_url' => 'https://api.deepseek.com', 'key' => 'required',
                'help' => 'Solo admite JSON libre (json_object): el esquema va en las instrucciones.'],
            'compatible' => $compat + ['label' => 'Otro compatible con OpenAI', 'base_url' => '', 'key' => 'optional',
                'help' => 'Cualquier servidor con /chat/completions. Si rechaza response_format, cambie el modo JSON a «objeto» o «instrucciones».'],
        ];
    }

    /**
     * Proveedores configurados, normalizados (con la llave aún cifrada). Sin ninguno, el de
     * anthropic_api_key de config.php si existe.
     *
     * @global class_config $config
     * @return list<array<string, mixed>>
     */
    public static function providers(): array
    {
        global $config;
        $list = [];
        $raw = $config['ai_providers'] ?? [];
        // De MongoDB llegan como BSONArray/BSONDocument.
        $raw = json_decode((string) json_encode($raw), true);
        foreach (is_array($raw) ? $raw : [] as $p) {
            if (is_array($p) && is_string($p['id'] ?? null) && in_array($p['type'] ?? null, self::TYPES, true)) {
                $list[] = self::normalize($p);
            }
        }
        if ($list === []) {
            $key = $config['anthropic_api_key'] ?? getenv('ANTHROPIC_API_KEY');
            if (!empty($key)) {
                $list[] = self::normalize([
                    'id' => 'anthropic', 'name' => 'Anthropic (config.php)', 'preset' => 'anthropic', 'type' => 'anthropic',
                    'model' => 'claude-opus-5-5', 'effort' => 'medium', 'default' => true, 'legacy' => true,
                ]);
            }
        }
        return $list;
    }

    /**
     * Proveedor por id, o el predeterminado con '' (el marcado default, si no el primero).
     *
     * @param string $id
     * @return array<string, mixed>
     * @throws RuntimeException Si no hay proveedores o el id no existe.
     */
    public static function provider(string $id = ''): array
    {
        return self::pick(self::providers(), $id);
    }

    /**
     * Elige de $list el proveedor $id o el predeterminado (parte pura de provider()).
     *
     * @param list<array<string, mixed>> $list
     * @param string $id
     * @return array<string, mixed>
     * @throws RuntimeException
     */
    public static function pick(array $list, string $id = ''): array
    {
        if ($list === []) {
            throw new RuntimeException('No hay proveedores de IA configurados: agréguelos en Admin → IA (o defina anthropic_api_key en config.php).');
        }
        foreach ($list as $p) {
            if ($id === '' ? !empty($p['default']) : $p['id'] === $id) {
                return $p;
            }
        }
        if ($id !== '') {
            throw new RuntimeException('El proveedor de IA «' . $id . '» no está configurado (Admin → IA).');
        }
        return $list[0];
    }

    /**
     * Completa un proveedor con los valores del catálogo y fuerza tipos.
     *
     * @param array<string, mixed> $p
     * @return array<string, mixed>
     */
    public static function normalize(array $p): array
    {
        $catalog = self::catalog();
        $type = in_array($p['type'] ?? null, self::TYPES, true) ? $p['type'] : 'openai_compatible';
        $preset = is_string($p['preset'] ?? null) && isset($catalog[$p['preset']]) && $catalog[$p['preset']]['type'] === $type
            ? $p['preset'] : ($type === 'openai_compatible' ? 'compatible' : $type);
        $def = $catalog[$preset];
        $str = fn ($v, string $fallback = '') => is_string($v) && $v !== '' ? $v : $fallback;
        $jsonMode = in_array($p['json_mode'] ?? null, $def['json_modes'], true) ? $p['json_mode'] : $def['json_mode'];
        return [
            'id' => (string) ($p['id'] ?? ''),
            'name' => $str($p['name'] ?? null, $def['label']),
            'preset' => $preset,
            'type' => $type,
            'base_url' => rtrim($str($p['base_url'] ?? null, $def['base_url']), '/'),
            'model' => $str($p['model'] ?? null, $def['models'][0] ?? ''),
            'api_key' => (string) ($p['api_key'] ?? ''),
            'effort' => is_string($p['effort'] ?? null) && in_array($p['effort'], $def['effort'], true) ? $p['effort'] : '',
            'json_mode' => $jsonMode,
            'max_tokens' => max(256, min(128000, (int) ($p['max_tokens'] ?? 16000) ?: 16000)),
            'timeout' => max(5, min(600, (int) ($p['timeout'] ?? 120) ?: 120)),
            'default' => !empty($p['default']),
            'legacy' => !empty($p['legacy']),
        ];
    }

    /**
     * Errores de un proveedor antes de guardarlo ('' = válido). $existing: ids ya usados por otros.
     *
     * @param array<string, mixed> $p Ya normalizado.
     * @param list<string> $existing
     * @return string
     */
    public static function validationError(array $p, array $existing = []): string
    {
        if (!preg_match('/^[a-z0-9][a-z0-9_-]{0,31}$/', (string) $p['id'])) {
            return 'El identificador solo admite minúsculas, dígitos, - y _ (hasta 32).';
        }
        if (in_array($p['id'], $existing, true)) {
            return 'Ya hay un proveedor con el identificador «' . $p['id'] . '».';
        }
        $url = parse_url((string) $p['base_url']);
        if (!is_array($url) || !in_array($url['scheme'] ?? '', ['http', 'https'], true) || empty($url['host']) || isset($url['user']) || isset($url['query'])) {
            return 'La URL base debe ser http(s)://servidor[:puerto]/ruta, sin usuario ni parámetros.';
        }
        if (!preg_match('/^[A-Za-z0-9._:\/@+-]{1,200}$/', (string) $p['model'])) {
            return 'Escriba el modelo (sin espacios).';
        }
        $required = self::catalog()[$p['preset']]['key'] ?? 'optional';
        if ($required === 'required' && $p['api_key'] === '') {
            return 'Este proveedor necesita una llave de API.';
        }
        return '';
    }

    /**
     * Cifra una llave para guardarla en configs (o la deja igual si es 'env:...' o '').
     *
     * @param string $plain
     * @param string|null $secret 32 bytes; null = secretKey().
     * @return string 'enc:' . base64(IV . TAG . datos), 'env:VAR' o ''.
     * @throws RuntimeException Sin ai_key en config.php.
     */
    public static function encryptKey(string $plain, ?string $secret = null): string
    {
        if ($plain === '' || str_starts_with($plain, 'env:')) {
            return $plain;
        }
        $secret ??= self::secretKey();
        $iv = random_bytes(12);
        $tag = '';
        $data = openssl_encrypt($plain, 'aes-256-gcm', $secret, OPENSSL_RAW_DATA, $iv, $tag, 'nfai');
        if ($data === false) {
            throw new RuntimeException('No se pudo cifrar la llave.');
        }
        return 'enc:' . base64_encode($iv . $tag . $data);
    }

    /**
     * Llave en claro de un proveedor: descifra 'enc:...', lee 'env:VAR' del entorno.
     *
     * @param string $stored
     * @param string|null $secret null = secretKey() (solo si hace falta).
     * @return string
     * @throws RuntimeException Si no se puede descifrar.
     */
    public static function decryptKey(string $stored, ?string $secret = null): string
    {
        if ($stored === '') {
            return '';
        }
        if (str_starts_with($stored, 'env:')) {
            return (string) getenv(substr($stored, 4));
        }
        if (!str_starts_with($stored, 'enc:')) {
            throw new RuntimeException('Llave de IA con formato desconocido: vuelva a escribirla en Admin → IA.');
        }
        $raw = base64_decode(substr($stored, 4), true);
        $plain = $raw !== false && strlen($raw) > 28
            ? openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $secret ?? self::secretKey(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16), 'nfai')
            : false;
        if ($plain === false) {
            throw new RuntimeException('No se pudo descifrar la llave de IA (¿cambió ai_key en config.php?). Vuelva a escribirla en Admin → IA.');
        }
        return $plain;
    }

    /**
     * Llave del proveedor $p lista para usar (el legado lee anthropic_api_key / ANTHROPIC_API_KEY).
     *
     * @global class_config $config
     * @param array<string, mixed> $p
     * @return string
     */
    public static function apiKey(array $p): string
    {
        global $config;
        if (!empty($p['legacy'])) {
            return (string) ($config['anthropic_api_key'] ?? getenv('ANTHROPIC_API_KEY'));
        }
        return self::decryptKey((string) $p['api_key']);
    }

    /**
     * Llave para mostrar: últimos 4 caracteres, 'env:VAR' tal cual o ''.
     *
     * @param string $stored
     * @return string
     */
    public static function maskKey(string $stored): string
    {
        if ($stored === '' || str_starts_with($stored, 'env:')) {
            return $stored;
        }
        try {
            $plain = self::decryptKey($stored);
            return '…' . substr($plain, -4);
        } catch (RuntimeException $e) {
            return '(no se puede descifrar)';
        }
    }

    /**
     * true si config.php tiene ai_key válida (para guardar llaves cifradas).
     *
     * @return bool
     */
    public static function hasSecret(): bool
    {
        try {
            self::secretKey();
            return true;
        } catch (RuntimeException $e) {
            return false;
        }
    }

    /**
     * $config['ai_key'] (32 bytes en base64), solo del archivo config.php.
     *
     * @global class_config $config
     * @return string
     * @throws RuntimeException
     */
    public static function secretKey(): string
    {
        global $config;
        $value = $config instanceof class_config ? $config->fileValue('ai_key') : ($config['ai_key'] ?? '');
        $key = base64_decode((string) $value, true);
        if ($key === false || strlen($key) !== 32) {
            throw new RuntimeException("Configure ai_key en includes/config.php para guardar llaves de IA: php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'");
        }
        return $key;
    }

    /**
     * Pide al proveedor una respuesta JSON que cumpla $schema (objeto con additionalProperties: false y todos
     * los campos en required, para que sirva también en modo estricto de OpenAI).
     *
     * @param array<string, mixed> $p Proveedor (provider()).
     * @param string $system Instrucciones.
     * @param string $user Mensaje.
     * @param array<string, mixed> $schema JSON Schema de la respuesta.
     * @param array{model?: string, effort?: string, max_tokens?: int, name?: string, api_key?: string} $options
     *        model/effort '' = los del proveedor; api_key = llave en claro en lugar de la guardada (pruebas).
     * @return array{data: array<string, mixed>, usage: array{input: int, output: int, cache: int}, model: string}
     * @throws RuntimeException Con un mensaje para el usuario si falla la llamada o la respuesta.
     */
    public static function json(array $p, string $system, string $user, array $schema, array $options = []): array
    {
        $p['model'] = !empty($options['model']) ? (string) $options['model'] : $p['model'];
        $p['effort'] = !empty($options['effort']) ? (string) $options['effort'] : $p['effort'];
        $p['max_tokens'] = (int) ($options['max_tokens'] ?? $p['max_tokens']);
        $key = isset($options['api_key']) ? (string) $options['api_key'] : self::apiKey($p);
        if ($key === '' && (self::catalog()[$p['preset']]['key'] ?? '') === 'required') {
            throw new RuntimeException('Falta la llave de la API del proveedor «' . $p['name'] . '» (Admin → IA).');
        }
        if ($p['type'] === 'anthropic') {
            return self::anthropic($p, $key, $system, $user, $schema);
        }
        $req = self::buildRequest($p, $key, $system, $user, $schema, (string) ($options['name'] ?? 'respuesta'));
        [$status, $body] = self::http($req['url'], $req['headers'], $req['body'], (int) $p['timeout']);
        return self::parseResponse($p['type'], $status, $body);
    }

    /**
     * Petición HTTP para OpenAI, Gemini o compatibles (sin enviarla).
     *
     * @param array<string, mixed> $p Proveedor con model/effort/max_tokens definitivos.
     * @param string $key Llave en claro ('' = sin autenticación).
     * @param string $system
     * @param string $user
     * @param array<string, mixed> $schema
     * @param string $name Nombre del esquema (OpenAI lo exige).
     * @return array{url: string, headers: list<string>, body: array<string, mixed>}
     */
    public static function buildRequest(array $p, string $key, string $system, string $user, array $schema, string $name = 'respuesta'): array
    {
        if ($p['json_mode'] !== 'schema') {
            $system .= "\n\nResponda únicamente con un objeto JSON (sin texto antes ni después, sin bloques de código) que cumpla este JSON Schema:\n"
                . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        if ($p['type'] === 'gemini') {
            if ($key !== '') {
                $headers[] = 'x-goog-api-key: ' . $key;
            }
            $config = ['maxOutputTokens' => (int) $p['max_tokens']];
            if ($p['json_mode'] !== 'prompt') {
                $config['responseMimeType'] = 'application/json';
            }
            if ($p['json_mode'] === 'schema') {
                $config['responseJsonSchema'] = $schema;
            }
            return [
                'url' => $p['base_url'] . '/models/' . rawurlencode((string) $p['model']) . ':generateContent',
                'headers' => $headers,
                'body' => [
                    'systemInstruction' => ['parts' => [['text' => $system]]],
                    'contents' => [['role' => 'user', 'parts' => [['text' => $user]]]],
                    'generationConfig' => $config,
                ],
            ];
        }
        if ($key !== '') {
            $headers[] = 'Authorization: Bearer ' . $key;
        }
        $body = [
            'model' => (string) $p['model'],
            'messages' => [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $user]],
        ];
        // OpenAI usa max_completion_tokens (los modelos de razonamiento rechazan max_tokens); los compatibles, max_tokens.
        $body[$p['type'] === 'openai' ? 'max_completion_tokens' : 'max_tokens'] = (int) $p['max_tokens'];
        if ($p['json_mode'] === 'schema') {
            $body['response_format'] = ['type' => 'json_schema', 'json_schema' => [
                'name' => preg_replace('/[^A-Za-z0-9_-]/', '_', $name) ?: 'respuesta', 'strict' => true, 'schema' => $schema,
            ]];
        } elseif ($p['json_mode'] === 'object') {
            $body['response_format'] = ['type' => 'json_object'];
        }
        if ($p['effort'] !== '') {
            $body['reasoning_effort'] = (string) $p['effort'];
        }
        return ['url' => $p['base_url'] . '/chat/completions', 'headers' => $headers, 'body' => $body];
    }

    /**
     * Interpreta la respuesta HTTP de OpenAI/compatibles o Gemini.
     *
     * @param string $type openai|openai_compatible|gemini
     * @param int $status Estado HTTP.
     * @param string $body Cuerpo.
     * @return array{data: array<string, mixed>, usage: array{input: int, output: int, cache: int}, model: string}
     * @throws RuntimeException
     */
    public static function parseResponse(string $type, int $status, string $body): array
    {
        $json = json_decode($body, true);
        if ($status >= 400 || !is_array($json)) {
            $detail = is_array($json) ? (string) ($json['error']['message'] ?? $json['message'] ?? '') : '';
            error_log('nfAI ' . $type . ' HTTP ' . $status . ': ' . mb_substr($detail !== '' ? $detail : $body, 0, 500));
            if ($status === 401 || $status === 403) {
                throw new RuntimeException('La llave de la API del proveedor de IA no es válida.');
            }
            if ($status === 429) {
                throw new RuntimeException('El servicio de IA está saturado o se agotó la cuota; intente en unos minutos.');
            }
            throw new RuntimeException('El servicio de IA respondió con un error' . ($status ? " (HTTP {$status})" : '')
                . ($detail !== '' ? ': ' . mb_substr($detail, 0, 200) : '.'));
        }
        if ($type === 'gemini') {
            if (!empty($json['promptFeedback']['blockReason'])) {
                throw new RuntimeException('La IA no respondió esta pregunta. Reformúlela.');
            }
            $candidate = $json['candidates'][0] ?? [];
            $finish = (string) ($candidate['finishReason'] ?? '');
            if (in_array($finish, ['SAFETY', 'RECITATION', 'PROHIBITED_CONTENT', 'BLOCKLIST', 'SPII'], true)) {
                throw new RuntimeException('La IA no respondió esta pregunta. Reformúlela.');
            }
            if ($finish !== '' && $finish !== 'STOP') {
                throw new RuntimeException('La IA no terminó la respuesta (' . $finish . ').');
            }
            $text = '';
            foreach ((array) ($candidate['content']['parts'] ?? []) as $part) {
                if (empty($part['thought']) && is_string($part['text'] ?? null)) {
                    $text .= $part['text'];
                }
            }
            $u = (array) ($json['usageMetadata'] ?? []);
            $usage = [
                'input' => (int) ($u['promptTokenCount'] ?? 0),
                'output' => (int) ($u['candidatesTokenCount'] ?? 0) + (int) ($u['thoughtsTokenCount'] ?? 0),
                'cache' => (int) ($u['cachedContentTokenCount'] ?? 0),
            ];
            return ['data' => self::extractJson($text), 'usage' => $usage, 'model' => (string) ($json['modelVersion'] ?? '')];
        }
        $choice = $json['choices'][0] ?? [];
        $message = (array) ($choice['message'] ?? []);
        if (!empty($message['refusal'])) {
            throw new RuntimeException('La IA no respondió esta pregunta. Reformúlela.');
        }
        $finish = (string) ($choice['finish_reason'] ?? 'stop');
        if ($finish === 'content_filter') {
            throw new RuntimeException('La IA no respondió esta pregunta. Reformúlela.');
        }
        if ($finish !== 'stop') {
            throw new RuntimeException('La IA no terminó la respuesta (' . $finish . ').');
        }
        $u = (array) ($json['usage'] ?? []);
        $usage = [
            'input' => (int) ($u['prompt_tokens'] ?? 0),
            'output' => (int) ($u['completion_tokens'] ?? 0),
            'cache' => (int) ($u['prompt_tokens_details']['cached_tokens'] ?? 0),
        ];
        return ['data' => self::extractJson((string) ($message['content'] ?? '')), 'usage' => $usage, 'model' => (string) ($json['model'] ?? '')];
    }

    /**
     * Objeto JSON de un texto: tal cual, dentro de un bloque ```json o entre la primera { y la última }
     * (modelos locales que agregan texto alrededor).
     *
     * @param string $text
     * @return array<string, mixed>
     * @throws RuntimeException Si no hay un objeto JSON.
     */
    public static function extractJson(string $text): array
    {
        $text = trim($text);
        $candidates = [$text];
        if (preg_match('/```(?:json)?\s*(.*?)```/is', $text, $m)) {
            $candidates[] = trim($m[1]);
        }
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $candidates[] = substr($text, $start, $end - $start + 1);
        }
        foreach ($candidates as $candidate) {
            $data = json_decode($candidate, true);
            if (is_array($data) && ($data === [] || !array_is_list($data))) {
                return $data;
            }
        }
        throw new RuntimeException($text === '' ? 'La IA devolvió una respuesta vacía.' : 'La IA no devolvió JSON válido.');
    }

    /**
     * POST JSON con curl.
     *
     * @param string $url
     * @param list<string> $headers
     * @param array<string, mixed> $body
     * @param int $timeout Segundos.
     * @return array{0: int, 1: string} [estado HTTP, cuerpo]
     * @throws RuntimeException Si no hay conexión.
     */
    private static function http(string $url, array $headers, array $body, int $timeout): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        ]);
        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        if ($response === false) {
            error_log('nfAI ' . $url . ': ' . $error);
            throw new RuntimeException('No se pudo conectar con el servicio de IA' . ($error !== '' ? ': ' . $error : '.'));
        }
        return [$status, (string) $response];
    }

    /**
     * Llamada con el SDK oficial de Anthropic: salida estructurada (output_config.format), effort, caché del
     * prompt de sistema y respaldo del servidor si el modelo declina.
     *
     * @param array<string, mixed> $p
     * @param string $key
     * @param string $system
     * @param string $user
     * @param array<string, mixed> $schema
     * @return array{data: array<string, mixed>, usage: array{input: int, output: int, cache: int}, model: string}
     * @throws RuntimeException
     */
    private static function anthropic(array $p, string $key, string $system, string $user, array $schema): array
    {
        $baseUrl = $p['base_url'] !== '' && $p['base_url'] !== self::catalog()['anthropic']['base_url'] ? $p['base_url'] : null;
        $client = new AnthropicClient(apiKey: $key, baseUrl: $baseUrl, requestOptions: ['timeout' => (int) $p['timeout']]);
        $outputConfig = ['format' => ['type' => 'json_schema', 'schema' => $schema]];
        if ($p['effort'] !== '') {
            $outputConfig['effort'] = $p['effort'];
        }
        try {
            $message = $client->beta->messages->create(
                model: (string) $p['model'],
                maxTokens: (int) $p['max_tokens'],
                system: [['type' => 'text', 'text' => $system, 'cacheControl' => ['type' => 'ephemeral']]],
                messages: [['role' => 'user', 'content' => $user]],
                outputConfig: $outputConfig,
                // Si el modelo declina por políticas, el servidor reintenta con su modelo de respaldo.
                fallbacks: 'default',
                betas: ['server-side-fallback-2026-07-01'],
            );
        } catch (AuthenticationException $e) {
            error_log('nfAI anthropic: ' . $e->getMessage());
            throw new RuntimeException('La llave de la API de Anthropic no es válida.');
        } catch (RateLimitException $e) {
            throw new RuntimeException('El servicio de IA está saturado; intente en unos minutos.');
        } catch (APIStatusException $e) {
            error_log('nfAI anthropic: ' . $e->getMessage());
            throw new RuntimeException('El servicio de IA respondió con un error.');
        } catch (APIConnectionException $e) {
            error_log('nfAI anthropic: ' . $e->getMessage());
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
                return [
                    'data' => self::extractJson($block->text),
                    'usage' => [
                        'input' => (int) $message->usage->inputTokens,
                        'output' => (int) $message->usage->outputTokens,
                        'cache' => (int) $message->usage->cacheReadInputTokens,
                    ],
                    'model' => (string) $message->model,
                ];
            }
        }
        throw new RuntimeException('La IA devolvió una respuesta vacía.');
    }

    /**
     * Prueba un proveedor con una pregunta mínima (Admin → IA).
     *
     * @param array<string, mixed> $p
     * @param string|null $key Llave en claro recién escrita; null = la guardada.
     * @return array{ok: bool, message: string, ms: int}
     */
    public static function test(array $p, ?string $key = null): array
    {
        $start = microtime(true);
        try {
            $r = self::json($p, 'Es una prueba de conexión. Responda en español.', 'Diga «hola» en el campo saludo y la suma 2 + 3 en el campo suma.', [
                'type' => 'object',
                'properties' => ['saludo' => ['type' => 'string'], 'suma' => ['type' => 'integer']],
                'required' => ['saludo', 'suma'],
                'additionalProperties' => false,
            ], ['max_tokens' => 2000, 'name' => 'prueba'] + ($key !== null ? ['api_key' => $key] : []));
            $ok = (int) ($r['data']['suma'] ?? 0) === 5;
            $message = ($ok ? 'Responde correctamente' : 'Responde, pero el resultado no es el esperado')
                . ' (modelo ' . ($r['model'] ?: $p['model']) . ', ' . $r['usage']['input'] . ' tokens de entrada, '
                . $r['usage']['output'] . ' de salida): ' . json_encode($r['data'], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            $ok = false;
            $message = $e->getMessage();
        }
        return ['ok' => $ok, 'message' => $message, 'ms' => (int) round((microtime(true) - $start) * 1000)];
    }
}
