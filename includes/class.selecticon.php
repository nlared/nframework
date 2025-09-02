<?php

class inputRadios extends baseOptions
{
    public $rquired;

    public function __toString()
    {
        $contas = 0;
        foreach ($this->options as $value => $text) {
            $result .= '<input type="radio" name="'.$this->name.'" id="'.$this->id.'_'.$contas.'" value="'.$value
            .'" data-role="radio" data-caption="'.$text.'"'.
               ' labelid="'.$this->id.'" data-ovalidate="'.$this->data_validate().'"'.
               ($this->onchange ? ' data-on-change="'.$this->onchange.'"' : '').
            ($this->value == $value ? ' checked ' : ' ').'/>';
            $contas++;
        }

        return ($this->caption != '' ? '<label id="'.$this->id.'">'.$this->caption.'</label>' : '').$result;
    }
}

// ############################################ S T A R T ##################################################
// ######################## AGREGUE PARA PONER ICONOS EN LAS OPCIONES DE LOS SELECT########################
function nflistoptionsIcons($options, $selected = [])
{
    if (! is_array($selected)) {
        $selected = [0 => $selected];
    }

    $result = '';

    foreach ($options as $key => $value) {
        if (is_array($value) && isset($value['group'])) {
            // Si es un grupo de opciones
            $result .= '<optgroup label="'.htmlspecialchars($value['group']).'">';
            foreach ($value['options'] as $optionValue => $optionText) {
                if (is_array($optionText)) {
                    $result .= '<option value="'.htmlspecialchars($optionValue).'" data-template="'.htmlspecialchars($optionText['icon']).'"'.(in_array($optionValue, $selected) ? ' selected>' : '>').htmlspecialchars($optionText['datashow']).'</option>';
                } else {
                    $result .= '<option value="'.htmlspecialchars($optionValue).'"'.(in_array($optionValue, $selected) ? ' selected>' : '>').htmlspecialchars($optionText).'</option>';
                }
            }
            $result .= '</optgroup>';
        } else {
            // Si no es un grupo de opciones
            if (is_array($value)) {
                $result .= '<option value="'.htmlspecialchars($key).'" data-template="'.htmlspecialchars($value['icon']).'"'.(in_array($key, $selected) ? ' selected>' : '>').htmlspecialchars($value['datashow']).'</option>';
            } else {
                $result .= '<option value="'.htmlspecialchars($key).'"'.(in_array($key, $selected) ? ' selected>' : '>').htmlspecialchars($value).'</option>';
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
        if ($this->combobox && $this->value != '' && ! array_search($this->value, $this->options)) {
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
        $result .= nflistoptionsIcons($this->options, $this->value);

        // onfocus=\"Autoformonfocus(this)\" onblur=\"Autoformonblur(this)\">\n";
        // $_SESSION['ANTIXSS'][$this->name]=[FILTER_VALIDATE_SELE];
        return
            ($this->caption != '' ? '<label for="'.$this->id.'">'.$this->caption.'</label>' : '').
            ($this->infobox != '' ? '&nbsp;<span class="mif-question nfinfoicon fg-red" content="'.htmlentities($this->infobox, ENT_QUOTES).'"></span>' : '')
            .'<select name="'.$this->name.($this->multiple ? '[]" multiple="multiple"' : '"').
            ' id="'.$this->id.'"'.$this->writetags().
            ' data-role="'.$this->role.'"'.
            ($this->canadd ? ' canadd="canadd"' : '').
            ($this->disabled ? ' disabled="disabled"' : '').
            ($this->required ? ' required="required"' : '').
            (! $this->datafilter ? ' data-filter="false"' : '').
            ($this->placeholder ? ' data-filter-placeholder="'.$this->placeholder.'"' : '').
                        ($this->validate ? 'data-validate="'.$this->validate.'"' : '').
            ($this->multiple ? ' multiple' : '').
            ($this->addclass ? ' class="'.$this->addclass.'"' : '').'>'.$result.'</select>'.
            ($this->invalid_feedback != '' ? '<span class="invalid_feedback">'.$this->invalid_feedback.'</span>' : '');

    }

    public function is_valid($newval)
    {
        // falta validar array
        // return($this->multiple ? true : filter_var($newval));
        // TODO: PROBAR return !is_array($newval);
        return true;
    }
}
