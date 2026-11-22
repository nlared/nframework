<?php

/**
 * Mapa (Leaflet) para dibujar una geocerca (polígono) o una polilínea, opcionalmente dentro de otra
 * geocerca que sirve de límite.
 *
 * Se guarda como GeoJSON (coordenadas [lng, lat] en double), listo para un índice 2dsphere:
 *   shape 'polygon'  → {type: 'Polygon', coordinates: [[[lng, lat], ..., primer punto]]}
 *   shape 'polyline' → {type: 'LineString', coordinates: [[lng, lat], ...]}
 *
 * `limit` acepta un Polygon, MultiPolygon, Feature o FeatureCollection (arreglo, BSON o JSON). Con límite,
 * el navegador no deja poner vértices ni trazar lados fuera de él, y is_valid() repite la comprobación en el
 * servidor, así que dataset->save() rechaza una figura manipulada. Los cálculos son planos sobre lng/lat:
 * suficientes para geocercas de ciudad o región, no para figuras que crucen el antimeridiano.
 *
 * En el navegador: mapshapes['<id>'] = {map, getPoints(), getGeoJSON(), setGeoJSON(g), clear(), undo(),
 * setLimit(g), fit()}. Cada cambio lanza el evento `mapshape:change` (event.detail.geometry) sobre
 * #<id>_wrap y ejecuta `onchange` (variable `geometry`).
 */
class mapshape extends baseInput
{
    public const POLYGON = 'polygon';
    public const POLYLINE = 'polyline';

    /** @var 'polygon'|'polyline' */
    public $shape = self::POLYGON;
    /** @var array<string, mixed>|object|string|null Geocerca límite (Polygon, MultiPolygon o Feature; arreglo, BSON o JSON). */
    public $limit;                 // geocerca límite (GeoJSON) o null
    /** @var int */
    public $height = 500;
    /** @var int */
    public $zoom = 14;
    /** @var array{lat: float, lng: float} */
    public $startpoint = ['lat' => 25.43328030, 'lng' => -100.96047970];
    /** @var string */
    public $color = '#1e88e5';
    /** @var string */
    public $limitcolor = '#e53935';
    /** @var int */
    public $maxpoints = 500;
    /** @var string */
    public $onchange = '';         // JS; recibe `geometry` (GeoJSON o null)
    /** @var bool */
    public $toolbar = true;        // botones Deshacer / Limpiar / Ajustar e información de la figura
    /** @var string */
    public $error = '';            // motivo del último is_valid() fallido

    /**
     * @param array<string, mixed> $options Propiedades públicas y las de baseInput.
     * @throws InvalidArgumentException Si shape no es polygon ni polyline.
     */
    public function __construct($options = [])
    {
        parent::__construct($options);
        if (!in_array($this->shape, [self::POLYGON, self::POLYLINE], true)) {
            throw new InvalidArgumentException('mapshape: shape debe ser polygon o polyline');
        }
    }

    // ------------------------------------------------------------------ valor

    /**
     * Puntos [lng, lat] de un valor (JSON, GeoJSON en arreglo/BSON, Feature); el anillo del polígono
     * se devuelve abierto. null si el valor está vacío o no es una figura válida del tipo de este control.
     *
     * @param mixed $val
     * @return list<array{0: float, 1: float}>|null
     */
    public function points($val): ?array
    {
        $geo = self::toGeometry($val);
        if ($geo === null) {
            return null;
        }
        $expected = $this->shape === self::POLYGON ? 'Polygon' : 'LineString';
        if (($geo['type'] ?? '') !== $expected || !is_array($geo['coordinates'] ?? null)) {
            return null;
        }
        $coords = $expected === 'Polygon' ? ($geo['coordinates'][0] ?? null) : $geo['coordinates'];
        if (!is_array($coords)) {
            return null;
        }
        $points = [];
        foreach ($coords as $c) {
            $p = self::point($c);
            if ($p === null) {
                return null;
            }
            $points[] = $p;
        }
        if ($expected === 'Polygon' && count($points) > 1 && $points[0] === $points[count($points) - 1]) {
            array_pop($points);
        }

        return $points;
    }

