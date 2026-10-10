<?php
/*
 * Extensión de carga usada por files.php: uploadfile.php incluye este archivo y llama a las
 * funciones indicadas en 'onupload' y 'ondelete' con la ruta del archivo y la configuración del control.
 * Devuelven un valor que se envía al navegador en `onresult`.
 */

/**
 * Se ejecuta después de guardar cada archivo subido.
 */
function subir(string $archivo, array $opciones)
{
	// Ejemplo: registrar la carga y devolver datos al navegador.
	file_put_contents(__DIR__ . '/tmp/cargas.log', date('c') . ' subido ' . basename($archivo) . "\n", FILE_APPEND);
	return ['nombre' => basename($archivo), 'bytes' => filesize($archivo)];
}

/**
 * Reemplaza el borrado estándar: si se define 'ondelete', esta función debe eliminar el archivo.
 */
function borrar(string $archivo, array $opciones)
{
	if (!is_file($archivo) || !unlink($archivo)) {
		return false;
	}
	file_put_contents(__DIR__ . '/tmp/cargas.log', date('c') . ' borrado ' . basename($archivo) . "\n", FILE_APPEND);
	return true;
}
