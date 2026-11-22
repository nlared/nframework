<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/CoreFixesTest.php';   // FakeCollection

/**
 * Esquema de Admin → nftables: validación de campos, compatibilidad con los documentos viejos y lo que se
 * genera para /nftables/ (controles, columnas, pipeline, filtros) sin MongoDB.
 */
class NfTableSchemaTest extends TestCase
{
    private static function field(array $raw, array &$errors = [], bool $strict = true): ?array
    {
        return \nfTableSchema::normalizeField($raw, $errors, false, $strict);
    }

    public function testEveryTypeIsAFrameworkClass(): void
    {
        foreach (array_keys(\nfTableSchema::TYPES) as $type) {
            $file = dirname(__DIR__, 2) . '/includes/class.' . ($type === 'inputaddress' ? 'address' : $type) . '.php';
            $this->assertTrue(class_exists($type) || is_file($file), "$type no es una clase del framework");
        }
        foreach (\nfTableSchema::TYPES as $type => $def) {
            foreach (array_keys($def['options']) as $option) {
                if (in_array($option, ['default', 'readonly', 'placeholder', 'pattern', 'validate', 'prepend', 'append'], true) || !class_exists($type)) {
                    continue;   // propiedades de baseInput
                }
                $this->assertTrue(property_exists($type, $option), "$type no tiene la propiedad $option");
            }
        }
    }

    public function testLegacyDocumentsAreRead(): void
    {
        $fields = \nfTableSchema::normalizeFields([
            ['field' => 'nombre', 'type' => 'inputtext', 'short_description' => 'Nombre', 'required' => '1'],
            ['field' => 'tags', 'type' => 'inputcheckboxes', 'short_description' => 'Etiquetas'],
            ['field' => 'x', 'type' => 'desconocido'],
            ['field' => '$where', 'type' => 'inputtext'],
        ]);
        $this->assertSame(['nombre', 'tags', 'x'], array_column($fields, 'field'));
        $this->assertSame('inputText', $fields[0]['type']);
        $this->assertTrue($fields[0]['required']);
        $this->assertSame('inputCheckBoxs', $fields[1]['type']);
        $this->assertSame('inputText', $fields[2]['type'], 'Un tipo desconocido guardado se lee como texto');
    }

    public function testStrictValidationReportsProblems(): void
    {
        $errors = [];
        $fields = \nfTableSchema::normalizeFields([
            ['field' => 'a', 'type' => 'inputText'],
            ['field' => 'a', 'type' => 'inputText'],
            ['field' => 'b', 'type' => 'nope'],
            ['field' => 'c.d', 'type' => 'inputText'],
            ['field' => 'c', 'type' => 'inputText'],
            ['field' => 'e', 'type' => 'Select'],
            ['field' => '_id', 'type' => 'inputText'],
        ], $errors, false, true);
        $this->assertSame(['a', 'c.d', 'e'], array_column($fields, 'field'));
        $text = implode("\n", $errors);
        $this->assertStringContainsString('a está repetido', $text);
        $this->assertStringContainsString('b: tipo desconocido', $text);
        $this->assertStringContainsString('se encimarían', $text);
        $this->assertStringContainsString('e: agregue las opciones', $text);
        $this->assertStringContainsString('_id', $text);
    }

    public function testOptionsAreWhitelistedAndCast(): void
    {
        $f = self::field(['field' => 'precio', 'type' => 'inputNumber', 'options' => [
            'min' => '0', 'max' => '100.5', 'step' => '0.01', 'onChange' => 'alert(1)', 'uppercase' => '1', 'readonly' => 'on',
        ]]);
        $this->assertSame(['min' => 0, 'max' => 100.5, 'step' => 0.01, 'readonly' => true], $f['options']);

        $opts = \nfTableSchema::componentOptions($f);
        $this->assertTrue($opts['backreadonly'], 'Solo lectura también en el servidor');
        $this->assertSame('float', $opts['data_validate']);
        $this->assertArrayNotHasKey('onChange', $opts);

        $errors = [];
        self::field(['field' => 'rfc', 'type' => 'inputText', 'options' => ['pattern' => '([A-Z']], $errors);
        $this->assertStringContainsString('patrón', $errors[0]);
    }

