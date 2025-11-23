<?php

/** @package  */
class Signature_pad extends baseInput
{
  public $minWidth = 0.5; // Minimum width of the pen
  public $maxWidth = 2.5; // Maximum width of the pen
  public $throttle = 16; // Throttle time in ms
  public $minDistance = 5; // Minimum distance between points
  public $backgroundColor = 'rgba(0,0,0,0)'; // Background color of the canvas
  public $penColor = 'black'; // Color of the pen
  public $velocityFilterWeight = 0.7; // Weight for velocity filter
  public $canvasContextOptions = ''; // Additional options for canvas context
  public $name; // Name attribute for the input
  public $path; // Path to save the signature image
  public $onSuccess = ''; // JavaScript code to execute on success
  public $onError = ''; // JavaScript code to execute on error
  public $onEmptyAlert = '';// Alert when trying to save an empty signature

  /** @return string  */
  public function __toString(): string
  {
    global $nframework, $javas;
    $_SESSION['nf5imageup'][$this->id] = [
      'path' => $this->path,
    ];
    addVarToGarbage('nf5imageup\\' . $this->id, time() + (60 * 60));
    if (! $nframework->onces['Signature_pad']) {
      $nframework->jss['115'] = 'https://cdn.jsdelivr.net/npm/signature_pad@5.1.0/dist/signature_pad.umd.min.js';
      $javas->addjs('
    		var canvaspad=[];
    		var signaturePad=[];
    		');
      $javas->addjs('
    		const ratio =  Math.max(window.devicePixelRatio || 1, 1);
    		', 'resize');
      $nframework->onces['Signature_pad'] = true;
    }
    $options = [
      'minWidth',
      'maxWidth',
      'throttle',
      'minDistance',
      'backgroundColor',
      'penColor',
      'velocityFilterWeight',
      'canvasContextOptions',
    ];

    foreach ($options as $option) {
      if (! empty($this->{$option})) {
        $data[$option] = $this->{$option};
      }
    }

    $onError = (empty($this->onError) ? 'alert("' . $nframework->language['upload_error'] . '");' : $this->onError);
    $onSuccess = (empty($this->onSuccess) ? 'alert("' . $nframework->language['upload_success'] . '");' : $this->onSuccess);
    $onEmptyAlert = (empty($this->onEmptyAlert) ? 'alert("' . $nframework->language['signature_empty'] . '");' : $this->onEmptyAlert);

    $javas->addjs('
		signaturePad["' . $this->id . '"] = new SignaturePad(document.getElementById("canvaspad_' . $this->id . '"),' . json_encode($data) . ' );');

    $javas->addjs(
      <<<addjs
canvaspad_{$this->id}.width = canvaspad_{$this->id}.offsetWidth * ratio;
canvaspad_{$this->id}.height = canvaspad_{$this->id}.offsetHeight * ratio;
canvaspad_{$this->id}.getContext("2d").scale(ratio, ratio);
signaturePad["{$this->id}"].clear(); 

document.getElementById("canvaspad_{$this->id}_clear").addEventListener("click", () => {
	signaturePad["{$this->id}"].clear();
});
    		
document.getElementById("canvaspad_{$this->id}_save").addEventListener("click", () => {
  if (signaturePad["{$this->id}"].isEmpty()) {
    {$onEmptyAlert}
    return;
  }

  const imageData = signaturePad["{$this->id}"].toDataURL("image/png");

  fetch("/nframework/imageup.php?id={$this->id}", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded"
    },
    body: "image=" + encodeURIComponent(imageData)
  })
  .then(response => response.text())
  .then(result => {
    {$onSuccess}
  })
  .catch(error => {        
    {$onError}
  });
});    		
addjs,
      'resize'
    );

    return '<canvas id="canvaspad_' . $this->id . '" class="signature-pad" style="left: 0;top: 0;width:400px; height:200px;"></canvas><br>
		<div class="button" id="canvaspad_' . $this->id . '_clear"><span class="mif-clear"></span> ' . $nframework->language['buttons']['clear'] . '</div>
		<div class="button" id="canvaspad_' . $this->id . '_save"><span class="mif-sign-pen"></span> ' . $nframework->language['buttons']['save'] . '</div>
		';
  }
}
