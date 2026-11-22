<?php

/**
 * Cliente del API del DENUE de INEGI (Directorio Estadístico Nacional de Unidades Económicas) usado para
 * sugerir domicilios en inputaddress a partir de los establecimientos registrados.
 *
 * El token se captura en Admin → Sitio → INEGI ($config['inegi-denue-token']) y solo se usa en el
 * servidor: el navegador consulta /nframework/denue.php, que llama a Denue::suggest().
 *
 * Particularidades del API que condicionan la búsqueda:
 *  - La condición se compara contra nombre, razón social, calle, colonia, actividad, municipio y
 *    localidad, solo con palabras completas e ignorando acentos ("venust" no encuentra nada).
 *  - El código postal no se puede buscar; se sugiere a partir de los demás campos llenados.
 *  - Sin resultados responde el texto "No hay resultados." en lugar de JSON.
 *  - www.inegi.org.mx no envía el certificado intermedio; se descarga del emisor (AIA) y solo se
 *    acepta si lo firma una autoridad raíz del sistema.
 */
class Denue
{
    public const BASE_URL = 'https://www.inegi.org.mx/app/api/denue/v1/consulta/';
    /** Certificado intermedio que www.inegi.org.mx omite en el handshake. */
    public const ISSUER_URL = 'http://crt.sectigo.com/SectigoPublicServerAuthenticationCAOVR36.crt';
    /** Campos de inputaddress que pueden autocompletarse. */
    public const FIELDS = ['state', 'municipality', 'city', 'neighborhood', 'street', 'zipcode'];
    /** Radio (m) de la búsqueda alrededor del punto del mapa; el API admite hasta 5000. */
    public const RADIUS = 3000;
    /** Registros que se piden por consulta a nivel estado. */
    public const ROWS = 150;
    /** Vigencia de la caché de respuestas del API (segundos). */
    public const CACHE_TTL = 86400;

    public const STATES = [
        '01' => 'Aguascalientes', '02' => 'Baja California', '03' => 'Baja California Sur', '04' => 'Campeche',
        '05' => 'Coahuila de Zaragoza', '06' => 'Colima', '07' => 'Chiapas', '08' => 'Chihuahua',
        '09' => 'Ciudad de México', '10' => 'Durango', '11' => 'Guanajuato', '12' => 'Guerrero',
        '13' => 'Hidalgo', '14' => 'Jalisco', '15' => 'México', '16' => 'Michoacán de Ocampo',
        '17' => 'Morelos', '18' => 'Nayarit', '19' => 'Nuevo León', '20' => 'Oaxaca',
        '21' => 'Puebla', '22' => 'Querétaro', '23' => 'Quintana Roo', '24' => 'San Luis Potosí',
        '25' => 'Sinaloa', '26' => 'Sonora', '27' => 'Tabasco', '28' => 'Tamaulipas',
        '29' => 'Tlaxcala', '30' => 'Veracruz de Ignacio de la Llave', '31' => 'Yucatán', '32' => 'Zacatecas',
    ];
    /** Nombres comunes de los estados (ya normalizados) que difieren del oficial. */
    private const STATE_ALIASES = [
        'coahuila' => '05', 'cdmx' => '09', 'distrito federal' => '09', 'df' => '09', 'mexico df' => '09',
        'estado de mexico' => '15', 'edomex' => '15', 'edo mex' => '15', 'michoacan' => '16',
        'queretaro de arteaga' => '22', 'veracruz' => '30',
    ];
    /** Palabras que se dejan en minúscula al pasar a formato título. */
    private const LOWER_WORDS = ['de', 'del', 'la', 'las', 'los', 'el', 'y', 'e', 'en', 'a'];

    /**
     * Token del API del DENUE ($config['inegi-denue-token'], Admin → Sitio → INEGI); '' si no hay.
     *
     * @global class_config $config
     * @return string
     */
    public static function token(): string
    {
        global $config;
        $token = $config['inegi-denue-token'] ?? '';
        return is_string($token) ? trim($token) : '';
    }

