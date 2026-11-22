<?php

/**
 * Lienzo para firmar (signature_pad). El botón Guardar envía el PNG a /nframework/imageup.php, que lo
 * escribe en `path` y responde en JSON.
 *
 * Eventos en el navegador (sobre el contenedor #<id>_signature; burbujean hasta document):
 *   signature:beforesave  antes de enviar; event.preventDefault() lo cancela
 *   signature:saved       guardada; event.detail = respuesta del servidor ({error, onresult, ...})
 *   signature:error       falló; event.detail.message
 *   signature:clear       se limpió el lienzo
 * En el servidor: `onsave` (función de `extension`) se llama con ($path, $conf) después de guardar.
 * Con `dataset` + `field` se registra la firma en el documento, y con `submit` se envía el formulario que
 * contiene al componente: el formulario ya no necesita su propio botón de guardar.
 */
class Signature_pad extends baseInput
{
  /** @var float */
  public $minWidth = 0.5; // Grosor mínimo del trazo
  /** @var float */
  public $maxWidth = 2.5; // Grosor máximo del trazo
  /** @var int */
  public $throttle = 16; // ms entre puntos
  /** @var int */
  public $minDistance = 5; // Distancia mínima entre puntos
  /** @var string */
  public $backgroundColor = 'rgba(0,0,0,0)'; // Fondo del lienzo
  /** @var string */
  public $penColor = 'black'; // Color del trazo
  /** @var float */
  public $velocityFilterWeight = 0.7;
  /** @var string */
  public $canvasContextOptions = '';
  /** @var string|null */
  public $path; // Ruta del PNG en el servidor (solo en la sesión)
  /** @var string */
  public $onSuccess = ''; // JS al guardar (variable `result` con la respuesta)
  /** @var string */
  public $onError = ''; // JS si falla (variable `message`)
  /** @var string */
  public $onEmptyAlert = ''; // JS al intentar guardar sin firma
  /** @var string|null */
  public $extension; // Archivo PHP con la función onsave
  /** @var string|null */
  public $onsave; // Nombre de la función: onsave(string $path, array $conf): mixed
  /** @var string|null */
  public $submit; // op con que se envía el formulario contenedor después de guardar la firma
  /** @var int */
  public $width = 400;
  /** @var int */
  public $height = 200;

  /**
   * @param array<string, mixed> $options Propiedades públicas (path, width, height, penColor, extension, onsave, submit...) y las de baseInput (id, name, caption, dataset, field...).
   */
  public function __construct($options = [])
  {
    parent::__construct($options);
    // dataset->save() no debe tocar este campo: el valor lo escribe imageup.php al guardar la firma.
    $this->backreadonly = true;
  }

  /**
   * La firma se guarda por su propio endpoint: el valor del campo siempre es válido.
   *
   * @param mixed $newval
   * @return bool
   */
  public function is_valid($newval)
  {
    return true;
  }

  /**
   * Configuración para /nframework/imageup.php (el navegador no la ve).
   *
   * @return array<string, mixed>
   */
  public function sessionConfig(): array
  {
    $conf = [
      'path' => $this->path,
      'extension' => $this->extension,
      'onsave' => $this->onsave,
    ];
    if (is_object($this->dataset) && $this->field !== '' && $this->dataset->collection instanceof MongoDB\Collection) {
      $conf['dataset'] = [
        'db' => $this->dataset->collection->getDatabaseName(),
        'collection' => $this->dataset->collection->getCollectionName(),
        '_id' => (string) $this->dataset->_id,
        'simpleid' => (bool) $this->dataset->simpleid,
        'field' => $this->field,
      ];
    }
    return $conf;
  }

