<?php

/**
 * Migas de pan a partir de la ruta del script actual (PHP_SELF): un enlace por carpeta y el nombre del archivo
 * sin extensión como último elemento (se omite index.php).
 *
 *   echo new BreadCrumbs();
 *   echo new BreadCrumbs(['root' => '/panel/', 'self' => 'reportes/ventas.php']);
 */
class BreadCrumbs
{
    /** @var string URL base de los enlaces ('/'). */
    public $root;
    /** @var string Ruta del script sin la '/' inicial. */
    public $self;

    /**
     * @param array{root?: string, self?: string} $options
     */
    public function __construct($options = [])
    {
        foreach ((array) $options as $option => $value) {
            if (property_exists($this, $option)) {
                $this->{$option} = $value;
            }
        }
        if (empty($this->root)) {
            $this->root = '/';
        }
        if ($this->self === null) {
            $this->self = ltrim((string) ($_SERVER['PHP_SELF'] ?? ''), '/');
        }
    }

    /**
     * @return string HTML (<ul class="breadcrumbs">).
     */
    public function __toString(): string
    {
        $e = fn($text) => htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
        $crumbs = '<li class="page-item"><a href="' . $e($this->root) . '" class="page-link"><span class="mif-meter"></span></a></li>';
        $parts = array_values(array_filter(explode('/', (string) $this->self), fn($part) => $part !== ''));
        $last = count($parts) - 1;
        $path = '';
        foreach ($parts as $i => $part) {
            // Ruta acumulada: antes cada enlace solo llevaba la carpeta anterior.
            $path .= ($path === '' ? '' : '/') . $part;
            if ($part === 'index.php') {
                continue;
            }
            $title = ucwords($i === $last ? pathinfo($part, PATHINFO_FILENAME) : $part);
            $crumbs .= '<li class="page-item"><a href="' . $e($this->root . $path) . '" class="page-link">' . $e($title) . '</a></li>';
        }
        return '<ul class="breadcrumbs default p-4 border bd-default">' . $crumbs . '</ul>';
    }
}
