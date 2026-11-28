<?php
$developermode=true;
$noobfuscate=true;
$nframework->usecommon=true;
$nframework->jss['101']='https://cdnjs.cloudflare.com/ajax/libs/jsrsasign/10.4.1/jsrsasign-all-min.min.js';
// Generador de QR (qrcode-generator): el /nframework/qrcodegen-v1.8.0-es5.js anterior no existe en el repositorio.
$nframework->jss['105']='https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js';
$javas->addjs("
/**
 * Dibuja text como QR en el canvas con id ele.
 */
function generateQRCode(text,ele) {
    var qr = qrcode(0, 'M');
    qr.addData(text, 'Byte');
    qr.make();
    var n = qr.getModuleCount();
    var margin = 4, scale = 8;
    var canvas = document.getElementById(ele);
    canvas.width = canvas.height = (n + margin * 2) * scale;
    var ctx = canvas.getContext('2d');
    ctx.fillStyle = 'white';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.fillStyle = 'black';
    for (var y = 0; y < n; y++) {
        for (var x = 0; x < n; x++) {
            if (qr.isDark(y, x)) ctx.fillRect((x + margin) * scale, (y + margin) * scale, scale, scale);
        }
    }
}
");
?>
<div class="container">
	<h1>Seleccionar llave</h1>
	<ul data-role="listview" id="lv_llaves" data-on-node-click="key_select">
	</ul>
	
<div class="dialog" data-role="dialog" id="DialogAdd">
    <div class="dialog-title">Agregar llave</div>
    <div class="dialog-content">
    	<h4 id="commonName">Nombre:</h4>
		<div id="serialNumber">CURP:</div>
		<div id="noCert">#Cert:</div>
		<div class='form-group'>
        	<label>Certificado<br>
			<input type="file" id="fielcert" name="fielcert" accept=".cer"
			data-role='input' data-caption='.cer' data-prepend='<span class="mif-folder">' data-validate="required"/>
		   </label>
    	</div>
        <div class='form-group'>
        	<label>Llave Privada<br>
				<input type="file" id="fielkey" name="fielkey" accept=".key"
				data-role='input' data-caption='.key' data-prepend='<span class="mif-folder">' data-validate="required"/>
		   </label>
        </div>
        <div class='form-group'>
    		<label>Contraseña<br>
			<input type="password" id="fielpass" data-role="input" name="fielpass" />
			</label>
    	</div>
	    	
    </div>
    <div class="dialog-actions">
        <button class="button js-dialog-close">Cancelar</button>
        <button class="button primary" onclick="firmtoadd()">Agregar</button>
    </div> 
</div>

<div class="dialog" data-role="dialog" id="DialogLogin">
    <div class="dialog-title">Iniciar Sesión</div>
    <div class="dialog-content">
    	<div class='form-group'>
    		<label>Contraseña<br>
			<input type="password" id="loginpass" data-role="input" name="fielpass2" />
			</label>
    	</div>
    </div>
    <div class="dialog-actions">
        <button class="button js-dialog-close">Cancelar</button>
        <button class="button primary" onclick="login();">Iniciar</button>
    </div> 
</div>

<div class="dialog" data-role="dialog" id="DialogSignature">
    <div class="dialog-title">Abrir Certificado</div>
    <div class="dialog-content">
    	<div class='form-group'>
    		<label>Contraseña<br>
			<input type="password" autocomplete ="off" id="fielpass2" data-role="input" name="fielpass2" />
			</label>
    	</div>
    </div>
    <div class="dialog-actions">
    	<button class="button js-dialog-close">Cancelar</button>
        <button class="button primary js-dialog-close">Abrir</button>
    </div> 
</div>


<div class="dialog" data-role="dialog" id="DialogTransfer" data-on-open="qrTxStart()" data-on-close="qrTxStop()">
    <div class="dialog-title">Enviar llave a otro dispositivo</div>
    <div class="dialog-content">
		<p>En el otro dispositivo abra esta página, pulse <span class="mif-camera"></span> y apunte la cámara a esta pantalla
		hasta completar todas las partes. Solo viaja la llave cifrada y el certificado; la contraseña no.</p>
		<center><canvas id="qrcode_tx" style="width:100%;max-width:400px;image-rendering:pixelated;"></canvas></center>
		<center>
			<button class="button" onclick="qrTxStep(-1)"><span class="mif-arrow-left"></span></button>
			<button class="button" id="qr_tx_pause" onclick="qrTxPause()"><span class="mif-pause"></span></button>
			<button class="button" onclick="qrTxStep(1)"><span class="mif-arrow-right"></span></button>
			<select id="qr_tx_speed" onchange="qrTxStart()" style="width:auto;display:inline-block">
				<option value="1500">Lento</option>
				<option value="800" selected>Normal</option>
				<option value="400">Rápido</option>
			</select>
			<div id="qr_tx_info">Parte 0 de 0</div>
		</center>
    </div>
    <div class="dialog-actions">
    	<button class="button js-dialog-close">Cerrar</button>
    </div>
</div>


<div class="dialog" data-role="dialog" id="DialogScan" data-on-open="qrRxOpen()" data-on-close="qrRxClose()">
    <div class="dialog-title">Recibir llave de otro dispositivo</div>
    <div class="dialog-content">
		<h4 id="capturados">Apunte la cámara a los códigos del otro dispositivo</h4>
		<div data-role="progress" id="qr_rx_progress" data-value="0"></div>
		<div id="qr_rx_faltan"></div>
	<?php
	$qrreader=new QRReader();
	$qrreader->setResult='setResult';
	echo $qrreader;
	?>
    </div>
    <div class="dialog-actions">
    	<button class="button js-dialog-close">Cerrar</button>
    </div>
</div>



<button class="button primary" id="button_qr" onclick="Metro.dialog.open('#DialogTransfer')" disabled><span class="mif-qrcode"></span></button>
<button class="button primary" id="button_scan" onclick="Metro.dialog.open('#DialogScan')"><span class="mif-camera"></span></button>
<button class="button primary"  onclick="Metro.dialog.open('#DialogAdd')">Agregar llave... </button>
<button class="button primary" disabled="true" id="button_login" disabled  onclick="Metro.dialog.open('#DialogLogin')">Iniciar... </button>

<br><br>
Demo:<br>
<a href="/sat/demo/GOYA780416GM0_csd.cer">GOYA780416GM0_csd.cer</a><br>
<a href="/sat/demo/GOYA780416GM0_csd.key">GOYA780416GM0_csd.key</a><br>
Contraseña:12345678a
</div>

<script>
	/*
	 * Transferencia de la llave por QR.
	 * Contenido: JSON {n: noCert, k: llave PKCS#8 cifrada en base64 (DER), c: certificado en base64 (DER)}.
	 * Se parte en trozos de QR_CHUNK caracteres; cada QR lleva "NFK1:<hash>:<i>:<total>:<trozo>", donde
	 * <hash> son los primeros 8 hex del SHA-256 del contenido completo (identifica la transferencia y la verifica).
	 */
	var QR_CHUNK=350;
	var qrTx={frames:[], i:0, timer:null, paused:false};
	var qrRx={hash:'', total:0, parts:{}, done:false};

	function pemToB64(pem){
		return pem.replace(/-----[^-]+-----/g,'').replace(/\s+/g,'');
	}

	function qrTxPrepare(nc){
		var ll=llaves[nc];
		var payload=JSON.stringify({n: nc, k: pemToB64(ll.key), c: pemToB64(ll.cert)});
		var hash=KJUR.crypto.Util.sha256(payload).substr(0,8);
		var total=Math.ceil(payload.length/QR_CHUNK);
		qrTx.frames=[];
		for(var i=0;i<total;i++){
			qrTx.frames.push('NFK1:'+hash+':'+i+':'+total+':'+payload.substr(i*QR_CHUNK,QR_CHUNK));
		}
		qrTx.i=0;
	}
	function qrTxShow(){
		if(!qrTx.frames.length) return;
		generateQRCode(qrTx.frames[qrTx.i],'qrcode_tx');
		$('#qr_tx_info').text('Parte '+(qrTx.i+1)+' de '+qrTx.frames.length);
	}
	function qrTxStep(d){
		var n=qrTx.frames.length;
		if(!n) return;
		qrTx.i=(qrTx.i+d+n)%n;
		qrTxShow();
	}
	function qrTxStart(){
		qrTxStop();
		qrTxShow();
		if(!qrTx.paused){
			qrTx.timer=setInterval(function(){ qrTxStep(1); }, parseInt($('#qr_tx_speed').val(),10)||800);
		}
	}
	function qrTxStop(){
		if(qrTx.timer){ clearInterval(qrTx.timer); qrTx.timer=null; }
	}
	function qrTxPause(){
		qrTx.paused=!qrTx.paused;
		$('#qr_tx_pause').html(qrTx.paused?'<span class="mif-play"></span>':'<span class="mif-pause"></span>');
		qrTxStart();
	}

	function qrRxReset(){
		qrRx={hash:'', total:0, parts:{}, done:false};
		$('#capturados').text('Apunte la cámara a los códigos del otro dispositivo');
		$('#qr_rx_faltan').text('');
		qrRxProgress(0);
	}
	function qrRxProgress(v){
		var p=Metro.getPlugin('#qr_rx_progress','progress');
		if(p) p.val(v);
	}
	function qrRxOpen(){
		qrRxReset();
		if(window.scanner) window.scanner.start();
	}
	function qrRxClose(){
		if(window.scanner) window.scanner.stop();
	}

	function setResult(result){
		var txt=(typeof result==='string')?result:(result&&result.data);
		if(qrRx.done || typeof txt!=='string') return;
		var m=txt.match(/^NFK1:([0-9a-f]{8}):(\d+):(\d+):([\s\S]*)$/);
		if(!m) return;   // otro QR: se ignora
		var hash=m[1], i=parseInt(m[2],10), total=parseInt(m[3],10);
		if(total<1 || total>500 || i>=total) return;
		if(hash!==qrRx.hash){   // transferencia nueva (otra llave): se empieza de cero
			qrRx={hash:hash, total:total, parts:{}, done:false};
		}
		qrRx.parts[i]=m[4];
		var faltan=[];
		for(var j=0;j<total;j++){ if(!(j in qrRx.parts)) faltan.push(j+1); }
		var tengo=total-faltan.length;
		$('#capturados').text('Capturadas '+tengo+' de '+total+' partes');
		$('#qr_rx_faltan').text(faltan.length?'Faltan: '+faltan.join(', '):'');
		qrRxProgress(Math.round(tengo*100/total));
		if(!faltan.length) qrRxComplete();
	}

	function qrRxComplete(){
		var payload='';
		for(var j=0;j<qrRx.total;j++) payload+=qrRx.parts[j];
		if(KJUR.crypto.Util.sha256(payload).substr(0,8)!==qrRx.hash){
			// Alguna parte se leyó mal: se vuelve a capturar todo.
			qrRxReset();
			$('#capturados').text('La llave recibida no coincide; vuelva a escanear.');
			return;
		}
		qrRx.done=true;
		if(window.scanner) window.scanner.stop();
		try{
			var obj=JSON.parse(payload);
			if(typeof obj.n!=='string' || !/^\d{1,40}$/.test(obj.n)) throw 'Número de certificado inválido';
			var keyPEM=KJUR.asn1.ASN1Util.getPEMStringFromHex(b64tohex(obj.k), 'ENCRYPTED PRIVATE KEY');
			var cPEM=KJUR.asn1.ASN1Util.getPEMStringFromHex(b64tohex(obj.c), 'CERTIFICATE');
			var x509=new X509();
			x509.readCertPEM(cPEM);
			llaves=Metro.storage.getItem('llaves', {});
			var nueva=!(obj.n in llaves);
			llaves[obj.n]={
				'key': keyPEM,
				'cert': cPEM,
				'subject': x509.getSubjectString(),
				'notAfter': x509.getNotAfter()
			};
			Metro.storage.setItem('llaves', llaves);
			if(nueva) agregar_llave_lista(obj.n, llaves[obj.n]);
			$('#capturados').text('Llave '+obj.n+' recibida y guardada.');
			qrRxProgress(100);
		}catch(e){
			$('#capturados').text('No se pudo leer la llave recibida: '+e);
		}
	}

	var keypass='';
	var nocert='';
	var prvp8pem;
	var certPEM;
	var prvkey;
	var llaves;

	function agregar_llave_lista(nc, value){
		var lv = Metro.getPlugin("#lv_llaves", "listview");
		var O="", uniqueIdentifier="";
		value.subject.split("/").forEach(function(p){
			var data=p.split("=");
			if(data[0]=="O") O=data[1];
			if(data[0]=="uniqueIdentifier") uniqueIdentifier=data[1];
		});
		lv.add({
			caption: nc+' '+uniqueIdentifier+' '+O,
			icon: "<span class='mif-key'>",
		});
	}

	function listar_llaves(){
		llaves=Metro.storage.getItem("llaves", {});
		for (const [nc, value] of Object.entries(llaves)) {
			agregar_llave_lista(nc, value);
		}
	}


	function key_select(d){
		var datos=d.innerText().split(' ');
		nocert=datos[0];
		console.log(datos[0]);
		var key="llaves",default_value = {};
		llaves=Metro.storage.getItem(key, default_value);
		console.log(llaves[datos[0]]);
		prvp8pem=llaves[datos[0]].key;
		certPEM=llaves[datos[0]].cert;
		$('#button_login').prop('disabled', false);
		$('#button_qr').prop('disabled', false);
		qrTxPrepare(datos[0]);
		
	}
	function key_open(el){
		keypass=$(el).val();
		console.log(keypass);
		prvkey = KEYUTIL.getKey(prvp8pem, keypass);
		//prvkey = KEYUTIL.getKey(prvp8pem,keypass);
		
	}
	

	function cargarkey(){
		var fileInputkey = document.getElementById('fielkey');
		if($('#fielpass').val()!='' && fileInputkey.files.length>0){
			var file = fileInputkey.files[0];
			var fr = new FileReader();
			fr.onloadend = function() {
				var hexDerFileContents = rstrtohex(fr.result); // raw string to hex
				prvp8pem = KJUR.asn1.ASN1Util.getPEMStringFromHex(hexDerFileContents, 'ENCRYPTED PRIVATE KEY');
				try{
					prvkey = KEYUTIL.getKey(prvp8pem, $('#fielpass').val());
					console.log(prvkey);
					$('#btnfirmar').prop('disabled', false);
				}catch(ee){
					console.log('aqui');
				}
			};
			fr.readAsBinaryString(file);
		}else{
			$('#btnfirmar').prop('disabled', true);
		}
	}
	function firmtoadd(){
		var cadenaoriginal="<?=uniqid(true);?>";
		var cadenafirmada=firmar(prvkey,cadenaoriginal);
		$('#cadenafirmada').val(hextob64(cadenafirmada));	
		isValid=validar(certPEM,cadenaoriginal,cadenafirmada);
		console.log(isValid);
		if(isValid){
			var key="llaves";
			var default_value = {};
			llaves=Metro.storage.getItem(key, default_value);
			var x509 = new X509(); // https://kjur.github.io/jsrsasign/api/symbols/X509.html
			x509.readCertPEM(certPEM);
			console.log('serial a guardar');
			var serial=x509.getSerialNumberHex();
			llaves[noCert]={
				'key': prvp8pem,
				'cert': certPEM,
				'subject': x509.getSubjectString(),
				'notAfter': x509.getNotAfter() 
			};
			Metro.storage.setItem(key, llaves);
			Metro.dialog.close('#DialogAdd');
			listar_llaves();
			
		}
	}
	
	function firmar(mprvkey,data){
		var sig = new KJUR.crypto.Signature({'alg':'SHA256withRSA'});
		sig.init(mprvkey);
		sig.updateString(data);
		var cadenafirmada=sig.sign();
		return cadenafirmada;
	}
	function validar(mcertPEM,cadenaoriginal,cadenafirmada){
		var sig2 = new KJUR.crypto.Signature({'alg':'SHA256withRSA'});
		sig2.init(mcertPEM);
		sig2.updateString(cadenaoriginal);
		var isValid = sig2.verify(cadenafirmada);
		//console.log(isValid);
		return isValid
	}
	

	window.onload = function() {
		$('#fielcert').on('change', function() {
		    var file_data = $(this).prop('files')[0];   
		    var form_data = new FormData();                  
		    form_data.append('file', file_data);
		
			var fr = new FileReader();
			fr.onloadend = function() {
				var hexDerFileContents = rstrtohex(fr.result); // raw string to hex
				certPEM = KJUR.asn1.ASN1Util.getPEMStringFromHex(hexDerFileContents, "CERTIFICATE"); // with 
				var x509 = new X509(); // https://kjur.github.io/jsrsasign/api/symbols/X509.html
				x509.readCertPEM(certPEM);
				console.log(x509);
				console.log(x509.getIssuerString());
				console.log(x509.getNotAfter());
				console.log(x509.getSerialNumberHex());
				console.log(x509.getSignatureAlgorithmField());
				console.log(x509.getSubjectString());
				console.log(certPEM);
			};
			fr.readAsBinaryString(file_data);
			
		    $.ajax({
				url: '/sat/upload.php', // point to server-side PHP script 
				dataType: 'text',  // what to expect back from the PHP script, if anything
				cache: false,
				dataType: "json",
				contentType: false,
				processData: false,
				data: form_data,                
				type: 'post',
				success: function(response){
					noCert=response.noCert;
					$('#commonName').text(response.commonName);
					$('#serialNumber').text('CURP:'+response.serialNumber);
					$('#noCert').text('#Cert:'+response.noCert);
				}
		    });
		});
		
	
		$('#fielkey').on('change', function(e) {
			cargarkey();
		});
		$('#fielpass').on("change", function(e) {
			cargarkey();
		});
		listar_llaves();
		// QRReader enciende la cámara al cargar; aquí solo se usa mientras el diálogo de recepción está abierto.
		if(window.scanner) window.scanner.stop();
	};
	function login(){
		try{
			key_open("#loginpass");
		}catch(error){
			alert(error);
			return;
		}
		$.ajax({
			url: "/sat/logintoken.php",
			cache: false
		})
		.done(function( data ) {
			cadenaoriginal=data.token;
			console.log("\n\Cadenaoriginal:\n"+cadenaoriginal);
			cadenafirmada=firmar(prvkey,cadenaoriginal);
			console.log("\nCadenafirmada:\n"+cadenafirmada);
		//	console.log("\nCadenafirmadah64:\n"+hextob64(cadenafirmada));
			var sello=stob64u(cadenafirmada);
			isvalid=validar(certPEM,cadenaoriginal,cadenafirmada);
			//&sello2='+hextob64u(cadenafirmada)
			console.log("\n"+isvalid);
			fetch("/sat/validate.php?nocert="+nocert+'&sello='+sello).then((resp) => resp.json()).then(function(data) {
				if(data.status=='buena'){
					window.location.assign("/");
					Metro.dialog.close('#DialogLogin');
				}
				
			});
		});
	}
</script>