<?php

/**
 * Atributo HTML con valor 'true'/'false': booltotag('data-x', 1) → ' data-x="true"'.
 *
 * @param string $tag
 * @param mixed $val
 * @return string
 */
function booltotag($tag, $val)
{
    return ' ' . $tag . '="' . ($val ? 'true' : 'false') . '"';
}
/**
 * Atributo HTML si $val no está vacío (sin escapar: pase valores de confianza).
 *
 * @param string $tag
 * @param mixed $val
 * @return string
 */
function strtotag($tag, $val)
{
    return !empty($val) ? ' ' . $tag . '="' . $val . '"' : '';
}
/**
 * Atributo HTML con las comillas dobles de $val cambiadas por simples (para HTML de iconos).
 *
 * @param string $tag
 * @param string $val
 * @return string
 */
function icontotag($tag, $val)
{
    return $tag != '' ? ' ' . $tag . '="' . str_replace('"', '\'', $val) . '"' : '';
}

/**
 * Siguiente valor de un contador (colección counters, documento {_id: $campo, seq}): folios consecutivos
 * atómicos.
 *
 * @global MongoDB\Client $m
 * @global class_config $config
 * @param string $campo Nombre del contador.
 * @return int
 */
function mongo_auto_increment($campo)
{
    global $m, $config;
    $result = $m->{$config['sitedb']}->counters->findOneAndUpdate(
        ['_id' => $campo],
        ['$inc' => ['seq' => 1]],
        [
            'upsert' => true,
            'projection' => ['seq' => 1],
            'returnDocument' => MongoDB\Operation\FindOneAndUpdate::RETURN_DOCUMENT_AFTER,
        ]
    );
    return $result->seq;
}

/**
 * Convierte el texto de un campo numérico al tipo que se guarda en Mongo.
 * $rules son las reglas de validación ("integer", "digits", "float"...): con integer/digits se guarda int, si no float.
 * Acepta separador de miles ("1,234.5"), igual que la validación con FILTER_FLAG_ALLOW_THOUSAND.
 *
 * @param mixed $val
 * @param string ...$rules
 * @return int|float|null null si está vacío o no es número.
 */
function nfnumbertomongo($val, ...$rules)
{
    if ($val === null || $val === '') {
        return null;
    }
    $number = filter_var((string) $val, FILTER_VALIDATE_FLOAT, FILTER_FLAG_ALLOW_THOUSAND);
    if ($number === false) {
        return null;
    }
    $rules = preg_split('/\s+/', trim(implode(' ', array_map('strval', $rules))));

    return array_intersect(['integer', 'digits'], $rules) ? (int) $number : $number;
}

/**
 * Base mínima: asigna cada opción del constructor como propiedad.
 */
#[\AllowDynamicProperties]
class Base
{
    /** @var array<string, mixed> */
    public $tags;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct($options = [])
    {
        $this->tags = [];
        foreach ($options as $option => $value) {
            $this->{$option} = $value;
        }
    }
}
/**
 * Base de los controles de formulario. El constructor recibe un arreglo de opciones: las que coinciden con una
 * propiedad se asignan y las demás se vuelven atributos HTML ($tags). Con dataset + field el control toma su
 * valor del documento (notación con punto para subcampos), se registra en el dataset y su name queda como
 * <nameprefix>[<field>]. Se imprime con echo (__toString()). Las subclases redefinen __toMongo() (tipo que se
 * guarda), __toPHP() (valor para mostrar) e is_valid() (validación en el servidor).
 *
 *   $nombre = new inputText(['dataset' => &$dataset, 'field' => 'nombre', 'caption' => 'Nombre', 'required' =>
 * true]);
 *   echo $nombre;
 */
#[\AllowDynamicProperties]
class baseInput
{
    /** @var bool */
    public $required = false;
    /** @var string Clases CSS del input. */
    public $addclass = '';
    /** @var string Nombre del tipo de control (lo fija cada subclase). */
    public $class = '';
    /** @var string|null type del <input> o variante del control. */
    public $type = '';
    /** @var string|null data-role de Metro UI. */
    public $role = '';
    /** @var string */
    public $infobox = '';
    /** @var string Por omisión se deriva de name (o field). */
    public $id = '';
    /** @var string Por omisión field, con el nameprefix del dataset: data[campo]. */
    public $name = '';
    /** @var string */
    public $nameprefix = '';
    /** @var dataset|datasetpdo|null Documento al que se liga el control (pásela por referencia: &$dataset). */
    public $dataset;
    /** @var embededArray|null Lista embebida a la que pertenece el control. */
    public $nfembeded;
    /** @var string Campo del documento; notación con punto para subcampos (direccion.calle). */
    public $field = '';
    /** @var bool Deshabilitado: no se valida ni se acepta desde el navegador. */
    public $disabled = false;
    /** @var string */
    public $placeholder = '';
    /** @var string Etiqueta (HTML). */
    public $caption = '';
    /** @var string */
    public $prependicon = '';
    /** @var bool */
    public $readonly = false;
    /** @var mixed Valor si el documento no tiene el campo. */
    public $default;
    /** @var string Reglas de validación de Metro separadas por espacios (email, integer, minlength=3...); también se revisan en el servidor. */
    public $validate = '';
    /** @var string Texto o HTML antes del input (data-prepend). */
    public $prepend = '';
    /** @var string Texto o HTML después del input (data-append). */
    public $append = '';
    /** @var string */
    public $prepend_options = '';
    /** @var string */
    public $append_options = '';
    /** @var string */
    public $autocomplete = '';
    /** @var string */
    public $title = '';
    /** @var string Expresión regular (sin delimitadores) que debe cumplir el valor. */
    public $pattern = '';
    /** @var string JS al cambiar el valor. */
    public $onChange = '';
    /** @var array<string, mixed> Atributos HTML adicionales (opciones que no son propiedades). */
    public $tags = [];
    /** @var mixed */
    public $value = null;
    /** @var string */
    public $datasize = '';
    /** @var bool Se muestra editable pero el servidor ignora lo que llegue. */
    public $backreadonly = false;
    /** @var string Mensaje cuando la validación falla. */
    public $invalid_feedback = '';

    /**
     * Valor que se guarda en MongoDB a partir de lo que envió el navegador. La base lo devuelve igual.
     *
     * @param mixed $val
     * @return mixed
     */
    public function __toMongo($val)
    {
        return $val;
    }

    /**
     * Asigna una opción después de construir: propiedad si existe, atributo HTML si no.
     *
     * @param string $option
     * @param mixed $value
     * @return void
     */
    public function __lset($option, $value)
    {
        $ovars = array_keys(get_object_vars($this));
        if ($option === 'value') {
            $this->value = $value;
        } elseif (in_array($option, $ovars, true)) {
            $this->{$option} = $value;
        } else {
            $this->tags[$option] = $value;
        }
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        if ($name === 'value') {
            if (isset($this->dataset) && isset($this->field)) {
                return $this->dataset->{$this->field};
            }

            return $this->value;
        }

        if (property_exists($this, $name)) {
            return $this->{$name};
        }

        return null;
    }

    /**
     * @param string $option
     * @return bool
     */
    public function __isset($option)
    {
        $ovars = array_keys(get_object_vars($this));
        if (in_array($option, $ovars, true)) {
            return isset($this->{$option});
        }

        return isset($this->tags[$option]);
    }

    /**
     * @param array<string, mixed> $options Propiedades del control; las demás se imprimen como atributos HTML.
     */
    public function __construct($options = [])
    {
        $this->tags = [];
        $ovars = array_keys(get_object_vars($this));
        if (!isset($options['class'])) {
            $options['class'] = 'inputText';
        }
        foreach ($options as $option => $value) {
            if ($option === 'value') {
                $this->value = $value;
            } elseif ($option === 'dataset') {
                if (is_object($value) && method_exists($value, 'addElement')) {
                    $value->addElement($this);
                }
                $this->dataset = $value;
            } elseif ($option === 'nfembeded') {
                if (is_object($value) && method_exists($value, 'addElement')) {
                    $value->addElement($this);
                }
                $this->nfembeded = $value;
            } elseif (in_array($option, $ovars, true)) {
                $this->{$option} = $value;
            } else {
                $this->tags[$option] = $value;
            }
        }

        if (empty($this->value) && !empty($this->default)) {
            $this->value = $this->default;
        }

        if (!empty($this->nfembeded)) {
            if ($this->name === '' && $this->field !== '') {
                $this->name = $this->field;
            }
            if (!empty($this->nfembeded->nameprefix)) {
                $this->name = $this->nfembeded->nameprefix . '[' . $this->name . ']';
            }
        }

        if (!empty($this->dataset)) {
            if ($this->name === '' && $this->field !== '') {
                $this->name = $this->field;
            }
            if (!empty($this->dataset->nameprefix)) {
                $this->name = $this->dataset->nameprefix . '[' . $this->name . ']';
            }

            if (!empty($this->dataset->allUppercase) && property_exists($this, 'uppercase')) {
                $this->uppercase = true;
            }
            if (!empty($this->dataset->allLowercase) && property_exists($this, 'lowercase')) {
                $this->lowercase = true;
            }
            if (strpos((string) $this->field, '.') !== false) {
                $data = $this->dataset->info ?? [];
                $keys = explode('.', str_replace(['$', '[', ']'], [$this->dataset->position ?? '', '.', ''], $this->field));
                $current = $data;

                foreach ($keys as $key) {
                    if ((is_array($current) || $current instanceof \MongoDB\Model\BSONArray) && isset($current[$key])) {
                        $current = $current[$key];
                    } elseif ((is_array($current) || $current instanceof \MongoDB\Model\BSONArray) && is_numeric($key) && isset($current[(int) $key])) {
                        $current = $current[(int) $key];
                    } elseif (is_object($current) && property_exists($current, $key)) {
                        $current = $current->$key;
                    } elseif ($current instanceof \MongoDB\Model\BSONDocument) {
                        $current = $current->$key;
                    } else {
                        $current = $this->default;
                    }
                }
                $this->value = $current;
            } elseif (isset($this->dataset->{$this->field})) {
                $this->value = $this->dataset->{$this->field};
            } elseif (isset($this->default)) {
                $this->value = $this->default;
            }
        } elseif (!isset($this->value) && isset($this->default)) {
            $this->value = $this->default;
        }

        if ($this->id === '') {
            $this->id = str_replace(['[', ']', '.'], ['_', '', '_'], (string) $this->name);
        }
        if ($this->id === '') {
            $this->id = str_replace(['[', ']', '.'], ['_', '', '_'], (string) $this->field);
        }
    }

