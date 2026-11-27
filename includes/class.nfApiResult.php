<?php

/**
 * Respuesta de un handler de nfApi con estado, metadatos o cabeceras propios. Un handler puede devolver
 * los datos directamente (200 {"data": ...}) o null (204); use esta clase para lo demás.
 *
 *   return new nfApiResult($nota, 201, [], ['Location' => '/api/v1/notas/' . $id]);
 *   return nfApi::paginated($items, $total, $pagina);   // nfApiResult con meta de paginación
 */
class nfApiResult
{
    /** @var mixed Va en "data". */
    public $data;
    /** @var int Estado HTTP. */
    public $status;
    /** @var array<string, mixed> Va en "meta" (se omite si está vacío). */
    public $meta;
    /** @var array<string, string> Cabeceras extra (nombre => valor). */
    public $headers;

    /**
     * @param mixed $data
     * @param int $status
     * @param array<string, mixed> $meta
     * @param array<string, string> $headers
     */
    public function __construct($data = null, $status = 200, $meta = [], $headers = [])
    {
        $this->data = $data;
        $this->status = (int) $status;
        $this->meta = (array) $meta;
        $this->headers = (array) $headers;
    }
}
