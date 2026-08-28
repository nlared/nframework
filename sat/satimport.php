<?php
if (empty($_GET['_id'])) {
	$newid = new MongoDB\BSON\ObjectID();
	header('Location: ?_id=' . $newid);
	exit();
}
use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\Fiel;
use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\FielRequestBuilder;
use PhpCfdi\SatWsDescargaMasiva\Service;
use PhpCfdi\SatWsDescargaMasiva\WebClient\GuzzleWebClient;
use PhpCfdi\SatWsDescargaMasiva\Services\Query\QueryParameters;
use PhpCfdi\SatWsDescargaMasiva\Shared\DateTimePeriod;
use PhpCfdi\SatWsDescargaMasiva\Services\Verify\VerifyResult;
use PhpCfdi\SatWsDescargaMasiva\Services\Download\DownloadResult;



require '../common2.php';
$dataset = new dataset(
	[
		'collection' => $m->{$config['sitedb']}->exampledata,
		'_id' => $_GET['_id'],
		'simpleid' => false,
		'nameprefix' => 'data'
	]
);
$noobfuscate = true;
$developermode = true;


$dtini = new inputDatetime(['dataset' => &$dataset, 'field' => 'dtini', 'caption' => 'Datetime ini:']);
$dtend = new inputDatetime(['dataset' => &$dataset, 'field' => 'dtend', 'caption' => 'DateTime fin:']);