    /**
     * Validación en el servidor: required, pattern y las reglas email, number/numeric, integer/digits y
     * float/double de validate. Un arreglo (controles compuestos) solo se revisa contra required.
     *
     * @param mixed $newval Valor recibido del navegador.
     * @return bool
     */
    public function is_valid($newval)
    {
        if ($newval === null || (is_string($newval) && trim($newval) === '')) {
            return !$this->required;
        }

        if (is_array($newval)) {
            // Controles compuestos (mapmarker, etc.) envían arreglos.
            return !$this->required || !empty($newval);
        }
        if (!is_scalar($newval) && !(is_object($newval) && method_exists($newval, '__toString'))) {
            return false;
        }

        $valueText = (string) $newval;
        $rules = !empty($this->validate) ? preg_split('/\s+/', trim((string) $this->validate)) : [];
        $rules = array_values(array_filter($rules ?? [], static fn($rule) => $rule !== ''));

        $patternValue = (string) ($this->pattern ?? '');
        if ($patternValue !== '') {
            $pattern = '/' . str_replace('/', '\/', $patternValue) . '/';
            if (preg_match($pattern, $valueText) !== 1) {
                return false;
            }
        }

        foreach ($rules as $rule) {
            if ($rule === 'required') {
                continue;
            }
            if ($rule === 'email' && !filter_var($valueText, FILTER_VALIDATE_EMAIL)) {
                return false;
            }
            if (($rule === 'number' || $rule === 'numeric') && !is_numeric($valueText)) {
                return false;
            }
            if (($rule === 'integer' || $rule === 'digits') && !preg_match('/^-?\d+$/', $valueText)) {
                return false;
            }
            if (($rule === 'float' || $rule === 'double') && !filter_var($valueText, FILTER_VALIDATE_FLOAT)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return string Atributos de $tags, escapados.
     */
    protected function writetags(): string
    {
        $result = '';
        foreach ($this->tags as $name => $value) {
            $result .= ' ' . $name . '="' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '"';
        }

        return $result;
    }

    /**
     * Atributo data-validate con validate, required y pattern.
     *
     * @return string
     */
    public function data_validate()
    {
        $rules = !empty($this->validate) ? preg_split('/\s+/', trim((string) $this->validate)) : [];
        $rules = array_values(array_filter($rules, static fn($rule) => $rule !== ''));

        if (!empty($this->required) && !in_array('required', $rules, true)) {
            $rules[] = 'required';
        }

        if (!empty($this->pattern)) {
            $hasPatternRule = false;
            foreach ($rules as $rule) {
                if (stripos((string) $rule, 'pattern') === 0) {
                    $hasPatternRule = true;
                    break;
                }
            }
            if (!$hasPatternRule) {
                $rules[] = 'pattern=(' . $this->pattern . ')';
            }
        }

        $rulesstr = trim(implode(' ', $rules));

        return $rulesstr !== '' ? ' data-validate="' . $rulesstr . '"' : '';
    }

    /**
     * Atributos comunes del input (data-label, class, required, readonly, disabled, prepend/append, placeholder...)
     * más data-validate, escapados.
     *
     * @return string
     */
    public function inputtags()
    {
        $attributes = [];

        if ($this->caption !== '') {
            $attributes[] = 'data-label="' . htmlspecialchars((string) $this->caption, ENT_QUOTES, 'UTF-8') . '"';
        }
        if ($this->addclass !== '') {
            $attributes[] = 'class="' . htmlspecialchars((string) $this->addclass, ENT_QUOTES, 'UTF-8') . '"';
        }
        if ($this->required) {
            $attributes[] = 'required="required"';
        }
        if (!empty($this->pattern)) {
            $attributes[] = 'data-mask-pattern="' . htmlspecialchars((string) $this->pattern, ENT_QUOTES, 'UTF-8') . '"';
        }
        if ($this->readonly) {
            $attributes[] = 'readonly="readonly"';
        }
        if ($this->disabled) {
            $attributes[] = 'disabled';
        }
        if ($this->prepend !== '') {
            $attributes[] = 'data-prepend="' . htmlspecialchars((string) $this->prepend, ENT_QUOTES, 'UTF-8') . '"';
        }
        if ($this->append !== '') {
            $attributes[] = 'data-append="' . htmlspecialchars((string) $this->append, ENT_QUOTES, 'UTF-8') . '"';
        }
        if ($this->prepend_options !== '') {
            $attributes[] = 'data-prepend-options="' . htmlspecialchars((string) $this->prepend_options, ENT_QUOTES, 'UTF-8') . '"';
        }
        if ($this->append_options !== '') {
            $attributes[] = 'data-append-options="' . htmlspecialchars((string) $this->append_options, ENT_QUOTES, 'UTF-8') . '"';
        }
        if ($this->autocomplete !== '') {
            $attributes[] = 'autocomplete="' . htmlspecialchars((string) $this->autocomplete, ENT_QUOTES, 'UTF-8') . '"';
        }
        if ($this->datasize !== '') {
            $attributes[] = 'data-size="' . htmlspecialchars((string) $this->datasize, ENT_QUOTES, 'UTF-8') . '"';
        }
        if ($this->placeholder !== '') {
            $attributes[] = 'placeholder="' . htmlspecialchars((string) $this->placeholder, ENT_QUOTES, 'UTF-8') . '"';
        }

        return ' ' . implode(' ', $attributes) . $this->data_validate();
    }

    public function getLabelHtml(): string
    {
        return $this->caption ? '<label class="label-for-input" for="' . $this->id . '">' . $this->caption . '</label>' : '';
    }

    public function getDisabledAttr(): string
    {
        return $this->disabled ? ' disabled' : '';
    }

    public function getPrependAttr(): string
    {
        return $this->prepend ? ' data-prepend="' . $this->prepend . '"' : '';
    }
    public function getAppendAttr(): string
    {
        return $this->append ? ' data-append="' . $this->append . '"' : '';
    }
    public function getReadonlyAttr(): string
    {
        return $this->readonly ? ' readonly="readonly"' : '';
    }
    public function getRequiredAttr(): string
    {
        return $this->required ? ' required="required"' : '';
    }
    public function getPatternAttr(): string
    {
        return !empty($this->pattern) ? ' data-mask-pattern="' . $this->pattern . '"' : '';
    }
    public function getAutocompleteAttr(): string
    {
        return $this->autocomplete ? ' autocomplete="' . $this->autocomplete . '"' : '';
    }
    public function getDataValidateAttr(): string
    {
        return $this->data_validate();
    }
    public function getClassAttr(): string
    {
        return $this->addclass ? ' class="' . $this->addclass . '"' : '';
    }
    /**
     * Igual que (string) $control.
     */
    public function render(): string
    {
        return $this->__toString();
    }
    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }
}
/**
 * Base de los controles con lista de opciones ['valor' => 'Texto'].
 */
class baseOptions extends baseInput
{
    /** @var array<int|string, string|array<int|string, string>> valor => texto; un arreglo como texto forma un <optgroup>. */
    public $options = [];
}
/**
 * Valor de solo lectura con el aspecto de un campo (no se envía).
 */
class label extends baseInput
{
    /**
     * @return string HTML.
     */
    public function __toString()
    {
        if ($this->value === null) {
            if (isset($this->default)) {
                $this->value = $this->default;
            } else {
                $this->value = '';
            }
        }

        return ($this->caption != '' ? '<label for="' . $this->id . '">' . $this->caption . '</label>' : '') .
            '<label class="input" ' . $this->writetags() . ' id="' . $this->id . '"' . '>' . htmlspecialchars($this->value) . '</label>';
    }
}
/**
 * Campo oculto.
 */
class inputHidden extends baseInput
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct($options = [])
    {
        parent::__construct($options);
    }

    /**
     * @return string HTML.
     */
    public function __toString()
    {
        return '<input type="hidden"' . ' id="' . $this->id . '" name="' . $this->name . '"' . $this->writetags() .
            ' value="' . htmlspecialchars($this->value) . '">';
    }
}

/**
 * Texto de una línea. type 'email' agrega la validación email. 'ajax' lo vuelve un buscador Tom Select sobre
 * una colección (ver nfTomSelectAjax()); 'suggest' muestra sugerencias manteniendo texto libre (ver
 * docs/autocomplete.php).
 *
 *   new inputText(['dataset' => &$dataset, 'field' => 'rfc', 'caption' => 'RFC', 'pattern' =>
 * '^[A-Z&Ñ]{3,4}\\d{6}[A-Z0-9]{3}$', 'uppercase' => true]);
 */
class inputText extends baseInput
{
    /** @var mixed */
    public $value;
    /** @var bool|null */
    public $search;
    /** @var string|null Máscara de Metro (input-mask). */
    public $mask;
    /** @var string|null */
    public $mask_pattern;
    /** @var string */
    public $pattern;
    /** @var string|null */
    public $inputtype;
    /** @var string */
    public $addclass = 'form-control';
    /** @var string type del <input>: text (omisión), email, password, tel... */
    public $type;
    /** @var bool|null Convierte a mayúsculas al escribir. */
    public $uppercase;
    /** @var bool|null */
    public $lowercase;
    /** @var bool|null */
    public $autotrim;
    /** @var string */
    public $autocomplete = 'off';
    /** @var array<string, mixed>|SelectAjaxOptions|null Búsqueda en servidor con Tom Select. */
    public $ajax;
    /** @var list<string>|array<string, mixed>|null Sugerencias: lista fija, ['url' => ...] o consulta a una colección. */
    public $suggest;

    /**
     * @param array<string, mixed> $options Además de las de baseInput: type, mask, uppercase, lowercase, autotrim, ajax, suggest.
     */
    public function __construct($options = [])
    {
        $options['class'] = 'inputText';
        $options['type'] ??= 'text';

        parent::__construct($options);

        // Set validation based on type
        if ($this->type === 'email') {
            $this->validate .= ' email';
        } elseif (empty($this->pattern)) {
            // Se agrega a las reglas del usuario ('validate' => 'email minlength=5'); antes las reemplazaba.
            $this->validate .= ' text';
        } else {
            $this->validate .= ' pattern=(' . $this->pattern . ')';
        }
    }

    public function __toString(): string
    {
        if (!empty($this->ajax)) {
            nfTomSelectAjax($this);
        } elseif (!empty($this->suggest)) {
            $this->renderSuggest();
        }

        return <<<HTML
            <div class="form-group">
                <input name="{$this->name}" 
                       id="{$this->id}" 
                       data-role="input{$this->getMaskRole()}" 
                       value="{$this->getEscapedValue()}" 
                       type="{$this->type}" 
                       {$this->inputtags()}
                       {$this->getInputAttributes()}
                       {$this->writetags()}>
            </div>
        HTML;
    }

    /**
     * @param mixed $newval
     */
    public function is_valid($newval): bool
    {
        return parent::is_valid($newval);
    }

    /**
     * Sugerencias mientras se escribe. A diferencia de 'ajax' (Tom Select, valor de un catálogo), el campo
     * sigue siendo texto libre: la lista solo ayuda a escribir. 'suggest' acepta:
     *   ['Manzana', 'Mango', ...]                lista fija, se filtra en el navegador
     *   '/api/x' o ['url' => '/api/x']            GET ?q=texto que responde JSON: ["a", ...], [{label, value}, ...]
     *                                            o {items: [...]}; con 'key' se toma otra propiedad
     *   ['collection' => 'clientes', 'columns' => ['nombre'], 'value' => '$nombre', ...]   búsqueda en MongoDB
     *                                            (db, label, pipeline, fields, distinct) vía select_ajax.php
     * Comunes: minlength (1), delay (250 ms), limit (20), depends (['param' => '#selector']: valor de otro
     * campo; en MongoDB es filtro exacto y se omite si está vacío), clearondepends (true) y onselect
     * (cuerpo de una función JS que recibe item e input).
     *
     * @global Javas $javas
     * @global class_config $config
     * @return void
     */
    private function renderSuggest(): void
    {
        global $javas, $config;
        $suggest = is_string($this->suggest) ? ['url' => $this->suggest] : (array) $this->suggest;
        if (array_is_list($suggest)) {
            $suggest = ['items' => $suggest];
        }

        $cfg = [];
        foreach (['minlength', 'delay', 'limit', 'depends', 'param', 'key', 'clearondepends'] as $option) {
            if (isset($suggest[$option])) {
                $cfg[$option] = $suggest[$option];
            }
        }
        if (isset($suggest['items'])) {
            $cfg['items'] = array_values((array) $suggest['items']);
        } elseif (!empty($suggest['url'])) {
            $cfg['url'] = (string) $suggest['url'];
        } elseif (!empty($suggest['collection'])) {
            $value = $suggest['value'] ?? '$' . ((array) ($suggest['columns'] ?? []))[0];
            $columns = (array) ($suggest['columns'] ?? (is_string($value) ? [ltrim($value, '$')] : []));
            $_SESSION['selectajax'][$this->id] = [
                'suggest' => true,
                'db' => $suggest['db'] ?? $config['sitedb'] ?? '',
                'collection' => $suggest['collection'],
                'columns' => $columns,
                'value' => $value,
                'label' => $suggest['label'] ?? $value,
                'pipeline' => $suggest['pipeline'] ?? [],
                'fields' => $suggest['fields'] ?? [],
                'distinct' => !empty($suggest['distinct']),
                'limit' => (int) ($suggest['limit'] ?? 20),
                'args' => array_keys((array) ($suggest['depends'] ?? [])),
            ];
            addVarToGarbage('selectajax\\' . $this->id, time() + (60 * 60));
            $cfg['url'] = '/nframework/select_ajax.php?id=' . urlencode($this->id);
        }

        // Sin autocompletado del navegador ni de gestores de contraseñas: se encimaría con la lista.
        $this->tags['data-nf-noautofill'] = 'true';
        nfSuggestScript();
        $flags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        $onselect = !empty($suggest['onselect']) ? 'function (item, input) {' . $suggest['onselect'] . '}' : 'null';
        $javas->addjs(
            'nfSuggest(document.getElementById(' . json_encode($this->id, $flags) . '), '
            . json_encode((object) $cfg, $flags) . ', ' . $onselect . ");\n",
            'ready'
        );
    }

    private function getMaskRole(): string
    {
        return ($this->mask || $this->mask_pattern) ? ',input-mask' : '';
    }

    private function getEscapedValue(): string
    {
        return htmlspecialchars($this->value ?? '', ENT_QUOTES, 'UTF-8');
    }

    private function getInputAttributes(): string
    {
        $attrs = [];

        if ($this->uppercase)
            $attrs[] = 'uppercase="true"';
        if ($this->lowercase)
            $attrs[] = 'lowercase="true"';
        if ($this->autotrim)
            $attrs[] = 'autotrim="true"';
        if ($this->mask)
            $attrs[] = 'data-mask="' . htmlspecialchars($this->mask) . '"';
        if ($this->mask_pattern)
            $attrs[] = 'data-mask-pattern="' . htmlspecialchars($this->mask_pattern) . '"';
        if ($this->pattern)
            $attrs[] = 'pattern="' . htmlspecialchars($this->pattern) . '"';

        return implode(' ', $attrs);
    }
}

/**
 * Número. Se guarda como int si data_validate o validate incluyen integer/digits, si no como float (ver
 * nfnumbertomongo()); acepta separador de miles.
 */
class inputNumber extends baseInput
{
    /** @var int|float|null */
    public $max;
    /** @var int|float|null */
    public $min;
    /** @var int|float|string|null */
    public $step;
    /** @var string|null 'integer', 'digits' o 'float'. */
    public $data_validate; // integer,digits,float

    /**
     * @param array<string, mixed> $options Además de las de baseInput: min, max, step, data_validate.
     */
    public function __construct($options = [])
    {
        $options['class'] = 'inputNumber';
        if (!isset($options['type'])) {
            $options['type'] = 'number';
        }
        parent::__construct($options);
    }

    /**
     * @param mixed $val
     * @return int|float|null
     */
    public function __toMongo($val)
    {
        return nfnumbertomongo($val, $this->data_validate, $this->validate);
    }

    /**
     * @global class_config $config
     * @return string HTML.
     */
    public function __toString()
    {
        global $config;
        if ($this->validate == '') {
            $this->validate = 'number'; // integer,float
        }

        return '<div class="form-group">
        <input type="number" data-role="input" id="' . $this->id . '" name="' . $this->name . '" value="' . $this->value . '"' .
            $this->inputtags() .
            ($this->max ? ' max="' . $this->max . '"' : '') .
            ($this->min ? ' min="' . $this->min . '"' : '') .
            ($this->step ? ' step="' . $this->step . '"' : '') .
            '></div>';
    }

