<?php

/**
 * API REST/JSON del sitio: rutas versionadas bajo /api/<versión>/, autenticación con token Bearer
 * (nfApiToken, que el usuario crea en /account/apitokens) y respuestas uniformes. Se declara en el
 * crouter.php del sitio:
 *
 *   $api = new nfApi('v1');
 *   $api->scope('notas:read', 'Leer notas')->scope('notas:write', 'Crear y editar notas');
 *   $api->get('/notas', function (array $p) {
 *       global $m, $config, $user;                       // $user = dueño del token
 *       $pg = nfApi::pagination();
 *       $filter = ['owner' => toMongoId($user->_id)];
 *       $notas = $m->{$config['sitedb']}->notas;
 *       return nfApi::paginated($notas->find($filter, ['skip' => $pg['skip'], 'limit' => $pg['limit']]), $notas->countDocuments($filter), $pg);
 *   }, ['scope' => 'notas:read']);
 *   $api->post('/notas', function () {
 *       $in = nfApi::input();
 *       if (!is_string($in['titulo'] ?? null) || $in['titulo'] === '') {
 *           throw new nfApiException(422, 'validation_failed', 'Falta el título.', ['titulo' => 'Requerido']);
 *       }
 *       ...
 *       return new nfApiResult($nota, 201);
 *   }, ['scope' => 'notas:write']);
 *   $api->get('/estado', fn () => ['ok' => true], ['auth' => false]);   // pública
 *
 * Respuestas: éxito {"data": ..., "meta": {...}} (meta solo si hay), null = 204 sin cuerpo; error
 * {"error": {"code": "not_found", "message": "...", "details": ...}} con su estado HTTP. Los ObjectId salen
 * como texto y las fechas UTCDateTime en ISO 8601 UTC (normalize()).
 *
 * Autenticación: solo Authorization: Bearer <token> (nunca la cookie de sesión), así que no aplica CSRF y la
 * sesión de la petición se descarta (no crea documentos en sessions). Dentro del handler $user es el dueño del
 * token y nfApi::$token su documento. No use requireAuth()/requireGroup() del framework (redirigen):
 * use nfApi::requireGroup() o lance nfApiException. Ver docs/api.php.
 */
class nfApi
{
    /** @var array<string, string> Scopes declarados en esta petición: nombre => descripción. */
    public static $scopes = [];
    /** @var int Rutas registradas en esta petición (0 = el sitio no tiene API). */
    public static $routes = 0;
    /** @var array<string, mixed>|null Documento del token de la petición actual (sin hash). */
    public static $token = null;
    /** @var array<string, mixed>|null Cuerpo ya leído por input(). */
    private static $input = null;

    /** @var string Versión: v1, v2... */
    public $version;
    /** @var array{0: int, 1: int} Peticiones por token: [cantidad, segundos]; [0, 0] = sin límite. */
    public $ratelimit;

    /**
     * @global class_config $config
     * @param string $version v1, v2... (también v1.1).
     * @param array{ratelimit?: array{0: int, 1: int}} $options ratelimit por omisión: $config['api_ratelimit'] o [600, 60].
     * @throws InvalidArgumentException Si la versión no es vN.
     */
    public function __construct($version = 'v1', $options = [])
    {
        global $config;
        if (!is_string($version) || !preg_match('/^v\d+(\.\d+)?$/', $version)) {
            throw new InvalidArgumentException('Versión de API inválida: use v1, v2...');
        }
        $this->version = $version;
        $limit = $options['ratelimit'] ?? $config['api_ratelimit'] ?? [600, 60];
        $this->ratelimit = [(int) ($limit[0] ?? 0), (int) ($limit[1] ?? 0)];
    }

    /**
     * Declara un scope (con su descripción para /account/apitokens). Los usados en 'scope' se declaran solos.
     *
     * @param string $scope minúsculas, dígitos y . _ : - (p.ej. notas:read).
     * @param string $description
     * @return static
     * @throws InvalidArgumentException Si el nombre no es válido.
     */
    public function scope($scope, $description = '')
    {
        if ($scope === '*' || !nfApiToken::validScope($scope)) {
            throw new InvalidArgumentException('Scope inválido: ' . json_encode($scope));
        }
        if ($description !== '' || !isset(self::$scopes[$scope])) {
            self::$scopes[$scope] = (string) $description;
        }
        return $this;
    }

    /**
     * Scopes declarados (para ofrecerlos al crear un token).
     *
     * @return list<string>
     */
    public static function scopes(): array
    {
        $names = array_keys(self::$scopes);
        sort($names);
        return $names;
    }

