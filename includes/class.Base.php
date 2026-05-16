<?php

function booltotag($tag, $val)
{
    return ' ' . $tag . '="' . ($val ? 'true' : 'false') . '"';
}
function strtotag($tag, $val)
{
    return !empty($val) ? ' ' . $tag . '="' . $val . '"' : '';
}
function icontotag($tag, $val)
{
    return $tag != '' ? ' ' . $tag . '="' . str_replace('"', '\'', $val) . '"' : '';
}

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

class Base
{
    public $tags;

    public function __construct($options = [])
    {
        $this->tags = [];
        foreach ($options as $option => $value) {
            $this->{$option} = $value;
        }
    }
}
class baseInput
{
    public $required;
    public $addclass;
    public $class;
    public $type;
    public $role;
    public $infobox;
    public $id;
    public $name;
    public $nameprefix;
    public $dataset;
    public $nfembeded;
    public $field;
    public $disabled;
    public $placeholder;
    public $caption;
    public $prependicon;
    public $readonly;
    public $default;
    public $validate;
    public $prepend;
    public $append;
    public $prepend_options;
    public $append_options;
    public $autocomplete;
    public $title;
    public $pattern;
    public $onChange;
    public $tags;
    public $value;
    public $datasize;
    public $backreadonly = false;
    public function __toMongo($val)
    {
        return $val;
    }

    public function __lset($option, $value)
    {
        $ovars = array_keys(get_object_vars($this));
        if ($option == 'value') {
            $this->value = $value;
        } elseif (in_array($option, $ovars)) {
            $this->{$option} = $value;
        } else {
            $this->tags[$option] = $value;
        }
        echo "$option,$value<br>";
    }

    public function __get($name)
    {
        switch ($name) {
            case 'value':
                if (isset($this->dataset)) {
                    return $this->dataset->{$this->field};
                } else {
                    return $this->value;
                }
        }
    }

    public function __isset($option)
    {
        $ovars = array_keys(get_object_vars($this));
        if (in_array($option, $ovars)) {
            return isset($this->{$option});
        } else {
            return isset($this->tags[$option]);
        }
    }