    /**
     * @param mixed $newval
     * @return bool
     */
    public function is_valid($newval)
    {
        if ($newval === null || (is_string($newval) && trim($newval) === '')) {
            return !$this->required;
        }

        $rules = !empty($this->validate) ? preg_split('/\s+/', trim((string) $this->validate)) : [];
        $rules = array_values(array_filter($rules, static fn($rule) => $rule !== ''));

        foreach ($rules as $rule) {
            if ($rule === 'float' || $rule === 'number') {
                return filter_var((string) $newval, FILTER_VALIDATE_FLOAT, FILTER_FLAG_ALLOW_THOUSAND) !== false;
            }
            if ($rule === 'integer' || $rule === 'digits') {
                return filter_var((string) $newval, FILTER_VALIDATE_INT) !== false;
            }
        }

        return filter_var((string) $newval, FILTER_VALIDATE_FLOAT, FILTER_FLAG_ALLOW_THOUSAND) !== false || filter_var((string) $newval, FILTER_VALIDATE_INT) !== false;
    }
}

/**
 * Número con botones + y −; el tipo guardado sale de validate (integer → int, si no float).
 */
class inputSpinner extends baseInput
{
    /** @var string|null */
    public $addclass;
    /**
     * @param mixed $val
     * @return int|float|null
     */
    public function __toMongo($val)
    {
        // inputSpinner no tiene propiedad $data_validate: el tipo sale de validate (integer, float...).
        return nfnumbertomongo($val, $this->validate);
    }

    /**
     * @return string HTML.
     */
    public function __toString()
    {
        if ($this->validate == '') {
            $this->validate = "number"; //integer,float 
        }
        if ($this->validate == 'float' || $this->validate == 'number') {
            $_SESSION['ANTIXSS'][$this->id] = [FILTER_VALIDATE_FLOAT, FILTER_FLAG_ALLOW_THOUSAND];
        } else {
            /*$_SESSION['ANTIXSS'][$this->id]=[
                FILTER_VALIDATE_INT,[
                'options' => [
                    'default' => $this->default,
                    'min_range' => $this->min,
                    'max_range' => $this->max
                ],
                'flags' => FILTER_FLAG_ALLOW_HEX]
            ];*/
            $_SESSION['ANTIXSS'][$this->id] = [
                FILTER_VALIDATE_INT
            ];
        }

        return ($this->caption != '' ? '<label for="' . $this->id . '">' . $this->caption . '</label>' : '') .
            '<input type="text" data-role="spinner" id="' . $this->id .
            '" name="' . $this->name .
            '"' . $this->writetags() .
            $this->data_validate() . ' value="' . $this->value . '"' .
            ($this->datasize ? ' data-size="' . $this->datasize . '"' : '') .
            ($this->required ? ' required="required"' : '') .
            ($this->readonly ? ' readonly="readonly"' : '') .
            ($this->disabled ? ' disabled' : '') .
            ($this->addclass ? ' class="' . $this->addclass . '"' : '') .
            ' autocomplete="off">';
    }
    /**
     * @param mixed $newval
     * @return bool
     */
    public function is_valid($newval)
    {
        if ($newval === null || (is_string($newval) && trim($newval) === '')) {
            return !$this->required;
        }

        $rules = !empty($this->validate) ? preg_split('/\s+/', trim((string) $this->validate)) : [];
        $rules = array_values(array_filter($rules, static fn($rule) => $rule !== ''));

        foreach ($rules as $rule) {
            if ($rule === 'float' || $rule === 'number') {
                return filter_var((string) $newval, FILTER_VALIDATE_FLOAT, FILTER_FLAG_ALLOW_THOUSAND) !== false;
            }
            if ($rule === 'integer' || $rule === 'digits') {
                return filter_var((string) $newval, FILTER_VALIDATE_INT) !== false;
            }
        }

        return filter_var((string) $newval, FILTER_VALIDATE_FLOAT, FILTER_FLAG_ALLOW_THOUSAND) !== false || filter_var((string) $newval, FILTER_VALIDATE_INT) !== false;
    }
}

/**
 * Calificación con estrellas (data-role rating).
 */
class inputRating extends baseInput
{
    /** @var list<string>|array<string, mixed> */
    public $tags = ['data-values', 'onchange'];
    /** @var string|null */
    public $onchange;
    /**
     * @return string HTML.
     */
    public function __toString()
    {
        return ($this->caption != '' ? '<label for="' . $this->id . '">' . $this->caption . '</label>' : '') .
            '<input data-role="rating"  id="' . $this->id .
            '" name="' . $this->name .
            '"' . $this->writetags() . ' data-value="' . $this->value . '">';
    }
}

/**
 * Selector de color (<input type="color">).
 */
class inputColor extends baseInput
{
    /** @var list<string>|array<string, mixed> */
    public $tags = ['data-values', 'onchange'];

    /** @var string|null */
    public $onchange;

    /**
     * @return string HTML.
     */
    public function __toString()
    {
        return '<input type="color" data-role="input" id="' . $this->id .
            '" name="' . $this->name .
            '"' . $this->inputtags() . ' value="' . $this->value . '">';
    }
} // */

/**
 * Fecha (Y-m-d). Por omisión se guarda como UTCDateTime a medianoche en $timezone (UTC si no se indica);
 * storagetype ST_STRING la guarda como texto.
 */
class inputDate extends baseInput
{
    /** @var string */
    private $format = 'Y-m-d';
    /** @var DateTimeZone|null */
    public $timezone;
    /** @var self::ST_* */
    public $storagetype = self::ST_MONGODATE;
    const ST_MONGODATE = 'st_mongodate';
    const ST_STRING = 'st_string';
    /**
     * @return string HTML.
     */
    public function __toString()
    {
        if ($this->type == '') {
            $this->type = 'calendarpicker';
        }

        // $this->validate="pattern='d{1,2}\/\d{1,2}\/\d{2,4}'";
        return
            '<div class="form-group">
           <input type="date" name="' . $this->name . '" id="' . $this->id . '" data-role="input" value="' . $this->__toPHP($this->value) . '" ' .
            $this->inputtags() .
            '></div>';
    }

    /**
     * @param mixed $date
     * @return bool
     */
    public function is_valid($date)
    {
        $d = DateTime::createFromFormat($this->format, $date);
        return (empty($date) && !$this->required) || ($d && $d->format($this->format) == $date);
    }

    /**
     * @param mixed $val Texto Y-m-d.
     * @return MongoDB\BSON\UTCDateTime|string|null
     */
    public function __toMongo($val)
    {
        if ($this->timezone == null) {
            $this->timezone = new DateTimeZone('UTC');
        }

        if (!empty($val)) {
            if ($this->storagetype == self::ST_STRING) {
                return $val;
            } else {
                // '!' pone en cero los campos que el formato no trae (la hora); sin él se guardaba la hora actual.
                $orig_date = DateTime::createFromFormat('!' . $this->format, $val, $this->timezone);
                if ($orig_date === false) {
                    return null;
                }
                $orig_date = $orig_date->getTimestamp();
                $utcdatetime = new MongoDB\BSON\UTCDateTime($orig_date * 1000);
                return $utcdatetime;
            }
        } else {
            return null;
        }
    }

    /**
     * Texto Y-m-d para mostrar a partir de lo guardado.
     *
     * @param mixed $val
     * @return mixed
     */
    public function __toPHP($val)
    {
        if ($this->storagetype == self::ST_STRING) {
            return $val;
        } elseif ($this->storagetype == self::ST_MONGODATE) {
            if ($val instanceof MongoDB\BSON\UTCDateTime) {
                // Se muestra en la misma zona con la que se guardó; toDateTime() regresa UTC.
                $datetime = $val->toDateTime()->setTimezone($this->timezone ?? new DateTimeZone('UTC'));
                $date = $datetime->format($this->format);
            } else {
                $date = $val;
            }
            return $date;
        }
    }
}
/**
 * Hora (H:i). Se guarda como UTCDateTime del 1 de enero de 1970 a esa hora en UTC.
 */
class inputTime extends baseInput
{
    /** @var string|null */
    public $type;

    /**
     * @return string HTML.
     */
    public function __toString()
    {
        if ($this->type == '') {
            $this->type = 'timepicker';
        }

        return
            '<div class="form-group">
            <input type="time" name="' . $this->name . '" id="' . $this->id . '" data-role="input" value="' . $this->__toPHP($this->value) . '" ' .
            $this->inputtags() .
            '></div>';
    }

    /**
     * @param mixed $val
     * @return mixed Texto H:i si era UTCDateTime.
     */
    public function __toPHP($val)
    {
        // If stored as MongoDB UTCDateTime convert to H:i, otherwise return as-is
        if ($val instanceof MongoDB\BSON\UTCDateTime) {
            $datetime = $val->toDateTime();
            return $datetime->format('H:i');
        } else {
            return $val;
        }
    }
    /**
     * @param mixed $val Texto H:i.
     * @return MongoDB\BSON\UTCDateTime|null
     */
    public function __toMongo($val)
    {
        if (empty($val)) {
            return null;
        }

        $orig_date = DateTime::createFromFormat('H:i', $val, new DateTimeZone('UTC'));
        if ($orig_date === false) {
            return null;
        }

        $orig_date = $orig_date->getTimestamp();
        return new MongoDB\BSON\UTCDateTime($orig_date * 1000);
    }
    /**
     * @param mixed $date
     * @return bool
     */
    public function is_valid($date)
    {
        if ($date === null || $date === '') {
            return !$this->required;
        }

        return preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', (string) $date) === 1;
    }
}
/**
 * Fecha y hora (datetime-local, Y-m-d\TH:i). Se guarda como UTCDateTime en $timezone (UTC si no se indica), o
 * como texto con ST_STRING.
 */
class inputDateTime extends baseInput
{
    /** @var DateTimeZone|null */
    public $timezone = null;
    public const ST_MONGODATE = 'st_mongodate';
    public const ST_STRING = 'st_string';
    /** @var self::ST_* */
    public $storagetype = self::ST_MONGODATE;

    /**
     * @global class_nframework $nframework
     * @return string HTML.
     */
    public function __toString()
    {
        global $nframework;
        $this->validate = 'string';
        // $nframework->csss['099dtime']='//cdn.nlared.com/jquery-datetimepicker/build/jquery.datetimepicker.min.css';
        return
            '<input name="' . $this->name . '" id="' . $this->id . '" class="form-control" type="datetime-local" data-role="input"' .
            //($this->required ? ' required="required"' : '') .
            //($this->readonly ? ' readonly="readonly"' : '') .
            ($this->prepend ? ' data-prepend="' . $this->prepend . '"' : '') .
            ($this->disabled ? ' disabled' : '') . $this->inputtags()
            . $this->data_validate() .
            $this->addtags . ' value="' . $this->__toPHP($this->value)
            . '" data-clear-button="false"  autocomplete="off"/>';
    }

    /**
     * @param mixed $val
     * @return MongoDB\BSON\UTCDateTime|string|null
     */
    public function __toMongo($val)
    {
        if ($this->timezone == null) {
            $this->timezone = new DateTimeZone('UTC');
        }

        if (!empty($val)) {
            if ($this->storagetype == self::ST_STRING) {
                return $val;
            } else {
                $orig_date = DateTime::createFromFormat('Y-m-d\TH:i', $val, $this->timezone);
                if ($orig_date === false) {
                    return null;
                }
                $orig_date = $orig_date->getTimestamp();
                $utcdatetime = new MongoDB\BSON\UTCDateTime($orig_date * 1000);
                return $utcdatetime;
            }
        } else {
            return null;
        }
    }

