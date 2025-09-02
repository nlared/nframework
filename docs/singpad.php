<?php
require_once '../common2.php';
$noobfuscate=true;



$sign=new Signature_pad(['name'=>'sigpad1','backgroundColor'=>'rgb(255,255,255)']);

$javas->addjs('
let undoData = [];
$("[data-action=clear]").click(function(){
  signaturePad["sigpad1"].clear();
});

$("[data-action=undo]").click(function(){
  const data = signaturePad["sigpad1"].toData();

  if (data && data.length > 0) {
    // remove the last dot or line
    const removed = data.pop();
    undoData.push(removed);
    signaturePad["sigpad1"].fromData(data);
  }
});

$("[data-action=redo]").click(function(){
  if (undoData.length > 0) {
    const data = signaturePad["sigpad1"].toData();
    data.push(undoData.pop());
    signaturePad["sigpad1"].fromData(data);
  }
});

$("[data-action=save-png]").click(function(){
  if (signaturePad["sigpad1"].isEmpty()) {
    alert("Please provide a signature first.");
  } else {
    const dataURL = signaturePad["sigpad1"].toDataURL();
    download(dataURL, "signature.png");
  }
});

$("[data-action=save-jpg]").click(function(){
  if (signaturePad["sigpad1"].isEmpty()) {
    alert("Please provide a signature first.");
  } else {
    const dataURL = signaturePad["sigpad1"].toDataURL("image/jpeg");
    download(dataURL, "signature.jpg");
  }
});

$("[data-action=save-svg]").click(function(){
  if (signaturePad["sigpad1"].isEmpty()) {
    alert("Please provide a signature first.");
  } else {
    const dataURL = signaturePad["sigpad1"].toDataURL(\'image/svg+xml\');
    download(dataURL, "signature.svg");
  }
});



');


?>
<div class="container">
	
	<?=$sign?><br>
	<button type="button" class="button clear" data-action="clear">Clear</button>
	<button type="button" class="button" data-action="undo" title="Ctrl-Z">Undo</button>
	<button type="button" class="button" data-action="redo" title="Ctrl-Y">Redo</button>
	<button type="button" class="button save" data-action="save-png">Save as PNG</button>
  <button type="button" class="button save" data-action="save-jpg">Save as JPG</button>
  <button type="button" class="button save" data-action="save-svg">Save as SVG</button>
</div>