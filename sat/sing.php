<?
$developermode=true;
$noobfuscate=true;
$nframework->usecommon=true;
$nframework->jss['101']='https://cdnjs.cloudflare.com/ajax/libs/jsrsasign/10.4.1/jsrsasign-all-min.min.js';
$javas->addjs('
','ready');
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
			data-role='file' data-caption='.cer' data-prepend='<span class="mif-folder">' data-validate="required"/>
		   </label>
    	</div>
        <div class='form-group'>
        	<label>Llave Privada<br>
				<input type="file" id="fielkey" name="fielkey" accept=".key"
				data-role='file' data-caption='.key' data-prepend='<span class="mif-folder">' data-validate="required"/>
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
			<input type="password" id="fielpass2" data-role="input" name="fielpass2" />
			</label>
    	</div>
    </div>
    <div class="dialog-actions">
        <button class="button js-dialog-close">Cancelar</button>
        <button class="button primary js-dialog-close">Abrir</button>
    </div> 
</div>





<button class="button primary"  onclick="Metro.dialog.open('#DialogAdd')">Agregar llave... </button>
<button class="button primary" disabled="true" id="button_login" disabled  onclick="Metro.dialog.open('#DialogLogin')">Iniciar... </button>
</div>

<script>
	var keypass='';
	var nocert='';
	var prvp8pem;
	var certPEM;
	var prvkey;
	var llaves;
	function listar_llaves(){
		var key="llaves",default_value = {};
		llaves=Metro.storage.getItem(key, default_value);
		console.log(llaves);
		
		var lv = Metro.getPlugin("#lv_llaves", "listview");
		var O="";
		var uniqueIdentifier="";
		for (const [key, value] of Object.entries(llaves)) {
			var ll=value.subject.split("/");
			console.log(ll);
			for (const [key2, value2] of Object.entries(ll)) {
				data=value2.split("=");
			//	console.log(data);
				if(data[0]=="O"){
					O=data[1];	
				}
				if(data[0]=="uniqueIdentifier"){
					uniqueIdentifier=data[1];	
				}
			}
			//console.log(O)
	        lv.add(null, {
	            caption: key+' ' +uniqueIdentifier+' '+O,
	            icon: "<span class='mif-key'>",
	         })
		}
	}


	function key_select(d){
		var datos=d.innerText().split(' ');
		nocert=datos[0];
		console.log(datos[0]);
		var key="llaves",default_value = {};
		var llaves=Metro.storage.getItem(key, default_value);
		console.log(llaves[datos[0]]);
		prvp8pem=llaves[datos[0]].key;
		certPEM=llaves[datos[0]].cert;
		$('#button_login').prop('disabled', false);
		
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
			var key="llaves",default_value = {};
			var llaves=Metro.session.getItem(key, default_value);
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