    /**
     * @param mixed $val
     * @return mixed
     */
    public function __toPHP($val)
    {
        if ($this->storagetype == self::ST_STRING) {
            return $val;
        } elseif ($this->storagetype == self::ST_MONGODATE) {
            if ($val instanceof MongoDB\BSON\UTCDateTime) {
                // Se muestra en la misma zona con la que se guardó; toDateTime() regresa UTC.
                $datetime = $val->toDateTime()->setTimezone($this->timezone ?? new DateTimeZone('UTC'));
                $date = $datetime->format('Y-m-d\TH:i');
            } else {
                $date = $val;
            }
            return $date;
        }
    }
}

/**
 * Editor HTML enriquecido (TinyMCE). Con upload sube imágenes a mediadir. El contenido es HTML: no lo muestre a
 * otros usuarios sin sanearlo.
 */
class inputMCE extends baseInput
{
    /** @var bool */
    public $upload = false;
    /** @var string|null Carpeta en disco para las imágenes subidas. */
    public $mediadir;
    /** @var string|null URL pública de mediadir. */
    public $baseurl;
    // public $id;
    /** @var string|null */
    public $extended_valid_elements;
    /** @var string|null */
    public $content_css;
    /**
     * @global class_nframework $nframework
     * @global Javas $javas
     * @return string HTML.
     */
    public function __toString()
    {
        global $nframework, $javas;
        /*
        'a11ychecker','advcode', 'editimage', 'powerpaste', 'tinymcespellchecker', 'tinydrive'
        */

        if (empty($this->content_css)) {
            $this->content_css = array_values((array) $nframework->csss);
        }
        $nframework->jss['025'] = 'https://cdn.jsdelivr.net/npm/hugerte@1/hugerte.min.js';
        // $nframework->jss['905']='https://cdn.nlared.com/hugerte/nf.js';
        $nframework->jss['905'] = 'https://cdn.nlared.com/hugerte/metro/plugin.js?n=' . date('ymdHis');
        /*if(!$nframework->onces['MCE']){
            $javas->addjs("
hugerte.PluginManager.add('myPlugin', function(editor, url) {
    // Agregar un botón
    editor.ui.registry.addButton('myButton', {
        text: 'Mi Botón',
        onAction: function() {
            editor.insertContent('<p>¡Hola desde el plugin!</p>');
        }
    });

    // Agregar un comando
    editor.addCommand('myCommand', function() {
        alert('Comando personalizado ejecutado');
    });

    // Evento de inicialización
    editor.on('init', function() {
        console.log('Plugin personalizado inicializado');
    });
});
	'containerid' => 'list',
	'dialogid' => $dialog->id,
	'template' => <<<T

            ");

            $nframework->onces['MCE']=true;
        }
        //*/
        // toolbar: 'undo redo | bold italic | alignleft aligncenter alignright | myButton',
        // code
        $javas->addjs("hugerte.init({
	selector:'textarea#" . $this->id . "',
	plugins: 'accordion advlist anchor autolink autosave charmap code codesample directionality emoticons fullscreen help image insertdatetime link lists media nonbreaking pagebreak preview quickbars save searchreplace table template visualblocks visualchars wordcount',
	image_list: '/nframework/tinymceimgs.php?_id=" . $this->id . "',
	convert_urls: false," .
            ($nframework->lang != 'en-US' ?
                "language_url: '//cdn.nlared.com/hugerte/langs/" . $nframework->lang_ . ".js',
	language: '" . $nframework->lang_ . "',
	" : '') . "
	content_css: " . json_encode($this->content_css) . ",
	relative_urls: false,
	//document_base_url: '//" . $_SERVER['HTTP_HOST'] . $this->baseurl . "',
    image_uploadtab: " . ($this->upload ? 'true' : 'false') . ",
    images_upload_url: '/nframework/tinymceupload.php?_id=" . $this->id . "',
	images_upload_base_path: '" . $this->baseurl . "',
	extended_valid_elements: '" . $this->extended_valid_elements . "',
setup: function(editor) {
  editor.on('PreInit', function() {
    editor.parser.addNodeFilter('iframe', function(nodes) {
      nodes.forEach(function(node) {
        node.attr('sandbox', 'allow-scripts allow-same-origin');
      });
    });
  });
}
});");
        /*
    setup: function (editor) {
        editor.on('init', function () {
            const head = editor.dom.select('head')[0];
            editor.dom.add(head, 'script', {
                src: 'https://cdn.metroui.org.ua/5.1.13/metro.js',
                type: 'text/javascript'
            })
        })
    }
*/
        $_SESSION['ANTIXSS'][($this->id)][0] = ['html'];
        $_SESSION['ANTIXSS'][($this->id)] = ['html'];

        $_SESSION['tinymceup'][$this->id] = [
            'mediadir' => $this->mediadir,
            'baseurl' => $this->baseurl,
        ];

        return ($this->caption != '' ? '<label for="' . $this->id . '">' . $this->caption . '</label>' : '') .
            '<textarea name="' . $this->name . '" id="' . $this->id
            . '"' .
            ($this->required ? ' required="required"' : '') .
            ($this->readonly ? ' readonly="readonly"' : '') .
            ($this->disabled ? ' disabled' : '') .
            ($this->placeholder ? ' placeholder="' . $this->placeholder . '"' : '') .
            $this->addtags . ' data-role="tinyMCE">' . $this->value .
            '</textarea>';
    }
}
/**
 * Texto de varias líneas, con contador de caracteres opcional.
 */
class textArea extends baseInput
{
    /** @var bool|null */
    public $uppercase;
    /** @var bool|string|null */
    public $charscounter;
    /** @var bool */
    public $spellcheck = true;
    /** @var string|null */
    public $charscountertemplate;
    /**
     * @return string HTML.
     */
    public function __toString()
    {
        if ($this->value === null) {
            if (isset($this->default)) {
                $this->value = $this->default;
            } else {
                $this->value = '';
            }
        }
        return '
        	<textarea data-role="textarea" name="' . $this->name . '" id="' . $this->id . '"' .
            $this->inputtags() .
            ($this->spellcheck ? ' spellcheck="true"' : '') .
            ($this->uppercase ? ' uppercase="true"' : '') .
            ($this->charscounter != '' ? ' data-chars-counter="' . $this->charscounter . '"' : '') .
            ($this->charscountertemplate != '' ? ' data-chars-counter-template="' . $this->charscountertemplate . '"' : '') .
            '>' . htmlentities($this->value) . '</textarea>';
    }
}
/**
 * Sin implementación propia (heredado).
 */
class AutoformList extends baseInput
{
    /** @var array<int|string, string> */
    public $options = [];
}
/**
 * Casilla. storeagetype define lo que se guarda: ST_MONGOBOOLEAN (bool, omisión), ST_INTEGER (1/0), ST_STRING
 * ('true'/'false') o ST_CUSTOM (customtrue/customfalse).
 */
class inputCheckBox extends baseOptions
{
    /** @var self::ST_* */
    public $storeagetype = self::ST_MONGOBOOLEAN;
    const ST_MONGOBOOLEAN = 'st_mongoboolean';
    const ST_INTEGER = 'st_integer';
    const ST_CUSTOM = 'st_custom';
    const ST_STRING = 'st_string';
    /** @var mixed */
    public $customtrue;
    /** @var mixed */
    public $customfalse;
    /**
     * @return string HTML.
     */
    public function __toString()
    {
        return '<input type="checkbox" name="' . $this->name . '" id="' . $this->id . '" value="1"' .
            ' data-role="checkbox" data-caption="' . $this->caption . '"' .
            ' labelid="' . $this->id . '" ' .
            $this->data_validate() .
            ($this->onchange ? ' data-on-change="' . $this->onchange . '"' : '') .
            ($this->value ? ' checked ' : ' ') . '/>';
    }
    /**
     * @param mixed $newval
     * @return bool
     */
    public function is_valid($newval)
    {
        // checkedvalue puede ser cualquier valor escalar; una casilla sin marcar no se envía (null).
        return $newval === null || is_scalar($newval);
    }
    /**
     * @param mixed $val
     * @return mixed
     */
    public function __toMongo($val)
    {
        if ($this->storeagetype == self::ST_MONGOBOOLEAN) {
            return filter_var($val, FILTER_VALIDATE_BOOLEAN);
        } elseif ($this->storeagetype == self::ST_STRING) {
            return $val ? 'true' : 'false';
        } elseif ($this->storeagetype == self::ST_INTEGER) {
            return $val ? 1 : 0;
        } else if ($this->storeagetype == self::ST_CUSTOM) {
            if ($val) {
                return $this->customtrue;;
            } else {
                return $this->customfalse;;
            }
        } else {
            return $val;
        }
    }
    /**
     * @param mixed $val
     * @return mixed
     */
    public function __toPHP($val)
    {
        if ($this->storeagetype == self::ST_MONGOBOOLEAN) {
            return filter_var($val, FILTER_VALIDATE_BOOLEAN);
        } elseif ($this->storeagetype == self::ST_STRING) {
            return $val == 'true' ? true : false;
        } elseif ($this->storeagetype == self::ST_INTEGER) {
            return $val == 1 ? true : false;
        } else if ($this->storeagetype == self::ST_CUSTOM) {
            return $val == $this->customtrue ? true : false;
        } else {
            return $val;
        }
    }
}


/**
 * Grupo de botones de opción a partir de options.
 */
class inputRadios extends baseOptions
{
    /** @var mixed Sin uso (errata histórica de required). */
    public $rquired;

    /**
     * @return string HTML.
     */
    public function __toString()
    {
        $contas = 0;
        $result = '';
        foreach ($this->options as $value => $text) {
            $result .= '<input type="radio" name="' . $this->name . '" id="' . $this->id . '_' . $contas . '" value="' . $value
                . '" data-role="radio" data-caption="' . $text . '"' .
                ' labelid="' . $this->id . '" ' . $this->data_validate() .
                ($this->onchange ? ' data-on-change="' . $this->onchange . '"' : '') .
                ($this->value == $value ? ' checked ' : ' ') . '/>'; //&nbsp;<label for="'.$this->id.'_'.$contas.'">'.$text.'</label>';
            $contas++;
        }

        return ($this->caption != '' ? '<label id="' . $this->id . '">' . $this->caption . '</label>' : '') . $result;
    }
}

/**
 * <option> (y <optgroup> para arreglos anidados) de una lista valor => texto. No escapa: los textos son HTML.
 *
 * @param array<int|string, string|array<int|string, string>> $options
 * @param mixed $selected Valor o lista de valores seleccionados.
 * @return string
 */
function nflistoptions($options, $selected = []): string
{
    $result = '';
    if (!is_array($selected)) {
        $selected = [0 => $selected];
    }
    foreach ($options as $value => $text) {
        if (is_array($text)) {
            $result .= '<optgroup label="' . $value . '">' . nflistoptions($text, $selected) . '</optgroup>';
        } else {
            $result .= '<option value="' . $value . '"' . (in_array($value, $selected) ? ' selected>' : '>') . $text . '</option>';
            // $result.='<option value="' . $value . '"' . ($value == $selected ? ' selected>' : '>') . $text . '</option>';
        }
    }

    return $result;
}

enum DataformatSelectType: string
{
    case String = 'string';
    case MongoID = 'mongoid';
}

/**
 * Opciones tipadas para la opción 'ajax' de Select e inputText (equivalen al arreglo).
 */
class SelectAjaxOptions
{
    /** @var string|null */
    public $db;
    /** @var string|null */
    public $collection;
    /** @var list<array<string, mixed>> */
    public $pipeline = [];
    /** @var list<string> Campos donde se busca el texto. */
    public $columns = [];
    /** @var list<string> */
    public $fields = [];
    /** @var string|null Campo que se muestra. */
    public $label;
    /** @var string|null Campo que se guarda. */
    public $value;
    /** @var mixed */
    public $args;
    /** @var string|null */
    public $adduri;
    /** @var int|null */
    public $limit;
    public String $load;
}

/**
 * Convierte un Select o inputText con 'ajax' en un buscador Tom Select que consulta
 * /nframework/select_ajax.php. Acepta 'ajax' como arreglo o como SelectAjaxOptions.
 * La consulta se guarda en la sesión: el navegador solo envía el texto buscado.
 *
 * @global class_nframework $nframework
 * @global Javas $javas
 * @param baseInput $input Select o inputText con la propiedad ajax.
 * @return void
 */
function nfTomSelectAjax(baseInput $input): void
{
    global $nframework, $javas;
    $ajax = is_object($input->ajax) ? get_object_vars($input->ajax) : (array) $input->ajax;
    $id = $input->id;
    $jsid = json_encode($id, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    $url = '/nframework/select_ajax.php?id=' . urlencode($id) . ($ajax['adduri'] ?? '');
    $jsurl = json_encode($url, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES);

    $nframework->csss[70] = 'https://cdn.jsdelivr.net/npm/tom-select@2.6.2/dist/css/tom-select.css';
    $nframework->jss[70] = 'https://cdn.jsdelivr.net/npm/tom-select@2.6.2/dist/js/tom-select.complete.min.js';
    $input->addclass = trim($input->addclass . ' tomselect');
    $input->role = 'tomselect';

    $load = !empty($ajax['load']) ? $ajax['load'] : <<<js
function (query, callback) {
        fetch({$jsurl} + '&q=' + encodeURIComponent(query))
            .then(res => res.json())
            .then(json => callback(Array.isArray(json) ? json : []))
            .catch(() => callback());
    }
js;
    unset($ajax['load']);
    $_SESSION['selectajax'][$id] = $ajax;
    addVarToGarbage('selectajax\\' . $id, time() + (60 * 60));

    $javas->addjs(
        <<<js
tomselects[{$jsid}] = new TomSelect('#' + CSS.escape({$jsid}), {
    valueField: 'value',
    labelField: 'label',
    searchField: 'label',
    load: {$load}
});
js,
        'ready'
    );

    // Valor inicial (uno o varios): se pide su etiqueta para mostrarla.
    $values = array_values(array_filter(array_map('strval', is_iterable($input->value) ? iterator_to_array($input->value, false) : [(string) $input->value]), fn($v) => $v !== ''));
    if ($values) {
        $jsvalues = json_encode($values, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        $javas->addjs(
            <<<js
{$jsvalues}.forEach(function (qid) {
    fetch({$jsurl} + '&qid=' + encodeURIComponent(qid))
        .then(res => res.json())
        .then(items => (Array.isArray(items) ? items : []).forEach(item => {
            tomselects[{$jsid}].addOption(item);
            tomselects[{$jsid}].addItem(item.value, true);
        }));
});
js,
            'ready'
        );
    }
}

/**
 * Registra una sola vez la función de navegador nfSuggest(input, cfg, onselect): lista de sugerencias
 * bajo un input (texto libre). Usa el estilo .autocomplete-list de Metro, escapa el texto de cada
 * sugerencia, se maneja con teclado (flechas, Enter, Esc) y cancela peticiones viejas.
 * También queda disponible como window.nfSuggest para usarla con inputs propios.
 *
 * @global class_nframework $nframework
 * @return void
 */
function nfSuggestScript(): void
{
    global $nframework;
    if (isset($nframework->javasonce['nfSuggest'])) {
        return;
    }
    $nframework->javasonce['nfSuggest'] = <<<'JS'
window.nfSuggest = function (input, cfg, onselect) {
	if (!input || input.nfSuggest) { return; }
	cfg = Object.assign({ items: null, url: null, param: 'q', key: null, minlength: 1, delay: 250, limit: 20, depends: {}, clearondepends: true }, cfg || {});
	var list = null, items = [], active = -1, timer = null, ctrl = null, cache = {};
	if (!document.getElementById('nf-suggest-css')) {
		var css = document.createElement('style');
		css.id = 'nf-suggest-css';
		css.textContent = '.nf-suggest-list{max-height:220px}.nf-suggest-list .item.nf-active{background-color:#1d1d1d1a}';
		document.head.appendChild(css);
	}
	function dep(sel) { return document.querySelector(sel); }
	function ensureList() {
		if (list && list.isConnected) { return list; }
		list = document.createElement('div');
		list.className = 'autocomplete-list nf-suggest-list';
		list.style.display = 'none';
		(input.closest('.input') || input.parentNode).appendChild(list);
		// mousedown (no click) para elegir antes de que el blur cierre la lista.
		list.addEventListener('mousedown', function (e) {
			var it = e.target.closest('.item');
			if (it) { e.preventDefault(); choose(+it.dataset.index); }
		});
		return list;
	}
	function norm(x) {
		if (x && typeof x === 'object') {
			var label = x.label != null ? x.label : x.value, value = x.value != null ? x.value : x.label;
			return Object.assign({}, x, { label: String(label == null ? '' : label), value: String(value == null ? '' : value) });
		}
		return { label: String(x), value: String(x) };
	}
	function render(q) {
		var l = ensureList(), lq = q.toLowerCase();
		l.textContent = '';
		active = -1;
		items.forEach(function (it, i) {
			var d = document.createElement('div'), span = document.createElement('span'), p = lq ? it.label.toLowerCase().indexOf(lq) : -1;
			d.className = 'item';
			d.dataset.index = i;
			if (p > -1) {
				var s = document.createElement('strong');
				s.textContent = it.label.substr(p, q.length);
				span.append(it.label.slice(0, p), s, it.label.slice(p + q.length));
			} else {
				span.textContent = it.label;
			}
			d.appendChild(span);
			l.appendChild(d);
		});
		l.style.display = items.length ? 'block' : 'none';
	}
	function hide() { if (list) { list.style.display = 'none'; } active = -1; }
	function search() {
		var q = input.value.trim();
		if (q.length < cfg.minlength) { items = []; hide(); return; }
		if (cfg.items) {
			var lq = q.toLowerCase();
			items = cfg.items.map(norm).filter(function (it) { return it.label.toLowerCase().indexOf(lq) > -1; }).slice(0, cfg.limit);
			render(q);
			return;
		}
		if (!cfg.url) { return; }
		var params = new URLSearchParams();
		params.set(cfg.param, q);
		Object.keys(cfg.depends).forEach(function (k) { var el = dep(cfg.depends[k]); params.set(k, el ? el.value : ''); });
		var url = cfg.url + (cfg.url.indexOf('?') > -1 ? '&' : '?') + params.toString();
		if (cache[url]) { items = cache[url]; render(q); return; }
		if (ctrl) { ctrl.abort(); }
		ctrl = new AbortController();
		fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, credentials: 'same-origin', signal: ctrl.signal })
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (data && !Array.isArray(data)) { data = cfg.key ? data[cfg.key] : data.items; }
				items = (Array.isArray(data) ? data : []).map(norm).slice(0, cfg.limit);
				cache[url] = items;
				if (input.value.trim() === q && document.activeElement === input) { render(q); }
			})
			.catch(function (e) { if (e.name !== 'AbortError') { hide(); } });
	}
	function choose(i) {
		var it = items[i];
		if (!it) { return; }
		input.value = it.value;
		hide();
		input.dispatchEvent(new Event('change', { bubbles: true }));
		if (typeof onselect === 'function') { onselect.call(input, it, input); }
		input.dispatchEvent(new CustomEvent('nf:suggest-select', { bubbles: true, detail: it }));
	}
	function mark(i) {
		var nodes = list ? list.querySelectorAll('.item') : [];
		if (!nodes.length) { return; }
		active = (i + nodes.length) % nodes.length;
		nodes.forEach(function (n, k) { n.classList.toggle('nf-active', k === active); });
		nodes[active].scrollIntoView({ block: 'nearest' });
	}
	input.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(search, cfg.items ? 0 : cfg.delay); });
	input.addEventListener('keydown', function (e) {
		var open = list && list.style.display !== 'none';
		if (e.key === 'ArrowDown') { e.preventDefault(); if (open) { mark(active + 1); } else { search(); } }
		else if (e.key === 'ArrowUp' && open) { e.preventDefault(); mark(active - 1); }
		else if (e.key === 'Enter' && open && active > -1) { e.preventDefault(); choose(active); }
		else if (e.key === 'Escape' && open) { e.preventDefault(); hide(); }
	});
	input.addEventListener('blur', function () { setTimeout(hide, 150); });
	Object.keys(cfg.depends).forEach(function (k) {
		var el = dep(cfg.depends[k]);
		if (el) { el.addEventListener('change', function () { cache = {}; if (cfg.clearondepends) { input.value = ''; } }); }
	});
	input.nfSuggest = {
		search: search,
		hide: hide,
		setItems: function (a) { cfg.items = Array.isArray(a) ? a : []; cache = {}; },
		clearCache: function () { cache = {}; }
	};
};
JS;
}

