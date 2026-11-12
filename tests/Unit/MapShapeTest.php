<?php

namespace Tests\Unit;

use MongoDB\BSON\Document;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/class.mapshape.php';

class MapShapeTest extends TestCase
{
    /** Cuadro de 0 a 10 (lng, lat) con un hueco de 4 a 6. */
    private const LIMIT = [
        'type' => 'Polygon',
        'coordinates' => [
            [[0, 0], [10, 0], [10, 10], [0, 10], [0, 0]],
            [[4, 4], [6, 4], [6, 6], [4, 6], [4, 4]],
        ],
    ];

    /** Forma de "U": la parte de arriba entre x=4 y x=6 está fuera. */
    private const U_LIMIT = [
        'type' => 'Polygon',
        'coordinates' => [[[0, 0], [10, 0], [10, 10], [6, 10], [6, 4], [4, 4], [4, 10], [0, 10], [0, 0]]],
    ];

    protected function setUp(): void
    {
        $GLOBALS['nframework'] = new \stdClass();
        $GLOBALS['nframework']->onces = [];
        $GLOBALS['nframework']->csss = [];
        $GLOBALS['nframework']->jss = [];
        $GLOBALS['javas'] = new class {
            public array $js = ['general' => '', 'ready' => ''];

            public function addjs($code, $section = 'general'): void
            {
                $this->js[$section] .= $code;
            }
        };
    }

    private static function polygon(array $pts): string
    {
        $pts[] = $pts[0];

        return json_encode(['type' => 'Polygon', 'coordinates' => [$pts]]);
    }

    public function testPolygonIsStoredAsClosedGeoJsonWithDoubles(): void
    {
        $shape = new \mapshape(['name' => 'zona']);

        $stored = $shape->__toMongo(json_encode(['type' => 'Polygon', 'coordinates' => [[['-100.1', '25.1'], [-100.2, 25.1], [-100.2, 25.2]]]]));

        $this->assertSame('Polygon', $stored['type']);
        $this->assertSame([[-100.1, 25.1], [-100.2, 25.1], [-100.2, 25.2], [-100.1, 25.1]], $stored['coordinates'][0]);
        $this->assertNull($shape->__toMongo(''));
    }

    public function testPolylineIsStoredAsLineString(): void
    {
        $shape = new \mapshape(['name' => 'ruta', 'shape' => \mapshape::POLYLINE]);

        $stored = $shape->__toMongo(json_encode(['type' => 'LineString', 'coordinates' => [[1, 2], [3, 4]]]));

        $this->assertSame(['type' => 'LineString', 'coordinates' => [[1.0, 2.0], [3.0, 4.0]]], $stored);
        // Una polilínea no acepta un polígono.
        $this->assertFalse($shape->is_valid(self::polygon([[1, 1], [2, 1], [2, 2]])));
    }

    public function testRejectsTooFewPointsBadCoordinatesAndSelfIntersection(): void
    {
        $shape = new \mapshape(['name' => 'zona', 'required' => true]);

        $this->assertFalse($shape->is_valid(''));
        $this->assertFalse($shape->is_valid(self::polygon([[1, 1], [2, 2]])));
        $this->assertFalse($shape->is_valid(self::polygon([[1, 1], [200, 2], [3, 3]])));
        $this->assertFalse($shape->is_valid(['type' => ['$ne' => 1]]));
        $this->assertFalse($shape->is_valid('{no es json'));
        // Moño: los lados 0-1 y 2-3 se cruzan.
        $this->assertFalse($shape->is_valid(self::polygon([[0, 0], [2, 2], [2, 0], [0, 2]])));
        $this->assertSame('Los lados del polígono no deben cruzarse.', $shape->error);
        $this->assertTrue($shape->is_valid(self::polygon([[0, 0], [2, 0], [2, 2], [0, 2]])));
        $this->assertTrue((new \mapshape(['name' => 'opcional']))->is_valid(''));
    }

    public function testLimitWithHole(): void
    {
        $shape = new \mapshape(['name' => 'zona', 'limit' => self::LIMIT]);

        $this->assertTrue($shape->is_valid(self::polygon([[1, 1], [3, 1], [3, 3], [1, 3]])));
        // Un vértice fuera del límite.
        $this->assertFalse($shape->is_valid(self::polygon([[1, 1], [11, 1], [3, 3]])));
        // Un vértice dentro del hueco.
        $this->assertFalse($shape->is_valid(self::polygon([[1, 1], [5, 5], [1, 3]])));
        // Vértices fuera del hueco pero un lado lo atraviesa.
        $this->assertFalse($shape->is_valid(self::polygon([[3, 5], [7, 5], [7, 9]])));
        // El polígono encierra el hueco completo.
        $this->assertFalse($shape->is_valid(self::polygon([[2, 2], [8, 2], [8, 8], [2, 8]])));
        $this->assertSame('La figura sale del límite permitido.', $shape->error);
    }