if ($nframework->isAjax()) {
	if ($_POST['op'] == 'importar') {
		$result = [
			'error' => $dataset->save()
		];
		// Creación de la FIEL, puede leer archivos DER (como los envía el SAT) o PEM (convertidos con openssl)
		$fiel = Fiel::create(
		    file_get_contents('/var/www/datas/'.$user->_id.'.cer'),
		    file_get_contents('/var/www/datas/'.$user->_id.'.key'),
		    $user->fielpassword
		);
		
		// verificar que la FIEL sea válida (no sea CSD y sea vigente acorde a la fecha del sistema)
		if (! $fiel->isValid()) {
		    return;
		}

		// creación del web client basado en Guzzle que implementa WebClientInterface
		// para usarlo necesitas instalar guzzlehttp/guzzle, pues no es una dependencia directa
		$webClient = new GuzzleWebClient();

		// creación del objeto encargado de crear las solicitudes firmadas usando una FIEL
		$requestBuilder = new FielRequestBuilder($fiel);

		// Creación del servicio
		$service = new Service($requestBuilder, $webClient);
		// Configurar zona horaria de México para las consultas SAT
		//date_default_timezone_set('America/Mexico_City');
		// ya implementado en el nframework
		

		// Crear la consulta con fechas más recientes y en zona horaria correcta
		$fechaInicio = $dataset->dtini->toDateTime()->format('Y-m-d H:i:s'); // Fecha más reciente
		$fechaFin = $dataset->dtini->toDateTime()->format('Y-m-d H:i:s');
		
		//echo "Consultando CFDIs del {$fechaInicio} al {$fechaFin} (hora de México)", PHP_EOL; 
		$request = QueryParameters::create(
		    DateTimePeriod::createFromValues($fechaInicio, $fechaFin),
		);
		
		// presentar la consulta
		$query = $service->query($request);
		
		
		// verificar que el proceso de consulta fue correcto
		if (! $query->getStatus()->isAccepted()) {
		    $result['error']= "Fallo al presentar la consulta: {$query->getStatus()->getMessage()}";
		    
		}else{
			$dataset->requestId=$query->getRequestId();
		// el identificador de la consulta está en $query->getRequestId()
		//echo "Se generó la solicitud {$query->getRequestId()}", PHP_EOL;
		}
		
		
		
		
		
		
		
		
		
		$rfc = "RFC del contribuyente"; #Se necesita en todos los procesos, excepto en el login
		$cert = file_get_contents('/var/www/datas/'.$user->_id.'.cer'); #Se necesita en todos los procesos
		$key = file_get_contents('/var/www/datas/'.$user->_id.'.key.pem'); #Se necesita en todos los procesos, es necesario convertir el archivo .key a .pem
		
		$fechaInicial = str_replace(' ','T',$dataset->dtini->toDateTime()->format('Y-m-d H:i:s')); #Solo se necesita al momento de hacer la solicitud, se debe respetar el formato
		$fechaFinal = str_replace(' ','T',$dataset->dtend->toDateTime()->format('Y-m-d H:i:s')); #Solo se necesita al momento de hacer la solicitud, se debe respetar el formato
		$TipoSolicitud = "CFDI"; #Solo se necesita al momento de hacer la solicitud, el valor solo puede ser: CFDI o Metadata
		$TipoConsulta = "Emitidos"; #Solo se necesita al momento de hacer la solicitud, el valor solo puede ser: Emitidos o Recibidos
		
		if ($TipoConsulta === "Emitidos") { #If que envia a solicitar.php el tipo de consulta a requerir
		    $TipoConsulta = "RfcEmisor";
		} elseif ($TipoConsulta === "Recibidos") {
		    $TipoConsulta = "RfcReceptor";
		}
		
		/*
		$idSolicitud = "id proporcionado por el SAT al momento de recibir tu solicitud."; #Solo se necesita al momento de verificar la solicitud realizada
		$idPaquete = "Lo proporciona el SAT al momento de verificar que este lista la solicitud para descargar"; #Solo se necesita al momento de descargar los paquetes (archivos zip)
		*/
		##### Ejemplos de uso #####
		$result['user']=$user;
		$result['dtini']=$fechaInicial;
		$result['dtend']=$fechaFinal;
		
		$Login = Login::soapRequest($cert, $key);
		$result['p1']=$Login; #Si todo sale bien, nos devolverá un Token de que inicio bien la sesión
		if(empty($dataset->solicitud)){
			$idSolicitud = Solicitar::soapRequest($cert, $key, $Login->token, $user['rfc'], $fechaInicial, $fechaFinal, $TipoSolicitud);
			$result['p2']=$idSolicitud; #Si todo sale bien, nos va a devolver un ID de solicitud, la cual tenemos que guardar para recuperar la información
			$dataset->solicitud=$idSolicitud;
		}else{
			$idSolicitud=$dataset->solicitud;
		}
		
		$Verificar = Verificar::soapRequest($cert, $key, $Login->token, $user['rfc'], $idSolicitud);
		$result['p3']=$Verificar; #Si la solicitud esta terminada, nos debe de regresar el codigo 3 que significa que esta lista para descargar el paquete o los paquetes zip
		
		$Descargar = Descargar::soapRequest($cert, $key, $Login->token, $user['rfc'], $idPaquete);
		Complemento::saveBase64File($Descargar->Paquete, $idPaquete . ".zip");
		$result['p4']=$Descargar; #Si todo sale bien, el paquete o los paquetes se deben de guardar con terminacion.zip		
		
	}
} else {
	$nframework->usecommon = true;
?>

	<div class="container p-5">
		<div class="box shadow-large">
			<div class="box-title">Importar</div>
			<?= secureform() ?>
			<div class="grid">
				<div class="row">
					<div class="cell"><?= $dtini ?></div>
					<div class="cell"><?= $dtend ?></div>
				</div>
				<div class="row">
					<div class="cell-md-2 offset-md-8"><a href="datatableajax.php" class="button primary btn btn-primary w-100"><span class="mif-exit"></span>&nbsp;Cerrar</a></div>
					<div class="cell-md-2"><button class="button success btn btn-success secureop  w-100" value="importar"><span class="mif-floppy-disk"></span>&nbsp;Guardar</button></div>
				</div>
			</div>
			</form>
		</div>
	</div>
<?}?>