    /**
     * GeoJSON que se guarda en MongoDB, o null si no hay figura.
     *
     * @param list<array{0: float, 1: float}>|null $points
     * @return array{type: 'Polygon'|'LineString', coordinates: list<mixed>}|null
     */
    public function geometry(?array $points): ?array
    {
        if (empty($points)) {
            return null;
        }
        if ($this->shape === self::POLYGON) {
            $points[] = $points[0];

            return ['type' => 'Polygon', 'coordinates' => [$points]];
        }

        return ['type' => 'LineString', 'coordinates' => $points];
    }

    /**
     * GeoJSON (Polygon o LineString) que se guarda, o null.
     *
     * @param mixed $val
     * @return array{type: string, coordinates: list<mixed>}|null
     */
    public function __toMongo($val)
    {
        return $this->geometry($this->points($val));
    }

    /**
     * Valida la figura con check(); el motivo queda en $this->error.
     *
     * @param mixed $newval
     * @return bool
     */
    public function is_valid($newval)
    {
        $this->error = '';
        if ($newval === null || $newval === '' || $newval === []) {
            $this->error = $this->required ? 'Dibuje la figura.' : '';

            return !$this->required;
        }
        $points = $this->points($newval);
        if ($points === null) {
            $this->error = 'La figura no es válida.';

            return false;
        }
        $this->error = $this->check($points);

        return $this->error === '';
    }

    /**
     * Valida los puntos [lng, lat]; devuelve el motivo o '' si la figura es correcta.
     *
     * @param list<array{0: float, 1: float}> $points
     * @return string
     */
    public function check(array $points): string
    {
        $min = $this->shape === self::POLYGON ? 3 : 2;
        if (count($points) < $min) {
            return "Se necesitan al menos $min puntos.";
        }
        if ($this->maxpoints > 0 && count($points) > $this->maxpoints) {
            return 'Se permiten como máximo ' . $this->maxpoints . ' puntos.';
        }
        $closed = $this->shape === self::POLYGON;
        if ($closed && self::selfIntersects($points)) {
            return 'Los lados del polígono no deben cruzarse.';
        }
        $limit = self::polygons($this->limit);
        if ($limit && !self::insidePolygons($points, $limit, $closed)) {
            return 'La figura sale del límite permitido.';
        }

        return '';
    }

    // ------------------------------------------------------------------ geometría

    /**
     * Convierte JSON/BSON/Feature a un arreglo GeoJSON de geometría, o null.
     *
     * @param mixed $val
     * @return array<string, mixed>|null
     */
    public static function toGeometry($val): ?array
    {
        if (is_string($val)) {
            $val = trim($val);
            if ($val === '') {
                return null;
            }
            $val = json_decode($val, true, 16);
        }
        $val = self::plain($val);
        if (!is_array($val)) {
            return null;
        }
        if (($val['type'] ?? '') === 'Feature') {
            $val = is_array($val['geometry'] ?? null) ? $val['geometry'] : null;
        }

        return is_array($val) && is_string($val['type'] ?? null) ? $val : null;
    }

    /**
     * Polígonos de una geocerca: lista de polígonos, cada uno lista de anillos (el primero exterior, los
     * demás huecos), cada anillo lista de [lng, lat] abierta. Acepta Polygon, MultiPolygon, Feature y
     * FeatureCollection.
     *
     * @param mixed $geo
     * @return list<list<list<array{0: float, 1: float}>>>
     */
    public static function polygons($geo): array
    {
        if (is_string($geo)) {
            $geo = json_decode($geo, true, 64);
        }
        $geo = self::plain($geo);
        if (!is_array($geo)) {
            return [];
        }
        $type = $geo['type'] ?? '';
        if ($type === 'FeatureCollection') {
            $out = [];
            foreach ((array) ($geo['features'] ?? []) as $feature) {
                $out = array_merge($out, self::polygons($feature));
            }

            return $out;
        }
        if ($type === 'Feature') {
            return self::polygons($geo['geometry'] ?? null);
        }
        $list = match ($type) {
            'Polygon' => [$geo['coordinates'] ?? []],
            'MultiPolygon' => $geo['coordinates'] ?? [],
            default => [],
        };
        $out = [];
        foreach ((array) $list as $poly) {
            $rings = [];
            foreach ((array) $poly as $ring) {
                $pts = [];
                foreach ((array) $ring as $c) {
                    if (($p = self::point($c)) !== null) {
                        $pts[] = $p;
                    }
                }
                if (count($pts) > 1 && $pts[0] === $pts[count($pts) - 1]) {
                    array_pop($pts);
                }
                if (count($pts) >= 3) {
                    $rings[] = $pts;
                }
            }
            if ($rings) {
                $out[] = $rings;
            }
        }

        return $out;
    }