    /**
     * @param string $path Relativa a /api/<versión>; parámetros Mezon: /notas/[s:id].
     * @param callable(array<string, string>, nfApi): mixed $handler
     * @param array{scope?: string, auth?: bool, ratelimit?: array{0: int, 1: int}} $options
     * @return static
     */
    public function get($path, callable $handler, $options = [])
    {
        return $this->route('GET', $path, $handler, $options);
    }

    /**
     * @param string $path
     * @param callable(array<string, string>, nfApi): mixed $handler
     * @param array{scope?: string, auth?: bool, ratelimit?: array{0: int, 1: int}} $options
     * @return static
     */
    public function post($path, callable $handler, $options = [])
    {
        return $this->route('POST', $path, $handler, $options);
    }

    /**
     * @param string $path
     * @param callable(array<string, string>, nfApi): mixed $handler
     * @param array{scope?: string, auth?: bool, ratelimit?: array{0: int, 1: int}} $options
     * @return static
     */
    public function put($path, callable $handler, $options = [])
    {
        return $this->route('PUT', $path, $handler, $options);
    }

    /**
     * @param string $path
     * @param callable(array<string, string>, nfApi): mixed $handler
     * @param array{scope?: string, auth?: bool, ratelimit?: array{0: int, 1: int}} $options
     * @return static
     */
    public function patch($path, callable $handler, $options = [])
    {
        return $this->route('PATCH', $path, $handler, $options);
    }

    /**
     * @param string $path
     * @param callable(array<string, string>, nfApi): mixed $handler
     * @param array{scope?: string, auth?: bool, ratelimit?: array{0: int, 1: int}} $options
     * @return static
     */
    public function delete($path, callable $handler, $options = [])
    {
        return $this->route('DELETE', $path, $handler, $options);
    }

    /**
     * Registra la ruta en el router del sitio.
     *
     * Opciones: scope (permiso que debe tener el token; '' = cualquier token válido), auth (false = pública,
     * sin token), ratelimit ([cantidad, segundos] por token para esta ruta).
     *
     * @global Mezon\Router\Router $router
     * @param string|list<string> $methods
     * @param string $path
     * @param callable(array<string, string>, nfApi): mixed $handler
     * @param array{scope?: string, auth?: bool, ratelimit?: array{0: int, 1: int}} $options
     * @return static
     * @throws InvalidArgumentException Si el scope no es válido.
     */
    public function route($methods, $path, callable $handler, $options = [])
    {
        global $router;
        $scope = (string) ($options['scope'] ?? '');
        if ($scope !== '') {
            $this->scope($scope);
        }
        self::$routes++;
        $router->addRoute(self::path($this->version, (string) $path), function (string $route, array $params) use ($handler, $options) {
            $this->dispatch($handler, $params, $options);
        }, $methods);
        return $this;
    }

    /**
     * Ruta completa: /api/<versión>/<path> (sin barra final).
     *
     * @param string $version
     * @param string $path
     * @return string
     */
    public static function path(string $version, string $path): string
    {
        $path = trim($path, '/');
        return '/api/' . $version . ($path !== '' ? '/' . $path : '');
    }

