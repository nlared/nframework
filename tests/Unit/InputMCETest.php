<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * inputMCE: dónde se pueden guardar las imágenes del editor y que no se sobrescriban.
 */
class InputMCETest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        $this->tmp = sys_get_temp_dir() . '/nf_mce_' . bin2hex(random_bytes(4));
        mkdir($this->tmp . '/fw/nframework', 0777, true);
        mkdir($this->tmp . '/fw/docs/tmp', 0777, true);
        mkdir($this->tmp . '/sitio/media', 0777, true);
    }

    protected function tearDown(): void
    {
        delTree($this->tmp);
    }

    public function testUploadDirRejectsFrameworkAndInvalidFolders(): void
    {
        $fw = $this->tmp . '/fw';
        $real = realpath($this->tmp);
        foreach ([null, '', 'media/', '/', $fw, $fw . '/', $fw . '/nframework', $fw . '/nframework/sub/', $fw . '/includes/x',
            $fw . '/admin', $fw . '/sat/certs', $this->tmp . '/sitio/../fw/nframework', "/tmp/a\0b"] as $dir) {
            $this->assertNull(\inputMCE::uploadDir($dir, $fw), var_export($dir, true));
        }
        $this->assertSame($real . '/sitio/media/', \inputMCE::uploadDir($this->tmp . '/sitio/media', $fw));
        $this->assertSame($real . '/sitio/media/2026/11/', \inputMCE::uploadDir($this->tmp . '/sitio/media/2026/11', $fw), 'Puede no existir aún');
        $this->assertSame($real . '/fw/docs/tmp/', \inputMCE::uploadDir($fw . '/docs/tmp/', $fw));
    }

    public function testUploadDirFollowsSymlinksIntoTheFramework(): void
    {
        $link = $this->tmp . '/sitio/atajo';
        symlink($this->tmp . '/fw/nframework', $link);
        $this->assertNull(\inputMCE::uploadDir($link . '/', $this->tmp . '/fw'));
    }

    public function testUploadProblemExplainsWhatIsMissing(): void
    {
        $fw = $this->tmp . '/fw';
        $media = $this->tmp . '/sitio/media/';
        $this->assertNull(\inputMCE::uploadProblem(false, null, null, $fw), 'Sin upload no hay nada que avisar');
        $this->assertNull(\inputMCE::uploadProblem(true, $media, '/media/', $fw));
        $this->assertStringStartsWith('Falta mediadir', \inputMCE::uploadProblem(true, '', '/media/', $fw));
        $this->assertStringContainsString('ruta absoluta', \inputMCE::uploadProblem(true, 'media/', '/media/', $fw));
        $this->assertStringContainsString('carpeta del framework', \inputMCE::uploadProblem(true, $fw . '/nframework/', '/x/', $fw));
        $this->assertStringStartsWith('Falta baseurl', \inputMCE::uploadProblem(true, $media, '', $fw));
    }

    /** @return array{0: string, 1: list<string>} HTML y JS generados. */
    private function render(array $options): array
    {
        $_SERVER['HTTP_HOST'] ??= 'localhost';
        $GLOBALS['nframework'] = new class {
            public array $csss = [];
            public array $jss = [];
            public string $lang = 'es-MX';
            public string $lang_ = 'es';
            public array $language = [];
        };
        $GLOBALS['javas'] = new class {
            public array $js = [];
            public function addjs($code, $section = 'general'): void
            {
                $this->js[] = $code;
            }
        };
        $html = (string) new \inputMCE($options + ['name' => 'html', 'id' => 'mce']);
        return [$html, $GLOBALS['javas']->js];
    }

    public function testEditorWarnsWhenUploadIsNotConfigured(): void
    {
        $saved = ini_set('error_log', '/dev/null');
        try {
            [$html, $js] = $this->render(['upload' => true, 'mediadir' => '', 'baseurl' => '/media/']);
        } finally {
            ini_set('error_log', (string) $saved);
        }
        $this->assertStringContainsString('remark warning', $html);
        $this->assertStringContainsString('no está configurada', $html, 'Sin developermode no se muestran rutas');
        $code = implode("\n", $js);
        $this->assertStringNotContainsString('images_upload_url', $code);
        $this->assertStringContainsString('paste_data_images: false', $code);
        $this->assertStringContainsString("editor.on('paste', nfNoImages)", $code);
        $this->assertStringContainsString('/^image\\//', $code, 'Expresión regular válida en JS');
        $this->assertArrayNotHasKey('mce', $_SESSION['tinymceup'] ?? []);
    }

    public function testEditorWithValidUploadHasNoWarning(): void
    {
        [$html, $js] = $this->render(['upload' => true, 'mediadir' => $this->tmp . '/sitio/media/', 'baseurl' => '/media/']);
        $this->assertStringNotContainsString('remark warning', $html);
        $this->assertStringContainsString('images_upload_url', implode("\n", $js));
        $this->assertStringNotContainsString('nfNoImages', implode("\n", $js));
    }

    public function testUniqueFilenameNeverOverwrites(): void
    {
        $dir = $this->tmp . '/sitio/media/';
        $this->assertSame('mceclip0.jpg', \inputMCE::uniqueFilename($dir, 'mceclip0.jpg'));
        touch($dir . 'mceclip0.jpg');
        touch($dir . 'mceclip0-1.jpg');
        $this->assertSame('mceclip0-2.jpg', \inputMCE::uniqueFilename($dir, 'mceclip0.jpg'));
    }
}
