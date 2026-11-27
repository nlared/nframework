<?php

/**
 * Índices de MongoDB que el framework necesita en la base de cada sitio: una sola lista para el instalador
 * (nframework/test.php los crea) y para el diagnóstico de Admin → Inicio (avisa cuáles faltan).
 *
 * Agregue aquí cualquier índice nuevo; así el panel no reporta «Índices: Presentes» cuando falta alguno.
 *
 *   foreach (nfIndexes::required($config) as $idx) {
 *       $db->{$idx['collection']}->createIndex($idx['key'], $idx['options']);
 *   }
 *   $faltan = nfIndexes::missing($m->{$config['sitedb']}, $config);   // ['efirmas(owner, nocert)', ...]
 *
 * No depende del autoload: test.php lo carga con require_once antes de include.php.
 */
class nfIndexes
{
    /**
     * Índices requeridos. Los TTL opcionales solo se incluyen cuando su opción de configuración está definida.
     *
     * @param array<string, mixed>|ArrayAccess<string, mixed> $config Configuración del sitio
     *        (usa tablefia_ttl_days y nfuristats_ttl_days).
     * @return list<array{collection: string, key: array<string, int>, options: array<string, mixed>}>
     */
    public static function required($config): array
    {
        $indexes = [
            ['collection' => 'users', 'key' => ['username' => 1], 'options' => ['unique' => true]],
            ['collection' => 'pages', 'key' => ['path' => 1]],
            ['collection' => 'usersgroups', 'key' => ['name' => 1]],
            ['collection' => 'nfuristats', 'key' => ['ip' => 1, 'created_at' => 1]],
            ['collection' => 'nfuristats', 'key' => ['created_at' => 1, 'ip' => 1]],
            ['collection' => 'nfsecurityrules', 'key' => ['enabled' => 1]],
            // User::in() consulta usersgroups por usuario y nombre en casi cada página.
            ['collection' => 'usersgroups', 'key' => ['users' => 1, 'name' => 1]],
            ['collection' => 'nf_attempts', 'key' => ['ip' => 1]],
            // Efirma: una entrada por usuario y certificado.
            ['collection' => 'efirmas', 'key' => ['owner' => 1, 'nocert' => 1], 'options' => ['unique' => true]],
            // TablefIA: listado de consultas guardadas por uso y búsqueda de la misma pregunta.
            ['collection' => 'nftablesfia', 'key' => ['uso' => 1, 'ultimaejecucion' => -1]],
            ['collection' => 'nftablesfia', 'key' => ['uso' => 1, 'prompt' => 1, 'confighash' => 1]],
            ['collection' => 'nftablesfia', 'key' => ['uso' => 1, 'promptnorm' => 1, 'confighash' => 1]],
            // API REST: búsqueda del token por su hash y listado por usuario (nfApiToken).
            ['collection' => 'nfapitokens', 'key' => ['hash' => 1], 'options' => ['unique' => true]],
            ['collection' => 'nfapitokens', 'key' => ['owner' => 1, 'created_at' => -1]],
            // job_worker.php: siguiente trabajo vencido de la cola por prioridad (Job::claimQuery()).
            ['collection' => 'jobs', 'key' => ['queue' => 1, 'failed_at' => 1, 'priority' => -1, 'delay_until' => 1]],
        ];
        // TablefIA: borrar consultas sin ejecutarse en N días que nadie marcó como favorita ('fijada' => false).
        if (!empty($config['tablefia_ttl_days'])) {
            $indexes[] = ['collection' => 'nftablesfia', 'key' => ['ultimaejecucion' => 1], 'options' => [
                'name' => 'tablefia_ttl',
                'expireAfterSeconds' => (int) $config['tablefia_ttl_days'] * 86400,
                'partialFilterExpression' => ['fijada' => false],
            ]];
        }
        // Opcional: borrar automáticamente las estadísticas de peticiones con más de N días.
        if (!empty($config['nfuristats_ttl_days'])) {
            $indexes[] = ['collection' => 'nfuristats', 'key' => ['createdAt' => 1], 'options' => [
                'expireAfterSeconds' => (int) $config['nfuristats_ttl_days'] * 86400,
            ]];
        }
        foreach ($indexes as &$idx) {
            $idx['options'] ??= [];
        }
        return $indexes;
    }

    /**
     * Índices requeridos que no existen en la base. Solo compara las llaves (campos, orden y dirección), no
     * las opciones.
     *
     * @param MongoDB\Database $db Base del sitio ($m->{$config['sitedb']}).
     * @param array<string, mixed>|ArrayAccess<string, mixed> $config Configuración del sitio.
     * @return list<string> Descripciones como 'nftablesfia(uso, prompt, confighash)'.
     */
    public static function missing($db, $config): array
    {
        $existing = [];
        $missing = [];
        foreach (self::required($config) as $idx) {
            $coll = $idx['collection'];
            if (!isset($existing[$coll])) {
                $existing[$coll] = [];
                foreach ($db->{$coll}->listIndexes() as $info) {
                    $existing[$coll][] = (array) $info->getKey();
                }
            }
            if (!self::hasKey($existing[$coll], $idx['key'])) {
                $missing[] = $coll . '(' . implode(', ', array_keys($idx['key'])) . ')';
            }
        }
        return $missing;
    }

    /**
     * ¿Alguna de las llaves existentes es igual a la buscada? El orden de los campos cuenta
     * ({users, name} no es {name, users}); 1 y 1.0 se consideran iguales.
     *
     * @param list<array<string, int|float|string>> $existing Llaves de listIndexes().
     * @param array<string, int|float|string> $key Llave buscada.
     */
    public static function hasKey(array $existing, array $key): bool
    {
        foreach ($existing as $candidate) {
            if (array_keys($candidate) === array_keys($key) && $candidate == $key) {
                return true;
            }
        }
        return false;
    }
}
