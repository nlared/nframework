<?php

class Dialog
{
    public $title;
    public $onShow;
    public $onAcept;
    public $onClose;
    public $id;
    public $content;

    public function __construct($options = [])
    {
        global $nframework;
        foreach ($options as $option => $value) {
            $this->{$option} = $value;
        }        
        if (empty($this->id)) {
            $this->id = 'dialogs_' . $nframework->counters('dialogs');
        }
    }

    public function __toString()
    {
        global $javas;
$java = <<<JAVA
	const {$this->id}= document.querySelector("#{$this->id}");
	$( "#{$this->id}_btnClose" ).on( "click", function() {
		
		{$this->id}.close();
	});	
JAVA;

        $javas->addjs($java);
$html = <<<HTML
<dialog id="{$this->id}" class="p-5" style="position: absolute; float: left; left: 50%; top: 50%; transform: translate(-50%, -50%);">
    <form id="{$this->id}_form">
        <input type="hidden" name="op" id="{$this->id}_op" value="agregar">
        <input type="hidden" name="pos" id="{$this->id}_pos" value="">
        <h2>{$this->title}</h2>
        <div class="grid">
            {$this->content}
            <div class="row">
                <div class="cell-6">
                    <div id="{$this->id}_btnClose" class="button primary btn btn-primary w-100"><span class="mif-cross"></span>&nbsp;Cerrar</div>
                </div>
                <div class="cell-6">
                    <div class="button primary btn btn-primary w-100" id="{$this->id}_btnAcept"><span class="mif-checkmark"></span>&nbsp;Aceptar</div>
                </div>
            </div>
        </div>
    </form>
</dialog>
HTML;
        return $html;
    }
}
