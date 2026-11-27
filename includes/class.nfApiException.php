<?php

/**
 * Error de la API REST: nfApi lo convierte en la respuesta {"error": {"code", "message", "details"}} con el
 * estado HTTP indicado. Lánzela desde el handler de una ruta de nfApi.
 *
 *   throw new nfApiException(404, 'not_found', 'La nota no existe.');
 *   throw new nfApiException(422, 'validation_failed', 'Revise los campos.', ['titulo' => 'Requerido']);
 */
class nfApiException extends RuntimeException
{
    /** @var int Estado HTTP (400-599). */
    public $status;
    /** @var string Código estable para el cliente (snake_case): not_found, validation_failed... */
    public $errorCode;
    /** @var mixed Datos extra para el cliente (p.ej. errores por campo); null = no se envía. */
    public $details;

    /**
     * @param int $status Estado HTTP; fuera de 400-599 se usa 500.
     * @param string $errorCode Código estable para el cliente.
     * @param string $message Texto para personas.
     * @param mixed $details Datos extra (arreglo serializable a JSON) o null.
     */
    public function __construct($status, $errorCode, $message = '', $details = null)
    {
        $status = (int) $status;
        $this->status = $status >= 400 && $status <= 599 ? $status : 500;
        $this->errorCode = (string) $errorCode;
        $this->details = $details;
        parent::__construct((string) $message, $this->status);
    }
}