    public function testConcaveLimitRejectsEdgesThatLeaveIt(): void
    {
        $line = new \mapshape(['name' => 'ruta', 'shape' => \mapshape::POLYLINE, 'limit' => json_encode(self::U_LIMIT)]);

        // Ambos extremos dentro (en los brazos de la U), pero el tramo cruza el hueco de arriba.
        $this->assertFalse($line->is_valid(json_encode(['type' => 'LineString', 'coordinates' => [[2, 8], [8, 8]]])));
        $this->assertTrue($line->is_valid(json_encode(['type' => 'LineString', 'coordinates' => [[2, 8], [2, 2], [8, 2], [8, 8]]])));
    }

    public function testLimitAcceptsFeatureCollectionAndMultiPolygon(): void
    {
        $collection = ['type' => 'FeatureCollection', 'features' => [
            ['type' => 'Feature', 'properties' => [], 'geometry' => ['type' => 'MultiPolygon', 'coordinates' => [
                [[[0, 0], [1, 0], [1, 1], [0, 1], [0, 0]]],
                [[[5, 5], [6, 5], [6, 6], [5, 6], [5, 5]]],
            ]]],
        ]];

        $this->assertCount(2, \mapshape::polygons($collection));
        $this->assertTrue(\mapshape::contains($collection, 5.5, 5.5));
        $this->assertFalse(\mapshape::contains($collection, 3, 3));
        $this->assertTrue(\mapshape::contains(self::LIMIT, 1, 1));
        $this->assertFalse(\mapshape::contains(self::LIMIT, 5, 5));   // dentro del hueco
    }

    public function testDatasetSaveRejectsShapesOutsideTheLimit(): void
    {
        require_once __DIR__ . '/BaseStorageTest.php';
        $collection = new FakeMongoCollection();
        $dataset = new \dataset(['collection' => $collection, 'nameprefix' => 'f']);
        $zona = new \mapshape(['dataset' => $dataset, 'field' => 'zona', 'limit' => self::LIMIT, 'required' => true]);

        $_POST['f'] = ['zona' => self::polygon([[1, 1], [11, 1], [3, 3]])];
        $this->assertStringContainsString('zona', (string) $dataset->save());

        $_POST['f'] = ['zona' => self::polygon([[1, 1], [3, 1], [3, 3]])];
        $this->assertFalse($dataset->save());
        $doc = $collection->raw((string) $dataset->_id);
        $this->assertSame(['type' => 'Polygon', 'coordinates' => [[[1.0, 1.0], [3.0, 1.0], [3.0, 3.0], [1.0, 1.0]]]], $doc['zona']);

        // Recargado desde BSON, el componente reconoce la figura guardada.
        $reloaded = new \dataset(['collection' => $collection, 'nameprefix' => 'f', '_id' => (string) $dataset->_id]);
        $again = new \mapshape(['dataset' => $reloaded, 'field' => 'zona']);
        $this->assertSame([[1.0, 1.0], [3.0, 1.0], [3.0, 3.0]], $again->points($again->value));
        $_POST = [];
    }

    public function testRendersStoredBsonValueAndLimit(): void
    {
        $shape = new \mapshape(['name' => 'data[zona]', 'limit' => self::LIMIT]);
        $stored = Document::fromPHP(['v' => $shape->__toMongo(self::polygon([[1, 1], [3, 1], [3, 3]]))])
            ->toPHP(['root' => 'array', 'document' => 'MongoDB\Model\BSONDocument', 'array' => 'MongoDB\Model\BSONArray'])['v'];
        $shape->value = $stored;

        $html = (string) $shape;

        $this->assertStringContainsString('id="data_zona"', $html);
        $this->assertStringContainsString('name="data[zona]"', $html);
        $this->assertStringContainsString('value="{&quot;type&quot;:&quot;Polygon&quot;', $html);
        $js = $GLOBALS['javas']->js['ready'];
        $this->assertStringContainsString('mapshapes["data_zona"] = nfMapShape(', $js);
        $this->assertStringContainsString('"points":[[1,1],[1,3],[3,3]]', $js);   // [lat, lng] para Leaflet
        $this->assertStringContainsString('"limit":{"type":"MultiPolygon"', $js);
        $this->assertStringContainsString('function nfMapShape', $GLOBALS['javas']->js['general']);
    }
}