    /**
     * true si hay token configurado.
     *
     * @return bool
     */
    public static function enabled(): bool
    {
        return self::token() !== '';
    }

    /**
     * Minúsculas, sin acentos ni signos y con espacios simples: para comparar textos.
     *
     * @param string $text
     * @return string
     */
    public static function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        $text = strtr($text, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n', 'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u']);
        $text = preg_replace('/[^a-z0-9]+/', ' ', $text);
        return trim($text);
    }

    /**
     * "VIRREYES RESIDENCIAL DE LA SIERRA" → "Virreyes Residencial de la Sierra".
     *
     * @param string $text
     * @return string
     */
    public static function title(string $text): string
    {
        $text = preg_replace('/\s+/u', ' ', trim($text));
        $words = explode(' ', mb_convert_case(mb_strtolower($text, 'UTF-8'), MB_CASE_TITLE, 'UTF-8'));
        foreach ($words as $i => $word) {
            if ($i > 0 && in_array(mb_strtolower($word, 'UTF-8'), self::LOWER_WORDS, true)) {
                $words[$i] = mb_strtolower($word, 'UTF-8');
            }
        }
        return implode(' ', $words);
    }

    /**
     * Clave INEGI (01-32) del estado escrito, o '' si no se reconoce.
     *
     * @param string $name
     * @return string
     */
    public static function stateCode(string $name): string
    {
        $norm = self::normalize($name);
        if ($norm === '') {
            return '';
        }
        if (isset(self::STATE_ALIASES[$norm])) {
            return self::STATE_ALIASES[$norm];
        }
        foreach (self::STATES as $code => $state) {
            if (self::normalize($state) === $norm) {
                return $code;
            }
        }
        // "Coahuila de Z." / "Nuevo Leon, Mexico": basta con que uno empiece con el otro.
        foreach (self::STATES as $code => $state) {
            $s = self::normalize($state);
            if (str_starts_with($s, $norm . ' ') || str_starts_with($norm, $s . ' ')) {
                return $code;
            }
        }
        return '';
    }

    /**
     * Convierte un establecimiento del DENUE en los campos de inputaddress.
     * Ubicacion viene como "LOCALIDAD, Municipio, ESTADO" (con espacios de relleno).
     *
     * @param array<string, mixed> $record Establecimiento tal como lo devuelve el API.
     * @return array{country: string, state: string, municipality: string, city: string, neighborhood: string, street: string, zipcode: string, lat: float|null, lng: float|null}
     */
    public static function parseRecord(array $record): array
    {
        $parts = array_map('trim', explode(',', (string) ($record['Ubicacion'] ?? '')));
        $stateName = (string) array_pop($parts);
        $municipality = (string) array_pop($parts);
        $city = implode(', ', array_filter($parts, fn($p) => $p !== ''));
        $code = self::stateCode($stateName);
        $street = trim((string) ($record['Calle'] ?? ''));
        if (in_array(self::normalize($street), ['ninguno', 'sin nombre', 'ninguna'], true)) {
            $street = '';
        }
        return [
            'country' => 'México',
            'state' => $code !== '' ? self::STATES[$code] : self::title($stateName),
            'municipality' => self::title($municipality),
            'city' => self::title($city !== '' ? $city : $municipality),
            'neighborhood' => self::title((string) ($record['Colonia'] ?? '')),
            'zipcode' => preg_match('/^\d{5}$/', trim((string) ($record['CP'] ?? ''))) ? trim($record['CP']) : '',
            'street' => self::title($street),
            'lat' => is_numeric($record['Latitud'] ?? null) ? (float) $record['Latitud'] : null,
            'lng' => is_numeric($record['Longitud'] ?? null) ? (float) $record['Longitud'] : null,
        ];
    }