    public function testCaptionIsEscaped(): void
    {
        $opts = \nfTableSchema::componentOptions(self::field(['field' => 'n', 'type' => 'inputText', 'short_description' => '<img src=x>']));
        $this->assertSame('&lt;img src=x&gt;', $opts['caption']);
    }

    public function testItemsFromTextAndArrays(): void
    {
        $this->assertSame(
            [['value' => 'a', 'label' => 'Activo'], ['value' => 'Baja', 'label' => 'Baja']],
            \nfTableSchema::parseItems("a=Activo\n\n Baja \na=Repetido")
        );
        $this->assertSame([['value' => 'x', 'label' => 'Equis']], \nfTableSchema::parseItems(['x' => 'Equis']));
        $this->assertSame([['value' => 'x', 'label' => 'x']], \nfTableSchema::parseItems(['x']));

        $f = self::field(['field' => 'estado', 'type' => 'Select', 'items' => "a=Activo\nb=Baja"]);
        $this->assertSame(['a' => 'Activo', 'b' => 'Baja'], \nfTableSchema::componentOptions($f)['options']);
    }

    public function testSelectAjaxFromCollection(): void
    {
        $f = self::field(['field' => 'cliente', 'type' => 'Select', 'ajax' => [
            'collection' => 'clientes', 'label' => 'nombre, rfc', 'value' => '', 'columns' => '', 'match' => '{"activo":true,"$where":"1"}', 'limit' => '500',
        ]]);
        $this->assertSame(['collection' => 'clientes', 'label' => ['nombre', 'rfc'], 'value' => '_id', 'columns' => ['nombre', 'rfc'],
            'match' => '{"activo":true}', 'limit' => 100], $f['ajax']);
        $this->assertArrayNotHasKey('items', $f);

        $opts = \nfTableSchema::componentOptions($f, ['db' => 'sitio']);
        $this->assertSame(\Select::formatMongoID, $opts['format'], '_id se guarda como ObjectId');
        $this->assertSame('sitio', $opts['ajax']['db']);
        $this->assertSame(['$toString' => '$_id'], $opts['ajax']['value'], 'El valor viaja como texto para cargar el inicial');
        $this->assertSame(['$match' => ['activo' => true]], $opts['ajax']['pipeline'][0]);
        $this->assertSame(['$concat' => [['$ifNull' => [['$toString' => '$nombre'], '']], ' · ', ['$ifNull' => [['$toString' => '$rfc'], '']]]], $opts['ajax']['label']);
    }

    public function testSelectAjaxRejectsSensitiveSources(): void
    {
        foreach ([
            ['collection' => 'sessions', 'label' => 'data'],
            ['collection' => 'users', 'label' => 'password'],
            ['collection' => 'system.users', 'label' => 'user'],
            ['collection' => 'clientes', 'label' => 'nombre', 'value' => '$where'],
        ] as $ajax) {
            $errors = [];
            $f = self::field(['field' => 'x', 'type' => 'Select', 'ajax' => $ajax], $errors);
            $this->assertArrayNotHasKey('ajax', $f, json_encode($ajax));
            $this->assertNotEmpty($errors, json_encode($ajax));
        }
    }

    public function testSuggestModes(): void
    {
        $self = self::field(['field' => 'ciudad', 'type' => 'inputText', 'suggest' => ['mode' => 'self']]);
        $opts = \nfTableSchema::componentOptions($self, ['db' => 'sitio', 'collection' => 'clientes']);
        $this->assertSame(['db' => 'sitio', 'collection' => 'clientes', 'columns' => ['ciudad'], 'value' => '$ciudad', 'distinct' => true, 'limit' => 20], $opts['suggest']);

        $other = self::field(['field' => 'puesto', 'type' => 'inputText', 'suggest' => ['mode' => 'collection', 'collection' => 'puestos', 'column' => 'nombre']]);
        $this->assertSame('puestos', \nfTableSchema::componentOptions($other, ['db' => 'sitio'])['suggest']['collection']);

        $list = self::field(['field' => 'color', 'type' => 'inputText', 'suggest' => ['mode' => 'list', 'items' => "rojo\nazul"]]);
        $this->assertSame(['items' => ['rojo', 'azul'], 'limit' => 20], \nfTableSchema::componentOptions($list)['suggest']);

        $none = self::field(['field' => 'n', 'type' => 'inputText', 'suggest' => ['mode' => '']]);
        $this->assertArrayNotHasKey('suggest', $none);
    }

