<?php

class inputSpinner extends baseInput
{
    public $addclass;

    public function __toMongo($val)
    {
        return $this->data_validate == 'integer' || $this->data_validate == 'digits' ? (int) $val : (float) $val;
    }

    public function __toString()
    {
        if ($this->validate == '') {
            $this->validate = 'number'; // integer,float
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
                FILTER_VALIDATE_INT,
            ];
        }

        return ($this->caption != '' ? '<label for="'.$this->id.'">'.$this->caption.'</label>' : '').
        '<input type="text" data-role="spinner" id="'.$this->id.
            '" name="'.$this->name.
            '"'.$this->writetags().
            ' data-validate="'.$this->data_validate().'" value="'.$this->value.'"'.
            ($this->datasize ? ' data-size="'.$this->datasize.'"' : '').
            ($this->required ? ' required="required"' : '').
            ($this->readonly ? ' readonly="readonly"' : '').
            ($this->disabled ? ' disabled' : '').
            ($this->addclass ? ' class="'.$this->addclass.'"' : '').
            ' autocomplete="off">';
    }

    public function is_valid($newval)
    {
        return is_numeric($newval);
    }
}