    /**
     * ¿El punto está dentro de la geocerca (Polygon, MultiPolygon, Feature...)? Respeta los huecos.
     *
     * @param mixed $geofence
     * @param float $lat
     * @param float $lng
     * @return bool
     */
    public static function contains($geofence, float $lat, float $lng): bool
    {
        return self::inPolygons([$lng, $lat], self::polygons($geofence));
    }

    /**
     * [lng, lat] en double, o null si la coordenada no es válida.
     *
     * @param mixed $c
     * @return array{0: float, 1: float}|null
     */
    private static function point($c): ?array
    {
        if (!is_array($c) || !isset($c[0], $c[1]) || !is_numeric($c[0]) || !is_numeric($c[1])) {
            return null;
        }
        $lng = round((float) $c[0], 7);
        $lat = round((float) $c[1], 7);
        if ($lng < -180 || $lng > 180 || $lat < -90 || $lat > 90) {
            return null;
        }

        return [$lng, $lat];
    }

    /**
     * BSONDocument/BSONArray/objetos → arreglos de PHP.
     *
     * @param mixed $val
     * @return mixed
     */
    private static function plain($val)
    {
        if ($val instanceof \MongoDB\Model\BSONDocument || $val instanceof \MongoDB\Model\BSONArray) {
            $val = $val->getArrayCopy();
        } elseif (is_object($val)) {
            $val = get_object_vars($val);
        }
        if (is_array($val)) {
            foreach ($val as $k => $v) {
                if (is_array($v) || is_object($v)) {
                    $val[$k] = self::plain($v);
                }
            }
        }

        return $val;
    }

    /**
     * Punto dentro de un anillo (ray casting).
     *
     * @param array{0: float, 1: float} $p
     * @param list<array{0: float, 1: float}> $ring
     * @return bool
     */
    private static function inRing(array $p, array $ring): bool
    {
        $inside = false;
        $n = count($ring);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            [$xi, $yi] = $ring[$i];
            [$xj, $yj] = $ring[$j];
            if ((($yi > $p[1]) !== ($yj > $p[1])) && ($p[0] < ($xj - $xi) * ($p[1] - $yi) / ($yj - $yi) + $xi)) {
                $inside = !$inside;
            }
        }