/**
 * Lista de opciones (Metro select) o, con 'ajax', buscador Tom Select en una colección. format 'mongoid' guarda
 * los valores como ObjectId; multiple guarda un arreglo.
 *
 *   new Select(['dataset' => &$dataset, 'field' => 'estado', 'options' => ['a' => 'Activo', 'b' => 'Baja']]);
 *   new Select(['dataset' => &$dataset, 'field' => 'cliente', 'format' => Select::formatMongoID,
 *       'ajax' => ['collection' => 'clientes', 'columns' => ['nombre'], 'label' => 'nombre', 'value' =>
 * '_id']]);
 */
class Select extends baseOptions
{
    const formatMongoID = 'mongoid';
    const formatString = 'string';
    /** @var bool|null */
    public $combobox;
    /** @var bool|null */
    public $multiple;
    /** @var string|null */
    public $invalid_feedback;
    /** @var array<int|string, string|array<int|string, string>> */
    public $options = [];
    /** @var bool|null Permite escribir valores que no están en la lista (Tom Select). */
    public $canadd;
    /** @var string|null */
    public $role;
    /** @var bool Campo de filtro en la lista de Metro. */
    public $datafilter = true;
    /** @var self::formatMongoID|self::formatString|DataformatSelectType|null */
    public $format; // mongoid or string

    /** @var array<string, mixed>|SelectAjaxOptions|null */
    public $ajax;

    /**
     * @param array<string, mixed> $options Además de las de baseInput: options, multiple, format, ajax, canadd, combobox.
     */
    public function __construct($options = [])
    {
        $options['class'] = 'select';
        parent::__construct($options);
    }

    private function isMongoIDFormat(): bool
    {
        return $this->format === self::formatMongoID || $this->format === DataformatSelectType::MongoID;
    }

    /**
     * ObjectId si $val es un id hexadecimal de 24 caracteres; si no, el valor igual.
     *
     * @param mixed $val
     * @return mixed
     */
    private static function toObjectID($val)
    {
        if ($val instanceof MongoDB\BSON\ObjectId) {
            return $val;
        }
        if (is_string($val) && preg_match('/^[0-9a-fA-F]{24}$/', $val)) {
            return new MongoDB\BSON\ObjectId($val);
        }
        return $val;
    }

    /**
     * @param mixed $val
     * @return mixed
     */
    public function __toPHP($val)
    {
        if ($this->multiple) {
            $val = (array) $val;
            if ($this->isMongoIDFormat()) {
                $val = array_map(fn($v) => $v instanceof MongoDB\BSON\ObjectId ? (string) $v : $v, $val);
            }
            return $val;
        }
        if ($this->isMongoIDFormat() && $val instanceof MongoDB\BSON\ObjectId) {
            return (string) $val;
        }
        return $val;
    }

    /**
     * @param mixed $val
     * @return mixed
     */
    public function __toMongo($val)
    {
        if ($this->multiple) {
            $val = (array) $val;
            if ($this->isMongoIDFormat()) {
                $val = array_values(array_map([self::class, 'toObjectID'], array_filter($val, fn($v) => $v !== '' && $v !== null)));
            }
            return $val;
        }
        if ($this->isMongoIDFormat()) {
            return self::toObjectID($val);
        }
        return $val;
    }
    /**
     * @global class_nframework $nframework
     * @global Javas $javas
     */
    public function __toString(): string
    {
        global $nframework, $javas;
        $result = '';
        if ($this->isMongoIDFormat()) {
            $this->value = $this->__toPHP($this->value);
        }
        if ($this->combobox && $this->value != '' && !array_search($this->value, $this->options)) {
            $this->options += [$this->value];
        }
        $this->role = 'select';
        if ($this->combobox) {
            $this->role = 'combobox';
        }

        if ($this->multiple) {
            $this->role = 'select';
            $this->value = (array) ($this->value);
        }/*
    //if (!is_array($this->value) && get_class($this->value)!='MongoDB\\Model\\BSONArray')$this->value=[];
    foreach ($this->options as $value => $text) {
        $result.='<option value="' . $value . '"' .(in_array($value, $this->value) ? ' selected>' : '>') . $text . '</option>';
    }&c_Estado=COA,
} else {
    foreach ($this->options as $value => $text) {
        $result.='<option value="' . $value . '"' . ($value == $this->value ? ' selected>' : '>') . $text . '</option>';
    }
}*/
        $result .= nflistoptions($this->options, $this->value);

        // onfocus=\"Autoformonfocus(this)\" onblur=\"Autoformonblur(this)\">\n";
        // $_SESSION['ANTIXSS'][$this->name]=[FILTER_VALIDATE_SELE];


        if (!empty($this->ajax)) {
            nfTomSelectAjax($this);
        }

        return ($this->caption != '' && $this->role == 'tomselect' ? '<label for="' . $this->id . '">' . $this->caption . '</label>' : '') .
            '<select name="' . $this->name . ($this->multiple ? '[]" multiple="multiple"' : '"') .
            ' id="' . $this->id . '"' . ' data-role="' . $this->role . '" ' .
            $this->inputtags() .
            ($this->prepend ? ' data-prepend="' . $this->prepend . '"' : '') .
            ($this->canadd ? ' canadd="canadd"' : '') .
            ($this->onChange ? ' onChange="' . $this->onChange . '"' : '') .
            (!$this->datafilter ? ' data-filter="false"' : '') .
            ($this->addclass ? ' class="' . $this->addclass . '"' : '') .
            'autocomplete="off" data-filter="true" data-filter-placeholder="' . $this->placeholder . '">' . $result . '</select>';
    }

    /**
     * Hoy siempre acepta el valor: no se comprueba que esté entre las opciones. Valídelo en la página si importa.
     *
     * @param mixed $newval
     * @return bool
     */
    public function is_valid($newval)
    {
        // falta validar array
        // return($this->multiple ? true : filter_var($newval));
        // TODO: PROBAR return !is_array($newval);
        return true;
    }
}
// ############################################ S T A R T ##################################################
// ######################## AGREGUE PARA PONER ICONOS EN LAS OPCIONES DE LOS SELECT########################
/**
 * Como nflistoptions() pero con el icono de Metro de cada valor (para SelectIcon).
 *
 * @param array<int|string, string> $options
 * @param mixed $selected
 * @return string
 */
function nflistoptionsIcons($options, $selected = [])
{
    if (!is_array($selected)) {
        $selected = [0 => $selected];
    }

    $result = '';
    foreach ($options as $key => $value) {
        if (is_array($value) && isset($value['group'])) {
            // Si es un grupo de opciones
            $result .= '<optgroup label="' . htmlspecialchars($value['group']) . '">';
            foreach ($value['options'] as $optionValue => $optionText) {
                if (is_array($optionText)) {
                    $result .= '<option value="' . htmlspecialchars($optionValue) . '" data-template="' . htmlspecialchars($optionText['icon']) . '"' . (in_array($optionValue, $selected) ? ' selected>' : '>') . htmlspecialchars($optionText['datashow']) . '</option>';
                } else {
                    $result .= '<option value="' . htmlspecialchars($optionValue) . '"' . (in_array($optionValue, $selected) ? ' selected>' : '>') . htmlspecialchars($optionText) . '</option>';
                }
            }
            $result .= '</optgroup>';
        } else {
            // Si no es un grupo de opciones
            if (is_array($value)) {
                $result .= '<option value="' . htmlspecialchars($key) . '" data-template="' . htmlspecialchars($value['icon']) . '"' . (in_array($key, $selected) ? ' selected>' : '>') . htmlspecialchars($value['datashow']) . '</option>';
            } else {
                $result .= '<option value="' . htmlspecialchars($key) . '"' . (in_array($key, $selected) ? ' selected>' : '>') . htmlspecialchars($value) . '</option>';
            }
        }
    }

    return $result;
}

/**
 * Select cuyas opciones son iconos de Metro UI (mif-*).
 */