    /**
     * Autentica, ejecuta el handler y envía la respuesta (o el error) en JSON.
     *
     * @global User $user
     * @global bool $developermode
     * @param callable(array<string, string>, nfApi): mixed $handler
     * @param array<string, string> $params Parámetros de la ruta.
     * @param array<string, mixed> $options
     * @return void
     */
    public function dispatch(callable $handler, array $params, array $options = [])
    {
        global $developermode;
        self::discardSession();
        try {
            if (($options['auth'] ?? true) !== false) {
                $this->authenticate((string) ($options['scope'] ?? ''), $options['ratelimit'] ?? $this->ratelimit);
            }
            [$status, $body, $headers] = self::envelope($handler($params, $this));
        } catch (nfApiException $e) {
            [$status, $body, $headers] = self::errorEnvelope($e);
        } catch (Throwable $e) {
            error_log('nfApi ' . ($_SERVER['REQUEST_METHOD'] ?? '') . ' ' . ($_SERVER['REQUEST_URI'] ?? '') . ': ' . get_class($e) . ': ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
            [$status, $body, $headers] = self::errorEnvelope(new nfApiException(500, 'internal_error',
                !empty($developermode) ? get_class($e) . ': ' . $e->getMessage() : 'Error interno del servidor.'));
        }
        self::send($status, $body, $headers);
    }

    /**
     * Valida el token Bearer, el scope y el límite de peticiones; deja en $user al dueño del token.
     *
     * @global User $user
     * @global string $ip
     * @param string $scope
     * @param array{0: int, 1: int} $ratelimit
     * @return void
     * @throws nfApiException 401, 403 o 429.
     */
    private function authenticate(string $scope, array $ratelimit): void
    {
        global $user, $ip;
        $raw = nfApiToken::fromAuthorization(nfApiToken::requestHeader());
        if ($raw === null) {
            throw new nfApiException(401, 'unauthorized', 'Falta el token: envíe la cabecera Authorization: Bearer <token>.');
        }
        $doc = nfApiToken::verify($raw);
        if ($doc === null) {
            // Muchos tokens inválidos desde la misma IP: se bloquea un rato.
            if (!nflogAttempt('api-auth:' . $ip, 20, 900)) {
                throw new nfApiException(429, 'rate_limited', 'Demasiados intentos con tokens inválidos; espere 15 minutos.');
            }
            throw new nfApiException(401, 'invalid_token', 'El token no existe, caducó o fue revocado.');
        }
        $owner = new User(['_id' => $doc['owner']]);
        if (empty($owner->_id) || !empty($owner->disabled)) {
            throw new nfApiException(401, 'invalid_token', 'El usuario del token no existe o está deshabilitado.');
        }
        if (!nfApiToken::allows((array) ($doc['scopes'] ?? []), $scope)) {
            throw new nfApiException(403, 'insufficient_scope', 'El token no tiene el permiso ' . $scope . '.', ['required' => $scope]);
        }
        [$limit, $seconds] = [(int) ($ratelimit[0] ?? 0), (int) ($ratelimit[1] ?? 0)];
        if ($limit > 0 && $seconds > 0 && !nflogAttempt('api:' . $doc['_id'], $limit, $seconds)) {
            header('Retry-After: ' . $seconds);
            throw new nfApiException(429, 'rate_limited', "Límite de {$limit} peticiones cada {$seconds} s.");
        }
        unset($doc['hash']);
        self::$token = $doc;
        $user = $owner;
    }

    /**
     * Estado, cuerpo y cabeceras de lo que devolvió el handler.
     *
     * @param mixed $out
     * @return array{0: int, 1: array<string, mixed>|null, 2: array<string, string>}
     */
    public static function envelope($out): array
    {
        if ($out instanceof nfApiResult) {
            $body = $out->status === 204 ? null : ['data' => self::normalize($out->data)];
            if ($body !== null && $out->meta !== []) {
                $body['meta'] = self::normalize($out->meta);
            }
            return [$out->status, $body, $out->headers];
        }
        if ($out === null) {
            return [204, null, []];
        }
        // Un handler que ya fijó otro 2xx (http_response_code(201)) lo conserva.
        $code = http_response_code();
        $status = is_int($code) && $code >= 200 && $code < 300 ? $code : 200;
        return [$status, ['data' => self::normalize($out)], []];
    }

    /**
     * Cuerpo de error uniforme.
     *
     * @param nfApiException $e
     * @return array{0: int, 1: array{error: array<string, mixed>}, 2: array<string, string>}
     */
    public static function errorEnvelope(nfApiException $e): array
    {
        $error = ['code' => $e->errorCode, 'message' => $e->getMessage()];
        if ($e->details !== null) {
            $error['details'] = self::normalize($e->details);
        }
        $headers = $e->status === 401 ? ['WWW-Authenticate' => 'Bearer realm="api"'] : [];
        return [$e->status, ['error' => $error], $headers];
    }

    /**
     * Envía la respuesta JSON; nfshutdown ya no procesa la salida.
     *
     * @global class_nframework $nframework
     * @param int $status
     * @param array<string, mixed>|null $body null = sin cuerpo (204).
     * @param array<string, string> $headers
     * @return void
     */
    public static function send(int $status, ?array $body, array $headers = []): void
    {
        global $nframework;
        http_response_code($status);
        foreach ($headers as $name => $value) {
            header($name . ': ' . str_replace(["\r", "\n"], '', (string) $value));
        }
        $json = $body === null ? '' : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PRESERVE_ZERO_FRACTION);
        $nframework->serveContent((string) $json, 'application/json; charset=utf-8', null);
    }

    /**
     * Respuesta 404 en el formato de la API (router.php la usa para /api/* sin ruta).
     *
     * @return void
     */
    public static function notFound(): void
    {
        self::discardSession();
        [$status, $body] = self::errorEnvelope(new nfApiException(404, 'not_found', 'Ruta o método no encontrado.'));
        self::send($status, $body);
    }

