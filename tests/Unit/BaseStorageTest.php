<?php

namespace Tests\Unit;

use DataformatSelectType;
use DateTimeZone;
use MongoDB\BSON\Document;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Model\BSONArray;
use MongoDB\Model\BSONDocument;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Colección en memoria con la interfaz que usa dataset (findOne/updateOne).
 * Cada documento se guarda como BSON y se lee con el mismo typeMap que la librería mongodb,
 * así los tipos regresan igual que desde el servidor (int32/double, UTCDateTime, ObjectId, BSONDocument...).
 */
class FakeMongoCollection
{
    /** @var array<string, Document> */
    public array $docs = [];
    public array $updates = [];

    public function findOne($filter = [], array $options = [])
    {
        $key = (string) ($filter['_id'] ?? '');
        if (!isset($this->docs[$key])) {
            return null;
        }

        return $this->docs[$key]->toPHP(BaseStorageTest::TYPEMAP);
    }

    public function updateOne($filter, $update, array $options = []): void
    {
        $this->updates[] = $update;
        $key = (string) $filter['_id'];
        $doc = isset($this->docs[$key]) ? $this->docs[$key]->toPHP(['root' => 'array', 'document' => 'array', 'array' => 'array']) : [];
        if (!isset($doc['_id'])) {
            $doc['_id'] = $filter['_id'];
        }
        foreach ($update['$set'] ?? [] as $path => $value) {
            $ref = &$doc;
            foreach (explode('.', $path) as $part) {
                $ref = &$ref[$part];
            }
            $ref = $value;
            unset($ref);
        }
        foreach (array_keys($update['$unset'] ?? []) as $path) {
            unset($doc[$path]);
        }
        $this->docs[$key] = Document::fromPHP($doc);
    }

    public function getCollectionName(): string
    {
        return 'fake';
    }

    /** Documento tal cual quedó guardado, leído como arreglos PHP. */
    public function raw(string $id): array
    {
        return $this->docs[$id]->toPHP(['root' => 'array', 'document' => 'array', 'array' => 'array']);
    }
}

/**
 * Guardado (formulario -> __toMongo -> BSON) y carga (BSON -> __toPHP / valor del componente)
 * de los componentes de class.Base.php.
 */
class BaseStorageTest extends TestCase
{
    /** typeMap por defecto de MongoDB\Collection. */
    public const TYPEMAP = ['root' => BSONDocument::class, 'document' => BSONDocument::class, 'array' => BSONArray::class];

    private const OID = '65f0a1b2c3d4e5f6a7b8c9d0';
    private const OID2 = '65f0a1b2c3d4e5f6a7b8c9d1';

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
        $_POST = [];
        $_SERVER['PHP_SELF'] = '/admin/test.php';