class SelectIcon extends baseOptions
{
    /** @var bool|null */
    public $combobox;
    /** @var bool|null */
    public $multiple;
    /** @var array<int|string, string> */
    public $options = [];
    /** @var bool|null */
    public $canadd;
    /** @var bool */
    public $datafilter = true;
    /**
     * @return string HTML.
     */
    public function __toString()
    {
        if ($this->combobox && $this->value != '' && !array_search($this->value, $this->options)) {
            $this->options += [$this->value];
        }
        $this->role = 'select';
        if ($this->combobox) {
            $this->role = 'combobox';
        }

        if ($this->multiple) {
            $this->role = 'select';
            $this->value = (array) ($this->value);
        }/*
    //if (!is_array($this->value) && get_class($this->value)!='MongoDB\\Model\\BSONArray')$this->value=[];
    foreach ($this->options as $value => $text) {
        $result.='<option value="' . $value . '"' .(in_array($value, $this->value) ? ' selected>' : '>') . $text . '</option>';
    }
} else {
    foreach ($this->options as $value => $text) {
        $result.='<option value="' . $value . '"' . ($value == $this->value ? ' selected>' : '>') . $text . '</option>';
    }
}*/
        $result = nflistoptionsIcons($this->options, $this->value);

        // onfocus=\"Autoformonfocus(this)\" onblur=\"Autoformonblur(this)\">\n";
        // $_SESSION['ANTIXSS'][$this->name]=[FILTER_VALIDATE_SELE];
        return ($this->caption != '' ? '<label for="' . $this->id . '">' . $this->caption . '</label>' : '') .
            ($this->infobox != '' ? '&nbsp;<span class="mif-question nfinfoicon fg-red" content="' . htmlentities($this->infobox, ENT_QUOTES) . '"></span>' : '')
            . '<select name="' . $this->name . ($this->multiple ? '[]" multiple="multiple"' : '"') .
            ' id="' . $this->id . '"' . $this->writetags() .
            ' data-role="' . $this->role . '"' .
            ($this->canadd ? ' canadd="canadd"' : '') .
            ($this->disabled ? ' disabled="disabled"' : '') .
            ($this->required ? ' required="required"' : '') .
            (!$this->datafilter ? ' data-filter="false"' : '') .
            ($this->placeholder ? ' data-filter-placeholder="' . $this->placeholder . '"' : '') .
            ($this->validate ? 'data-validate="' . $this->validate . '"' : '') .
            ($this->multiple ? ' multiple' : '') .
            ($this->addclass ? ' class="' . $this->addclass . '"' : '') . '>' . $result . '</select>' .
            ($this->invalid_feedback != '' ? '<span class="invalid_feedback">' . $this->invalid_feedback . '</span>' : '');
    }

    /**
     * @param mixed $newval
     * @return bool
     */
    public function is_valid($newval)
    {
        // falta validar array
        // return($this->multiple ? true : filter_var($newval));
        // TODO: PROBAR return !is_array($newval);
        return true;
    }
}
/**
 * Grupo de casillas a partir de options; se guarda como ['valor' => bool, ...] con las marcadas.
 */
class inputCheckBoxs extends Select
{
    /** @var 'left'|'right' */
    public $captionposition = 'right';
    /** @var bool */
    public $horizontal = false;
    /** @var bool */
    public $nometro = false;
    /** @var string|null */
    public $type;
    /**
     * @return string HTML.
     */
    public function __toString()
    {
        $result = '';
        $tempcheck = $this->value;
        if ($this->type == '') {
            $this->type = 'checkbox';
        }
        foreach ($this->options as $value => $text) {
            $result .= ($this->horizontal ? '<br>' : '') . '<input labelid="' . $this->id . '" type="checkbox" data-role="checkbox" id="' . $this->id . '_' . $value . '" name="' .
                $this->name . "[$value]\"" .
                (($tempcheck[$value] ?? null) === true ? ' checked' : '') .
                " data-caption=\"$text\" data-caption-position=\"" . $this->captionposition . '">' .
                ($this->nometro ? '<label for="' . $this->id . '_' . $value . '">' . $text . '</label>' : '');
            // $fields.=str_replace('%field%', $result, $this->format['fields'][2]);
            $_SESSION['ANTIXSS'][$this->id . '_' . $value] = [FILTER_VALIDATE_BOOLEAN];
        }

        // $result = str_replace('%fields%', $fields, $this->format['fields'][1]);
        return ($this->caption != '' ? '<label id="' . $this->id . '">' . $this->caption . '</label>' : '') . $result;
    }

    /**
     * @param mixed $vals
     * @return array<string, bool>
     */
    public function __toMongo($vals)
    {
        $tomongo = [];
        // Sin ninguna casilla marcada el navegador no envía el campo (null).
        foreach ((array) $vals as $name => $val) {
            $tomongo[$name] = ($val == 'on' ? true : false);
        }

        return $tomongo;
    }
}

/**
 * Base de inputFile e inputFiles. Los archivos se suben por /nframework/uploadfile.php con la configuración
 * guardada en la sesión (carpeta, permisos, eventos): el navegador nunca elige la carpeta destino.
 */
abstract class BaseFileInput extends baseInput
{
    /** @var string|null Carpeta destino en el servidor. */
    public $dir;
    /** @var bool */
    public $download = true;
    /** @var bool */
    public $preview = true;
    /** @var bool */
    public $delete = true;
    /** @var bool|null */
    public $disabled;
    /** @var bool|null Crear dir si no existe. */
    public $create_dir;
    /** @var string|null accept del <input type="file"> (image/*, .pdf...). */
    public $accept;
    /** @var list<string> */
    public $allowext = []; // extensiones protegidas que se aceptan (p. ej. ['py', 'sh']); ver nfValidUploadFilename()
    /** @var string|null capture del input (user, environment) en móviles. */
    public $capture;
    /** @var 'input'|'drop'|'button' */
    public $mode = 'input'; // input,drop,button
    /** @var int|string Inicio de la ventana para subir (timestamp); '' = ahora. */
    public $limit_time_start = '';
    /** @var int|string Fin de la ventana para subir; '' = 30 minutos. */
    public $limit_time_end = '';
    /** @var string|null Archivo PHP con las funciones onupload/ondelete/onlist/oncountcheck. */
    public $extension;
    /** @var array<string, mixed> Datos que reciben esas funciones. */
    public $extensioninfo = [];
    /** @var string|null */
    public $onupload;
    /** @var string|null */
    public $ondelete;
    /** @var string|null */
    public $oncountcheck;
    /** @var string|null */
    public $onlist;
    /** @var string */
    public $dropIcon = '<span class=\'mif-cloud-upload\'></span>';
    /** @var string */
    public $clearButtonIcon = '<span class=\'mif-cross\'></span>';
    /** @var string|null */
    public $caption;


    /**
     * @param mixed $newval
     * @return bool
     */
    public function is_valid($newval)
    {
        // Los archivos se suben por /nframework/uploadfile.php, no llegan en el POST del formulario.
        return true;
    }

    /**
     * Agrega jQuery UI y jQuery File Upload a la página.
     *
     * @global class_nframework $nframework
     */
    protected function initializeFileUpload(): void
    {
        global $nframework;

        if (!isset($_SESSION['uploads4'])) {
            $_SESSION['uploads4'] = [];
        }

        $nframework->addjqueryui();
        $nframework->addfileupload();
    }

    /**
     * Configuración que se guarda en $_SESSION para /nframework/uploadfile.php.
     *
     * @global class_nframework $nframework
     * @return array<string, mixed>
     */
    protected function getSessionConfig(): array
    {
        global $nframework;

        return [
            'dir' => $this->dir,
            'formname' => $this->id,
            'delete' => $this->delete,
            'download' => $this->download,
            'preview' => $this->preview,
            'extension' => $this->extension,
            'extensioninfo' => $this->extensioninfo,
            'onupload' => $this->onupload,
            'ondelete' => $this->ondelete,
            'create_dir' => $this->create_dir,
            'allowext' => array_values((array) $this->allowext),
            'onlist' => $this->onlist,
            'oncountcheck' => $this->oncountcheck,
            'limit_time_start' => $this->limit_time_start ?: time(),
            'limit_time_end' => $this->limit_time_end ?: strtotime('+30 minutes'),
        ];
    }

    /**
     * @global Javas $javas
     */
    protected function addBaseJavaScript(): void
    {
        global $javas;

        $javas->addjs(<<<JS
            (function(){
                var inputId = '#{$this->id}';
                var progressId = '#{$this->id}_progress';
                var \$input = $(inputId);
                var \$progress = $(progressId);

                if (\$input.length) {
                    \$input.off('.nfFileUpload');
                    \$input.removeData('blueimp-fileupload');
                    \$input.removeData('fileupload');
                    \$input.removeData('nf-fileupload-bound');

                    try {
                        \$input.fileupload('destroy');
                    } catch (e) {}
                }

                $.ajax({
                    url: '/nframework/uploadfile.php',
                    method: "POST",
                    data: "mid={$this->id}",
                    dataType: 'json',
                    success: function(data) {
                        nffileupload_{$this->id}(data);
                    }
                });

                \$progress.hide();
                \$input.fileupload({
                    url: '/nframework/uploadfile.php',
                    dataType: "json",
                    done: function (e, data) {
                        nffileupload_{$this->id}(data.result);
                        try {
                            \$input.val('');
                            \$input.trigger('change');
                        } catch (e) {}
                    },
                    progressall: function (e, data) {
                        var progress = parseInt(data.loaded / data.total * 100, 10);
                        if (progress === 100 || progress === 0) {
                            \$progress.hide();
                        } else {
                            \$progress.show();
                            \$progress.attr("data-value", progress);
                        }
                    }
                }).bind("fileuploadcompleted", function(e, data) {
                    console.log("eventFinished");
                }).prop("disabled", !$.support.fileInput)
                  .parent().addClass($.support.fileInput ? undefined : "disabled");

                \$input.data('nf-fileupload-bound', true);
            })();
        JS, 'ready');
    }

    protected function getCaptureAttr(): string
    {
        return $this->capture ? ' capture="' . $this->capture . '"' : '';
    }

    protected function getAcceptAttr(): string
    {
        return $this->accept ? ' accept="' . $this->accept . '"' : '';
    }
}

/**
 * Un archivo con nombre fijo (path): subir, previsualizar, descargar y eliminar. Ver docs/files.php.
 */
class inputFile extends BaseFileInput
{
    /** @var string|null Nombre del archivo dentro de dir. */
    public $path;
    /** @var string|null JS al terminar la subida. */
    public $onDone;
    /**
     * @global Javas $javas
     * @global class_nframework $nframework
     * @return string HTML.
     */
    public function __toString()
    {
        global $javas, $nframework;
        $lng = $nframework->language;
        $this->initializeFileUpload();

        // Prepare session data with path-specific config.
        // Reinitializing the same mid guarantees a clean state when the control is reused
        // without reloading the page.
        unset($_SESSION['uploads4'][$this->id]);
        $_SESSION['uploads4'][$this->id] = array_merge($this->getSessionConfig(), [
            'dir' => dirname($this->path),
            'extension' => $nframework->api_path . '/uploadfile_ext_path.php',
            'extensioninfo' => ['path' => $this->path],
            'onupload' => 'onupload',
            'ondelete' => 'ondelete',
            'onlist' => 'onlist',
            'ondownload' => 'ondownload',
            'oncountcheck' => 'oncountcheck',
            'countlimit' => 0,
        ]);

        // Add JavaScript
        $javas->addjs(<<<JS
            function nffileupload_{$this->id}(data) {
                {$this->onDone}
            }
            function nffiledelete_{$this->id}(params) {
                {$this->onDelete}
            }
            function {$this->id}_delete() {
                var posting = jQuery.post('/nframework/uploadfile.php', {
                    'delete': true,
                    'mid': '{$this->id}'                
                },
                function(data) {
                    nffiledelete_{$this->id}(data);
                }, "json");
            }
        JS, 'general');

        $this->addBaseJavaScript();

        // Build mode attributes
        /*$modeAttrs = '';
        if ($this->mode === 'drop' || $this->mode === 'button') {
            $modeAttrs = ' data-mode="' . $this->mode . '" data-files-title="archivo(s) seleccionado(s)" data-drop-title="<strong>Selecciona archivo(s)</strong>"';
        }*/
        if ($this->placeholder == '') {
            $this->placeholder = $lng['Drag file here to upload'];
        }
        return <<<HTML
            {$this->getLabelHtml()}
            <p>
                <input type="file" id="{$this->id}" name="{$this->id}"
                    {$this->getDisabledAttr()}
                    {$this->getPrependAttr()}
                    {$this->getCaptureAttr()}                    
                    {$this->getAcceptAttr()}                  
                    data-sequential-uploads="true" 
                    data-caption="{$this->caption}"
                    placeholder="{$this->placeholder}"                    
                    data-drop-icon="{$this->dropIcon}"
                    data-clear-button="true"
                    data-clear-button-icon="{$this->clearButtonIcon}"
                    data-role="file"                     
                    data-mode="{$this->mode}"
                    data-button-title="<span class='mif-folder'></span>"
                    data-form-data='{"mid":"{$this->id}"}' />
                <div data-role="progress" id="{$this->id}_progress" data-type="buffer" data-value="0" data-buffer="100" data-small="true"></div>
            </p>
        HTML;
    }
}

/**
 * Varios archivos en dir, con límites de cantidad (countlimit) y tamaño (sizelimit). Ver docs/files.php.
 *
 *   new inputFiles(['id' => 'docs', 'dir' => '/var/data/expedientes/' . $id, 'create_dir' => true, 'accept' =>
 * '.pdf', 'countlimit' => 5]);
 */
