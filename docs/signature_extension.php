<?php
/**
 * Evento del servidor de la firma de docs/signature.php (opción 'onsave').
 * Recibe la ruta del PNG ya guardado y la configuración del componente; lo que devuelve llega al navegador
 * en result.onresult. Una excepción cancela con su mensaje (el archivo ya quedó escrito).
 */
function firmaGuardada(string $path, array $conf): array
{
	[$ancho, $alto] = getimagesize($path);
	return ['bytes' => filesize($path), 'ancho' => $ancho, 'alto' => $alto];
}