        return $inside;
    }

    /**
     * Punto dentro de algún polígono y fuera de sus huecos.
     *
     * @param array{0: float, 1: float} $p
     * @param list<list<list<array{0: float, 1: float}>>> $polygons
     * @return bool
     */
    private static function inPolygons(array $p, array $polygons): bool
    {
        foreach ($polygons as $rings) {
            if (!self::inRing($p, $rings[0])) {
                continue;
            }
            $inHole = false;
            for ($h = 1, $c = count($rings); $h < $c; $h++) {
                if (self::inRing($p, $rings[$h])) {
                    $inHole = true;
                    break;
                }
            }
            if (!$inHole) {
                return true;
            }
        }

        return false;
    }

    /**
     * Producto cruz de ab × ac: > 0 a la izquierda, < 0 a la derecha, 0 colineales.
     *
     * @param array{0: float, 1: float} $a
     * @param array{0: float, 1: float} $b
     * @param array{0: float, 1: float} $c
     * @return float
     */
    private static function orient(array $a, array $b, array $c): float
    {
        return ($b[0] - $a[0]) * ($c[1] - $a[1]) - ($b[1] - $a[1]) * ($c[0] - $a[0]);
    }

    /**
     * Cruce propio de los segmentos ab y cd (tocarse en un extremo no cuenta).
     *
     * @param array{0: float, 1: float} $a
     * @param array{0: float, 1: float} $b
     * @param array{0: float, 1: float} $c
     * @param array{0: float, 1: float} $d
     * @return bool
     */
    private static function crosses(array $a, array $b, array $c, array $d): bool
    {
        $d1 = self::orient($c, $d, $a);
        $d2 = self::orient($c, $d, $b);
        $d3 = self::orient($a, $b, $c);
        $d4 = self::orient($a, $b, $d);

        return (($d1 > 0 && $d2 < 0) || ($d1 < 0 && $d2 > 0)) && (($d3 > 0 && $d4 < 0) || ($d3 < 0 && $d4 > 0));
    }

    /**
     * true si dos lados no contiguos del polígono se cruzan.
     *
     * @param list<array{0: float, 1: float}> $pts
     * @return bool
     */
    private static function selfIntersects(array $pts): bool
    {
        $n = count($pts);
        for ($i = 0; $i < $n; $i++) {
            $a = $pts[$i];
            $b = $pts[($i + 1) % $n];
            for ($j = $i + 2; $j < $n; $j++) {
                if ($i === 0 && $j === $n - 1) {
                    continue;   // lados contiguos por el cierre del anillo
                }
                if (self::crosses($a, $b, $pts[$j], $pts[($j + 1) % $n])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Todos los vértices dentro, ningún lado cruza un borde del límite y, si la figura es un polígono,
     * no encierra ninguno de sus huecos.
     *
     * @param list<array{0: float, 1: float}> $pts
     * @param list<list<list<array{0: float, 1: float}>>> $polygons
     * @param bool $closed true = polígono (se revisa también el lado de cierre).
     * @return bool
     */
    private static function insidePolygons(array $pts, array $polygons, bool $closed): bool
    {
        foreach ($pts as $p) {
            if (!self::inPolygons($p, $polygons)) {
                return false;
            }
        }
        $n = count($pts);
        $segments = $closed ? $n : $n - 1;
        foreach ($polygons as $rings) {
            foreach ($rings as $h => $ring) {
                $m = count($ring);
                for ($i = 0; $i < $segments; $i++) {
                    $a = $pts[$i];
                    $b = $pts[($i + 1) % $n];
                    for ($j = 0; $j < $m; $j++) {
                        if (self::crosses($a, $b, $ring[$j], $ring[($j + 1) % $m])) {
                            return false;
                        }
                    }
                }
                if ($closed && $h > 0 && self::inRing($ring[0], $pts)) {
                    return false;
                }
            }
        }

        return true;
    }

    // ------------------------------------------------------------------ HTML

    /**
     * @global class_nframework $nframework
     * @global Javas $javas
     * @return string HTML.
     */
    public function __toString()
    {
        global $nframework, $javas;
        $nframework->csss['005leaflet'] = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
        $nframework->jss['100leaflet'] = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';

        $points = $this->points($this->value) ?? [];
        $geometry = $this->geometry($points);
        $editable = !$this->readonly && !$this->disabled;
        $limit = self::polygons($this->limit);

        if (empty($nframework->onces['mapshape'])) {
            $nframework->onces['mapshape'] = true;
            $javas->addjs('var mapshapes = window.mapshapes || {}; window.maps = window.maps || [];' . self::script());
        }
        $opts = json_encode([
            'id' => $this->id,
            'shape' => $this->shape,
            'points' => array_map(fn($p) => [$p[1], $p[0]], $points),
            'limit' => $limit ? ['type' => 'MultiPolygon', 'coordinates' => array_map(
                fn($rings) => array_map(fn($r) => array_merge($r, [$r[0]]), $rings),
                $limit
            )] : null,
            'center' => [(float) ($this->startpoint['lat'] ?? 0), (float) ($this->startpoint['lng'] ?? 0)],
            'zoom' => (int) $this->zoom,
            'color' => (string) $this->color,
            'limitcolor' => (string) $this->limitcolor,
            'maxpoints' => (int) $this->maxpoints,
            'editable' => $editable,
        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        $onchange = (string) ($this->onchange ?: $this->onChange);
        $javas->addjs('mapshapes[' . json_encode($this->id) . '] = nfMapShape(' . $opts . ', function (geometry) {' . $onchange . "\n});", 'ready');

        $id = htmlspecialchars($this->id, ENT_QUOTES, 'UTF-8');
        $value = $geometry ? htmlspecialchars(json_encode($geometry), ENT_QUOTES, 'UTF-8') : '';
        $bar = '';
        if ($this->toolbar) {
            $buttons = $editable ? '<button type="button" class="button small" data-mapshape="undo" title="Deshacer"><span class="mif-undo"></span></button>'
                . '<button type="button" class="button small" data-mapshape="clear" title="Limpiar"><span class="mif-bin"></span></button>' : '';
            $bar = '<div class="d-flex flex-align-center flex-wrap mt-1" style="gap:.25rem">' . $buttons
                . '<button type="button" class="button small" data-mapshape="fit" title="Ajustar vista"><span class="mif-enlarge"></span></button>'
                . '<span class="ml-2 text-small" data-mapshape="info"></span></div>';
        }

        return '<div class="nf-mapshape" id="' . $id . '_wrap">' . $this->getLabelHtml()
            . '<div id="' . $id . '_map" style="height:' . (int) $this->height . 'px;"></div>' . $bar
            . '<input type="hidden" id="' . $id . '" name="' . htmlspecialchars($this->name, ENT_QUOTES, 'UTF-8') . '" value="' . $value . '"'
            . ($this->disabled ? ' disabled' : '') . '>'
            . '</div>';
    }

    /**
     * Editor en el navegador (una vez por página). Repite las mismas reglas que check().
     *
     * @return string
     */
    private static function script(): string
    {
        return <<<'JS'
function nfMapShape(o, onchange) {
    var wrap = document.getElementById(o.id + '_wrap'), input = document.getElementById(o.id);
    var info = wrap.querySelector('[data-mapshape=info]');
    var closed = o.shape === 'polygon', pts = o.points.slice(), history = [], limit = null, limitLayer = null;
    var map = L.map(o.id + '_map', {doubleClickZoom: false}).setView(o.center, o.zoom);
    maps[o.id + '_map'] = map;
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 19, attribution: '&copy; OpenStreetMap'}).addTo(map);
    var layer = (closed ? L.polygon([], {color: o.color}) : L.polyline([], {color: o.color, weight: 4})).addTo(map);
    var handles = L.layerGroup().addTo(map);

    // ---- geometría plana en [lng, lat], igual que mapshape::check()
    function xy(p) { return [p[1], p[0]]; }
    function inRing(p, r) {
        var inside = false;
        for (var i = 0, j = r.length - 1; i < r.length; j = i++) {
            if (((r[i][1] > p[1]) !== (r[j][1] > p[1])) && (p[0] < (r[j][0] - r[i][0]) * (p[1] - r[i][1]) / (r[j][1] - r[i][1]) + r[i][0])) inside = !inside;
        }
        return inside;
    }
    function inLimit(p) {
        return limit.some(function (rings) {
            return inRing(p, rings[0]) && !rings.slice(1).some(function (h) { return inRing(p, h); });
        });
    }
    function orient(a, b, c) { return (b[0] - a[0]) * (c[1] - a[1]) - (b[1] - a[1]) * (c[0] - a[0]); }
    function crosses(a, b, c, d) {
        var d1 = orient(c, d, a), d2 = orient(c, d, b), d3 = orient(a, b, c), d4 = orient(a, b, d);
        return ((d1 > 0 && d2 < 0) || (d1 < 0 && d2 > 0)) && ((d3 > 0 && d4 < 0) || (d3 < 0 && d4 > 0));
    }
    function check(list) {
        var p = list.map(xy), n = p.length, i, j, k;
        if (o.maxpoints > 0 && n > o.maxpoints) return 'Se permiten como máximo ' + o.maxpoints + ' puntos.';
        var segs = closed && n > 2 ? n : n - 1;
        if (closed && n > 3) {
            for (i = 0; i < n; i++) for (j = i + 2; j < n; j++) {
                if (i === 0 && j === n - 1) continue;
                if (crosses(p[i], p[(i + 1) % n], p[j], p[(j + 1) % n])) return 'Los lados del polígono no deben cruzarse.';
            }
        }
        if (!limit) return '';
        for (i = 0; i < n; i++) if (!inLimit(p[i])) return 'El punto queda fuera del límite.';
        for (k = 0; k < limit.length; k++) for (var h = 0; h < limit[k].length; h++) {
            var r = limit[k][h], m = r.length;
            for (i = 0; i < segs; i++) for (j = 0; j < m; j++) {
                if (crosses(p[i], p[(i + 1) % n], r[j], r[(j + 1) % m])) return 'El trazo sale del límite.';
            }
            if (closed && h > 0 && n > 2 && inRing(r[0], p)) return 'La figura no puede encerrar un hueco del límite.';
        }
        return '';
    }
    function setLimit(g) {
        if (limitLayer) { map.removeLayer(limitLayer); limitLayer = null; }
        limit = null;
        var polys = [];
        (function collect(g) {
            if (!g) return;
            if (g.type === 'FeatureCollection') g.features.forEach(collect);
            else if (g.type === 'Feature') collect(g.geometry);
            else if (g.type === 'Polygon') polys.push(g.coordinates);
            else if (g.type === 'MultiPolygon') polys = polys.concat(g.coordinates);
        })(typeof g === 'string' ? JSON.parse(g) : g);
        if (!polys.length) return;
        limit = polys.map(function (rings) {
            return rings.map(function (r) {
                var a = r[0], z = r[r.length - 1];
                return (a[0] === z[0] && a[1] === z[1]) ? r.slice(0, -1) : r.slice();
            });
        });
        limitLayer = L.geoJSON({type: 'MultiPolygon', coordinates: polys}, {
            interactive: false,
            style: {color: o.limitcolor, weight: 2, dashArray: '6 6', fillOpacity: 0.04}
        }).addTo(map);
    }

    // ---- medidas
    function length() {
        var d = 0;
        for (var i = 1; i < pts.length; i++) d += map.distance(pts[i - 1], pts[i]);
        if (closed && pts.length > 2) d += map.distance(pts[pts.length - 1], pts[0]);
        return d;
    }
    function area() {
        var a = 0, R = 6378137, rad = Math.PI / 180;
        for (var i = 0; i < pts.length; i++) {
            var p1 = pts[i], p2 = pts[(i + 1) % pts.length];
            a += (p2[1] - p1[1]) * rad * (2 + Math.sin(p1[0] * rad) + Math.sin(p2[0] * rad));
        }
        return Math.abs(a * R * R / 2);
    }
    function fmt(v, unit) {
        if (unit === 'm') return v < 1000 ? v.toFixed(0) + ' m' : (v / 1000).toFixed(2) + ' km';
        return v < 10000 ? v.toFixed(0) + ' m²' : (v < 1e6 ? (v / 10000).toFixed(2) + ' ha' : (v / 1e6).toFixed(2) + ' km²');
    }
    function say(msg, bad) {
        if (!info) { if (bad) { if (typeof toast === 'function') toast(msg); else alert(msg); } return; }
        if (msg) { info.textContent = msg; info.className = 'ml-2 text-small ' + (bad ? 'fg-red' : ''); return; }
        var t = pts.length + ' punto' + (pts.length === 1 ? '' : 's');
        if (pts.length > 1) t += ' · ' + (closed ? 'perímetro ' : 'longitud ') + fmt(length(), 'm');
        if (closed && pts.length > 2) t += ' · área ' + fmt(area());
        info.textContent = t;
        info.className = 'ml-2 text-small';
    }

    // ---- estado
    function geojson() {
        var c = pts.map(xy), min = closed ? 3 : 2;
        if (c.length < min) return null;
        return closed ? {type: 'Polygon', coordinates: [c.concat([c[0]])]} : {type: 'LineString', coordinates: c};
    }
    function commit(silent) {
        var g = geojson();
        input.value = g ? JSON.stringify(g) : '';
        draw();
        say();
        if (silent) return;
        $(input).trigger('change');
        wrap.dispatchEvent(new CustomEvent('mapshape:change', {bubbles: true, detail: {geometry: g}}));
        if (onchange) onchange.call(wrap, g);
    }
    function apply(list) {
        var err = check(list);
        if (err) { say(err, true); return false; }
        history.push(pts);
        pts = list;
        commit();
        return true;
    }
    var vIcon = L.divIcon({className: '', iconSize: [12, 12], html: '<div style="width:12px;height:12px;border-radius:50%;background:#fff;border:2px solid ' + o.color + ';box-sizing:border-box"></div>'});
    var mIcon = L.divIcon({className: '', iconSize: [10, 10], html: '<div style="width:10px;height:10px;border-radius:50%;background:' + o.color + ';opacity:.45"></div>'});
    function draw() {
        layer.setLatLngs(pts);
        handles.clearLayers();
        if (!o.editable) return;
        pts.forEach(function (p, i) {
            var mk = L.marker(p, {icon: vIcon, draggable: true, title: 'Arrastre para mover; clic derecho o doble clic para borrar'}).addTo(handles);
            mk.on('drag', function (e) {
                var ll = e.target.getLatLng(), list = pts.slice();
                list[i] = [ll.lat, ll.lng];
                if (!check(list)) { layer.setLatLngs(list); mk._nfLast = list[i]; say(); } else say(check(list), true);
            });
            mk.on('dragend', function () {
                var list = pts.slice();
                if (mk._nfLast) { list[i] = mk._nfLast; apply(list); } else draw();
            });
            mk.on('contextmenu dblclick', function (e) {
                if (e.originalEvent) L.DomEvent.stop(e.originalEvent);
                var list = pts.slice();
                list.splice(i, 1);
                apply(list);
            });
        });
        var n = pts.length, segs = closed && n > 2 ? n : n - 1;
        for (var i = 0; i < segs; i++) (function (i) {
            var a = pts[i], b = pts[(i + 1) % n];
            L.marker([(a[0] + b[0]) / 2, (a[1] + b[1]) / 2], {icon: mIcon, title: 'Clic para agregar un vértice'}).addTo(handles)
                .on('click', function (e) {
                    if (e.originalEvent) L.DomEvent.stop(e.originalEvent);
                    var list = pts.slice();
                    list.splice(i + 1, 0, [(a[0] + b[0]) / 2, (a[1] + b[1]) / 2]);
                    apply(list);
                });
        })(i);
    }
    function fit() {
        if (pts.length) map.fitBounds(L.latLngBounds(pts), {padding: [20, 20], maxZoom: 18});
        else if (limitLayer) map.fitBounds(limitLayer.getBounds(), {padding: [20, 20]});
    }

    if (o.editable) {
        map.on('click', function (e) { apply(pts.concat([[e.latlng.lat, e.latlng.lng]])); });
    }
    wrap.addEventListener('click', function (e) {
        var b = e.target.closest('[data-mapshape]');
        if (!b) return;
        var act = b.getAttribute('data-mapshape');
        if (act === 'undo') api.undo();
        else if (act === 'clear') api.clear();
        else if (act === 'fit') fit();
    });
    if (window.ResizeObserver) new ResizeObserver(function () { map.invalidateSize(); }).observe(document.getElementById(o.id + '_map'));

    setLimit(o.limit);
    commit(true);
    fit();

    var api = {
        map: map,
        getPoints: function () { return pts.slice(); },
        getGeoJSON: geojson,
        setGeoJSON: function (g) {
            g = typeof g === 'string' ? JSON.parse(g) : g;
            if (g && g.type === 'Feature') g = g.geometry;
            var c = !g ? [] : (g.type === 'Polygon' ? g.coordinates[0] : g.coordinates) || [];
            var list = c.map(xy);
            if (closed && list.length > 1 && list[0][0] === list[list.length - 1][0] && list[0][1] === list[list.length - 1][1]) list.pop();
            var ok = apply(list);
            if (ok) fit();
            return ok;
        },
        clear: function () { if (pts.length) { history.push(pts); pts = []; commit(); } },
        undo: function () { if (history.length) { pts = history.pop(); commit(); } },
        setLimit: function (g) { setLimit(g); var err = check(pts); if (err) say(err, true); fit(); return !err; },
        check: function () { return check(pts); },
        fit: fit
    };
    return api;
}
JS;
    }
}
