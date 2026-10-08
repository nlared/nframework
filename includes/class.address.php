<?php

/**
 * Captura de dirección con campos y/o mapa.
 *
 * Opciones:
 *  - showfields    bool   Muestra los campos de la dirección (default true).
 *  - showmap       bool   Muestra el mapa para ubicar la dirección (default true).
 *  - fields        array  Campos a mostrar y su orden (default: todos, ver FIELDS).
 *  - defaults      array  Valores predeterminados por campo, se usan si el campo está vacío.
 *                         Incluye 'lat' y 'lng' para el punto inicial del mapa.
 *  - frozen        array  Campos congelados (solo lectura). Su valor no puede cambiarse desde el
 *                         navegador: al guardar se conserva el predeterminado o el ya almacenado.
 *                         Use 'location' para congelar la ubicación del mapa, o true para congelar todo.
 *  - requiredfields array Campos obligatorios.
 *  - labels        array  Etiquetas personalizadas por campo.
 *  - map_height    int    Alto del mapa en px (default 400).
 *  - geocoder      string 'nominatim' (default, sin llave) o 'google' (usa $config['google-maps-api']).
 *
 * El valor se guarda como arreglo: country, state, municipality, city, zipcode, neighborhood,
 * street, external_number, internal_number, lat, lng.
 *
 * Ejemplo:
 *  new inputaddress([
 *      'dataset' => &$dataset, 'field' => 'domicilio', 'caption' => 'Domicilio',
 *      'defaults' => ['country' => 'México', 'state' => 'Coahuila'],
 *      'frozen' => ['country'],
 *      'requiredfields' => ['street', 'zipcode'],
 *  ]);
 */
class inputaddress extends baseInput
{
    public const FIELDS = [
        'country' => 'País',
        'zipcode' => 'Código postal',
        'state' => 'Estado',
        'municipality' => 'Municipio',
        'city' => 'Ciudad / localidad',
        'neighborhood' => 'Colonia',
        'street' => 'Calle',
        'external_number' => 'No. exterior',
        'internal_number' => 'No. interior',
    ];
    private const COORDS = ['lat', 'lng'];

    public $showfields = true;
    public $showmap = true;
    public $fields = [];
    public $defaults = [];
    public $frozen = [];
    public $requiredfields = [];
    public $labels = [];
    public $map_height = 400;
    public $geocoder = 'nominatim';
    public $startpoint = ['lat' => 25.43328030, 'lng' => -100.96047970];

    public function __construct($options = [])
    {
        $options['class'] = 'inputaddress';
        parent::__construct($options);

        if (empty($this->fields)) {
            $this->fields = array_keys(self::FIELDS);
        }
        $this->fields = array_values(array_intersect($this->fields, array_keys(self::FIELDS)));
        if ($this->frozen === true) {
            $this->frozen = array_merge(array_keys(self::FIELDS), ['location']);
        }
        $this->frozen = (array) $this->frozen;
        $this->requiredfields = (array) $this->requiredfields;
    }

    private function isFrozen(string $field): bool
    {
        return in_array($field, $this->frozen, true)
            || (in_array($field, self::COORDS, true) && in_array('location', $this->frozen, true));
    }

    private function storedValue(): array
    {
        $value = $this->value;
        if ($value instanceof Traversable) {
            $value = iterator_to_array($value);
        }
        return is_array($value) ? $value : [];
    }

    /**
     * Valor efectivo por campo: almacenado, o el predeterminado si está vacío.
     */
    private function currentValues(): array
    {
        $stored = $this->storedValue();
        $values = [];
        foreach (array_merge(array_keys(self::FIELDS), self::COORDS) as $field) {
            $values[$field] = (isset($stored[$field]) && $stored[$field] !== '') ? $stored[$field] : ($this->defaults[$field] ?? '');
        }
        if ($values['lat'] === '' || $values['lng'] === '') {
            $values['lat'] = $this->startpoint['lat'];
            $values['lng'] = $this->startpoint['lng'];
        }
        return $values;
    }

    public function __toMongo($val)
    {
        $val = is_array($val) ? $val : [];
        $stored = $this->storedValue();
        $result = [];
        foreach (array_merge(array_keys(self::FIELDS), self::COORDS) as $field) {
            if ($this->isFrozen($field)) {
                // Lo congelado no se toma del navegador.
                $result[$field] = (isset($stored[$field]) && $stored[$field] !== '') ? $stored[$field] : ($this->defaults[$field] ?? '');
            } elseif (in_array($field, self::COORDS, true)) {
                $result[$field] = is_numeric($val[$field] ?? null) ? (float) $val[$field] : ($stored[$field] ?? null);
            } else {
                $result[$field] = is_scalar($val[$field] ?? null) ? trim((string) $val[$field]) : ($stored[$field] ?? '');
            }
        }
        return $result;
    }