    public function testEmbeddedArraySubfields(): void
    {
        $errors = [];
        $f = self::field(['field' => 'telefonos', 'type' => 'embededArray', 'short_description' => 'Teléfonos', 'required' => true, 'subfields' => [
            ['field' => 'numero', 'type' => 'inputText', 'short_description' => 'Número', 'required' => true],
            ['field' => 'tipo', 'type' => 'Select', 'items' => "cel=Celular\ncasa=Casa"],
            ['field' => 'mapa', 'type' => 'mapmarker'],
            ['field' => 'a.b', 'type' => 'inputText'],
        ]], $errors);
        $this->assertSame(['numero', 'tipo'], array_column($f['subfields'], 'field'));
        $this->assertFalse($f['required']);
        $this->assertCount(2, $errors, implode("\n", $errors));
        $this->assertArrayNotHasKey('ajax', $f['subfields'][1]);

        $errors = [];
        self::field(['field' => 'vacio', 'type' => 'embededArray'], $errors);
        $this->assertStringContainsString('al menos un campo', $errors[0]);
    }

    public function testEmbeddedNamesCannotCollideWithPageControls(): void
    {
        $errors = [];
        \nfTableSchema::normalizeFields([
            ['field' => 'numero', 'type' => 'inputText'],
            ['field' => 'telefonos', 'type' => 'embededArray', 'subfields' => [['field' => 'numero', 'type' => 'inputText']]],
        ], $errors, false, true);
        $this->assertStringContainsString('chocan', implode("\n", $errors));
    }

    public function testEmbeddedTemplateIsSafeTwig(): void
    {
        $f = self::field(['field' => 'items', 'type' => 'embededArray', 'subfields' => [
            ['field' => 'tipo', 'type' => 'Select', 'short_description' => "{{ _self }}'", 'items' => "a={% debug %}\nb=<b>O'Brien</b>"],
            ['field' => 'activo', 'type' => 'inputCheckBox'],
        ]]);
        $template = \nfTableSchema::embeddedTemplate($f);
        $twig = new \Twig\Environment(new \Twig\Loader\ArrayLoader(['t' => $template]), ['autoescape' => 'html']);
        $html = $twig->render('t', ['items' => [['tipo' => 'b', 'activo' => true], ['tipo' => 'zz', 'activo' => false]], 'function_get' => 'g', 'function_delete' => 'd']);
        $this->assertStringContainsString('{{ _self }}&#039;', $html, 'El título es texto, no código');
        $this->assertStringContainsString('&lt;b&gt;O&#039;Brien&lt;/b&gt;', $html);
        $this->assertStringContainsString('<td>zz</td>', $html);
        $this->assertStringContainsString('<td>Sí</td>', $html);
        $this->assertStringContainsString("g('0')", $html);
    }

    public function testColumnsPipelineAndFilters(): void
    {
        $fields = \nfTableSchema::normalizeFields([
            ['field' => 'nombre', 'type' => 'inputText', 'short_description' => 'Nombre'],
            ['field' => 'cliente', 'type' => 'Select', 'ajax' => ['collection' => 'clientes', 'label' => 'nombre']],
            ['field' => 'direccion.cp', 'type' => 'inputText'],
            ['field' => 'estado', 'type' => 'Select', 'items' => "a=Activo"],
            ['field' => 'nota', 'type' => 'inputText', 'list' => false],
            ['field' => 'foto', 'type' => 'inputFile'],
        ]);
        $columns = \nfTableSchema::columns($fields);
        $this->assertSame(['nombre', 'nfc_1', 'nfc_2', 'estado'], array_column($columns, 'column'));
        foreach ($columns as $c) {
            $this->assertStringContainsString('nfEsc(', $c['render'], 'Toda celda se escapa');
        }

        $pipeline = \nfTableSchema::pipeline($fields);
        $this->assertSame('clientes', $pipeline[0]['$lookup']['from']);
        $this->assertSame('cliente', $pipeline[0]['$lookup']['localField']);
        $this->assertSame('_id', $pipeline[0]['$lookup']['foreignField']);
        $this->assertSame('$direccion.cp', $pipeline[1]['$addFields']['nfc_2']);
        $this->assertArrayHasKey('$ifNull', $pipeline[1]['$addFields']['nfc_1']);

        $filters = array_column(\nfTableSchema::filters($fields), null, 'id');
        $this->assertSame(['nombre', 'nfc_1', 'direccion.cp', 'estado', 'nota'], array_keys($filters));
        $this->assertSame(['a' => 'Activo'], $filters['estado']['values']);
    }

