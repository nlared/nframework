<?php

/**
 * Registro de CSS, JS y metas del <head> con atributos, disponible como $nframework->ui. syncFromArrays()
 * incorpora lo definido con $nframework->csss / jss.
 */
class UIManager
{
    /** @var array<string, array{url: string, attributes: array<string, string>}> */
    private array $css = [];
    /** @var array<string, array{url: string, attributes: array<string, string>}> */
    private array $js = [];
    /** @var array<string, array{content: string, attributes: array<string, string>}> */
    private array $metas = [];
    private string $title = '';
    private string $lang = 'en';

    /**
     * @param string $title
     */
    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    /**
     * @param string $lang
     */
    public function setLang(string $lang): void
    {
        $this->lang = $lang;
    }

    /**
     * @param string $id Clave de orden.
     * @param string $url
     * @param array<string, string> $attributes Atributos extra del <link>.
     */
    public function addCss(string $id, string $url, array $attributes = []): void
    {
        $this->css[$id] = [
            'url' => $url,
            'attributes' => $attributes
        ];
    }

    /**
     * @param string $id Clave de orden.
     * @param string $url
     * @param array<string, string> $attributes Atributos extra del <script>.
     */
    public function addJs(string $id, string $url, array $attributes = []): void
    {
        $this->js[$id] = [
            'url' => $url,
            'attributes' => $attributes
        ];
    }

    /**
     * @param string $name
     * @param string $content
     * @param array<string, string> $attributes
     */
    public function addMeta(string $name, string $content, array $attributes = []): void
    {
        $this->metas[$name] = [
            'content' => $content,
            'attributes' => $attributes
        ];
    }

    /**
     * HTML de metas, título y hojas de estilo para el <head>.
     */
    public function renderHead(): string
    {
        $html = '';

        // Metas
        foreach ($this->metas as $name => $meta) {
            $attrStr = '';
            foreach ($meta['attributes'] as $k => $v) {
                $attrStr .= ' ' . $k . '="' . htmlspecialchars($v) . '"';
            }
            if (!empty($name) && !isset($meta['attributes']['property'])) {
                $html .= '<meta name="' . $name . '" content="' . htmlspecialchars($meta['content']) . '"' . $attrStr . '>' . "\n";
            } else {
                // Open graph or custom
                $html .= '<meta content="' . htmlspecialchars($meta['content']) . '"' . $attrStr . '>' . "\n";
            }
        }

        // CSS
        ksort($this->css);
        foreach ($this->css as $id => $item) {
            $attrStr = '';
            foreach ($item['attributes'] as $k => $v) {
                $attrStr .= ' ' . $k . '="' . htmlspecialchars($v) . '"';
            }
            $html .= '<link rel="stylesheet" href="' . $item['url'] . '"' . $attrStr . '>' . "\n";
        }

        return $html;
    }

    /**
     * HTML de los <script> para el final del <body>.
     */
    public function renderFooter(): string
    {
        $html = '';

        // JS
        ksort($this->js);
        foreach ($this->js as $id => $item) {
            $attrStr = '';
            foreach ($item['attributes'] as $k => $v) {
                $attrStr .= ' ' . $k . '="' . htmlspecialchars($v) . '"';
            }
            $html .= '<script src="' . $item['url'] . '"' . $attrStr . '></script>' . "\n";
        }

        return $html;
    }

    // Backward compatibility helper
    /**
     * Agrega las URLs de $nframework->csss / jss que no estén registradas (compatibilidad).
     *
     * @param array<string, string> $csss
     * @param array<string, string> $jss
     */
    public function syncFromArrays(array $csss, array $jss): void
    {
        foreach ($csss as $id => $url) {
            if (!isset($this->css[$id])) {
                $this->addCss((string) $id, $url);
            }
        }
        foreach ($jss as $id => $url) {
            if (!isset($this->js[$id])) {
                $this->addJs((string) $id, $url);
            }
        }
    }
}
