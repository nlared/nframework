<?php

class Signature_pad extends baseInput
{
    public $minWidth = 0.5;

    public $maxWidth = 2.5;

    public $throttle = 16;

    public $minDistance = 5;

    public $backgroundColor = 'rgba(0,0,0,0)';

    public $penColor = 'black';

    public $velocityFilterWeight = 0.7;

    public $canvasContextOptions = '';

    public $name;

    public $path;

    public function __toString(): string
    {
        global $nframework,$javas;
        $_SESSION['nf5imageup'][$this->id] = [
            'path' => $this->path,
        ];
        addVarToGarbage('nf5imageup\\'.$this->id, time() + (60 * 60));

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

        $javas->addjs('
		signaturePad["'.$this->id.'"] = new SignaturePad(document.getElementById("canvaspad_'.$this->id.'"),'.json_encode($data).' );');
        $javas->addjs(<<<addjs
canvaspad_{$this->id}.width = canvaspad_{$this->id}.offsetWidth * ratio;
canvaspad_{$this->id}.height = canvaspad_{$this->id}.offsetHeight * ratio;
canvaspad_{$this->id}.getContext("2d").scale(ratio, ratio);
signaturePad["{$this->id}"].clear(); 

document.getElementById("canvaspad_{$this->id}_clear").addEventListener("click", () => {
	signaturePad["{$this->id}"].clear();
});
    		
document.getElementById("canvaspad_{$this->id}_save").addEventListener("click", () => {
  if (signaturePad["{$this->id}"].isEmpty()) {
    alert("Please sign before sending.");
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
    alert("Upload successful: " + result);
  })
  .catch(error => {
    console.error("Upload failed:", error);
    alert("Upload failed.");
  });
});
    		
addjs
            , 'resize');

        return '<canvas id="canvaspad_'.$this->id.'" class="signature-pad" style="left: 0;top: 0;width:400px; height:200px;"></canvas><br>
		<div class="button flat" id="canvaspad_'.$this->id.'_clear"><span class="mif-clear"></span> '.$nframework->language['buttons']['clear'].'</div>
		<div class="button flat" id="canvaspad_'.$this->id.'_save"><span class="mif-sign-pen"></span> '.$nframework->language['buttons']['save'].'</div>
		';
    }
}