    public function __construct($options = [])
    {
        $this->tags = [];
        $ovars = array_keys(get_object_vars($this));
        if (!isset($options['class'])) {
            $options['class'] = 'inputText';
        }
        foreach ($options as $option => $value) {
            if ($option == 'value') {
                $this->value = $value;
            } elseif ($option == 'dataset') {
                $value->addElement($this);
                $this->dataset = $value;
            } elseif ($option == 'nfembeded') {
                $value->addElement($this);
                $this->nfembeded = $value;
            } elseif (in_array($option, $ovars)) {
                $this->{$option} = $value;
            } else {
                $this->tags[$option] = $value;
            }
        }

        if (empty($this->value) && !empty($this->default)) {
            $this->value = $this->default;
        }

        if (!empty($this->nfembeded)) {
            if ($this->name == '' & $this->field != '') {
                $this->name = $this->field;
            }
            $this->name = $this->nfembeded->nameprefix . '[' . $this->name . ']';
        }

        if (!empty($this->dataset)) {
            if ($this->name == '' & $this->field != '') {
                $this->name = $this->field;
            }
            $this->name = $this->dataset->nameprefix . '[' . $this->name . ']';
            if (strpos($this->field, '.') !== false) {
                $data = $this->dataset->info;
                $keys = explode('.', str_replace(['$', '[', ']'], [$this->dataset->position, '.', ''], $this->field));
                // unset($data['nfversions']);
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
                        $current = $this->default; // Retorna null si la clave no existe
                    }
                }

                $this->value = $current;
            } else {

                if (!isset($this->dataset->{$this->field}) && isset($this->default)) {
                    $this->value = $this->default;
                } else {
                    $this->value = $this->dataset->{$this->field};
                }
            }
        } else {
            if (!isset($this->value) && isset($this->default)) {
                $this->value = $this->default;
            }
        }
        if ($this->id == '') {
            $this->id = str_replace(['[', ']', '.'], ['_', '', '_'], $this->name);
        }
        if ($this->id == '') {
            $this->id = str_replace(['[', ']', '.'], ['_', '', '_'], $this->field);
        }
    }

    public function is_valid($newval)
    {
        return $this->pattern != '' ? filter_var($newval, FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '/' . $this->pattern . '/']]) : true;
    }

    protected function writetags(): string
    {
        $result = '';
        foreach ($this->tags as $name => $value) {
            $result .= ' ' . $name . '="' . $value . '"';
        }

        return $result;
    }

    public function data_validate()
    {
        if (!empty($this->validate)) {
            $rules = explode(' ', $this->validate);
        } else {
            $rules = [];
        }
        if ($this->required && !in_array('required', $rules)) {
            $rules[] = 'required';
        }

        if ($this->pattern) {
            foreach ($rules as $rule) {
                if (substr($rule, 0, 7) == 'pattern') {
                    $encontrado = true;
                }
            }
            if (!$encontrado) {
                $rules[] = 'pattern=(' . $this->pattern . ')';
            }
        }
        $rulesstr = trim(implode(' ', $rules));

        return !empty($rulesstr) ? ' data-validate="' . $rulesstr . '"' : '';
    }

    public function inputtags()
    {
        return ($this->caption ? ' data-label="' . $this->caption . '"' : '') .
            ($this->addclass ? ' class="' . $this->addclass . '"' : '') .
            ($this->required ? ' required="required"' : '') .
            ($this->pattern ? ' data-mask-pattern="' . $this->pattern . '"' : '') .
            ($this->readonly ? ' readonly="readonly"' : '') .
            ($this->disabled ? ' disabled' : '') .
            ($this->prepend ? ' data-prepend="' . $this->prepend . '"' : '') .
            ($this->append ? ' data-append="' . $this->append . '"' : '') .
            ($this->prepend_options ? ' data-prepend-options="' . $this->prepend_options . '"' : '') .
            ($this->append_options ? ' data-append-options="' . $this->append_options . '"' : '') .
            ($this->invalid_feedback != '' ? '<span class="invalid_feedback">' . $this->invalid_feedback . '</span>' : '') .
            ($this->autocomplete ? '" autocomplete="' . $this->autocomplete . '"' : '') .
            ($this->datasize ? ' data-size="' . $this->datasize . '"' : '') .
            ($this->placeholder ? ' placeholder="' . $this->placeholder . '"' : '') .
            $this->data_validate();
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
        return $this->pattern ? ' data-mask-pattern="' . $this->pattern . '"' : '';
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
    public function render(): string
    {
        return $this->__toString();
    }
    public function getValue()
    {
        return $this->value;
    }
}
class baseOptions extends baseInput
{
    public $options = [];
}
class label extends baseInput
{
    public function __toString()
    {
        if ($this->value === null) {
            if (isset($this->default)) {
                $this->value = $this->default;
            } else {
                $this->value = '';
            }
        }
        return '<label' . $this->writetags() . ' id="' . $this->id . '"' . '>' . htmlspecialchars($this->value) . '</label>';
    }
}
class inputHidden extends baseInput
{
    public function __construct($options = [])
    {
        parent::__construct($options);
    }

    public function __toString()
    {
        return '<input type="hidden"' . ' id="' . $this->id . '" name="' . $this->name . '"' . $this->writetags() .
            ' value="' . htmlspecialchars($this->value) . '">';
    }
}

class inputText extends baseInput
{
    public $value;
    public $search;
    public $mask;
    public $mask_pattern;
    public $pattern;
    public $inputtype;
    public $addclass = 'form-control';
    public $type;
    public $uppercase;
    public $lowercase;
    public $autotrim;
    public $autocomplete = 'off';

    public function __construct($options = [])
    {
        $options['class'] = 'inputText';
        $options['type'] ??= 'text';

        parent::__construct($options);

        // Set validation based on type
        if ($this->type === 'email') {
            $this->validate .= ' email';
        } elseif (empty($this->pattern)) {
            $this->validate = ' text';
        } else {
            $this->validate .= ' pattern=(' . $this->pattern . ')';
        }
    }