    /**
     * Sugerencias para el campo $field mientras se escribe $q, usando como contexto los demás campos
     * ya llenados ($context: state, municipality, city, neighborhood, street, zipcode, country, lat, lng,
     * located). Devuelve [{label, value, address}] donde address son los campos a llenar al elegir.
     *
     * @param string $field Campo de inputaddress (state, municipality, city, neighborhood, street, zipcode).
     * @param string $q Texto escrito.
     * @param array<string, mixed> $context
     * @param int $limit
     * @return list<array{label: string, value: string, address: array<string, mixed>}>
     */
    public static function suggest(string $field, string $q, array $context = [], int $limit = 15): array
    {
        if (!in_array($field, self::FIELDS, true)) {
            return [];
        }
        $q = trim($q);
        $ctx = [];
        foreach (array_merge(self::FIELDS, ['country']) as $key) {
            $ctx[$key] = is_scalar($context[$key] ?? null) ? trim((string) $context[$key]) : '';
        }
        // Fuera de México el DENUE no tiene nada que aportar.
        if ($ctx['country'] !== '' && !in_array(self::normalize($ctx['country']), ['mexico', 'mx', 'mex', 'estados unidos mexicanos'], true)) {
            return [];
        }
        if ($field === 'state') {
            return self::suggestStates($q, $limit);
        }
        if (!self::enabled()) {
            return [];
        }

        $state = self::stateCode($ctx['state']);
        $near = null;
        if (!empty($context['located']) && is_numeric($context['lat'] ?? null) && is_numeric($context['lng'] ?? null)) {
            $near = [(float) $context['lat'], (float) $context['lng']];
        }

        $items = [];
        foreach (self::conditions($field, $q, $ctx) as $condition) {
            $records = [];
            if ($near !== null) {
                $records = self::request('Buscar/' . rawurlencode($condition) . '/' . $near[0] . ',' . $near[1] . '/' . self::RADIUS);
            }
            if (!$records) {
                $records = self::request('BuscarEntidad/' . rawurlencode($condition) . '/' . ($state !== '' ? $state : '00') . '/1/' . self::ROWS);
            }
            $items = self::rank($field, $q, $ctx, $records, $limit);
            if ($items) {
                break;
            }
        }
        return $items;
    }

    /**
     * Textos a buscar, del más al menos específico. El API solo reconoce palabras completas, así que
     * también se prueba sin la última palabra (la que se está escribiendo) y con los otros campos.
     *
     * @param string $field
     * @param string $q
     * @param array<string, mixed> $ctx
     * @return list<string> Máximo 3.
     */
    public static function conditions(string $field, string $q, array $ctx): array
    {
        $list = [];
        $words = self::normalize($q) === '' ? [] : explode(' ', self::normalize($q));
        if ($field !== 'zipcode' && $words) {
            $list[] = implode(' ', $words);
            if (count($words) > 1) {
                $list[] = implode(' ', array_slice($words, 0, -1));
            }
        }
        foreach (['neighborhood', 'street', 'city', 'municipality'] as $key) {
            if ($key !== $field && self::normalize($ctx[$key] ?? '') !== '') {
                $list[] = self::normalize($ctx[$key]);
            }
        }
        // Las palabras de una o dos letras no las indexa el API.
        $list = array_filter($list, fn($c) => strlen(str_replace(' ', '', $c)) > 2);
        return array_slice(array_values(array_unique($list)), 0, 3);
    }