  /**
   * @global class_nframework $nframework
   * @global Javas $javas
   * @return string HTML del lienzo y sus botones.
   */
  public function __toString(): string
  {
    global $nframework, $javas;
    $_SESSION['nf5imageup'][$this->id] = $this->sessionConfig();
    addVarToGarbage('nf5imageup\\' . $this->id, time() + (60 * 60));
    if (!$nframework->onces['Signature_pad']) {
      $nframework->jss['115'] = 'https://cdn.jsdelivr.net/npm/signature_pad@5.1.0/dist/signature_pad.umd.min.js';
      $javas->addjs('var signaturePad=[];');
      $nframework->onces['Signature_pad'] = true;
    }

    $data = [];
    foreach (['minWidth', 'maxWidth', 'throttle', 'minDistance', 'backgroundColor', 'penColor', 'velocityFilterWeight', 'canvasContextOptions'] as $option) {
      if (!empty($this->{$option})) {
        $data[$option] = $this->{$option};
      }
    }

    $lng = $nframework->language;
    $onError = $this->onError ?: 'Swal.fire({icon: "error", text: message});';
    $onSuccess = $this->onSuccess ?: ($this->submit ? '' : 'toast(' . json_encode($lng['upload_success']) . ');');
    $onEmptyAlert = $this->onEmptyAlert ?: 'Swal.fire({icon: "info", text: ' . json_encode($lng['signature_empty']) . '});';
    $id = json_encode($this->id);
    $options = json_encode($data);
    $submit = json_encode((string) $this->submit);
    $uploadError = json_encode($lng['upload_error']);

    // Una sola vez: crear el lienzo y los botones. En 'resize' solo se ajusta el tamaño (antes se volvían
    // a registrar los clics en cada resize y una firma se subía varias veces).
    $javas->addjs(<<<JS
(function(){
  var id = {$id}, canvas = document.getElementById("canvaspad_" + id), box = document.getElementById(id + "_signature");
  var pad = signaturePad[id] = new SignaturePad(canvas, {$options});
  function emit(name, detail, cancelable) {
    var ev = new CustomEvent("signature:" + name, {bubbles: true, cancelable: !!cancelable, detail: detail || {}});
    return box.dispatchEvent(ev);
  }
  // isEmpty() de signature_pad queda en true tras fromData() (lo usa resize): se cuentan los trazos.
  function empty() { return pad.toData().length === 0; }
  function resize() {
    var ratio = Math.max(window.devicePixelRatio || 1, 1), data = pad.toData();
    canvas.width = canvas.offsetWidth * ratio;
    canvas.height = canvas.offsetHeight * ratio;
    canvas.getContext("2d").scale(ratio, ratio);
    pad.clear();
    pad.fromData(data);   // conserva lo firmado al cambiar el tamaño de la ventana
  }
  resize();
  window.addEventListener("resize", resize);
  document.getElementById("canvaspad_" + id + "_clear").addEventListener("click", function () {
    pad.clear();
    emit("clear");
  });
  document.getElementById("canvaspad_" + id + "_save").addEventListener("click", function () {
    if (empty()) {
      {$onEmptyAlert}
      return;
    }
    if (!emit("beforesave", {pad: pad}, true)) {
      return;
    }
    var button = this;
    button.classList.add("disabled");
    fetch("/nframework/imageup.php?id=" + encodeURIComponent(id), {
      method: "POST",
      credentials: "same-origin",
      headers: {"Content-Type": "application/x-www-form-urlencoded", "X-Requested-With": "XMLHttpRequest"},
      body: "image=" + encodeURIComponent(pad.toDataURL("image/png"))
    })
    .then(function (response) { return response.json(); })
    .then(function (result) {
      if (result.error) { throw new Error(result.error); }
      emit("saved", result);
      {$onSuccess}
      var submit = {$submit}, form = box.closest("form");
      if (submit !== "" && form) {
        var op = form.querySelector("input[name=op]");
        if (op) { op.value = submit; }
        form.requestSubmit();   // mismo camino que un botón .secureop: validación de Metro y AJAX
      }
    })
    .catch(function (e) {
      var message = e && e.message && e.message !== "Failed to fetch" ? e.message : {$uploadError};
      emit("error", {message: message});
      {$onError}
    })
    .finally(function () { button.classList.remove("disabled"); });
  });
})();
JS, 'ready');

    $w = (int) $this->width;
    $h = (int) $this->height;
    return '<div id="' . htmlspecialchars($this->id) . '_signature" class="signature">'
      . ($this->caption !== '' ? '<label>' . $this->caption . '</label><br>' : '')
      . '<canvas id="canvaspad_' . htmlspecialchars($this->id) . '" class="signature-pad border bd-default" style="width:' . $w . 'px; max-width:100%; height:' . $h . 'px; touch-action:none"></canvas><br>'
      . '<button type="button" class="button" id="canvaspad_' . htmlspecialchars($this->id) . '_clear"><span class="mif-clear"></span> ' . $lng['buttons']['clear'] . '</button> '
      . '<button type="button" class="button primary" id="canvaspad_' . htmlspecialchars($this->id) . '_save"><span class="mif-sign-pen"></span> ' . $lng['buttons']['save'] . '</button>'
      . '</div>';
  }
}