    public function __toString(): string
    {
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

    public function is_valid($newval): bool
    {
        if (empty($this->pattern)) {
            return is_string($newval);
        }

        return (bool) preg_match('/' . $this->pattern . '/', $newval);
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

class inputNumber extends baseInput
{
    public $max;
    public $min;
    public $step;
    public $data_validate; // integer,digits,float

    public function __construct($options = [])
    {
        $options['class'] = 'inputNumber';
        if (!isset($options['type'])) {
            $options['type'] = 'number';
        }
        parent::__construct($options);
    }

    public function __toMongo($val)
    {
        return $this->data_validate == 'integer' || $this->data_validate == 'digits' ? (int) $val : (float) $val;
    }

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

    public function is_valid($newval)
    {
        if ($this->validate == 'float') {
            return filter_var($newval, FILTER_VALIDATE_FLOAT, FILTER_FLAG_ALLOW_THOUSAND);
        } else {
            return filter_var($newval, FILTER_VALIDATE_INT);
        }
    }
}

class inputSpinner extends baseInput
{
    public $addclass;
    public function __toMongo($val)
    {
        return ($this->data_validate == 'integer' || $this->data_validate == 'digits' ? (int) $val : (float) $val);
    }

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
            ' data-validate="' . $this->data_validate() . '" value="' . $this->value . '"' .
            ($this->datasize ? ' data-size="' . $this->datasize . '"' : '') .
            ($this->required ? ' required="required"' : '') .
            ($this->readonly ? ' readonly="readonly"' : '') .
            ($this->disabled ? ' disabled' : '') .
            ($this->addclass ? ' class="' . $this->addclass . '"' : '') .
            ' autocomplete="off">';
    }
    public function is_valid($newval)
    {
        return is_numeric($newval);
    }
}

class inputRating extends baseInput
{
    public $tags = ['data-values', 'onchange'];
    public $onchange;
    public function __toString()
    {
        return ($this->caption != '' ? '<label for="' . $this->id . '">' . $this->caption . '</label>' : '') .
            '<input data-role="rating"  id="' . $this->id .
            '" name="' . $this->name .
            '"' . $this->writetags() . ' data-value="' . $this->value . '">';
    }
}

class inputColor extends baseInput
{
    public $tags = ['data-values', 'onchange'];

    public $onchange;

    public function __toString()
    {
        return '<input type="color" data-role="input" id="' . $this->id .
            '" name="' . $this->name .
            '"' . $this->inputtags() . ' value="' . $this->value . '">';
    }
} // */

class inputDate extends baseInput
{
    private $format = 'Y-m-d';
    public $timezone;
    public $storagetype = self::ST_MONGODATE;
    const ST_MONGODATE = 'st_mongodate';
    const ST_STRING = 'st_string';
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

    public function is_valid($date)
    {
        $d = DateTime::createFromFormat($this->format, $date);
        return (empty($date) && !$this->required) || ($d && $d->format($this->format) == $date);
    }

    public function __toMongo($val)
    {
        if ($this->timezone == null) {
            $this->timezone = new DateTimeZone('UTC');
        }

        if (!empty($val)) {
            if ($this->storagetype == self::ST_STRING) {
                return $val;
            } else {
                $orig_date = DateTime::createFromFormat($this->format, $val, $this->timezone);
                $orig_date = $orig_date->getTimestamp();
                $utcdatetime = new MongoDB\BSON\UTCDateTime($orig_date * 1000);
                return $utcdatetime;
            }
        } else {
            return null;
        }
    }

    public function __toPHP($val)
    {
        if ($this->storagetype == self::ST_STRING) {
            return $val;
        } elseif ($this->storagetype == self::ST_MONGODATE) {
            if ($val instanceof MongoDB\BSON\UTCDateTime) {
                $datetime = $val->toDateTime();
                $date = $datetime->format($this->format);
            } else {
                $date = $val;
            }
            return $date;
        }
    }
}
class inputTime extends baseInput
{
    public $type;

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
    public function __toMongo($val)
    {
        if (!empty($val)) {
            $orig_date = DateTime::createFromFormat('H:i', $val, new DateTimeZone('UTC'));
            $orig_date = $orig_date->getTimestamp();
            $utcdatetime = new MongoDB\BSON\UTCDateTime($orig_date * 1000);
        }

        return $utcdatetime;
    }
    public function is_valid($date)
    {
        //todo: implement validation logic
        return preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $date);
    }
}
class inputDateTime extends baseInput
{
    public $timezone = null;
    public const ST_MONGODATE = 'st_mongodate';
    public const ST_STRING = 'st_string';
    public $storagetype = self::ST_MONGODATE;

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
            . ' data-validate="' . $this->data_validate() . '"' .
            $this->addtags . ' value="' . $this->__toPHP($this->value)
            . '" data-clear-button="false"  autocomplete="off"/>';
    }

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
                $orig_date = $orig_date->getTimestamp();
                $utcdatetime = new MongoDB\BSON\UTCDateTime($orig_date * 1000);
                return $utcdatetime;
            }
        } else {
            return null;
        }
    }

    public function __toPHP($val)
    {
        if ($this->storagetype == self::ST_STRING) {
            return $val;
        } elseif ($this->storagetype == self::ST_MONGODATE) {
            if ($val instanceof MongoDB\BSON\UTCDateTime) {
                $datetime = $val->toDateTime();
                $date = $datetime->format('Y-m-d\TH:i');
            } else {
                $date = $val;
            }
            return $date;
        }
    }
}