    /**
     * La API no usa la sesión: se descarta sin escribirla (un cliente sin cookie crearía una por petición) y
     * se quita la cookie que session_start() ya había preparado.
     *
     * @return void
     */
    private static function discardSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_abort();
        }
        if (!headers_sent()) {
            header_remove('Set-Cookie');
        }
    }

    /**
     * Convierte tipos de MongoDB a JSON simple: ObjectId → texto, UTCDateTime/DateTime → ISO 8601 UTC
     * (2026-11-27T18:30:00.000Z), Decimal128 → texto, Binary → base64, BSONDocument/BSONArray → arreglos.
     *
     * @param mixed $value
     * @param int $depth
     * @return mixed
     */
    public static function normalize($value, int $depth = 0)
    {
        if ($depth > 64) {
            return null;
        }
        if ($value instanceof MongoDB\BSON\ObjectId || $value instanceof MongoDB\BSON\Decimal128) {
            return (string) $value;
        }
        if ($value instanceof MongoDB\BSON\UTCDateTime) {
            $value = $value->toDateTime();
        }
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z');
        }
        if ($value instanceof MongoDB\BSON\Binary) {
            return base64_encode($value->getData());
        }
        if ($value instanceof MongoDB\Model\BSONDocument || $value instanceof MongoDB\Model\BSONArray) {
            $value = $value->getArrayCopy();
        }
        if ($value instanceof Traversable) {
            $value = iterator_to_array($value, false);
        }
        if ($value instanceof JsonSerializable) {
            $value = $value->jsonSerialize();
        }
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = self::normalize($v, $depth + 1);
            }
            return $value;
        }
        if (is_object($value)) {
            return self::normalize(get_object_vars($value), $depth + 1);
        }
        return $value;
    }

    /**
     * Cuerpo de la petición como arreglo: JSON (Content-Type application/json) o formulario. Fuerce los
     * tipos de cada campo antes de usarlo en una consulta (un JSON puede traer {"$ne": ...}).
     *
     * @param string|null $raw Cuerpo (null = php://input); para pruebas.
     * @param string|null $contentType null = el de la petición.
     * @return array<string, mixed>
     * @throws nfApiException 400 si el JSON no es válido o no es un objeto.
     */
    public static function input(?string $raw = null, ?string $contentType = null): array
    {
        if ($raw === null && self::$input !== null) {
            return self::$input;
        }
        $contentType ??= (string) ($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '');
        if (!preg_match('#^application/(.+\+)?json#i', trim($contentType))) {
            return $raw === null ? (self::$input = $_POST) : [];
        }
        $text = $raw ?? (string) file_get_contents('php://input');
        if (trim($text) === '') {
            $data = [];
        } else {
            try {
                $data = json_decode($text, true, 64, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                throw new nfApiException(400, 'invalid_json', 'El cuerpo no es JSON válido: ' . $e->getMessage());
            }
            if (!is_array($data) || ($data !== [] && array_is_list($data))) {
                throw new nfApiException(400, 'invalid_json', 'El cuerpo debe ser un objeto JSON.');
            }
        }
        if ($raw === null) {
            self::$input = $data;
        }
        return $data;
    }

    /**
     * Paginación desde ?page=N&per_page=M (enteros; per_page entre 1 y $max).
     *
     * @param int $default per_page si no viene.
     * @param int $max
     * @param array<string, mixed>|null $query null = $_GET; para pruebas.
     * @return array{page: int, per_page: int, skip: int, limit: int}
     */
    public static function pagination(int $default = 20, int $max = 100, ?array $query = null): array
    {
        $query ??= $_GET;
        $int = fn ($v, int $fallback) => is_string($v) && ctype_digit($v) ? (int) $v : (is_int($v) ? $v : $fallback);
        $page = max(1, min(1000000, $int($query['page'] ?? null, 1)));
        $perPage = max(1, min($max, $int($query['per_page'] ?? null, $default)));
        return ['page' => $page, 'per_page' => $perPage, 'skip' => ($page - 1) * $perPage, 'limit' => $perPage];
    }

    /**
     * Resultado paginado: data = lista, meta = {page, per_page, total, pages}.
     *
     * @param iterable<mixed> $items
     * @param int $total Total de elementos (countDocuments con el mismo filtro).
     * @param array{page: int, per_page: int} $pagination Lo que devolvió pagination().
     * @return nfApiResult
     */
    public static function paginated(iterable $items, int $total, array $pagination): nfApiResult
    {
        $items = is_array($items) ? array_values($items) : iterator_to_array($items, false);
        $perPage = max(1, (int) $pagination['per_page']);
        return new nfApiResult($items, 200, [
            'page' => (int) $pagination['page'],
            'per_page' => $perPage,
            'total' => $total,
            'pages' => (int) ceil($total / $perPage),
        ]);
    }

    /**
     * Exige que el dueño del token pertenezca a alguno de los grupos; si no, 403.
     *
     * @global User $user
     * @param string ...$groups
     * @return void
     * @throws nfApiException
     */
    public static function requireGroup(string ...$groups): void
    {
        global $user;
        foreach ($groups as $group) {
            if ($user instanceof User && $user->in($group)) {
                return;
            }
        }
        throw new nfApiException(403, 'forbidden', 'No tiene permiso para esta operación.');
    }
}