    /**
     * Filtra los establecimientos que coinciden con lo escrito, los ordena por cuántos campos del
     * contexto respetan y deja una sugerencia por valor distinto.
     *
     * @param string $field
     * @param string $q
     * @param array<string, mixed> $ctx
     * @param list<array<string, mixed>> $records Establecimientos del API.
     * @param int $limit
     * @return list<array{label: string, value: string, address: array<string, mixed>}>
     */
    public static function rank(string $field, string $q, array $ctx, array $records, int $limit = 15): array
    {
        $nq = self::normalize($q);
        $keys = [
            'street' => ['street', 'neighborhood', 'zipcode', 'municipality'],
            'neighborhood' => ['neighborhood', 'zipcode', 'municipality'],
            'zipcode' => ['zipcode', 'neighborhood', 'municipality'],
            'city' => ['city', 'municipality', 'state'],
            'municipality' => ['municipality', 'state'],
        ][$field];
        $fill = [
            'street' => ['street', 'neighborhood', 'zipcode', 'city', 'municipality', 'state', 'country', 'lat', 'lng'],
            'neighborhood' => ['neighborhood', 'zipcode', 'city', 'municipality', 'state', 'country', 'lat', 'lng'],
            'zipcode' => ['zipcode', 'neighborhood', 'city', 'municipality', 'state', 'country', 'lat', 'lng'],
            'city' => ['city', 'municipality', 'state', 'country'],
            'municipality' => ['municipality', 'state', 'country'],
        ][$field];

        $found = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }
            $address = self::parseRecord($record);
            $value = $address[$field];
            $nvalue = self::normalize($value);
            if ($nvalue === '') {
                continue;
            }
            if ($nq !== '') {
                $match = $field === 'zipcode'
                    ? str_starts_with($nvalue, $nq)
                    : !array_filter(explode(' ', $nq), fn($w) => !str_contains($nvalue, $w));
                if (!$match) {
                    continue;
                }
            }
            $score = 0;
            foreach (['state', 'municipality', 'city', 'neighborhood', 'street', 'zipcode'] as $key) {
                $c = self::normalize($ctx[$key] ?? '');
                if ($key === $field || $c === '') {
                    continue;
                }
                $v = self::normalize($address[$key]);
                if ($key === 'state') {
                    $hit = self::stateCode($ctx['state']) === self::stateCode($address['state']);
                } elseif ($key === 'zipcode') {
                    $hit = $v === $c;
                } else {
                    $hit = $v !== '' && (str_contains($v, $c) || str_contains($c, $v));
                }
                $score += $hit ? 2 : -1;
            }
            if ($nq !== '' && str_starts_with($nvalue, $nq)) {
                $score += 1;
            }
            $key = implode('|', array_map(fn($k) => self::normalize((string) $address[$k]), $keys));
            if (isset($found[$key])) {
                // El DENUE tiene capturas erróneas: lo que más se repite es lo más probable.
                $found[$key]['count']++;
                continue;
            }
            $label = $value;
            $details = [];
            foreach (array_slice($keys, 1) as $k) {
                if ($address[$k] !== '' && $k !== 'state') {
                    $details[] = $k === 'zipcode' ? 'CP ' . $address[$k] : $address[$k];
                }
            }
            if ($field === 'zipcode' || $field === 'municipality' || $field === 'city') {
                $details[] = $address['state'];
            }
            if ($details) {
                $label .= ' — ' . implode(', ', $details);
            }
            $found[$key] = [
                'label' => $label,
                'value' => $value,
                'address' => array_intersect_key($address, array_flip($fill)),
                'score' => $score,
                'count' => 1,
            ];
        }
        uasort($found, fn($a, $b) => [$b['score'], $b['count'], $a['label']] <=> [$a['score'], $a['count'], $b['label']]);
        return array_map(function ($item) {
            unset($item['score'], $item['count']);
            return $item;
        }, array_slice(array_values($found), 0, $limit));
    }

    /**
     * Estados cuyo nombre coincide con $q (sin consultar el API).
     *
     * @param string $q
     * @param int $limit
     * @return list<array{label: string, value: string, address: array<string, mixed>}>
     */
    private static function suggestStates(string $q, int $limit): array
    {
        $nq = self::normalize($q);
        $items = [];
        foreach (self::STATES as $state) {
            if ($nq === '' || str_contains(self::normalize($state), $nq)) {
                $items[] = ['label' => $state, 'value' => $state, 'address' => ['state' => $state, 'country' => 'México']];
            }
        }
        return array_slice($items, 0, $limit);
    }

    /**
     * Llama al API (con caché) y devuelve la lista de establecimientos; [] si no hay o si falla.
     *
     * @param string $path Ruta del API después de BASE_URL (sin el token).
     * @return list<array<string, mixed>>
     */
    public static function request(string $path): array
    {
        $token = self::token();
        if ($token === '') {
            return [];
        }
        return nfCacheRemember('denue:' . $path . ':' . md5($token), self::CACHE_TTL, function () use ($path, $token) {
            $body = self::httpGet(self::BASE_URL . $path . '/' . rawurlencode($token));
            $data = $body === null ? null : json_decode($body, true);
            return is_array($data) && array_is_list($data) ? $data : [];
        });
    }

    /**
     * GET con cURL (12 s) que reintenta con caBundle() si falla la verificación TLS del certificado de INEGI.
     *
     * @param string $url
     * @return string|null Cuerpo de la respuesta con estado 200, o null.
     */
    private static function httpGet(string $url): ?string
    {
        $get = function (?string $cainfo) use ($url) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 12,
                CURLOPT_CONNECTTIMEOUT => 6,
                CURLOPT_USERAGENT => 'nframework',
            ] + ($cainfo ? [CURLOPT_CAINFO => $cainfo] : []));
            $body = curl_exec($ch);
            $errno = curl_errno($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            return [$status === 200 && is_string($body) ? $body : null, $errno];
        };
        [$body, $errno] = $get(null);
        // 60: emisor desconocido (falta el intermedio); 51: certificado del par no verificado.
        if ($body === null && in_array($errno, [51, 60], true)) {
            $bundle = self::caBundle();
            if ($bundle !== null) {
                [$body] = $get($bundle);
            }
        }
        if ($body === null) {
            error_log('Denue: sin respuesta de ' . preg_replace('#/[^/]+$#', '/***', $url) . ' (curl ' . $errno . ')');
        }
        return $body;
    }

    /**
     * Paquete de CA del sistema + el intermedio que INEGI no envía. El intermedio se descarga por HTTP,
     * por eso solo se usa si es una CA verificada contra las raíces del sistema.
     *
     * @return string|null Ruta del paquete de CA, o null si no se pudo armar.
     */
    private static function caBundle(): ?string
    {
        $system = null;
        $locations = openssl_get_cert_locations();
        foreach ([ini_get('curl.cainfo'), ini_get('openssl.cafile'), $locations['default_cert_file'] ?? '', '/etc/ssl/certs/ca-certificates.crt', '/etc/pki/tls/certs/ca-bundle.crt'] as $file) {
            if (is_string($file) && $file !== '' && is_readable($file)) {
                $system = $file;
                break;
            }
        }
        if ($system === null) {
            return null;
        }
        $bundle = sys_get_temp_dir() . '/nframework-denue-ca-' . md5($system . self::ISSUER_URL) . '.pem';
        if (is_readable($bundle) && filemtime($bundle) > time() - 30 * 86400 && filemtime($bundle) >= filemtime($system)) {
            return $bundle;
        }
        $ch = curl_init(self::ISSUER_URL);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 2]);
        $der = curl_exec($ch);
        if (!is_string($der) || $der === '') {
            return null;
        }
        $pem = str_contains($der, '-----BEGIN CERTIFICATE-----')
            ? $der
            : "-----BEGIN CERTIFICATE-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END CERTIFICATE-----\n";
        $info = @openssl_x509_parse($pem);
        $isCa = is_array($info) && str_contains((string) ($info['extensions']['basicConstraints'] ?? ''), 'CA:TRUE');
        if (!$isCa || @openssl_x509_checkpurpose($pem, X509_PURPOSE_ANY, [$system]) !== true) {
            error_log('Denue: el certificado intermedio descargado no es válido; se ignora.');
            return null;
        }
        $tmp = @tempnam(sys_get_temp_dir(), 'nfca');
        if ($tmp === false || @file_put_contents($tmp, file_get_contents($system) . "\n" . $pem) === false) {
            return null;
        }
        @chmod($tmp, 0644);
        @rename($tmp, $bundle);
        return $bundle;
    }
}