class inputMCE extends baseInput
{
    public $upload = false;
    public $mediadir;
    public $baseurl;
    public $id;
    public $extended_valid_elements;
    public $content_css;
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
class textArea extends baseInput
{
    public $uppercase;
    public $charscounter;
    public $spellcheck = true;
    public $charscountertemplate;
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
class AutoformList extends baseInput
{
    public $options = [];
}
class inputCheckBox extends baseOptions
{
    public $storeagetype = self::ST_MONGOBOOLEAN;
    const ST_MONGOBOOLEAN = 'st_mongoboolean';
    const ST_INTEGER = 'st_integer';
    const ST_CUSTOM = 'st_custom';
    const ST_STRING = 'st_string';
    public $customtrue;
    public $customfalse;
    public function __toString()
    {
        return '<input type="checkbox" name="' . $this->name . '" id="' . $this->id . '" value="1"' .
            ' data-role="checkbox" data-caption="' . $this->caption . '"' .
            ' labelid="' . $this->id . '"' .
            ' data-ovalidate="' . $this->data_validate() . '"' .
            ($this->onchange ? ' data-on-change="' . $this->onchange . '"' : '') .
            ($this->value ? ' checked ' : ' ') . '/>';
    }
    public function is_valid($newval)
    {
        return filter_var($newval, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) !== null;
    }
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


class inputRadios extends baseOptions
{
    public $rquired;

    public function __toString()
    {
        $contas = 0;
        $result = '';
        foreach ($this->options as $value => $text) {
            $result .= '<input type="radio" name="' . $this->name . '" id="' . $this->id . '_' . $contas . '" value="' . $value
                . '" data-role="radio" data-caption="' . $text . '"' .
                ' labelid="' . $this->id . '" data-ovalidate="' . $this->data_validate() . '"' .
                ($this->onchange ? ' data-on-change="' . $this->onchange . '"' : '') .
                ($this->value == $value ? ' checked ' : ' ') . '/>'; //&nbsp;<label for="'.$this->id.'_'.$contas.'">'.$text.'</label>';
            $contas++;
        }

        return ($this->caption != '' ? '<label id="' . $this->id . '">' . $this->caption . '</label>' : '') . $result;
    }
}

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

class SelectAjaxOptions
{
    public $db;
    public $collection;
    public $pipeline = [];
    public $columns = [];
    public $fields = [];
    public $label;
    public $value;
}
class Select extends baseOptions
{
    public $combobox;
    public $multiple;
    public $invalid_feedback;
    public $options = [];
    public $canadd;
    public $role;
    public $datafilter = true;
    public $format; // mongoid    

    public $ajax;

    public function __construct($options = [])
    {
        $options['class'] = 'select';
        parent::__construct($options);
    }

