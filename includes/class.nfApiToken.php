<?php

/**
 * Tokens de acceso a la API REST del sitio (colección nfapitokens). Cada token pertenece a un usuario, actúa
 * con sus permisos y se limita a una lista de scopes (permisos de la API, '*' = todos). Solo se guarda el
 * sha256 del token: se muestra una vez al crearlo y después no se puede recuperar.
 *
 *   $nuevo = nfApiToken::create($user->_id, 'Integración ERP', ['notas:read'], 90);
 *   echo $nuevo['token'];                          // nfat_… (mostrar una sola vez)
 *   $doc = nfApiToken::verify($token);             // null si no existe, caducó o se revocó
 *   nfApiToken::allows($doc['scopes'], 'notas:read');
 *   nfApiToken::revoke($user->_id, $id);
 *
 * Documento: {owner: ObjectId, name, hash, hint, scopes: [string], created_at, expires_at|null,
 * last_used_at|null, last_ip, revoked_at|null}. Ver docs/api.php y nfApi.
 */
class nfApiToken
{
    /** Prefijo visible: permite reconocer un token filtrado (escáneres de secretos) y descartar basura sin consultar. */
    public const PREFIX = 'nfat_';
    /** Caducidades que ofrece /account/apitokens, en días (0 = no caduca). */
    public const EXPIRATIONS = [30, 90, 365, 0];
    /** Segundos entre actualizaciones de last_used_at (evita una escritura por petición). */
    public const TOUCH_INTERVAL = 60;

    /**
     * Token nuevo: prefijo + 48 caracteres hexadecimales (192 bits aleatorios).
     *
     * @return string
     */
    public static function generate(): string
    {
        return self::PREFIX . bin2hex(random_bytes(24));
    }

    /**
     * true si $token tiene la forma de generate(); evita consultar la base con cualquier texto.
     *
     * @param mixed $token
     * @return bool
     */
    public static function wellFormed($token): bool
    {
        return is_string($token) && preg_match('/^' . self::PREFIX . '[a-f0-9]{48}$/', $token) === 1;
    }

    /**
     * Huella con que se guarda y se busca el token. El token es aleatorio y largo, así que basta sha256 (no
     * necesita sal ni un hash lento como una contraseña).
     *
     * @param string $token
     * @return string
     */
    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Token de la cabecera Authorization ("Bearer <token>", sin distinguir mayúsculas en "Bearer").
     *
     * @param string $header Valor de la cabecera.
     * @return string|null
     */
    public static function fromAuthorization(string $header): ?string
    {
        return preg_match('/^\s*Bearer\s+(\S+)\s*$/i', $header, $m) ? $m[1] : null;
    }