    public function testFilesNeedAConfiguredFolder(): void
    {
        $f = self::field(['field' => 'foto', 'type' => 'inputFile', 'options' => ['accept' => 'image/*']]);
        $this->assertNull(\nfTableSchema::componentOptions($f, ['collection' => 'productos', '_id' => '65a1b2c3d4e5f60718293a4b']));

        $opts = \nfTableSchema::componentOptions($f, ['collection' => 'productos', '_id' => '65a1b2c3d4e5f60718293a4b', 'files' => '/srv/nf/']);
        $this->assertSame('/srv/nf/productos/65a1b2c3d4e5f60718293a4b/foto/', $opts['dir']);
        $this->assertSame('/srv/nf/productos/65a1b2c3d4e5f60718293a4b/foto/archivo', $opts['path']);
        $this->assertNull(\nfTableSchema::filesDir(['files' => '/srv', 'collection' => 'p', '_id' => '../x'], 'foto'));
    }

    public function testInferFieldsFromDocuments(): void
    {
        $docs = [];
        foreach (range(1, 8) as $i) {
            $docs[] = [
                '_id' => $i,
                'nombre' => 'Cliente ' . $i,
                'estado' => $i % 2 ? 'activo' : 'baja',
                'saldo' => $i * 10.5,
                'vip' => $i > 6,
                'alta' => new \MongoDB\BSON\UTCDateTime(strtotime('2026-01-0' . $i . ' 00:00:00 UTC') * 1000),
                'direccion' => ['cp' => '2500' . $i],
                'telefonos' => [['numero' => '844' . $i, 'tipo' => 'cel']],
                'password' => 'x',
                'cliente' => new \MongoDB\BSON\ObjectId(),
            ];
        }
        $fields = array_column(\nfTableSchema::inferFields($docs), null, 'field');
        $this->assertSame('inputText', $fields['nombre']['type']);
        $this->assertSame('Select', $fields['estado']['type']);
        $this->assertSame(['activo', 'baja'], array_column($fields['estado']['items'], 'value'));
        $this->assertSame('inputNumber', $fields['saldo']['type']);
        $this->assertSame('inputCheckBox', $fields['vip']['type']);
        $this->assertSame('inputDate', $fields['alta']['type']);
        $this->assertSame('inputText', $fields['direccion.cp']['type']);
        $this->assertSame('embededArray', $fields['telefonos']['type']);
        $this->assertSame(['numero', 'tipo'], array_column($fields['telefonos']['subfields'], 'field'));
        $this->assertStringContainsString('Buscar en una colección', $fields['cliente']['long_description']);
        $this->assertArrayNotHasKey('password', $fields);
        $this->assertArrayNotHasKey('_id', $fields);

        // Lo detectado pasa la validación estricta con que se guarda.
        $errors = [];
        \nfTableSchema::normalizeFields(array_values($fields), $errors, false, true);
        $this->assertSame([], $errors);
    }