    public function __toPhp($val)
    {
        if ($this->multiple) {
            return (array) $val;
        } else {
            if ($this->format == DataformatSelectType::MongoID) {
                return (string) $val;
            }
            return $val;
        }
    }
    public function __toMongo($val)
    {
        if ($this->multiple) {
            return (array) $val;
        } else {
            if ($this->format == DataformatSelectType::MongoID) {
                return  new MongoDB\BSON\ObjectID($val);
            }
            return $val;
        }
    }
    public function __toString(): string
    {
        global $nframework, $javas;
        $result = '';
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
        $result .= nflistoptions($this->options, $this->value);

        // onfocus=\"Autoformonfocus(this)\" onblur=\"Autoformonblur(this)\">\n";
        // $_SESSION['ANTIXSS'][$this->name]=[FILTER_VALIDATE_SELE];


        if ($this->ajax) {

            $nframework->csss[70] = "https://cdn.jsdelivr.net/npm/tom-select/dist/css/tom-select.css";
            $nframework->jss[70] = "https://cdn.jsdelivr.net/npm/tom-select/dist/js/tom-select.complete.min.js";

            $_SESSION['selectajax'][$this->id] = $this->ajax;
            addVarToGarbage('selectajax\\' . $this->id, time() + (60 * 60));



            $javas->addjs(
                <<<js
	tomselects['{$this->id}'] = new TomSelect('#{$this->id}',{
		valueField:'value',
		labelField:'label',
		searchField:'label',
		load:function(query,callback){
			if(!query.length)return callback();
			fetch('/nframework/select_ajax.php?id={$this->id}&q='+encodeURIComponent(query))
			.then(res=>res.json())
			.then(json=>{
				callback(json);
			}).catch(()=>{
				callback();
			});
		}
	});
js,
                'ready'
            );


            if (!empty($this->value)) {
                $javas->addjs(
                    <<<js
fetch('/nframework/select_ajax.php?id={$this->id}&qid='+encodeURIComponent('{$this->value}'))
    .then(res => res.json())
    .then(items => {
        items.forEach(item => {
            tomselects['{$this->id}'].addOption(item);
            tomselects['{$this->id}'].addItem(item.value);
        });
    });
js,
                    'ready'
                );
            }
        }

        return
            '<select name="' . $this->name . ($this->multiple ? '[]" multiple="multiple"' : '"') .
            ' id="' . $this->id . '"' . ' data-role="' . $this->role . '" ' .
            $this->inputtags() .
            ($this->prepend ? ' data-prepend="' . $this->prepend . '"' : '') .
            ($this->canadd ? ' canadd="canadd"' : '') .
            ($this->onChange ? ' onChange="' . $this->onChange . '"' : '') .
            (!$this->datafilter ? ' data-filter="false"' : '') .
            ' data-filter="true" data-filter-placeholder="' . $this->placeholder . '">' . $result . '</select>';
    }

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

class SelectIcon extends baseOptions
{
    public $combobox;
    public $multiple;
    public $options = [];
    public $canadd;
    public $datafilter = true;
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

    public function is_valid($newval)
    {
        // falta validar array
        // return($this->multiple ? true : filter_var($newval));
        // TODO: PROBAR return !is_array($newval);
        return true;
    }

    public function __toMongo($val)
    {
        return $val == 'on' ? true : false;
    }
    public function __toPHP($val)
    {
        return $val ? 'on' : '';
    }
}
class inputCheckBoxs extends Select
{
    public $captionposition = 'right';
    public $horizontal = false;
    public $nometro = false;
    public $type;
    public function __toString()
    {
        $result = '';
        $tempcheck = $this->value;
        if ($this->type == '') {
            $this->type = 'checkbox';
        }
        foreach ($this->options as $value => $text) {
            $result .= ($this->horizontal ? '<br>' : '') . '<input type="checkbox" data-role="checkbox" id="' . $this->id . '_' . $value . '" name="' .
                $this->name . "[$value]\"" .
                ($tempcheck[$value] === true ? ' checked' : '') .
                " data-caption=\"$text\" data-caption-position=\"" . $this->captionposition . '">' .
                ($this->nometro ? '<label for="' . $this->id . '_' . $value . '">' . $text . '</label>' : '');
            // $fields.=str_replace('%field%', $result, $this->format['fields'][2]);
            $_SESSION['ANTIXSS'][$this->id . '_' . $value] = [FILTER_VALIDATE_BOOLEAN];
        }

        // $result = str_replace('%fields%', $fields, $this->format['fields'][1]);
        return ($this->caption != '' ? '<label for="' . $this->id . '">' . $this->caption . '</label>' : '') . $result;
    }

    public function __toMongo($vals)
    {
        foreach ($vals as $name => $val) {
            $tomongo[$name] = ($val == 'on' ? true : false);
        }

        return $tomongo;
    }
}

abstract class BaseFileInput extends baseInput
{
    public $dir;
    public $download = true;
    public $preview = true;
    public $delete = true;
    public $disabled;
    public $create_dir;
    public $accept;
    public $capture;
    public $mode = 'input'; // input,drop,button
    public $limit_time_start = '';
    public $limit_time_end = '';
    public $extension;
    public $extensioninfo = [];
    public $onupload;
    public $ondelete;
    public $oncountcheck;
    public $onlist;
    public $dropIcon = '<span class=\'mif-cloud-upload\'></span>';
    public $clearButtonIcon = '<span class=\'mif-cross\'></span>';
    public $caption;


