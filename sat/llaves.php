<?php
/*
 * Llaves del SAT guardadas en este navegador (e.firma / CSD).
 *
 * El .cer y el .key (cifrado con su contraseña) viven en localStorage, clave "llaves", con el mismo formato
 * que usa el componente Efirma: {noCert: {key, cert, subject, notAfter, tipo?, rfc?, nombre?}}. La llave
 * privada nunca sale del navegador; al servidor solo llega el certificado (/sat/upload.php) para que
 * /sat/validate.php pueda verificar el inicio de sesión.
 */
require 'include.php';
$noobfuscate=true;
$nframework->usecommon=true;
$nframework->metas['title']='Mis llaves del SAT';
$nframework->jss['101']='https://cdnjs.cloudflare.com/ajax/libs/jsrsasign/10.4.1/jsrsasign-all-min.min.js';
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
<style>
	.nf-llaves{max-width:1100px;margin:0 auto;padding:1.5rem 1rem 3rem}
	/* QRReader agrega "div{margin-bottom:16px}" global; aquí se controla el espaciado. */
	.nf-llaves div{margin-bottom:0}
	.nf-llaves .ll-head{display:flex;flex-wrap:wrap;gap:1rem;align-items:flex-end;justify-content:space-between;margin-bottom:1.5rem}
	.nf-llaves .ll-head h1{margin:0;font-size:1.75rem}
	.nf-llaves .ll-head p{margin:.25rem 0 0;color:var(--nf-muted)}
	.nf-llaves .ll-actions{display:flex;flex-wrap:wrap;gap:.5rem}
	.nf-llaves .ll-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1rem}
	.nf-llaves .ll-card{background:var(--nf-surface);border:1px solid var(--nf-line);border-radius:var(--nf-radius-lg);
		box-shadow:var(--nf-shadow);padding:1rem 1.1rem;display:flex;flex-direction:column;gap:.75rem}
	.nf-llaves .ll-card.is-expired{opacity:.75}
	.nf-llaves .ll-top{display:flex;gap:.75rem;align-items:flex-start}
	.nf-llaves .ll-icon{flex:none;width:2.5rem;height:2.5rem;border-radius:var(--nf-radius);display:grid;place-items:center;
		background:var(--nf-accent-soft);color:var(--nf-accent-ink);font-size:1.2rem}
	.nf-llaves .ll-name{font-weight:600;line-height:1.25;word-break:break-word}
	.nf-llaves .ll-rfc{font-family:ui-monospace,monospace;color:var(--nf-muted);font-size:.9rem}
	.nf-llaves .ll-meta{display:grid;grid-template-columns:auto 1fr;gap:.2rem .75rem;font-size:.85rem;margin:0}
	.nf-llaves .ll-meta dt{color:var(--nf-muted)}
	.nf-llaves .ll-meta dd{margin:0;font-family:ui-monospace,monospace;word-break:break-all}
	.nf-llaves .ll-tags{display:flex;flex-wrap:wrap;gap:.35rem}
	.nf-llaves .ll-badge{display:inline-block;padding:.1rem .5rem;border-radius:999px;font-size:.75rem;font-weight:600;
		border:1px solid var(--nf-line);background:var(--nf-surface-2)}
	.nf-llaves .ll-badge.ok{color:var(--nf-success);border-color:currentColor}
	.nf-llaves .ll-badge.warn{color:var(--nf-warning);border-color:currentColor}
	.nf-llaves .ll-badge.bad{color:var(--nf-danger);border-color:currentColor}
	.nf-llaves .ll-foot{display:flex;gap:.4rem;margin-top:auto;padding-top:.75rem;border-top:1px solid var(--nf-line)}
	.nf-llaves .ll-foot .grow{flex:1}
	.nf-llaves .ll-empty{text-align:center;padding:3rem 1rem;border:2px dashed var(--nf-line-strong);border-radius:var(--nf-radius-lg);color:var(--nf-muted)}
	.nf-llaves .ll-empty .mif-key{font-size:2.5rem;display:block;margin-bottom:.75rem}
	.nf-llaves .ll-empty h3{color:var(--nf-ink);margin:.25rem 0}
	.nf-llaves .ll-note{margin-top:2rem;font-size:.85rem;color:var(--nf-muted);display:flex;gap:.5rem;align-items:flex-start}
	/* Diálogos */
	.nf-llaves .ll-field{margin-bottom:.9rem}
	.nf-llaves .ll-field > label{display:block;font-weight:600;margin-bottom:.3rem}
	.nf-llaves .ll-preview{border:1px solid var(--nf-line);border-radius:var(--nf-radius);background:var(--nf-surface-2);padding:.75rem;margin-bottom:.9rem}
	.nf-llaves .ll-status{min-height:1.4em;font-size:.9rem}
	.nf-llaves .ll-status.ok{color:var(--nf-success)}
	.nf-llaves .ll-status.bad{color:var(--nf-danger)}
	.nf-llaves .ll-qr{text-align:center}
	.nf-llaves .ll-qr canvas{width:300px;max-width:100%;height:auto;image-rendering:pixelated;border-radius:var(--nf-radius)}
	.nf-llaves .ll-qr-ctrl{display:flex;flex-wrap:wrap;gap:.4rem;justify-content:center;align-items:center;margin:.75rem 0 .25rem}
	/* Del lector QR solo se muestran el video y el selector de cámara. */
	.nf-llaves .ll-reader > *{display:none}
	.nf-llaves .ll-reader > #video-container{display:block;margin:.75rem 0}
	.nf-llaves .ll-reader > div:has(#cam-list){display:flex;gap:.5rem;align-items:center;justify-content:center;font-size:.85rem}
	.nf-llaves .ll-reader > div:has(#cam-list) b{font-weight:400;color:var(--nf-muted)}
	.nf-llaves .ll-reader video{width:100%!important;max-width:320px;height:auto!important;aspect-ratio:1;object-fit:cover;border-radius:var(--nf-radius)}
	/* SweetAlert se agrega al body (fuera de .nf-llaves); Metro da width:100% a todo input y se desborda. */
	.ll-swal .swal2-input{box-sizing:border-box;width:calc(100% - 3em);max-width:none;margin:.75em 1.5em .25em}
	.ll-swal .swal2-input-label{margin-top:1em}
	.ll-swal .swal2-html-container{margin-top:.5em}
	@media (max-width:600px){ .nf-llaves .ll-actions,.nf-llaves .ll-actions .button{width:100%} }
</style>

<div class="nf-llaves">
	<div class="ll-head">
		<div>
			<h1>Mis llaves del SAT</h1>
			<p>e.firma y CSD guardados en este navegador. La llave privada no sale de este dispositivo.</p>
		</div>
		<div class="ll-actions">
			<button class="button" onclick="qrRxOpen()"><span class="mif-camera"></span> Recibir de otro dispositivo</button>
			<button class="button primary" onclick="addOpen()"><span class="mif-plus"></span> Agregar llave</button>
		</div>
	</div>

	<div id="ll_grid" class="ll-grid"></div>

	<div id="ll_empty" class="ll-empty" style="display:none">
		<span class="mif-key"></span>
		<h3>Aún no hay llaves en este navegador</h3>
		<p>Agregue su certificado (.cer), su llave privada (.key) y la contraseña, o recíbala desde otro dispositivo.</p>
		<button class="button primary mt-2" onclick="addOpen()"><span class="mif-plus"></span> Agregar llave</button>
	</div>

	<div class="ll-note">
		<span class="mif-info"></span>
		<span>Las llaves se guardan cifradas con su contraseña en el almacenamiento local de este navegador. Si borra los datos
		del sitio tendrá que agregarlas de nuevo. No use esta página en equipos compartidos.</span>
	</div>

	<!-- Agregar llave -->
	<div class="dialog" data-role="dialog" id="DialogAdd" data-width="520">
		<div class="dialog-title">Agregar llave</div>
		<div class="dialog-content">
			<div class="ll-field">
				<label for="fielcert">Certificado (.cer)</label>
				<input type="file" id="fielcert" accept=".cer" data-role="file" data-button-title="<span class='mif-folder'></span>">
			</div>
			<div id="ll_preview" class="ll-preview" style="display:none"></div>
			<div class="ll-field">
				<label for="fielkey">Llave privada (.key)</label>
				<input type="file" id="fielkey" accept=".key" data-role="file" data-button-title="<span class='mif-folder'></span>">
			</div>
			<div class="ll-field">
				<label for="fielpass">Contraseña de la llave privada</label>
				<input type="password" id="fielpass" data-role="input" autocomplete="off" data-nf-noautofill>
			</div>
			<div id="ll_add_status" class="ll-status"></div>
		</div>
		<div class="dialog-actions">
			<button class="button js-dialog-close">Cancelar</button>
			<button class="button primary" id="ll_add_btn" onclick="firmtoadd()" disabled>Agregar</button>
		</div>
	</div>

	<!-- Enviar por QR -->
	<div class="dialog" data-role="dialog" id="DialogTransfer" data-on-close="qrTxStop()">
		<div class="dialog-title">Enviar llave a otro dispositivo</div>
		<div class="dialog-content">
			<p id="qr_tx_key" class="text-bold"></p>
			<p>En el otro dispositivo abra esta página, pulse <b>Recibir de otro dispositivo</b> y apunte la cámara a esta
			pantalla hasta completar todas las partes. Solo viajan la llave cifrada y el certificado; la contraseña no.</p>
			<div class="ll-qr">
				<canvas id="qrcode_tx" width="300" height="300"></canvas>
				<div class="ll-qr-ctrl">
					<button class="button" onclick="qrTxStep(-1)" title="Anterior"><span class="mif-arrow-left"></span></button>
					<button class="button" id="qr_tx_pause" onclick="qrTxPause()" title="Pausar"><span class="mif-pause"></span></button>
					<button class="button" onclick="qrTxStep(1)" title="Siguiente"><span class="mif-arrow-right"></span></button>
					<select id="qr_tx_speed" onchange="qrTxStart()" style="width:auto">
						<option value="1500">Lento</option>
						<option value="800" selected>Normal</option>
						<option value="400">Rápido</option>
					</select>
				</div>
				<div id="qr_tx_info" class="text-small">Parte 0 de 0</div>
			</div>
		</div>
		<div class="dialog-actions">
			<button class="button js-dialog-close">Cerrar</button>
		</div>
	</div>

	<!-- Recibir por QR -->
	<div class="dialog" data-role="dialog" id="DialogScan" data-on-close="qrRxClose()">
		<div class="dialog-title">Recibir llave de otro dispositivo</div>
		<div class="dialog-content">
			<p id="capturados">Apunte la cámara a los códigos del otro dispositivo</p>
			<div data-role="progress" id="qr_rx_progress" data-value="0"></div>
			<div id="qr_rx_faltan" class="text-small mt-1"></div>
			<div class="ll-reader">
			<?php
			$qrreader=new QRReader();
			$qrreader->setResult='setResult';
			echo $qrreader;
			?>
			</div>
		</div>
		<div class="dialog-actions">
			<button class="button js-dialog-close">Cerrar</button>
		</div>
	</div>
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
	var llaves={};
	// Datos del alta en curso.
	var add={cert:'', key:'', noCert:'', prv:null};

	function esc(s){ return $('<div>').text(s==null?'':String(s)).html(); }
	function fail(msg){ Swal.fire({icon:'error', title:'Llaves del SAT', text:String(msg)}); }
	function cargarLlaves(){ llaves=Metro.storage.getItem('llaves', {}) || {}; return llaves; }

	/** Datos legibles de una llave guardada. */
	function infoLlave(nc, l){
		var subj=l.subject||'', get=function(k){ var m=new RegExp('/'+k+'=([^/]*)').exec(subj); return m?m[1]:''; };
		var rfc=l.rfc || (get('x500UniqueIdentifier')||get('uniqueIdentifier')).split('/')[0].trim();
		var vence=parseAsn1Date(l.notAfter);
		var dias=vence ? Math.floor((vence-Date.now())/86400000) : null;
		return {
			nocert:nc,
			nombre:l.nombre || get('O') || get('name') || get('CN'),
			rfc:rfc,
			curp:get('serialNumber').split('/')[0].trim(),
			tipo:l.tipo || (/\/OU=/.test(subj)?'csd':'fiel'),
			vence:vence, dias:dias
		};
	}
	/** Fecha ASN.1 de jsrsasign ("YYMMDDhhmmssZ" o "YYYYMMDDhhmmssZ") a Date. */
	function parseAsn1Date(s){
		var m=/^(\d{2}|\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})Z$/.exec(s||'');
		if(!m) return null;
		var y=parseInt(m[1],10); if(m[1].length===2) y+= y<50?2000:1900;
		return new Date(Date.UTC(y, m[2]-1, m[3], m[4], m[5], m[6]));
	}
	function fmtFecha(d){ return d ? d.toLocaleDateString('es-MX',{year:'numeric',month:'short',day:'numeric'}) : '—'; }
	function vigenciaBadge(i){
		if(i.dias===null) return '<span class="ll-badge">Vigencia desconocida</span>';
		if(i.dias<0) return '<span class="ll-badge bad">Vencida</span>';
		if(i.dias<=30) return '<span class="ll-badge warn">Vence en '+i.dias+' día'+(i.dias===1?'':'s')+'</span>';
		return '<span class="ll-badge ok">Vigente</span>';
	}

	function render(){
		cargarLlaves();
		var $g=$('#ll_grid').empty(), ids=Object.keys(llaves);
		$('#ll_empty').toggle(ids.length===0);
		ids.map(function(nc){ return infoLlave(nc, llaves[nc]); })
			.sort(function(a,b){ return (a.nombre||'').localeCompare(b.nombre||''); })
			.forEach(function(i){
				var $c=$('<div class="ll-card">').toggleClass('is-expired', i.dias!==null && i.dias<0).html(
					'<div class="ll-top">'+
						'<div class="ll-icon"><span class="mif-'+(i.tipo==='csd'?'files-empty':'key')+'"></span></div>'+
						'<div><div class="ll-name">'+esc(i.nombre||'Sin nombre')+'</div><div class="ll-rfc">'+esc(i.rfc)+'</div></div>'+
					'</div>'+
					'<div class="ll-tags"><span class="ll-badge">'+(i.tipo==='csd'?'CSD':'e.firma')+'</span>'+vigenciaBadge(i)+'</div>'+
					'<dl class="ll-meta">'+
						(i.curp?'<dt>CURP</dt><dd>'+esc(i.curp)+'</dd>':'')+
						'<dt>No. cert.</dt><dd>'+esc(i.nocert)+'</dd>'+
						'<dt>Vence</dt><dd>'+esc(fmtFecha(i.vence))+'</dd>'+
					'</dl>'+
					'<div class="ll-foot">'+
						'<button class="button primary grow" data-act="login"><span class="mif-enter"></span> Iniciar sesión</button>'+
						'<button class="button" data-act="qr" title="Enviar a otro dispositivo"><span class="mif-qrcode"></span></button>'+
						'<button class="button" data-act="del" title="Eliminar de este navegador"><span class="mif-bin"></span></button>'+
					'</div>'
				);
				$c.on('click','[data-act]',function(){
					var act=$(this).data('act');
					if(act==='login') login(i);
					else if(act==='qr') qrTxOpen(i);
					else if(act==='del') eliminar(i);
				});
				$g.append($c);
			});
	}

	function eliminar(i){
		Swal.fire({
			icon:'warning', title:'¿Eliminar esta llave?',
			html:esc(i.nombre)+'<br><code>'+esc(i.nocert)+'</code><br><small>Solo se borra de este navegador.</small>',
			showCancelButton:true, confirmButtonText:'Eliminar', cancelButtonText:'Cancelar'
		}).then(function(r){
			if(!r.isConfirmed) return;
			cargarLlaves();
			delete llaves[i.nocert];
			Metro.storage.setItem('llaves', llaves);
			render();
		});
	}

	/* ---------- Alta ---------- */

	function addOpen(){
		add={cert:'', key:'', noCert:'', prv:null};
		$('#fielcert,#fielkey').each(function(){
			var p=Metro.getPlugin(this,'file');
			if(p && typeof p.clear==='function') p.clear(); else $(this).val('');
		});
		$('#fielpass').val('');
		$('#ll_preview').hide().empty();
		addStatus('', '');
		$('#ll_add_btn').prop('disabled', true);
		Metro.dialog.open('#DialogAdd');
	}
	function addStatus(cls, txt){ $('#ll_add_status').attr('class','ll-status '+cls).text(txt); }
	function leerArchivo(file){
		return new Promise(function(ok, ko){
			var fr=new FileReader();
			fr.onload=function(){ ok(rstrtohex(fr.result)); };
			fr.onerror=ko;
			fr.readAsBinaryString(file);
		});
	}

	function cargarCert(file){
		add.cert=''; add.noCert='';
		$('#ll_preview').hide().empty();
		if(!file){ revisarAlta(); return; }
		leerArchivo(file).then(function(hex){
			var pem=KJUR.asn1.ASN1Util.getPEMStringFromHex(hex, 'CERTIFICATE');
			var x=new X509(); x.readCertPEM(pem);
			add.cert=pem;
			var i=infoLlave('', {subject:x.getSubjectString(), notAfter:x.getNotAfter()});
			$('#ll_preview').html(
				'<div class="ll-name">'+esc(i.nombre)+'</div><div class="ll-rfc mb-2">'+esc(i.rfc)+'</div>'+
				'<div class="ll-tags mb-2"><span class="ll-badge">'+(i.tipo==='csd'?'CSD':'e.firma')+'</span>'+vigenciaBadge(i)+'</div>'+
				'<dl class="ll-meta">'+(i.curp?'<dt>CURP</dt><dd>'+esc(i.curp)+'</dd>':'')+
				'<dt>No. cert.</dt><dd id="ll_prev_nc">registrando…</dd><dt>Vence</dt><dd>'+esc(fmtFecha(i.vence))+'</dd></dl>'
			).show();
			// El servidor guarda el certificado para validar el inicio de sesión y devuelve su número.
			var fd=new FormData(); fd.append('file', file);
			return $.ajax({url:'/sat/upload.php', type:'post', data:fd, processData:false, contentType:false, dataType:'json'})
				.then(function(r){
					if(!r || r.error || !r.noCert) throw (r && r.error) || 'El servidor no reconoció el certificado.';
					add.noCert=r.noCert;
					$('#ll_prev_nc').text(r.noCert);
					revisarAlta();
				}, function(){ throw 'No se pudo registrar el certificado en el servidor.'; });
		}).catch(function(e){
			add.cert=''; add.noCert='';
			$('#ll_preview').hide();
			addStatus('bad', typeof e==='string' ? e : 'El archivo no es un certificado válido.');
			revisarAlta();
		});
	}
	function cargarKey(file){
		add.key='';
		if(!file){ revisarAlta(); return; }
		leerArchivo(file).then(function(hex){
			add.key=KJUR.asn1.ASN1Util.getPEMStringFromHex(hex, 'ENCRYPTED PRIVATE KEY');
			revisarAlta();
		}).catch(function(){ addStatus('bad','No se pudo leer la llave privada.'); });
	}
	/** Abre la llave con la contraseña y comprueba que corresponda al certificado. */
	function revisarAlta(){
		var pass=$('#fielpass').val();
		add.prv=null;
		$('#ll_add_btn').prop('disabled', true);
		if(!add.cert || !add.key || !pass){
			addStatus('', add.cert && add.key ? 'Escriba la contraseña de la llave privada.' : '');
			return;
		}
		try{ add.prv=KEYUTIL.getKey(add.key, pass); }
		catch(e){ addStatus('bad','Contraseña incorrecta o el archivo .key no es válido.'); return; }
		var reto=KJUR.crypto.Util.getRandomHexOfNbytes(16);
		if(!validar(add.cert, reto, firmar(add.prv, reto))){
			add.prv=null;
			addStatus('bad','La llave privada no corresponde al certificado.');
			return;
		}
		if(!add.noCert){ addStatus('', 'Registrando el certificado…'); return; }
		addStatus('ok','Llave y certificado correctos.');
		$('#ll_add_btn').prop('disabled', false);
	}
	function firmtoadd(){
		if(!add.prv || !add.noCert){ revisarAlta(); return; }
		var x=new X509(); x.readCertPEM(add.cert);
		cargarLlaves();
		var nueva=!(add.noCert in llaves);
		llaves[add.noCert]={key:add.key, cert:add.cert, subject:x.getSubjectString(), notAfter:x.getNotAfter()};
		Metro.storage.setItem('llaves', llaves);
		$('#fielpass').val('');
		add.prv=null;
		Metro.dialog.close('#DialogAdd');
		render();
		toast(nueva ? 'Llave agregada' : 'Llave actualizada');
	}

	function firmar(mprvkey,data){
		var sig=new KJUR.crypto.Signature({'alg':'SHA256withRSA'});
		sig.init(mprvkey);
		sig.updateString(data);
		return sig.sign();
	}
	function validar(mcertPEM,cadenaoriginal,cadenafirmada){
		var sig2=new KJUR.crypto.Signature({'alg':'SHA256withRSA'});
		sig2.init(mcertPEM);
		sig2.updateString(cadenaoriginal);
		return sig2.verify(cadenafirmada);
	}

	/* ---------- Inicio de sesión ---------- */

	function login(i){
		var l=cargarLlaves()[i.nocert];
		if(!l){ render(); return; }
		Swal.fire({
			title:'Iniciar sesión', html:esc(i.nombre)+'<br><small>'+esc(i.rfc)+'</small>',
			customClass:{popup:'ll-swal'},
			input:'password', inputLabel:'Contraseña de la llave privada', inputAttributes:{autocomplete:'off', 'data-nf-noautofill':''},
			showCancelButton:true, confirmButtonText:'Iniciar', cancelButtonText:'Cancelar', showLoaderOnConfirm:true,
			preConfirm:function(pass){
				var prv;
				try{ prv=KEYUTIL.getKey(l.key, pass); }
				catch(e){ Swal.showValidationMessage('Contraseña incorrecta.'); return false; }
				return $.ajax({url:'/sat/logintoken.php', cache:false, dataType:'json'}).then(function(data){
					// validate.php espera el hexadecimal de la firma codificado en base64url.
					var sello=stob64u(firmar(prv, data.token));
					return fetch('/sat/validate.php?nocert='+encodeURIComponent(i.nocert)+'&sello='+encodeURIComponent(sello)).then(function(r){ return r.json(); });
				}).then(function(data){
					if(!data || data.status!=='buena'){ Swal.showValidationMessage((data && data.error) || 'El SAT no validó la firma.'); return false; }
					return data;
				}, function(){ Swal.showValidationMessage('No se pudo contactar al servidor.'); return false; });
			}
		}).then(function(r){ if(r.isConfirmed) window.location.assign('/'); });
	}

	/* ---------- Enviar por QR ---------- */

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
		if(!qrTx.frames.length){
			$('#qr_tx_info').text('No hay datos para enviar.');
			return;
		}
		try{
			if(typeof qrcode!=='function') throw 'no se cargó el generador de QR (qrcode-generator)';
			generateQRCode(qrTx.frames[qrTx.i],'qrcode_tx');
			$('#qr_tx_info').text('Parte '+(qrTx.i+1)+' de '+qrTx.frames.length);
		}catch(e){
			qrTxStop();
			console.error(e);
			$('#qr_tx_info').text('No se pudo generar el QR: '+e);
		}
	}
	function qrTxOpen(i){
		cargarLlaves();
		try{ qrTxPrepare(i.nocert); }catch(e){ console.error(e); qrTx.frames=[]; }
		$('#qr_tx_key').text(i.nombre+' · '+i.rfc);
		Metro.dialog.open('#DialogTransfer');
		qrTxStart();
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

	/* ---------- Recibir por QR ---------- */

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
		Metro.dialog.open('#DialogScan');
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
			cargarLlaves();
			llaves[obj.n]={
				'key': keyPEM,
				'cert': cPEM,
				'subject': x509.getSubjectString(),
				'notAfter': x509.getNotAfter()
			};
			Metro.storage.setItem('llaves', llaves);
			render();
			$('#capturados').text('Llave '+obj.n+' recibida y guardada.');
			qrRxProgress(100);
		}catch(e){
			$('#capturados').text('No se pudo leer la llave recibida: '+e);
		}
	}

	window.onload=function(){
		$('#fielcert').on('change', function(){ cargarCert(this.files[0]); });
		$('#fielkey').on('change', function(){ cargarKey(this.files[0]); });
		$('#fielpass').on('input', revisarAlta);
		render();
		// QRReader enciende la cámara al cargar; aquí solo se usa mientras el diálogo de recepción está abierto.
		if(window.scanner) window.scanner.stop();
	};
</script>