    /**
     * Cabecera Authorization de la petición actual. PHP-FPM la entrega en HTTP_AUTHORIZATION; Apache con
     * CGI a veces solo en REDIRECT_HTTP_AUTHORIZATION o en getallheaders().
     *
     * @return string
     */
    public static function requestHeader(): string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if ($header === '' && function_exists('getallheaders')) {
            foreach ((array) getallheaders() as $name => $value) {
                if (strcasecmp((string) $name, 'Authorization') === 0) {
                    $header = (string) $value;
                }
            }
        }
        return is_string($header) ? $header : '';
    }

    /**
     * Nombre de scope válido: minúsculas, dígitos y . _ : - (p.ej. notas:read), o '*' (todos).
     *
     * @param mixed $scope
     * @return bool
     */
    public static function validScope($scope): bool
    {
        return $scope === '*' || (is_string($scope) && preg_match('/^[a-z0-9][a-z0-9._:-]{0,63}$/', $scope) === 1);
    }

    /**
     * Scopes pedidos al crear un token, filtrados contra los que existen. '*' solo se acepta si $allowAll.
     *
     * @param mixed $requested Lo que llegó del formulario.
     * @param list<string> $known Scopes registrados (nfApi::scopes()).
     * @param bool $allowAll Permitir '*'.
     * @return list<string> Sin repetir y ordenados; '*' sustituye a los demás.
     */
    public static function normalizeScopes($requested, array $known, bool $allowAll = true): array
    {
        $scopes = [];
        foreach ((array) $requested as $scope) {
            if ($scope === '*' && $allowAll) {
                return ['*'];
            }
            if (self::validScope($scope) && in_array($scope, $known, true)) {
                $scopes[$scope] = true;
            }
        }
        $scopes = array_keys($scopes);
        sort($scopes);
        return $scopes;
    }

    /**
     * true si los scopes del token cubren $scope. '' (ruta sin scope) basta con un token válido.
     *
     * @param list<string> $tokenScopes
     * @param string $scope
     * @return bool
     */
    public static function allows(array $tokenScopes, string $scope): bool
    {
        return $scope === '' || in_array('*', $tokenScopes, true) || in_array($scope, $tokenScopes, true);
    }

    /**
     * true si el documento del token se puede usar en $now (no revocado ni caducado).
     *
     * @param array<string, mixed> $doc
     * @param int|null $now Marca de tiempo Unix (null = ahora).
     * @return bool
     */
    public static function usable(array $doc, ?int $now = null): bool
    {
        $now ??= time();
        if (!empty($doc['revoked_at'])) {
            return false;
        }
        $expires = $doc['expires_at'] ?? null;
        return !$expires instanceof MongoDB\BSON\UTCDateTime || $expires->toDateTime()->getTimestamp() > $now;
    }

    /**
     * true si $u puede crear tokens: sesión iniciada y, si $config['api_tokens_group'] tiene un grupo, miembro de él.
     *
     * @global class_config $config
     * @param User $u
     * @return bool
     */
    public static function canCreate(User $u): bool
    {
        global $config;
        $group = (string) ($config['api_tokens_group'] ?? '');
        return $u->isLoggedIn() && ($group === '' || $u->in($group));
    }

    /**
     * Crea un token para $owner. Devuelve el token en claro (mostrarlo una vez) y el documento guardado.
     *
     * @global MongoDB\Client $m
     * @global class_config $config
     * @global string $ip
     * @param MongoDB\BSON\ObjectId|string $owner _id del usuario.
     * @param string $name Para qué es (lo escribe el usuario).
     * @param list<string> $scopes Ya normalizados (normalizeScopes()).
     * @param int $days Días de vigencia; 0 = no caduca.
     * @return array{token: string, doc: array<string, mixed>}
     */
    public static function create($owner, string $name, array $scopes, int $days = 90): array
    {
        global $m, $config, $ip;
        $token = self::generate();
        $doc = [
            'owner' => $owner instanceof MongoDB\BSON\ObjectId ? $owner : new MongoDB\BSON\ObjectId((string) $owner),
            'name' => mb_substr(trim($name), 0, 80),
            'hash' => self::hash($token),
            'hint' => substr($token, -4),
            'scopes' => array_values($scopes),
            'created_at' => new MongoDB\BSON\UTCDateTime(),
            'created_ip' => (string) $ip,
            'expires_at' => $days > 0 ? new MongoDB\BSON\UTCDateTime((time() + $days * 86400) * 1000) : null,
            'last_used_at' => null,
            'last_ip' => null,
            'revoked_at' => null,
        ];
        $doc['_id'] = $m->{$config['sitedb']}->nfapitokens->insertOne($doc)->getInsertedId();
        return ['token' => $token, 'doc' => $doc];
    }

    /**
     * Documento del token si existe y se puede usar; registra el último uso (a lo más cada TOUCH_INTERVAL s).
     *
     * @global MongoDB\Client $m
     * @global class_config $config
     * @global string $ip
     * @param string $token Token en claro.
     * @return array<string, mixed>|null
     */
    public static function verify(string $token): ?array
    {
        global $m, $config, $ip;
        if (!self::wellFormed($token)) {
            return null;
        }
        $tokens = $m->{$config['sitedb']}->nfapitokens;
        $doc = $tokens->findOne(['hash' => self::hash($token)], ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]);
        if (!is_array($doc) || !self::usable($doc)) {
            return null;
        }
        $last = $doc['last_used_at'] ?? null;
        if (!$last instanceof MongoDB\BSON\UTCDateTime || $last->toDateTime()->getTimestamp() < time() - self::TOUCH_INTERVAL) {
            $tokens->updateOne(['_id' => $doc['_id']], ['$set' => ['last_used_at' => new MongoDB\BSON\UTCDateTime(), 'last_ip' => (string) $ip]]);
        }
        return $doc;
    }

    /**
     * Tokens de un usuario, del más reciente al más antiguo (sin el hash).
     *
     * @global MongoDB\Client $m
     * @global class_config $config
     * @param MongoDB\BSON\ObjectId|string $owner
     * @param bool $withRevoked Incluir los revocados.
     * @return list<array<string, mixed>>
     */
    public static function forUser($owner, bool $withRevoked = false): array
    {
        global $m, $config;
        $filter = ['owner' => $owner instanceof MongoDB\BSON\ObjectId ? $owner : new MongoDB\BSON\ObjectId((string) $owner)];
        if (!$withRevoked) {
            $filter['revoked_at'] = null;
        }
        return $m->{$config['sitedb']}->nfapitokens->find($filter, [
            'sort' => ['created_at' => -1],
            'projection' => ['hash' => 0],
            'typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array'],
        ])->toArray();
    }

    /**
     * Revoca un token de $owner (o todos con $id = null). Devuelve cuántos se revocaron.
     *
     * @global MongoDB\Client $m
     * @global class_config $config
     * @param MongoDB\BSON\ObjectId|string $owner
     * @param MongoDB\BSON\ObjectId|string|null $id null = todos los del usuario.
     * @return int
     */
    public static function revoke($owner, $id = null): int
    {
        global $m, $config;
        $filter = [
            'owner' => $owner instanceof MongoDB\BSON\ObjectId ? $owner : new MongoDB\BSON\ObjectId((string) $owner),
            'revoked_at' => null,
        ];
        if ($id !== null) {
            if (!isValidObjectId($id)) {
                return 0;
            }
            $filter['_id'] = $id instanceof MongoDB\BSON\ObjectId ? $id : new MongoDB\BSON\ObjectId((string) $id);
        }
        return $m->{$config['sitedb']}->nfapitokens->updateMany($filter, ['$set' => ['revoked_at' => new MongoDB\BSON\UTCDateTime()]])->getModifiedCount();
    }
}