    protected function initializeFileUpload(): void
    {
        global $nframework;

        if (!isset($_SESSION['uploads4'])) {
            $_SESSION['uploads4'] = [];
        }

        $nframework->addjqueryui();
        $nframework->addfileupload();
    }

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
            'onlist' => $this->onlist,
            'oncountcheck' => $this->oncountcheck,
            'limit_time_start' => $this->limit_time_start ?: time(),
            'limit_time_end' => $this->limit_time_end ?: strtotime('+30 minutes'),
        ];
    }

    protected function addBaseJavaScript(): void
    {
        global $javas;

        $javas->addjs(<<<JS
            $.ajax({
                url: '/nframework/uploadfile.php',
                method: "POST",
                data: "mid={$this->id}",
                dataType: 'json',
                success: function(data) {
                    nffileupload_{$this->id}(data);
                }
            });
            
            $("#{$this->id}_progress").hide();
            $("#{$this->id}").fileupload({
                url: '/nframework/uploadfile.php',
                dataType: "json",
                done: function (e, data) {
                    nffileupload_{$this->id}(data.result);
                },
                progressall: function (e, data) {
                    var progress = parseInt(data.loaded / data.total * 100, 10);
                    var pg = $("#{$this->id}_progress");
                    if (progress === 100 || progress === 0) {
                        pg.hide();
                    } else {
                        pg.show();
                        pg.attr("data-value", progress);
                    }
                }
            }).bind("fileuploadcompleted", function(e, data) {
                console.log("eventFinished");
            }).prop("disabled", !$.support.fileInput)
              .parent().addClass($.support.fileInput ? undefined : "disabled");           
            
              
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

class inputFile extends BaseFileInput
{
    public $path;
    public $onDone;
    public function __toString()
    {
        global $javas, $nframework;
        $lng = $nframework->language;
        $this->initializeFileUpload();

        // Prepare session data with path-specific config
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

class inputFiles extends BaseFileInput
{
    public $sizelimit = 0;
    public $countlimit;
    // Use nowdoc for better performance and readability
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

    public function __toString()
    {
        global $javas, $nframework;
        $lng = $nframework->language;
        $this->initializeFileUpload();

        // Prepare session configuration
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

    private function getDropAttr(): string
    {
        return $this->drop ? ' data-mode="drop" data-files-title="archivo(s) seleccionado(s)" data-drop-title="<strong>Selecciona archivo(s)</strong>"' : '';
    }
}


class mapmarker extends baseInput
{
    public const GeoJSON = 0x02;

    //	public $latitude;
    //	public $longitude;
    public $onchange;
    public $type;
    public $height = 500;
    public $value;
    public $hiddedata;
    public $startpoint = [
        'lng' => -100.96047970,
        'lat' => 25.43328030,

    ];

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

    public function __toMongo($val)
    {
        return $this->type == self::GeoJSON ? ['type' => 'Point', 'coordinates' => [floatval($val['lng']), floatval($val['lat'])]] : $val;
    }
}

class example extends Base
{
    public $content;

    public $title;

    public function __toString()
    {
        return '<div class="example" data-text="' . $this->title . '">' . $this->content . '</div>';
    }
}

class datasetpdo
{
    public $elements;
    private $collection;
    private $_id;
    public $info = [];
    public $nameprefix;
    public $simpleid;
    public $autosave;
    public $position;
    public $fieldprefix;
    private $exists = false;

    public function addElement(&$element)
    {
        $this->elements[] = $element;
    }

    public function __construct($options, $query = [])
    {
        foreach ($options as $option => $value) {
            $this->{$option} = $value;
        }
        if ($this->_id != '' && $this->key != '') {

            // $this->info =(array) $this->collection->findOne(['_id'=>$this->_id]);
            $sth = $this->pdo->query('SELECT * FROM ' . $this->table . ' WHERE ' . $this->key . '="' . $this->_id . '"');
            $this->info = $sth->fetch(PDO::FETCH_ASSOC);
            if (count($this->info) == 0) {
                $this->info = ['_id' => $this->_id];
            } else {
                $this->exists = true;
            }
        }
        unset($this->info['']);
    }


    public function save()
    {
        $errores = '';
        foreach ($this->elements as $element) {
            $element->value = $_POST[$this->nameprefix][$element->field];
            if ($element->disabled != false && !$element->is_valid($_POST[$this->nameprefix][$element->field])) {
                $errores .= 'Error en:' . $element->field . '<br/>';
            }
        }
        if (empty($errores)) {
            if (!$this->exists) {
                foreach ($this->elements as $element) {
                    $changes[$element->field] = $_POST[$this->nameprefix][$element->field];
                }
                $sql = 'INSERT INTO ' . $this->table
                    . ' (' . implode(',', array_keys($changes)) . ') values("' . implode('","', $changes) . '")';
            } else {
                foreach ($this->elements as $element) {
                    if ($element->field == $this->key) {
                        $where = ' where ' . $element->field . '="' . $this->_id . '"';
                    } else {
                        $sqls[] = $element->field . '="' . $_POST[$this->nameprefix][$element->field] . '"';
                    }
                }
                $sql = 'UPDATE ' . $this->table . ' SET ' . implode(',', $sqls) . $where;
            }
            //echo $sql;
            $this->pdo->query($sql);
        }
    }

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

    public function __isset($name)
    {
        return isset($this->info[$name]);
    }
}

#[\AllowDynamicProperties]
class dataset
{
    public $elements = [];
    public $collection;
    private $_id;
    public $info = [];
    public $nameprefix;
    public $simpleid;
    public $autosave; // pensar
    public $mongo_session;
    public $position = ''; // se va
    public $fieldprefix; // se va
    public $historic = false;
    private $nfprotected;
    public function addElement(&$element)
    {
        $this->elements[] = $element;
    }

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

    public function __isset($name)
    {
        return isset($this->info[$name]);
    }

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
    public function tonfTables($id): void
    {
        global $m, $config;
        foreach ($this->elements as $element) {
            $m->{$config['sitedb']}->nftables->updateOne(
                ['_id' => $id],
                [
                    '$addToSet' => [
                        'type' =>  get_class($element),
                        'field' => $element->field,
                    ],
                    ['upsert' => true]
                ]
            );
        }
    }
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
        foreach ($this->elements as $element) {
            if ($element->disabled != false && !$element->is_valid($_POST[$this->nameprefix][$element->field])) {
                $errores .= 'Error en:' . $element->field . '<br/>';
            } else {
                $element->value = $_POST[$this->nameprefix][$element->field];
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
                    if (!$element->backreadonly) {
                        $results['addata'][] = str_replace('$', $this->position, $this->fieldprefix . $element->field);
                        if ($_POST[$this->nameprefix][$element->field] == '') {
                            $changes['$unset'][str_replace('$', $this->position, $this->fieldprefix . $element->field)] = 1;
                        } else {
                            $changes['$set'][str_replace('$', $this->position, $this->fieldprefix . $element->field)] =
                                $element->__toMongo($_POST[$this->nameprefix][$element->field]);
                            $results['addata'][] = str_replace('$', $this->position, $this->fieldprefix . $element->field);
                        }
                        if (strpos($element->field, '.') !== false) {
                            $punto = true;
                        } else {
                            $this->info[$element->field] = $element->__toMongo($_POST[$this->nameprefix][$element->field]);
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
                $this->collection->updateOne(['_id' => $this->_id], $changes, ['upsert' => true], $options);
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
class Icon
{
    public $src;

    public function __construct($src)
    {
        $this->src = $src;
    }

    public function __toString()
    {
        return strpos($this->src, '.') === false ?
            '<span class="icon mif-' . $this->src . '"></span> ' :
            '<img src="' . $this->src . '" class="icon">';
    }
}

class TreeViewItem
{
    public $children;
    public $icon;
    public $caption;
    public $addnodetag;
    public function __construct($caption, $icon, $options = [])
    {
        $this->caption = $caption;
        $this->icon = $icon;
        foreach ($options as $option => $valor) {
            $this->{$option} = $valor;
        }
    }

    public function __toString()
    {
        $tmp = (string) $this->icon . $this->caption;
        if (count($this->children) > 0) {
            $tmp .= '<ul>' . implode('', $this->children) . '</ul>';
        }
        return '<li class="item" ' . $this->addnodetag . ' data-icon="' . $this->icon->data() . '" data-caption="' . $this->caption . '">' . $tmp . '</li>';
    }
}
class TreeView
{
    public $children;
    public function __toString()
    {
        return '<ul data-role="treeview"
			     id="tree_add_leaf_example">' . implode('', $this->children) . '</ul>';
    }
}
