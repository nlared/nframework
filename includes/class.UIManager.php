<?php

class UIManager
{
    private array $css = [];
    private array $js = [];
    private array $metas = [];
    private string $title = '';
    private string $lang = 'en';

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function setLang(string $lang): void
    {
        $this->lang = $lang;
    }

    public function addCss(string $id, string $url, array $attributes = []): void
    {
        $this->css[$id] = [
            'url' => $url,
            'attributes' => $attributes
        ];
    }

    public function addJs(string $id, string $url, array $attributes = []): void
    {
        $this->js[$id] = [
            'url' => $url,
            'attributes' => $attributes
        ];
    }

    public function addMeta(string $name, string $content, array $attributes = []): void
    {
        $this->metas[$name] = [
            'content' => $content,
            'attributes' => $attributes
        ];
    }

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