    public function testToPhpGeneratesValidCode(): void
    {
        $fields = [
            self::field(['field' => 'cliente', 'type' => 'Select', 'short_description' => 'Cliente', 'required' => true,
                'ajax' => ['collection' => 'clientes', 'label' => 'nombre']]),
            self::field(['field' => 'ciudad', 'type' => 'inputText', 'suggest' => ['mode' => 'self']]),
            self::field(['field' => 'foto', 'type' => 'inputFile']),
            self::field(['field' => 'telefonos', 'type' => 'embededArray', 'subfields' => [['field' => 'numero', 'type' => 'inputText']]]),
        ];
        foreach ($fields as $f) {
            $code = \nfTableSchema::toPhp($f, 'clientes');
            $this->assertNotFalse(@token_get_all('<?php ' . $code, TOKEN_PARSE), $code);
            $this->assertStringNotContainsString('__', $code, 'Quedó un marcador sin reemplazar: ' . $code);
        }
        $select = \nfTableSchema::toPhp($fields[0]);
        $this->assertStringContainsString("\$cliente = new Select([\n    'dataset' => &\$dataset,", $select);
        $this->assertStringContainsString("'db' => \$config['sitedb'],", $select);
        $this->assertStringContainsString("'format' => Select::formatMongoID,", $select);
        $this->assertStringContainsString("\$config['nftables_files'] . '/clientes/' . \$dataset->_id . '/foto/'", \nfTableSchema::toPhp($fields[2], 'clientes'));
    }

    /** @return array<string, array{0: string}> */
    public static function types(): array
    {
        $out = [];
        foreach (array_keys(\nfTableSchema::TYPES) as $type) {
            $out[$type] = [$type];
        }
        return $out;
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('types')]
    public function testEveryTypeBuildsAndRenders(string $type): void
    {
        foreach (['Dialog', 'mapshape', 'address', 'Signature_pad', 'Denue'] as $class) {
            require_once dirname(__DIR__, 2) . '/includes/class.' . $class . '.php';
        }
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SERVER['PHP_SELF'] = '/nftables/pedidos/x';
        $_SERVER['HTTP_HOST'] ??= 'localhost';
        $savedConfig = $GLOBALS['config'];
        $GLOBALS['nframework'] = new class {
            public array $onces = [];
            public array $javasonce = [];
            public array $csss = [];
            public array $jss = [];
            public string $lang = 'es-MX';
            public string $lang_ = 'es';
            public string $api_path = '/nframework/';
            public array $language = [];
            public array $languages = [];
            public function counters($name) { return 1; }
            public function __call($name, $args) { return null; }   // addjqueryui(), addfileupload()...
        };
        $nframework = $GLOBALS['nframework'];
        require dirname(__DIR__, 2) . '/includes/i18n/es-MX.php';   // llena $nframework->languages
        $nframework->language = $nframework->languages['es-MX'];
        $GLOBALS['javas'] = new class {
            public function addjs($code, $section = 'general'): void
            {
            }
        };

        $errors = [];
        $field = self::field([
            'field' => 'campo', 'type' => $type, 'short_description' => 'Campo',
            'items' => "a=A\nb=B",
            'subfields' => [['field' => 'sub', 'type' => 'inputText']],
        ], $errors);
        $this->assertSame([], $errors);

        $collection = new FakeCollection('pedidos');
        $collection->findOneResult = null;
        $dataset = new \dataset(['collection' => $collection, '_id' => '65a1b2c3d4e5f60718293a4b', 'nameprefix' => 'data']);
        $built = \nfTableSchema::build($field, $dataset, [
            'db' => 'sitio', 'collection' => 'pedidos', '_id' => '65a1b2c3d4e5f60718293a4b', 'files' => sys_get_temp_dir() . '/nftables_test',
        ]);
        try {
            if (is_array($built)) {
                $this->assertInstanceOf(\embededArray::class, $built['list']);
                $this->assertCount(1, $built['controls']);
                $html = (string) $built['dialog'] . (string) $built['list'];
            } else {
                $this->assertInstanceOf($type, $built);
                $html = (string) $built;
            }
            $this->assertIsString($html);
        } finally {
            $GLOBALS['config'] = $savedConfig;
        }
    }

    public function testImportTypes(): void
    {
        $this->assertSame('integer', \nfTableSchema::importType('inputnumber'));
        $this->assertSame('date', \nfTableSchema::importType('inputDate'));
        $this->assertSame('boolean', \nfTableSchema::importType('inputCheckBox'));
        $this->assertSame('string', \nfTableSchema::importType('cualquiera'));
    }
}
