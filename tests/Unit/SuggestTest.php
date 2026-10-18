<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * inputText con 'suggest' y el helper compartido nfTomSelectAjax().
 */
class SuggestTest extends TestCase
{
    private array $js = [];
    private array $saved = [];

    protected function setUp(): void
    {
        foreach (['config', 'nframework', 'javas'] as $name) {
            $this->saved[$name] = $GLOBALS[$name] ?? null;
        }
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
        $this->js = [];
        $js = &$this->js;

        $GLOBALS['config'] = ['sitedb' => 'sitio'];
        $GLOBALS['nframework'] = new \stdClass();
        $GLOBALS['nframework']->javasonce = [];
        $GLOBALS['nframework']->csss = [];
        $GLOBALS['nframework']->jss = [];
        $GLOBALS['javas'] = new class ($js) {
            public function __construct(private array &$js)
            {
            }

            public function addjs($code, $section = 'general'): void
            {
                $this->js[] = $code;
            }
        };
    }

    protected function tearDown(): void
    {
        foreach ($this->saved as $name => $value) {
            $GLOBALS[$name] = $value;
        }
    }

    public function testStaticListIsSentToTheBrowserEscaped(): void
    {
        $html = (string) new \inputText(['name' => 'fruta', 'suggest' => ['Manzana', '</script>']]);

        $this->assertStringContainsString('data-nf-noautofill="true"', $html);
        $this->assertArrayHasKey('nfSuggest', $GLOBALS['nframework']->javasonce);
        $this->assertCount(1, $this->js);
        $this->assertStringContainsString('nfSuggest(document.getElementById("fruta")', $this->js[0]);
        $this->assertStringContainsString('"items":["Manzana","\u003C/script\u003E"]', $this->js[0]);
        $this->assertStringNotContainsString('</script>', $this->js[0]);
        $this->assertArrayNotHasKey('selectajax', $_SESSION);
    }

    public function testMongoSuggestStoresQueryInSessionOnly(): void
    {
        (string) new \inputText(['name' => 'cliente', 'suggest' => [
            'collection' => 'clientes',
            'value' => '$nombre',
            'distinct' => true,
            'depends' => ['ciudad' => '#ciudad'],
        ]]);

        $info = $_SESSION['selectajax']['cliente'];
        $this->assertTrue($info['suggest']);
        $this->assertSame('sitio', $info['db']);
        $this->assertSame(['nombre'], $info['columns']);
        $this->assertSame('$nombre', $info['label']);
        $this->assertSame(['ciudad'], $info['args']);
        $this->assertSame(20, $info['limit']);
        $this->assertStringContainsString('"url":"/nframework/select_ajax.php?id=cliente"', $this->js[0]);
        $this->assertStringNotContainsString('clientes', $this->js[0]);
    }

    public function testUrlAndOnselect(): void
    {
        (string) new \inputText(['name' => 'lenguaje', 'suggest' => ['url' => '?op=lenguajes', 'onselect' => 'console.log(item);']]);

        $this->assertStringContainsString('"url":"?op=lenguajes"', $this->js[0]);
        $this->assertStringContainsString('function (item, input) {console.log(item);}', $this->js[0]);
    }

    public function testScriptIsRegisteredOnce(): void
    {
        (string) new \inputText(['name' => 'a', 'suggest' => ['x']]);
        $GLOBALS['nframework']->javasonce['nfSuggest'] = 'marcado';
        (string) new \inputText(['name' => 'b', 'suggest' => ['y']]);

        $this->assertSame('marcado', $GLOBALS['nframework']->javasonce['nfSuggest']);
    }

    public function testTomSelectAcceptsSelectAjaxOptions(): void
    {
        $ajax = new \SelectAjaxOptions();
        $ajax->db = 'sitio';
        $ajax->collection = 'countrys';
        $ajax->columns = ['name'];
        $ajax->label = '$name';
        $ajax->value = '$iso2';

        $html = (string) new \Select(['name' => 'pais', 'value' => 'MX', 'ajax' => $ajax]);

        $this->assertStringContainsString('data-role="tomselect"', $html);
        $this->assertSame('countrys', $_SESSION['selectajax']['pais']['collection']);
        $this->assertCount(2, $this->js);
        $this->assertStringContainsString('["MX"].forEach', $this->js[1]);
    }
}