    public function __toPHP($val)
    {
        return $val;
    }

    public function is_valid($newval)
    {
        $newval = is_array($newval) ? $newval : [];
        $required = $this->requiredfields;
        if ($this->required && empty($required)) {
            $required = $this->fields;
        }
        foreach ($required as $field) {
            if (!$this->isFrozen($field) && trim((string) ($newval[$field] ?? '')) === '') {
                return false;
            }
        }
        foreach (self::COORDS as $coord) {
            if (isset($newval[$coord]) && $newval[$coord] !== '' && !is_numeric($newval[$coord])) {
                return false;
            }
        }
        return true;
    }

    private function fieldId(string $field): string
    {
        return $this->id . '_' . $field;
    }

    private function fieldName(string $field): string
    {
        return $this->name . '[' . $field . ']';
    }

    private function renderField(string $field, $value): string
    {
        $input = new inputText([
            'name' => $this->fieldName($field),
            'id' => $this->fieldId($field),
            'value' => (string) $value,
            'caption' => $this->labels[$field] ?? self::FIELDS[$field],
            'readonly' => $this->isFrozen($field) || $this->readonly,
            'required' => in_array($field, $this->requiredfields, true) && !$this->isFrozen($field),
            'disabled' => $this->disabled,
        ]);
        return $input->__toString();
    }

