<?php

/**
 * Migas de pan a partir de la ruta del script actual (PHP_SELF): un enlace por carpeta y el nombre del archivo
 * sin extensión como último elemento (se omite index.php).
 *
 *   echo new BreadCrumbs();
 */
class BreadCrumbs
{
    /** @var string URL base de los enlaces ('/'). */
    public $root;
    /** @var string Ruta del script sin la '/' inicial. */
    public $self;
    /**
     * @param array<string, mixed> $options Se acepta por compatibilidad; hoy no se usa.
     */
    public function __construct($options = [])
    {

        if (empty($this->root)) {
            $this->root = '/';
            $this->self = substr($_SERVER['PHP_SELF'], 1);
        }
    }

    /**
     * @return string HTML (<ul class="breadcrumbs">).
     */
    public function __toString(): string
    {
        $crumbs = '<li class="page-item"><a href="' . $this->root . '" class="page-link"><span class="mif-meter"></span></a></li>';
        $parts = explode('/', $this->self);
        $cparts = count($parts) - 1;
        foreach ($parts as $i => $part) {
            if ($part != 'index.php') {
                $title = ($i == $cparts ? strstr($part, '.', true) : $part);
                $title = ucwords($title);
                $crumbs .= '<li"><a href="' . $this->root . $add . $part . '" class="page-link">' . $title . '</a></li>';
                $add = $part . '/';
            }
        }
        return '<ul class="breadcrumbs default p-4 border bd-default">' . $crumbs . '</ul>';
    }
}
