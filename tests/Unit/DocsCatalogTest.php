<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * docs/components.json: catálogo de páginas de /docs y de componentes. Lo leen docs/common2.php
 * (menú lateral y portada) y agentes de IA, así que debe coincidir con los archivos y clases reales.
 */
class DocsCatalogTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    private static function catalog(): array
    {
        return json_decode(file_get_contents(self::ROOT . '/docs/components.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    public function testEveryPageExistsAndIsListedOnce(): void
    {
        $files = [];
        foreach (self::catalog()['sections'] as $section) {
            $this->assertNotEmpty($section['section']);
            foreach ($section['pages'] as $page) {
                foreach (['file', 'title', 'icon', 'description'] as $key) {
                    $this->assertNotEmpty($page[$key] ?? null, "Página sin '$key' en {$section['section']}");
                }
                $this->assertFileExists(self::ROOT . '/docs/' . $page['file']);
                $files[] = $page['file'];
            }
        }
        $this->assertSame(array_unique($files), $files, 'Hay páginas repetidas en el catálogo');
    }

    public function testEveryDocsPageIsInTheCatalog(): void
    {
        $listed = [];
        foreach (self::catalog()['sections'] as $section) {
            $listed = array_merge($listed, array_column($section['pages'], 'file'));
        }
        // Páginas auxiliares que no son ejemplos.
        $auxiliary = ['common.php', 'common2.php', 'signature_extension.php', 'upload_extension.php'];
        foreach (glob(self::ROOT . '/docs/*.php') as $path) {
            $file = basename($path);
            if (!in_array($file, $auxiliary, true)) {
                $this->assertContains($file, $listed, "docs/$file no está en docs/components.json");
            }
        }
    }

    public function testLlmsTxtListsEveryPage(): void
    {
        $llms = file_get_contents(self::ROOT . '/llms.txt');
        $this->assertStringStartsWith("# nframework\n", $llms);
        $this->assertStringContainsString('(/AGENTS.md)', $llms);
        $this->assertStringContainsString('(/docs/components.json)', $llms);
        foreach (self::catalog()['sections'] as $section) {
            $this->assertStringContainsString("\n## {$section['section']}\n", $llms, "llms.txt sin la sección {$section['section']}");
            foreach ($section['pages'] as $page) {
                $this->assertStringContainsString("(/docs/{$page['file']})", $llms, "llms.txt no enlaza docs/{$page['file']}");
            }
        }
    }

    public function testEveryComponentPointsToItsClassAndPage(): void
    {
        $classes = [];
        foreach (self::catalog()['components'] as $component) {
            $class = $component['class'];
            $this->assertNotEmpty($component['summary'], "$class sin 'summary'");
            $this->assertContains($component['category'], ['formulario', 'datos', 'interfaz', 'backend'], "$class: categoría desconocida");
            $source = self::ROOT . '/' . $component['file'];
            $this->assertFileExists($source, "$class: no existe {$component['file']}");
            $this->assertMatchesRegularExpression('/^\s*(abstract |final )?class ' . preg_quote($class, '/') . '\b/m', file_get_contents($source), "$class no se define en {$component['file']}");
            if ($component['doc'] !== null) {
                $this->assertFileExists(self::ROOT . '/docs/' . $component['doc'], "$class: no existe docs/{$component['doc']}");
            }
            $classes[] = $class;
        }
        $this->assertSame(array_unique($classes), $classes, 'Hay componentes repetidos en el catálogo');
    }
}
