<?
class QRReader{
	public String $exitpath='/';
	public $setResult='defaultSetResult';
	function __toString():string{
		global $nframework;
		$nframework->jss['121']='https://cdn.nlared.com/qr-scanner/qr-scanner.umd.min.js';
		$nframework->jss['122']='https://cdn.nlared.com/qr-scanner/qr-scanner.legacy.min.js';
		
return '
<div id="video-container">
    <center><video id="qr-video" style="width:300px;height:300px"></video></center>
</div>

<b>Device has camera: </b>
<span id="cam-has-camera"></span>
<br>
<div>
    <b>Preferred camera:</b>
    <select id="cam-list">
        <option value="environment" selected>Environment Facing (default)</option>
        <option value="user">User Facing</option>
    </select>
</div>
<b>Camera has flash: </b>
<span id="cam-has-flash"></span>
<div>
    <button id="flash-toggle">📸 Flash: <span id="flash-state">off</span></button>
</div>
<br>
<b>Detected QR code: </b>
<span id="cam-qr-result">None</span>
<br>
<b>Last detected at: </b>
<span id="cam-qr-result-timestamp"></span>
<br>
<button id="start-button">Start</button>
<button id="stop-button">Stop</button>
<hr>

<script type="module">
    
    const video = document.getElementById("qr-video");
    const videoContainer = document.getElementById(\'video-container\');
    const camHasCamera = document.getElementById(\'cam-has-camera\');
    const camList = document.getElementById(\'cam-list\');
    const camHasFlash = document.getElementById(\'cam-has-flash\');
    const flashToggle = document.getElementById(\'flash-toggle\');
    const flashState = document.getElementById(\'flash-state\');
    const camQrResult = document.getElementById(\'cam-qr-result\');
    const camQrResultTimestamp = document.getElementById(\'cam-qr-result-timestamp\');
    
    function defaultSetResult(result) {
        console.log(result.data);
        camQrResult.textContent = result.data;
        camQrResultTimestamp.textContent = new Date().toString();
        camQrResult.style.color = \'teal\';
        clearTimeout(camQrResult.highlightTimeout);
        camQrResult.highlightTimeout = setTimeout(() => camQrResult.style.color = \'inherit\', 100);
    }

    // ####### Web Cam Scanning #######

    const scanner = new QrScanner(video, result => '.$this->setResult.'(result), {
        onDecodeError: error => {
            camQrResult.textContent = error;
            camQrResult.style.color = \'inherit\';
        },
        highlightScanRegion: true,
        highlightCodeOutline: true,
    });

    const updateFlashAvailability = () => {
        scanner.hasFlash().then(hasFlash => {
            camHasFlash.textContent = hasFlash;
            flashToggle.style.display = hasFlash ? \'inline-block\' : \'none\';
        });
    };

    scanner.start().then(() => {
        updateFlashAvailability();
        // List cameras after the scanner started to avoid listCamera\'s stream and the scanner\'s stream being requested
        // at the same time which can result in listCamera\'s unconstrained stream also being offered to the scanner.
        // Note that we can also start the scanner after listCameras, we just have it this way around in the demo to
        // start the scanner earlier.
        QrScanner.listCameras(true).then(cameras => cameras.forEach(camera => {
            const option = document.createElement(\'option\');
            option.value = camera.id;
            option.text = camera.label;
            camList.add(option);
        }));
    });

    QrScanner.hasCamera().then(hasCamera => camHasCamera.textContent = hasCamera);

    // for debugging
    window.scanner = scanner;

   

    camList.addEventListener(\'change\', event => {
        scanner.setCamera(event.target.value).then(updateFlashAvailability);
    });

    flashToggle.addEventListener(\'click\', () => {
        scanner.toggleFlash().then(() => flashState.textContent = scanner.isFlashOn() ? \'on\' : \'off\');
    });

    document.getElementById(\'start-button\').addEventListener(\'click\', () => {
        scanner.start();
    });

    document.getElementById(\'stop-button\').addEventListener(\'click\', () => {
        scanner.stop();
    });

   
 
</script>
<style>
    div {
        margin-bottom: 16px;
    }

    #video-container {
        line-height: 0;
    }

    #video-container.example-style-1 .scan-region-highlight-svg,
    #video-container.example-style-1 .code-outline-highlight {
        stroke: #64a2f3 !important;
    }

    #video-container.example-style-2 {
        position: relative;
        width: 400px;
        overflow: hidden;
    }
    #video-container.example-style-2 .scan-region-highlight {
        border-radius: 30px;
        outline: rgba(0, 0, 0, .25) solid 50vmax;
    }
    #video-container.example-style-2 .scan-region-highlight-svg {
        display: none;
    }
    #video-container.example-style-2 .code-outline-highlight {
        stroke: rgba(255, 255, 255, .5) !important;
        stroke-width: 15 !important;
        stroke-dasharray: none !important;
    }

    #flash-toggle {
        display: none;
    }

    hr {
        margin-top: 32px;
    }
    input[type="file"] {
        display: block;
        margin-bottom: 16px;
    }
</style>';
	}
}