        $GLOBALS['nframework'] = new \stdClass();
        $GLOBALS['nframework']->onces = [];
        $GLOBALS['nframework']->csss = [];
        $GLOBALS['nframework']->jss = [];
        $GLOBALS['javas'] = new class {
            public array $js = [];

            public function addjs($js): void
            {
                $this->js[] = $js;
            }
        };
    }

    /** Simula guardar el valor en Mongo y volver a leerlo. */
    private static function roundTrip($value)
    {
        return Document::fromPHP(['v' => $value])->toPHP(self::TYPEMAP)['v'];
    }

    // ---------------------------------------------------------------- texto

    public static function plainInputs(): array
    {
        return [
            'inputText' => [\inputText::class],
            'textArea' => [\textArea::class],
            'inputHidden' => [\inputHidden::class],
            'inputColor' => [\inputColor::class],
            'inputRating' => [\inputRating::class],
            'inputMCE' => [\inputMCE::class],
            'inputRadios' => [\inputRadios::class],
            'SelectIcon' => [\SelectIcon::class],
        ];
    }

    #[DataProvider('plainInputs')]
    public function testPlainInputsStoreValueUnchanged(string $class): void
    {
        $input = new $class(['field' => 'campo']);
        $value = 'Ñandú "comillas" <b>html</b> & 123';

        $stored = $input->__toMongo($value);

        $this->assertSame($value, $stored);
        $this->assertSame($value, self::roundTrip($stored));
    }

    public function testSelectIconStoresSelectedOptionNotBoolean(): void
    {
        $select = new \SelectIcon([
            'field' => 'icono',
            'options' => ['home' => ['datashow' => 'Inicio', 'icon' => 'mif-home'], 'user' => ['datashow' => 'Usuario', 'icon' => 'mif-user']],
        ]);

        $this->assertSame('user', self::roundTrip($select->__toMongo('user')));
        $this->assertFalse(method_exists(\SelectIcon::class, '__toPHP'));
    }

    // ---------------------------------------------------------------- números

    public static function numberCases(): array
    {
        return [
            'validate integer' => [['validate' => 'integer'], '42', 42],
            'validate digits' => [['validate' => 'digits'], '7', 7],
            'validate con varias reglas' => [['validate' => 'required integer'], '5', 5],
            'integer negativo' => [['validate' => 'integer'], '-15', -15],
            'sin regla es float' => [[], '42.5', 42.5],
            'entero sin regla es float' => [[], '42', 42.0],
            'validate float' => [['validate' => 'float'], '0.1', 0.1],
            'separador de miles' => [['validate' => 'float'], '1,234.5', 1234.5],
            'miles con integer' => [['validate' => 'integer'], '1,234', 1234],
            'vacío' => [[], '', null],
            'null' => [[], null, null],
            'no numérico' => [[], 'abc', null],
        ];
    }

    #[DataProvider('numberCases')]
    public function testInputNumberStoresNumericType(array $options, $posted, $expected): void
    {
        $input = new \inputNumber(['field' => 'n'] + $options);

        $stored = $input->__toMongo($posted);

        $this->assertSame($expected, $stored);
        $this->assertSame($expected, self::roundTrip($stored));
    }

    public function testInputNumberLegacyDataValidateProperty(): void
    {
        $input = new \inputNumber(['field' => 'n', 'data_validate' => 'integer']);

        $this->assertSame(42, $input->__toMongo('42'));
    }

    #[DataProvider('numberCases')]
    public function testInputSpinnerStoresNumericType(array $options, $posted, $expected): void
    {
        $input = new \inputSpinner(['field' => 'n'] + $options);

        $stored = $input->__toMongo($posted);

        $this->assertSame($expected, $stored);
        $this->assertSame($expected, self::roundTrip($stored));
    }

    // ---------------------------------------------------------------- fechas

    public static function timezones(): array
    {
        return [
            'UTC por defecto' => [null],
            'UTC' => ['UTC'],
            'Ciudad de México (-6)' => ['America/Mexico_City'],
            'Tijuana (-7/-8)' => ['America/Tijuana'],
            'Tokio (+9)' => ['Asia/Tokyo'],
            'Kiritimati (+14)' => ['Pacific/Kiritimati'],
            'Pago Pago (-11)' => ['Pacific/Pago_Pago'],
        ];
    }

    #[DataProvider('timezones')]
    public function testInputDateStoresMidnightAndLoadsSameDay(?string $tz): void
    {
        $options = ['field' => 'fecha'];
        if ($tz !== null) {
            $options['timezone'] = new DateTimeZone($tz);
        }
        $input = new \inputDate($options);

        $stored = $input->__toMongo('2026-06-03');

        $this->assertInstanceOf(UTCDateTime::class, $stored);
        $expected = new \DateTime('2026-06-03 00:00:00', new DateTimeZone($tz ?? 'UTC'));
        $this->assertSame($expected->getTimestamp() * 1000, (int) (string) $stored);

        $loaded = self::roundTrip($stored);
        $this->assertInstanceOf(UTCDateTime::class, $loaded);
        $this->assertSame('2026-06-03', $input->__toPHP($loaded));
    }

    public function testInputDateStringStorage(): void
    {
        $input = new \inputDate(['field' => 'fecha', 'storagetype' => \inputDate::ST_STRING]);

        $stored = $input->__toMongo('2026-06-03');

        $this->assertSame('2026-06-03', $stored);
        $this->assertSame('2026-06-03', $input->__toPHP(self::roundTrip($stored)));
    }

    public function testInputDateEmptyAndInvalidValues(): void
    {
        $input = new \inputDate(['field' => 'fecha']);

        $this->assertNull($input->__toMongo(''));
        $this->assertNull($input->__toMongo(null));
        $this->assertNull($input->__toMongo('03/06/2026'));
        $this->assertNull($input->__toPHP(null));
        // Datos antiguos guardados como texto se muestran tal cual.
        $this->assertSame('2026-06-03', $input->__toPHP('2026-06-03'));
    }

    public function testInputDateRendersLoadedValue(): void
    {
        $input = new \inputDate(['field' => 'fecha', 'name' => 'fecha', 'timezone' => new DateTimeZone('Asia/Tokyo')]);
        $input->value = self::roundTrip($input->__toMongo('2026-01-01'));

        $this->assertStringContainsString('value="2026-01-01"', (string) $input);
    }

    #[DataProvider('timezones')]
    public function testInputDateTimeRoundTripKeepsLocalTime(?string $tz): void
    {
        $options = ['field' => 'fh'];
        if ($tz !== null) {
            $options['timezone'] = new DateTimeZone($tz);
        }
        $input = new \inputDateTime($options);

        $stored = $input->__toMongo('2026-06-03T14:30');

        $this->assertInstanceOf(UTCDateTime::class, $stored);
        $expected = new \DateTime('2026-06-03 14:30:00', new DateTimeZone($tz ?? 'UTC'));
        $this->assertSame($expected->getTimestamp() * 1000, (int) (string) $stored);
        $this->assertSame('2026-06-03T14:30', $input->__toPHP(self::roundTrip($stored)));
    }

    public function testInputDateTimeStringStorageAndInvalidValues(): void
    {
        $string = new \inputDateTime(['field' => 'fh', 'storagetype' => \inputDateTime::ST_STRING]);
        $this->assertSame('2026-06-03T14:30', $string->__toPHP(self::roundTrip($string->__toMongo('2026-06-03T14:30'))));

        $input = new \inputDateTime(['field' => 'fh']);
        $this->assertNull($input->__toMongo(''));
        $this->assertNull($input->__toMongo('basura'));
        $this->assertNull($input->__toPHP(null));
    }

    public function testInputDateTimeRendersLoadedValue(): void
    {
        $input = new \inputDateTime(['field' => 'fh', 'name' => 'fh', 'timezone' => new DateTimeZone('America/Mexico_City')]);
        $input->value = self::roundTrip($input->__toMongo('2026-06-03T09:05'));

        $this->assertStringContainsString('value="2026-06-03T09:05"', (string) $input);
    }

    public function testInputTimeRoundTrip(): void
    {
        $input = new \inputTime(['field' => 'hora']);

        $stored = $input->__toMongo('14:30');

        $this->assertInstanceOf(UTCDateTime::class, $stored);
        $this->assertSame('14:30', $stored->toDateTime()->format('H:i'));
        $this->assertSame('14:30', $input->__toPHP(self::roundTrip($stored)));

        $this->assertNull($input->__toMongo(''));
        $this->assertNull($input->__toMongo('abc'));
        $this->assertSame('08:15', $input->__toPHP('08:15'));
    }

    // ---------------------------------------------------------------- casillas

    public static function checkboxStorages(): array
    {
        return [
            'booleano (defecto)' => [[], true, false],
            'booleano' => [['storeagetype' => \inputCheckBox::ST_MONGOBOOLEAN], true, false],
            'texto' => [['storeagetype' => \inputCheckBox::ST_STRING], 'true', 'false'],
            'entero' => [['storeagetype' => \inputCheckBox::ST_INTEGER], 1, 0],
            'personalizado' => [['storeagetype' => \inputCheckBox::ST_CUSTOM, 'customtrue' => 'S', 'customfalse' => 'N'], 'S', 'N'],
        ];
    }

    #[DataProvider('checkboxStorages')]
    public function testInputCheckBoxStorageFormats(array $options, $storedChecked, $storedUnchecked): void
    {
        $input = new \inputCheckBox(['field' => 'activo'] + $options);

        // El navegador envía value="1" si está marcada y nada si no.
        $checked = self::roundTrip($input->__toMongo('1'));
        $unchecked = self::roundTrip($input->__toMongo(null));

        $this->assertSame($storedChecked, $checked);
        $this->assertSame($storedUnchecked, $unchecked);
        $this->assertTrue($input->__toPHP($checked));
        $this->assertFalse($input->__toPHP($unchecked));
    }

    public function testInputCheckBoxRendersCheckedState(): void
    {
        $on = new \inputCheckBox(['field' => 'activo', 'name' => 'activo', 'value' => self::roundTrip(true)]);
        $off = new \inputCheckBox(['field' => 'activo', 'name' => 'activo', 'value' => self::roundTrip(false)]);

        $this->assertStringContainsString(' checked ', (string) $on);
        $this->assertStringNotContainsString(' checked ', (string) $off);
    }

    public function testInputCheckBoxsStoresBooleanMap(): void
    {
        $input = new \inputCheckBoxs(['field' => 'permisos', 'name' => 'permisos', 'options' => ['leer' => 'Leer', 'escribir' => 'Escribir', 'borrar' => 'Borrar']]);

        $stored = self::roundTrip($input->__toMongo(['leer' => 'on', 'borrar' => 'on']));

        $this->assertInstanceOf(BSONDocument::class, $stored);
        $this->assertSame(['leer' => true, 'borrar' => true], $stored->getArrayCopy());

        $input->value = $stored;
        $html = (string) $input;
        $this->assertMatchesRegularExpression('/id="permisos_leer"[^>]* checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/id="permisos_escribir"[^>]* checked/', $html);
        $this->assertMatchesRegularExpression('/id="permisos_borrar"[^>]* checked/', $html);
    }

    // ---------------------------------------------------------------- select

    public function testSelectStringFormatStoresValueUnchanged(): void
    {
        $select = new \Select(['field' => 'estado', 'options' => ['a' => 'A', self::OID => 'B']]);

        $this->assertSame(self::OID, self::roundTrip($select->__toMongo(self::OID)));
        $this->assertSame('a', $select->__toPHP(self::roundTrip($select->__toMongo('a'))));
    }

    public static function mongoIdFormats(): array
    {
        return [
            'constante' => [\Select::formatMongoID],
            'enum' => [DataformatSelectType::MongoID],
        ];
    }

    #[DataProvider('mongoIdFormats')]
    public function testSelectMongoIdStoresObjectId($format): void
    {
        $select = new \Select(['field' => 'cliente', 'format' => $format]);

        $stored = self::roundTrip($select->__toMongo(self::OID));

        $this->assertInstanceOf(ObjectId::class, $stored);
        $this->assertSame(self::OID, (string) $stored);
        $this->assertSame(self::OID, $select->__toPHP($stored));
        // Un valor que no es ObjectId válido se guarda como texto.
        $this->assertSame('nuevo', $select->__toMongo('nuevo'));
    }

    public function testSelectMultipleMongoIdStoresObjectIdList(): void
    {
        $select = new \Select(['field' => 'etiquetas', 'format' => \Select::formatMongoID, 'multiple' => true]);

        $stored = self::roundTrip($select->__toMongo([self::OID, '', self::OID2, null]));

        $this->assertInstanceOf(BSONArray::class, $stored);
        $this->assertCount(2, $stored);
        $this->assertContainsOnlyInstancesOf(ObjectId::class, $stored);
        $this->assertSame([self::OID, self::OID2], $select->__toPHP($stored));
    }

    public function testSelectMultipleStringFormatStoresList(): void
    {
        $select = new \Select(['field' => 'colores', 'multiple' => true]);

        $stored = self::roundTrip($select->__toMongo(['rojo', 'azul']));

        $this->assertSame(['rojo', 'azul'], $select->__toPHP($stored));
        $this->assertSame(['rojo'], $select->__toMongo('rojo'));
    }

    // ---------------------------------------------------------------- mapa

    public function testMapmarkerGeoJsonUsesLngLatOrder(): void
    {
        $map = new \mapmarker(['field' => 'ubicacion', 'name' => 'ubicacion', 'type' => \mapmarker::GeoJSON]);

        $stored = self::roundTrip($map->__toMongo(['lat' => '25.4332803', 'lng' => '-100.9604797']));

        $this->assertSame('Point', $stored['type']);
        $this->assertSame([-100.9604797, 25.4332803], $stored['coordinates']->getArrayCopy());

        $map->value = $stored;
        $html = (string) $map;
        $this->assertStringContainsString('id="ubicacion_lat" type="text" value="25.4332803"', $html);
        $this->assertStringContainsString('id="ubicacion_lng" type="text" value="-100.9604797"', $html);
    }

    public function testMapmarkerPlainStoresLatLngArray(): void
    {
        $map = new \mapmarker(['field' => 'ubicacion', 'name' => 'ubicacion']);

        $stored = self::roundTrip($map->__toMongo(['lat' => '19.43', 'lng' => '-99.13']));

        $this->assertSame(['lat' => '19.43', 'lng' => '-99.13'], $stored->getArrayCopy());
        $map->value = $stored;
        $this->assertStringContainsString('id="ubicacion_lat" type="text" value="19.43"', (string) $map);
    }

    // ---------------------------------------------------------------- dataset

    /** Crea el formulario sobre un dataset; con $id carga el documento existente. */
    private function buildForm(FakeMongoCollection $collection, ?string $id = null): array
    {
        $options = ['collection' => $collection, 'nameprefix' => 'f'];
        if ($id !== null) {
            $options['_id'] = $id;
        }
        $dataset = new \dataset($options);

        $tz = new DateTimeZone('America/Mexico_City');
        $fields = [
            'nombre' => new \inputText(['dataset' => $dataset, 'field' => 'nombre']),
            'edad' => new \inputNumber(['dataset' => $dataset, 'field' => 'edad', 'validate' => 'integer']),
            'precio' => new \inputNumber(['dataset' => $dataset, 'field' => 'precio', 'validate' => 'float']),
            'cantidad' => new \inputSpinner(['dataset' => $dataset, 'field' => 'cantidad', 'validate' => 'integer']),
            'nacimiento' => new \inputDate(['dataset' => $dataset, 'field' => 'nacimiento', 'timezone' => $tz]),
            'cita' => new \inputDateTime(['dataset' => $dataset, 'field' => 'cita', 'timezone' => $tz]),
            'hora' => new \inputTime(['dataset' => $dataset, 'field' => 'hora']),
            'activo' => new \inputCheckBox(['dataset' => $dataset, 'field' => 'activo']),
            'cliente' => new \Select(['dataset' => $dataset, 'field' => 'cliente', 'format' => \Select::formatMongoID]),
            'icono' => new \SelectIcon(['dataset' => $dataset, 'field' => 'icono']),
            'ubicacion' => new \mapmarker(['dataset' => $dataset, 'field' => 'ubicacion', 'type' => \mapmarker::GeoJSON]),
            'notas' => new \textArea(['dataset' => $dataset, 'field' => 'notas']),
        ];

        return [$dataset, $fields];
    }

    private static function validPost(): array
    {
        return [
            'nombre' => 'José Pérez',
            'edad' => '42',
            'precio' => '1,234.50',
            'cantidad' => '3',
            'nacimiento' => '1984-02-29',
            'cita' => '2026-06-03T14:30',
            'hora' => '09:45',
            'activo' => '1',
            'cliente' => self::OID,
            'icono' => 'home',
            'ubicacion' => ['lat' => '25.4332803', 'lng' => '-100.9604797'],
            'notas' => '',
        ];
    }

    public function testDatasetSaveStoresMongoTypes(): void
    {
        $collection = new FakeMongoCollection();
        [$dataset] = $this->buildForm($collection);
        $_POST['f'] = self::validPost();

        $this->assertFalse($dataset->save());

        $doc = $collection->raw($dataset->_id);
        $this->assertSame('José Pérez', $doc['nombre']);
        $this->assertSame(42, $doc['edad']);
        $this->assertSame(1234.5, $doc['precio']);
        $this->assertSame(3, $doc['cantidad']);
        $this->assertInstanceOf(UTCDateTime::class, $doc['nacimiento']);
        $this->assertSame('1984-02-29T00:00:00-06:00', $doc['nacimiento']->toDateTime()->setTimezone(new DateTimeZone('America/Mexico_City'))->format('c'));
        $this->assertInstanceOf(UTCDateTime::class, $doc['cita']);
        $this->assertSame('2026-06-03T20:30:00+00:00', $doc['cita']->toDateTime()->format('c'));
        $this->assertInstanceOf(UTCDateTime::class, $doc['hora']);
        $this->assertTrue($doc['activo']);
        $this->assertInstanceOf(ObjectId::class, $doc['cliente']);
        $this->assertSame(self::OID, (string) $doc['cliente']);
        $this->assertSame('home', $doc['icono']);
        $this->assertSame(['type' => 'Point', 'coordinates' => [-100.9604797, 25.4332803]], $doc['ubicacion']);
        // Campos sin punto se guardan con $set completo: vacío queda como '' (no $unset).
        $this->assertSame('', $doc['notas']);
    }

    public function testDatasetReloadGivesComponentsTheOriginalValues(): void
    {
        $collection = new FakeMongoCollection();
        [$dataset] = $this->buildForm($collection);
        $_POST['f'] = self::validPost();
        $dataset->save();

        [, $fields] = $this->buildForm($collection, $dataset->_id);

        $this->assertSame('José Pérez', $fields['nombre']->value);
        $this->assertSame(42, $fields['edad']->value);
        $this->assertSame(1234.5, $fields['precio']->value);
        $this->assertSame(3, $fields['cantidad']->value);
        $this->assertSame('1984-02-29', $fields['nacimiento']->__toPHP($fields['nacimiento']->value));
        $this->assertSame('2026-06-03T14:30', $fields['cita']->__toPHP($fields['cita']->value));
        $this->assertSame('09:45', $fields['hora']->__toPHP($fields['hora']->value));
        $this->assertTrue($fields['activo']->__toPHP($fields['activo']->value));
        $this->assertSame(self::OID, $fields['cliente']->__toPHP($fields['cliente']->value));
        $this->assertSame('home', $fields['icono']->value);
        $this->assertSame('', $fields['notas']->value);

        $this->assertStringContainsString('value="1984-02-29"', (string) $fields['nacimiento']);
        $this->assertStringContainsString('value="2026-06-03T14:30"', (string) $fields['cita']);
        $this->assertStringContainsString(' checked ', (string) $fields['activo']);
        $this->assertStringContainsString('value="25.4332803"', (string) $fields['ubicacion']);
        $this->assertStringContainsString('value="-100.9604797"', (string) $fields['ubicacion']);
    }

    public function testDatasetEditStoresClearedFieldsAndUncheckedBoxes(): void
    {
        $collection = new FakeMongoCollection();
        [$dataset] = $this->buildForm($collection);
        $_POST['f'] = self::validPost();
        $dataset->save();

        [$reloaded] = $this->buildForm($collection, $dataset->_id);
        $post = self::validPost();
        $post['edad'] = '';
        unset($post['activo']);
        $_POST['f'] = $post;

        $this->assertFalse($reloaded->save());

        $doc = $collection->raw($dataset->_id);
        $this->assertNull($doc['edad']);
        $this->assertFalse($doc['activo']);
        $this->assertSame('José Pérez', $doc['nombre']);

        [, $fields] = $this->buildForm($collection, $dataset->_id);
        $this->assertFalse($fields['activo']->__toPHP($fields['activo']->value));
        $this->assertStringNotContainsString(' checked ', (string) $fields['activo']);
    }

    public function testDatasetRejectsInvalidValuesWithoutSaving(): void
    {
        $collection = new FakeMongoCollection();
        [$dataset] = $this->buildForm($collection);
        $post = self::validPost();
        $post['edad'] = '42.5';
        $post['nacimiento'] = '29/02/1984';
        $_POST['f'] = $post;

        $errors = $dataset->save();

        $this->assertIsString($errors);
        $this->assertStringContainsString('edad', $errors);
        $this->assertStringContainsString('nacimiento', $errors);
        $this->assertSame([], $collection->docs);
    }
}