    private function renderHidden(string $field, $value): string
    {
        return '<input type="hidden" id="' . $this->fieldId($field) . '" name="' . htmlspecialchars($this->fieldName($field), ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '">';
    }

    private function addMapJs(array $values): void
    {
        global $nframework, $javas, $config;

        $nframework->csss['005leaflet'] = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
        $nframework->jss['100leaflet'] = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';

        $useGoogle = $this->geocoder === 'google' && !empty($config['google-maps-api']);
        if ($useGoogle) {
            $nframework->jss['101gmaps'] = 'https://maps.googleapis.com/maps/api/js?key=' . urlencode($config['google-maps-api']);
        }

        $opts = json_encode([
            'id' => $this->id,
            'lat' => (float) $values['lat'],
            'lng' => (float) $values['lng'],
            'draggable' => !$this->isFrozen('lat') && !$this->readonly && !$this->disabled,
            'fields' => $this->fields,
            'frozen' => array_values(array_filter(array_keys(self::FIELDS), fn($f) => $this->isFrozen($f))),
            'geocoder' => $useGoogle ? 'google' : 'nominatim',
            'onchange' => $this->onChange,
        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

        if (empty($nframework->onces['inputaddress'])) {
            $nframework->onces['inputaddress'] = true;
            $javas->addjs(<<<'JS'
var nfAddress = {
    maps: {},
    // Campos de la respuesta del geocodificador inverso -> campos del componente
    nominatimMap: {country: ['country'], zipcode: ['postcode'], state: ['state'], municipality: ['county', 'municipality', 'city'], city: ['city', 'town', 'village'], neighborhood: ['suburb', 'neighbourhood', 'quarter'], street: ['road'], external_number: ['house_number']},
    googleMap: {country: ['country'], zipcode: ['postal_code'], state: ['administrative_area_level_1'], municipality: ['administrative_area_level_2', 'locality'], city: ['locality'], neighborhood: ['sublocality', 'neighborhood'], street: ['route'], external_number: ['street_number']},
    el: function (o, f) { return document.getElementById(o.id + '_' + f); },
    setPosition: function (o, lat, lng, pan) {
        var m = nfAddress.maps[o.id];
        m.marker.setLatLng([lat, lng]);
        if (pan) { m.map.setView([lat, lng], 17); }
        nfAddress.el(o, 'lat').value = lat;
        nfAddress.el(o, 'lng').value = lng;
        if (o.onchange) { try { (new Function(o.onchange))(); } catch (e) { console.error(e); } }
    },
    fill: function (o, values) {
        o.fields.forEach(function (f) {
            var input = nfAddress.el(o, f);
            if (input && values[f] !== undefined && o.frozen.indexOf(f) === -1) { input.value = values[f]; }
        });
    },
    reverse: function (o, lat, lng) {
        if (o.geocoder === 'google') {
            new google.maps.Geocoder().geocode({location: {lat: lat, lng: lng}}).then(function (r) {
                if (!r.results[0]) { return; }
                var values = {};
                Object.keys(nfAddress.googleMap).forEach(function (f) {
                    r.results[0].address_components.some(function (c) {
                        if (nfAddress.googleMap[f].some(function (t) { return c.types.indexOf(t) !== -1; })) { values[f] = c.long_name; return true; }
                    });
                });
                nfAddress.fill(o, values);
            });
            return;
        }
        fetch('https://nominatim.openstreetmap.org/reverse?format=jsonv2&addressdetails=1&lat=' + lat + '&lon=' + lng)
            .then(function (r) { return r.json(); })
            .then(function (d) {
                var a = d.address || {}, values = {};
                Object.keys(nfAddress.nominatimMap).forEach(function (f) {
                    nfAddress.nominatimMap[f].some(function (k) { if (a[k]) { values[f] = a[k]; return true; } });
                });
                nfAddress.fill(o, values);
            }).catch(function (e) { console.error(e); });
    },
    search: function (o) {
        var parts = ['street', 'external_number', 'neighborhood', 'city', 'municipality', 'state', 'zipcode', 'country']
            .map(function (f) { var i = nfAddress.el(o, f); return i ? i.value.trim() : ''; })
            .filter(function (v) { return v !== ''; });
        if (!parts.length) { return; }
        var q = parts.join(', ');
        if (o.geocoder === 'google') {
            new google.maps.Geocoder().geocode({address: q}).then(function (r) {
                if (r.results[0]) { var p = r.results[0].geometry.location; nfAddress.setPosition(o, p.lat(), p.lng(), true); }
            });
            return;
        }
        fetch('https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&q=' + encodeURIComponent(q))
            .then(function (r) { return r.json(); })
            .then(function (d) { if (d[0]) { nfAddress.setPosition(o, parseFloat(d[0].lat), parseFloat(d[0].lon), true); } })
            .catch(function (e) { console.error(e); });
    },
    init: function (o) {
        var map = L.map(o.id + '_map').setView([o.lat, o.lng], 16);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 19, attribution: '&copy; OpenStreetMap'}).addTo(map);
        var marker = L.marker([o.lat, o.lng], {draggable: o.draggable}).addTo(map);
        nfAddress.maps[o.id] = {map: map, marker: marker};
        if (o.draggable) {
            marker.on('dragend', function () {
                var p = marker.getLatLng();
                nfAddress.setPosition(o, p.lat, p.lng, false);
                nfAddress.reverse(o, p.lat, p.lng);
            });
            map.on('click', function (e) {
                nfAddress.setPosition(o, e.latlng.lat, e.latlng.lng, false);
                nfAddress.reverse(o, e.latlng.lat, e.latlng.lng);
            });
        }
        var btn = document.getElementById(o.id + '_search');
        if (btn) { btn.addEventListener('click', function (e) { e.preventDefault(); nfAddress.search(o); }); }
        // Leaflet calcula mal el tamaño si el mapa estaba oculto (diálogos, pestañas).
        setTimeout(function () { map.invalidateSize(); }, 300);
    }
};
JS, 'general');
        }
        $javas->addjs('nfAddress.init(' . $opts . ');', 'ready');
    }

    public function __toString()
    {
        $values = $this->currentValues();
        $id = htmlspecialchars($this->id, ENT_QUOTES, 'UTF-8');

        $fieldsHtml = '';
        if ($this->showfields) {
            $rows = '';
            foreach ($this->fields as $field) {
                $size = in_array($field, ['external_number', 'internal_number'], true) ? 'cell-md-3' : 'cell-md-6';
                $rows .= '<div class="' . $size . '">' . $this->renderField($field, $values[$field]) . '</div>';
            }
            $fieldsHtml = '<div class="row">' . $rows . '</div>';
        }
        // Los campos no mostrados viajan ocultos para conservar su valor al guardar.
        $hidden = '';
        foreach (array_keys(self::FIELDS) as $field) {
            if (!$this->showfields || !in_array($field, $this->fields, true)) {
                $hidden .= $this->renderHidden($field, $values[$field]);
            }
        }
        foreach (self::COORDS as $coord) {
            $hidden .= $this->renderHidden($coord, $values[$coord]);
        }

        $mapHtml = '';
        if ($this->showmap) {
            $this->addMapJs($values);
            $searchButton = ($this->showfields && !$this->isFrozen('lat') && !$this->readonly && !$this->disabled)
                ? '<button type="button" class="button small mt-1" id="' . $id . '_search"><span class="mif-search"></span> Buscar dirección en el mapa</button>'
                : '';
            $mapHtml = '<div id="' . $id . '_map" style="height:' . (int) $this->map_height . 'px;"></div>' . $searchButton;
        }

        $caption = $this->caption !== '' ? '<label class="label-for-input">' . htmlspecialchars($this->caption, ENT_QUOTES, 'UTF-8') . '</label>' : '';
        if ($this->showfields && $this->showmap) {
            $body = '<div class="row"><div class="cell-md-6">' . $fieldsHtml . '</div><div class="cell-md-6">' . $mapHtml . '</div></div>';
        } else {
            $body = $fieldsHtml . $mapHtml;
        }

        return '<div class="grid nf-address" id="' . $id . '">' . $caption . $body . $hidden . '</div>';
    }
}