class inputFiles extends BaseFileInput
{
    /** @var int Tamaño máximo por archivo en bytes; 0 = sin límite. */
    public $sizelimit = 0;
    /** @var int|null */
    public $countlimit;
    // Use nowdoc for better performance and readability
    /** @var string JS que dibuja la lista al terminar (variable html). */
    public $onDone = <<<'JS'
        html += '<div style="overflow-x:auto;overflow-y: auto;height: 250px;">';
        html += '<table class="table"><thead><tr><th>Nombre</th><th>Descargar</th><th>Ver</th><th>Eliminar</th></tr></thead><tbody>';
        jQuery.each(data.files, function(index, file) {
            html += '<tr>';
            html += '<td>' + file.name + '</td>';
            html += '<td>' + (data.download === true ? '<a href="javascript:nfFileDownload(\'' + id + '\',\'' + file.name + '\')"><span class="mif-download">Descargar</span></a>' : 'Sin permiso') + '</td>';
            html += '<td>' + (data.preview === true ? '<a href="javascript:nfFilePreview(\'' + id + '\',\'' + file.name + '\')"><span class="mif-eye">Ver</span></a>' : 'Sin permiso') + '</td>';
            html += '<td>' + (data.delete === true ? '<a href="javascript:if(confirm(\'Are you sure you want to delete?\')){nfFileDeleteTable(\'' + id + '\',\'' + file.name + '\')}"><span class="mif-cross"></span> Eliminar</a><br>' : 'Sin permiso<br>') + '</td>';
            html += '</tr>';
        });
        html += '</tbody></table></div>';
        $("#" + id + "_list").html(html);
    JS;

    /**
     * @global Javas $javas
     * @global class_nframework $nframework
     * @return string HTML.
     */
    public function __toString()
    {
        global $javas, $nframework;
        $lng = $nframework->language;
        $this->initializeFileUpload();

        // Prepare session configuration; the same control id may be re-used without reload.
        unset($_SESSION['uploads4'][$this->id]);
        $_SESSION['uploads4'][$this->id] = array_merge($this->getSessionConfig(), [
            'countlimit' => (int) $this->countlimit,
            'sizelimit' => $this->sizelimit,
        ]);

        // Add JavaScript
        $javas->addjs(<<<JS
            function nffileupload_{$this->id}(data) {
                var id = '{$this->id}';
                var html = '';
                {$this->onDone}
            }
            
        JS, 'ready');

        $this->addBaseJavaScript();

        return <<<HTML
            {$this->getLabelHtml()}
            <p>
                <input type="file" id="{$this->id}" name="{$this->id}"
                    {$this->getDisabledAttr()}
                    {$this->getPrependAttr()}
                    {$this->getCaptureAttr()}
                    {$this->getDropAttr()}
                    {$this->getAcceptAttr()}
                    multiple
                    data-sequential-uploads="true" 
                    placeholder="{$lng['Drag files here to upload']}"
                    data-drop-icon="{$this->dropIcon}"
                    data-clear-button="true"
                    data-clear-button-icon="{$this->clearButtonIcon}"
                    data-role="file" 
                    data-mode="{$this->mode}"
                    data-button-title="<span class='mif-folder'></span>"
                    data-form-data='{"mid":"{$this->id}"}' />
                <div id="{$this->id}_list"></div>
                <div data-role="progress" id="{$this->id}_progress" data-type="buffer" data-value="0" data-buffer="100" data-small="true"></div>
            </p>
        HTML;
    }

    /**
     * @return string
     */
    private function getDropAttr(): string
    {
        return $this->drop ? ' data-mode="drop" data-files-title="archivo(s) seleccionado(s)" data-drop-title="<strong>Selecciona archivo(s)</strong>"' : '';
    }
}


/**
 * Punto en un mapa (Leaflet). Se guarda como ['lat', 'lng'] o, con type = mapmarker::GeoJSON, como GeoJSON Point
 * (para índices 2dsphere).
 */
class mapmarker extends baseInput
{
    public const GeoJSON = 0x02;

    //	public $latitude;
    //	public $longitude;
    /** @var string|null */
    public $onchange;
    /** @var int|null mapmarker::GeoJSON para guardar como Point. */
    public $type;
    /** @var int */
    public $height = 500;
    /** @var mixed */
    public $value;
    /** @var mixed */
    public $hiddedata;
    /** @var array{lng: float, lat: float} */
    public $startpoint = [
        'lng' => -100.96047970,
        'lat' => 25.43328030,

    ];

    /**
     * @global class_nframework $nframework
     * @global Javas $javas
     * @return string HTML.
     */
    public function __toString()
    {
        global $nframework, $javas;
        $nframework->csss['005rte'] = 'https://unpkg.com/leaflet@1.6.0/dist/leaflet.css" integrity="sha512-xwE/Az9zrjBIphAcBb3F6JVqxf46+CDLwfLMHloNu6KEQCAWi6HcDUbeOfBIptF7tcCzusKFjFw2yuvEpDL9wQ==" crossorigin="';
        $nframework->jss['100leaflet'] = 'https://unpkg.com/leaflet@1.6.0/dist/leaflet.js" integrity="sha512-gZwIG9x3wUXg2hdXF6+rVkLF/0Vi9U8D2Ntg4Ga5I5BZpVkVxlJWbSQtXPSiUTtC0TjtGOmxa1AJPuV0CPthew==" crossorigin="';

        if (!isset($nframework->onces['maps'])) {
            $javas->addjs('var maps=[];');
            $nframework->onces['maps'] = true;
        }
        if (!isset($nframework->onces['mapsmarker'])) {
            $javas->addjs('var mapsmarker=[];');
            $nframework->onces['mapsmarker'] = true;
        }
        $css = '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.6.0/dist/leaflet.css"
	integrity="sha512-xwE/Az9zrjBIphAcBb3F6JVqxf46+CDLwfLMHloNu6KEQCAWi6HcDUbeOfBIptF7tcCzusKFjFw2yuvEpDL9wQ=="
	crossorigin=""/>';

        // $lat=($this->value['lat']!=''?$this->value['lat']:$this->startpoint['lat']);
        // $lng=($this->value['lng']!=''?$this->value['lng']:$this->startpoint['lng']);

        if (empty($this->value)) {
            $lat = $this->startpoint['lat'];
            $lng = $this->startpoint['lng'];
        } else {
            if ($this->type == self::GeoJSON) {
                $lng = $this->value['coordinates']['0'];
                $lat = $this->value['coordinates']['1'];
            } else {
                $lat = $this->value['lat'];
                $lng = $this->value['lng'];
            }
        }

        $javas->addjs("
		var startPoint = [$lat,$lng];
		maps['" . $this->id . "_map'] = L.map('" . $this->id . "_map', {editable: true}).setView(startPoint, 16),
    	tilelayer = L.tileLayer('https://{s}.tile.openstreetmap.fr/hot/{z}/{x}/{y}.png', {maxZoom: 20, attribution: 'Data \u00a9 <a href=\"https://www.openstreetmap.org/copyright\"> OpenStreetMap Contributors </a> Tiles \u00a9 HOT'})
		.addTo(maps['" . $this->id . "_map']);
		
		mapsmarker['" . $this->id . "_mapmarker'] = new L.marker([$lat,$lng], { draggable:'true'});
    	mapsmarker['" . $this->id . "_mapmarker'].on('dragend', function(event){
            var marker = event.target;
            var position = marker.getLatLng();
            maps['" . $this->id . "_map'].flyTo(position);
            $('#" . $this->id . "_lat').val(position.lat);
            $('#" . $this->id . "_lng').val(position.lng);
            " . (!empty($this->onchange) ? $this->onchange : '') . "
            marker.setLatLng(position,{draggable:'true'}).bindPopup(position).update();
    	});
		maps['" . $this->id . "_map'].addLayer(mapsmarker['" . $this->id . "_mapmarker']);
		");

        return '<div id="' . $this->id . '_map" style="height:' . $this->height . 'px;"></div>
		<div class="grid' . ($this->hiddedata ? ' no-visible' : '') . '">
			<div class="row">
				<div class="cell-6"><input name="' . $this->name . '[lat]" id="' . $this->id . '_lat" type="text" value="' . $lat . '" ></div>
				<div class="cell-6"><input name="' . $this->name . '[lng]" id="' . $this->id . '_lng" type="text" value="' . $lng . '" ></div>
			</div>
		</div>
		';
    }

    /**
     * @param array{lat: mixed, lng: mixed}|mixed $val
     * @return mixed
     */
    public function __toMongo($val)
    {
        return $this->type == self::GeoJSON ? ['type' => 'Point', 'coordinates' => [floatval($val['lng']), floatval($val['lat'])]] : $val;
    }
}

/**
 * Recuadro de ejemplo de Metro (div.example).
 */
class example extends Base
{
    /** @var string */
    public $content;

    /** @var string */
    public $title;

    /**
     * @return string HTML.
     */
    public function __toString()
    {
        return '<div class="example" data-text="' . $this->title . '">' . $this->content . '</div>';
    }
}

/**
 * Como dataset pero sobre un registro SQL vía PDO (MySQL, PostgreSQL, SQLite). Los controles se ligan igual
 * (dataset + field); save() inserta o actualiza con consultas preparadas. Tabla y columnas se validan como
 * identificadores. Ver docs/datasetpdo.php.
 *
 *   $ds = new datasetpdo(['pdo' => $pdo, 'table' => 'clientes', 'key' => 'id', '_id' => $id, 'nameprefix' =>
 * 'data']);
 */
class datasetpdo
{
    /** @var list<baseInput>|null */
    public $elements;
    /** @var PDO */
    public $pdo;   // conexión PDO
    /** @var string */
    public $table; // tabla
    /** @var string Columna llave primaria. */
    public $key;   // columna llave primaria
    /** @var mixed */
    private $collection;
    /** @var mixed Valor de la llave del registro. */
    private $_id;
    /** @var array<string, mixed> Registro actual. */
    public $info = [];
    /** @var string|null */
    public $nameprefix;
    /** @var bool|null */
    public $simpleid;
    /** @var mixed */
    public $autosave;
    /** @var mixed */
    public $position;
    /** @var mixed */
    public $fieldprefix;
    /** @var bool */
    private $exists = false;

    /**
     * @param baseInput $element
     * @return void
     */
    public function addElement(&$element)
    {
        $this->elements[] = $element;
    }

    /**
     * @param array{pdo: PDO, table: string, key: string, _id?: mixed, nameprefix?: string} $options
     * @param array<string, mixed> $query Sin uso.
     * @throws InvalidArgumentException Si table o key no son identificadores válidos.
     */
    public function __construct($options, $query = [])
    {
        foreach ($options as $option => $value) {
            $this->{$option} = $value;
        }
        if ($this->_id != '' && $this->key != '') {
            $sth = $this->pdo->prepare('SELECT * FROM ' . self::identifier($this->table) . ' WHERE ' . self::identifier($this->key) . ' = ?');
            $sth->execute([$this->_id]);
            $this->info = $sth->fetch(PDO::FETCH_ASSOC) ?: [];
            if (count($this->info) == 0) {
                $this->info = ['_id' => $this->_id];
            } else {
                $this->exists = true;
            }
        }
        unset($this->info['']);
    }


    /**
     * Valida un nombre de tabla/columna; los identificadores no pueden ir como parámetros.
     *
     * @param mixed $name
     * @throws InvalidArgumentException
     */
    private static function identifier($name): string
    {
        if (!is_string($name) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
            throw new InvalidArgumentException('Identificador SQL inválido');
        }
        return '`' . $name . '`';
    }

    /**
     * Valida los controles con lo recibido en $_POST[nameprefix] y hace INSERT (registro nuevo) o UPDATE.
     *
     * @return string|false false si se guardó; texto HTML con los campos inválidos si no.
     */
    public function save()
    {
        $errores = '';
        $post = $_POST[$this->nameprefix] ?? [];
        foreach ($this->elements as $element) {
            $value = $post[$element->field] ?? null;
            if (!$element->disabled && !$element->is_valid($value)) {
                $errores .= 'Error en:' . $element->field . '<br/>';
            }
            $element->value = $value;
        }
        if (!empty($errores)) {
            return $errores;
        }
        // Consultas preparadas: antes los valores del formulario se concatenaban en el SQL.
        $changes = [];
        foreach ($this->elements as $element) {
            if (!$element->disabled && $element->field != $this->key) {
                $changes[self::identifier($element->field)] = $post[$element->field] ?? null;
            }
        }
        if (!$this->exists) {
            $changes[self::identifier($this->key)] = $this->_id;
            $sql = 'INSERT INTO ' . self::identifier($this->table)
                . ' (' . implode(',', array_keys($changes)) . ') VALUES (' . implode(',', array_fill(0, count($changes), '?')) . ')';
            $params = array_values($changes);
            $this->exists = true;   // el siguiente save() de este objeto actualiza en lugar de insertar
        } else {
            $sets = array_map(fn($column) => $column . ' = ?', array_keys($changes));
            $sql = 'UPDATE ' . self::identifier($this->table) . ' SET ' . implode(',', $sets) . ' WHERE ' . self::identifier($this->key) . ' = ?';
            $params = array_merge(array_values($changes), [$this->_id]);
        }
        $this->pdo->prepare($sql)->execute($params);
        return false;
    }

    /**
     * @param string $name
     * @return mixed false si no existe.
     */
    public function &__get($name)
    {
        $result = false;
        if ($name != '') {
            if ($name == '_id') {
                $result = (string) $this->_id;
            } else {
                if (array_key_exists($name, $this->info)) {
                    if (gettype($this->info[$name]) == 'object') {
                        $result = iterator_to_array($this->info[$name], true);
                    } else {
                        $result = $this->info[$name];
                    }
                }
            }
        }

        return $result;
    }

