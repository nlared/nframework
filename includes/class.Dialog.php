<?php

/**
 * Ventana modal (<dialog>) con un formulario, botones Cerrar y Aceptar, y campos ocultos op y pos. El contenido
 * es HTML crudo: escape lo que venga del usuario. Abrirla en el navegador:
 * document.querySelector('#id').showModal().
 *
 *   echo new Dialog(['id' => 'dlgnota', 'title' => 'Nueva nota', 'content' => $campo]);
 */
class Dialog
{
    /** @var string Título (HTML). */
    public $title;
    /** @var string|null */
    public $onShow;
    /** @var string|null */
    public $onAcept;
    /** @var string|null */
    public $onClose;
    /** @var string Id del <dialog>; por omisión dialogs_<n>. También es el nombre de la constante JS con el elemento. */
    public $id;
    /** @var string HTML dentro de <div class="grid">: filas de controles. */
    public $content;

    /**
     * @global class_nframework $nframework
     * @param array{id?: string, title?: string, content?: string, onShow?: string, onAcept?: string, onClose?: string} $options
     */
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

    /**
     * @global Javas $javas
     * @return string HTML del <dialog>; registra en $javas el botón Cerrar.
     */
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