    /**
     * @param string $name
     * @return bool
     */
    public function __isset($name)
    {
        return isset($this->info[$name]);
    }
}

/**
 * Documento de MongoDB ligado a controles de formulario. Carga el documento por _id (o genera uno nuevo), expone
 * sus campos como propiedades y guarda con save() lo que llega en $_POST[nameprefix], validando y convirtiendo
 * tipos con cada control (__toMongo). Asignar una propiedad que no es del objeto hace updateOne con upsert al
 * momento. Ver docs/databinding.php.
 *
 *   $dataset = new dataset(['collection' => $m->{$config['sitedb']}->productos, '_id' => $_GET['_id'],
 * 'nameprefix' => 'data']);
 *   $nombre = new inputText(['dataset' => &$dataset, 'field' => 'nombre', 'required' => true]);
 *   $errores = $dataset->save();   // false = guardado
 *   $dataset->estado = 'ok';       // updateOne inmediato
 */
#[\AllowDynamicProperties]
class dataset
{
    /** @var list<baseInput> Controles ligados (se registran solos). */
    public $elements = [];
    /** @var MongoDB\Collection */
    public $collection;
    /** @var MongoDB\BSON\ObjectId|string Se lee como texto con $dataset->_id. */
    private $_id;
    /** @var array<string, mixed> Documento actual. */
    public $info = [];
    /** @var string|null Prefijo de los nombres: data → data[campo] y $_POST['data']. */
    public $nameprefix;
    /** @var bool|null true = _id de texto libre (RFC, folio) en lugar de ObjectId. */
    public $simpleid;
    /** @var mixed Sin uso. */
    public $autosave; // pensar
    /** @var MongoDB\Driver\Session|null Sesión para transacciones. */
    public $mongo_session;
    /** @var string|int Reemplaza '$' en los campos (arreglos posicionales). */
    public $position = ''; // se va
    /** @var string|null Prefijo que se antepone a cada campo al guardar. */
    public $fieldprefix; // se va
    /** @var bool true = guarda las versiones previas en nfversions. */
    public $historic = false;
    /** @var bool */
    public $allUpper = false;
    /** @var bool */
    public $allLower = false;
    /** @var list<string> Campos que no se pueden asignar como propiedad. */
    private $nfprotected;
    /**
     * @param baseInput $element
     * @return void
     */
    public function addElement(&$element)
    {
        $this->elements[] = $element;
    }

    /**
     * @param array{collection: MongoDB\Collection, _id?: string|MongoDB\BSON\ObjectId, nameprefix?: string, simpleid?: bool, historic?: bool, mongo_session?: MongoDB\Driver\Session, position?: string|int, fieldprefix?: string} $options
     * @param array<string, mixed> $query Sin uso.
     * @throws MongoDB\Driver\Exception\InvalidArgumentException Si _id no es un ObjectId válido y simpleid no está activo.
     */
    public function __construct($options, $query = [])
    {
        $this->nfprotected = [
            '_id',
            'nfversions',
            'nfprotected',
        ];
        foreach ($options as $option => $value) {
            $this->{$option} = $value;
        }
        if ($this->_id != '') {
            $this->_id = ($this->simpleid == true ?
                trim($this->_id)
                : new MongoDB\BSON\ObjectID(trim($this->_id))
            );

            $this->info = (array) $this->collection->findOne(['_id' => $this->_id]);
            if (count($this->info) == 0) {
                $this->info = ['_id' => $this->_id];
            }
        } else {
            $this->_id = new MongoDB\BSON\ObjectID;
            $this->info = ['_id' => $this->_id];
        }
        unset($this->info['']);
    }

    /**
     * Vuelve a leer el documento de la base.
     *
     * @return void
     */
    public function refresh()
    {
        if ($this->_id != '') {
            $this->info = $this->collection->findOne([
                '_id' => ($this->simpleid == true ?
                    trim($this->_id)
                    : toMongoId(trim($this->_id))
                ),
            ]);
            if (count($this->info) == 0) {
                $this->info = ['_id' => $this->_id];
            }
        }
    }

    /**
     * @param string $name
     * @return bool
     */
    public function __isset($name)
    {
        return isset($this->info[$name]);
    }

    /**
     * Propiedades del objeto se asignan normal; cualquier otro nombre es un campo del documento y se guarda de
     * inmediato con $set (upsert) si cambió. _id y nfversions están protegidos.
     *
     * @param string $name
     * @param mixed $value
     * @return void
     */
    public function __set($name, $value)
    {
        if (!in_array($name, $this->nfprotected)) {
            if (property_exists($this, $name)) {
                $this->{$name} = $value;
            } else {
                $options = [];
                if (!empty($this->mongo_session)) {
                    $options['session'] = $this->mongo_session;
                }
                if ($this->info[$name] != $value) {
                    $this->info[$name] = $value;
                    if ($this->_id == '') {
                        if ($this->historic) {
                            $this->createversion();
                        }

                        $r = $this->collection->insertOne($this->info, $options);
                        $this->info['_id'] = $r['_id'];
                    } else {
                        $options['upsert'] = true;
                        $operations = [
                            '$set' => [$name => $value],
                        ];

                        $this->info[$name] = $value;
                        if ($this->historic) {
                            $this->createversion();
                            $operations['$set']['nfversions'] = $this->info['nfversions'];
                        }

                        $this->collection->updateOne(['_id' => $this->_id], $operations, $options);

                        //       echo "set";
                        //      print_r($this->_id);
                        // $this->collection->save($this->info);
                    }
                    // $this->col->update(['_id'=>$this->id],['$set'=>[$name=>$value]]);
                }
            }
        }

        return true;
    }

    /**
     * Quita el campo del documento con $unset.
     *
     * @param string $name
     * @return void
     */
    public function __unset($name)
    {
        if ($name != '_id') {
            $options = [];
            if (!empty($this->mongo_session)) {
                $options['session'] = $this->mongo_session;
            }
            unset($this->info[$name]);
            $operations = [
                '$unset' => [$name => 1],
            ];
            if ($this->historic) {
                $this->createversion();
                $operations['$set']['nfversions'] = $this->info['nfversions'];
            }

            $this->collection->updateOne(
                ['_id' => $this->info['_id']],
                [$operations],
                $options
            );

            // $this->col->update(['_id'=>$this->id],['$unset'=>[$name=>1]]);
        }

        return true;
    }
    /**
     * Intenta registrar los campos de los controles en la definición de nftables de la colección.
     *
     * @global MongoDB\Client $m
     * @global class_config $config
     * @param mixed $id
     * @return void
     */
    public function tonfTables($id): void
    {
        global $m, $config;
        foreach ($this->elements as $element) {
            $fields[] = [
                'type' =>  get_class($element),
                'field' => $element->field,
                'short_description' => $element->caption,
            ];
        }
        $m->{$config['sitedb']}->nftables->updateOne(
            ['nfcollection' => $this->collection->getCollectionName()],
            [
                '$addToSet' => [
                    'type' =>  get_class($element),
                    'field' => $element->field,
                ],
                ['upsert' => true]
            ]
        );
    }
    /**
     * Campo del documento (arreglos/BSON como arreglo de PHP); '_id' en texto; null si no existe.
     *
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        $result = null;
        if ($name != '') {
            if ($name == '_id') {
                $result = (string) $this->_id;
            } else {
                if (array_key_exists($name, $this->info)) {
                    if (is_iterable($this->info[$name]) == 'object') {
                        $result = iterator_to_array($this->info[$name], true);
                    } else {
                        $result = $this->info[$name];
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Agrega a nfversions una copia del documento actual con usuario y fecha.
     *
     * @global User $user
     * @return void
     */
    private function createversion()
    {
        global $user;
        $versiones = (array) $this->info['nfversions'];
        $olddata = $this->info;
        unset($olddata['_id']);
        unset($olddata['nfversions']);
        if (count($olddata) > 0) {
            $versiones[] = [
                'user' => $user->_id,
                'fh' => new MongoDB\BSON\UTCDateTime,
                'data' => $olddata,
            ];
            $this->info['nfversions'] = $versiones;
        }
    }

    /**
     * Valida cada control con lo recibido en $_POST[nameprefix]. Si todo es válido guarda: los campos vacíos se
     * quitan ($unset) y los demás se convierten con __toMongo(); con subcampos (punto) usa $set/$unset por campo,
     * si no reemplaza los campos del documento con $set. Los controles disabled o backreadonly no se toman del
     * navegador.
     *
     * @global mixed $result
     * @return string|false false si se guardó; texto HTML con los campos inválidos si no.
     * @throws Exception Si un control ligado no tiene field.
     */
    public function save()
    {
        global $result;
        $options = [];
        $errores = '';
        $punto = false;
        $changes = [];

        if (!empty($this->mongo_session)) {
            $options['session'] = $this->mongo_session;
        }
        if (!empty($this->nftable)) {
            $nftableenable = true;
        }
        foreach ($this->elements as $element) {
            // Se validan los campos editables (antes solo se validaban los deshabilitados).
            if (!$element->disabled && !$element->is_valid($_POST[$this->nameprefix][$element->field] ?? null)) {
                $errores .= 'Error en:' . $element->field . '<br/>';
            } else {
                $element->value = $_POST[$this->nameprefix][$element->field] ?? null;
            }
        }
        if (empty($errores)) {
            $toset = [];
            $tounset = [];

            foreach ($this->elements as $element) {
                if (empty($element->field)) {
                    throw new Exception('key vacio:' . json_encode($this->elements));
                }
                if ($element->field == '_id') {
                    $element->value = (string) $this->_id;
                } else {
                    // Los campos deshabilitados no se aceptan desde el navegador.
                    if (!$element->backreadonly && !$element->disabled) {
                        $results['addata'][] = str_replace('$', $this->position, $this->fieldprefix . $element->field);
                        // Una casilla sin marcar no viene en el POST.
                        $posted = $_POST[$this->nameprefix][$element->field] ?? null;
                        if ($posted == '') {
                            $changes['$unset'][str_replace('$', $this->position, $this->fieldprefix . $element->field)] = 1;
                        } else {
                            $changes['$set'][str_replace('$', $this->position, $this->fieldprefix . $element->field)] =
                                $element->__toMongo($posted);
                            $results['addata'][] = str_replace('$', $this->position, $this->fieldprefix . $element->field);
                        }
                        if (strpos($element->field, '.') !== false) {
                            $punto = true;
                        } else {
                            $this->info[$element->field] = $element->__toMongo($posted);
                        }
                    }
                }
            }
            if ($this->historic) {
                $this->createversion();
                $toset['nfversions'] = $this->info['nfversions'];
            }
            if ($punto) {
                //	echo '<textarea>'.print_r($changes,true).'</textarea>';
                if ($this->historic) {
                    $changes['$set']['nfversions'] = $this->info['nfversions'];
                }
                $this->collection->updateOne(['_id' => $this->_id], $changes, ['upsert' => true] + $options);
            } else {
                $options['upsert'] = true;
                $this->collection->updateOne(['_id' => $this->_id], ['$set' => $this->info], $options);
            }

            return false;
        } else {
            // $errores;
            return $errores;
        }
    }
}
/*
class datasetArray
{
    private $info;
    public $elements;
    public $nameprefix;
    public $dataset;
    public $name;
    public $field;

    public function addElement(&$element)
    {
        $this->elements[] = $element;
    }

    public function __construct($options)
    {
        $ovars = array_keys(get_object_vars($this));
        foreach ($options as $option => $value) {
            if ($option == 'value') {
                $this->value = $value;
            } elseif ($option == 'dataset') {
                $this->dataset = $value;
                $value->addElement($this);
            } elseif (in_array($option, $ovars)) {
                $this->{$option} = $value;
            } else {
                $this->tags[$option] = $value;
            }
        }
        if ($this->dataset != '') {
            if ($this->name == '' && $this->field != '') {
                $this->name = $this->field;
            }
            $this->name = $this->dataset->nameprefix.'['.$this->name.']';
            $this->nameprefix = $this->name;
            $this->value = $this->dataset->{$this->field};
        }
    }

    public function save()
    {
        $this->dataset->{$this->field} = $this->info;
    }

    public function is_valid($value)
    {
        return true; // TODO check
    }
}*/
/**
 * Icono: nombre de Metro ('home' → mif-home) o URL de imagen si contiene un punto.
 */
class Icon
{
    /** @var string */
    public $src;

    /**
     * @param string $src
     */
    public function __construct($src)
    {
        $this->src = $src;
    }

    /**
     * @return string HTML.
     */
    public function __toString()
    {
        return strpos($this->src, '.') === false ?
            '<span class="icon mif-' . $this->src . '"></span> ' :
            '<img src="' . $this->src . '" class="icon">';
    }
}

/**
 * Nodo de TreeView.
 */
class TreeViewItem
{
    /** @var list<TreeViewItem>|null */
    public $children;
    /** @var Icon */
    public $icon;
    /** @var string */
    public $caption;
    /** @var string|null Atributos extra del <li>. */
    public $addnodetag;
    /**
     * @param string $caption
     * @param Icon $icon
     * @param array<string, mixed> $options children, addnodetag.
     */
    public function __construct($caption, $icon, $options = [])
    {
        $this->caption = $caption;
        $this->icon = $icon;
        foreach ($options as $option => $valor) {
            $this->{$option} = $valor;
        }
    }

    /**
     * @return string HTML.
     */
    public function __toString()
    {
        $tmp = (string) $this->icon . $this->caption;
        if (count($this->children) > 0) {
            $tmp .= '<ul>' . implode('', $this->children) . '</ul>';
        }
        return '<li class="item" ' . $this->addnodetag . ' data-icon="' . $this->icon->data() . '" data-caption="' . $this->caption . '">' . $tmp . '</li>';
    }
}
/**
 * Árbol de Metro UI (data-role treeview) con nodos TreeViewItem.
 */
class TreeView
{
    /** @var list<TreeViewItem> */
    public $children;
    /**
     * @return string HTML.
     */
    public function __toString()
    {
        return '<ul data-role="treeview"
			     id="tree_add_leaf_example">' . implode('', $this->children) . '</ul>';
    }
}
