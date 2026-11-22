<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Colección falsa: guarda las llamadas y responde findOne() con lo que se le indique.
 */
class FakeCollection
{
    /** @var list<array{0: string, 1: array<mixed>}> */
    public array $calls = [];
    public mixed $findOneResult = null;

    public function __construct(public string $name = 'productos')
    {
    }

    public function findOne(...$args): mixed
    {
        $this->calls[] = ['findOne', $args];
        return $this->findOneResult;
    }

    public function updateOne(...$args): void
    {
        $this->calls[] = ['updateOne', $args];
    }

    public function getCollectionName(): string
    {
        return $this->name;
    }

    /** @return list<array<mixed>> Argumentos de cada updateOne(). */
    public function updates(): array
    {
        return array_values(array_map(fn($c) => $c[1], array_filter($this->calls, fn($c) => $c[0] === 'updateOne')));
    }
}

/**
 * Cliente/base falsos: $m->{db}->{colección} devuelve siempre la misma FakeCollection por nombre.
 */
class FakeMongo
{
    /** @var array<string, FakeCollection> */
    public array $collections = [];

    public function __get(string $name): mixed
    {
        // Primer nivel: base de datos (devuelve el mismo objeto); segundo: colección.
        return $this->collections[$name] ??= new FakeCollection($name);
    }
}

/**
 * Correcciones del núcleo que se pueden probar sin MongoDB.
 */
class CoreFixesTest extends TestCase
{
    private mixed $savedM;

    protected function setUp(): void
    {
        $this->savedM = $GLOBALS['m'] ?? null;
    }

    protected function tearDown(): void
    {
        $GLOBALS['m'] = $this->savedM;
    }

    private function user(array $info, object $m): \User
    {
        $r = new \ReflectionClass(\User::class);
        $user = $r->newInstanceWithoutConstructor();
        $r->getProperty('info')->setValue($user, $info);
        $r->getProperty('m')->setValue($user, $m);
        $r->getProperty('db')->setValue($user, 'pruebas');
        return $user;
    }

    public function testUserArrayAccessSavesWithObjectId(): void
    {
        $m = new FakeMongo();
        $m->collections['pruebas'] = $m;   // $m->pruebas->users
        $id = '65a1b2c3d4e5f60718293a4b';
        $user = $this->user(['_id' => $id, 'username' => 'a@b.mx'], $m);

        $user['telefono'] = '8441234567';
        unset($user['telefono']);

        [$set, $unset] = $m->users->updates();
        $this->assertEquals(new \MongoDB\BSON\ObjectId($id), $set[0]['_id']);
        $this->assertSame(['$set' => ['telefono' => '8441234567']], $set[1]);
        $this->assertEquals(new \MongoDB\BSON\ObjectId($id), $unset[0]['_id']);
        $this->assertSame(['$unset' => ['telefono' => '']], $unset[1]);
        $this->assertSame($id, $user['_id']);
    }

    public function testUserArrayAccessIgnoresProtectedFields(): void
    {
        $m = new FakeMongo();
        $m->collections['pruebas'] = $m;
        $user = $this->user(['_id' => '65a1b2c3d4e5f60718293a4b', 'username' => 'a@b.mx'], $m);
        $user['username'] = 'otro@b.mx';
        $this->assertSame([], $m->users->updates());
        $this->assertSame('a@b.mx', $user['username']);
    }

    public function testDatasetUnsetSendsAnUpdateDocument(): void
    {
        $collection = new FakeCollection();
        $dataset = new \dataset(['collection' => $collection, '_id' => '65a1b2c3d4e5f60718293a4b']);
        $collection->calls = [];

        unset($dataset->notas);

        [$update] = $collection->updates();
        $this->assertEquals(['_id' => new \MongoDB\BSON\ObjectId('65a1b2c3d4e5f60718293a4b')], $update[0]);
        $this->assertSame(['$unset' => ['notas' => '']], $update[1], 'Debe ser un documento, no un pipeline');
    }

    public function testDatasetRefreshWhenTheDocumentNoLongerExists(): void
    {
        $collection = new FakeCollection();
        $dataset = new \dataset(['collection' => $collection, '_id' => '65a1b2c3d4e5f60718293a4b']);
        $collection->findOneResult = null;

        $dataset->refresh();

        $this->assertSame('65a1b2c3d4e5f60718293a4b', $dataset->_id);
        $this->assertCount(1, $dataset->info);
    }

    public function testDatasetRefreshLoadsTheDocumentAsArray(): void
    {
        $collection = new FakeCollection();
        $dataset = new \dataset(['collection' => $collection, '_id' => '65a1b2c3d4e5f60718293a4b']);
        $collection->findOneResult = new \ArrayObject(['_id' => 'x', 'nombre' => 'Tornillo']);

        $dataset->refresh();

        $this->assertSame('Tornillo', $dataset->nombre);
    }

    public function testDatasetAllUpperTurnsOnUppercase(): void
    {
        $dataset = new \dataset(['collection' => new FakeCollection(), 'allUpper' => true]);
        $legacy = new \dataset(['collection' => new FakeCollection()]);
        $legacy->allUppercase = true;   // nombre que leían los controles: se sigue aceptando

        $this->assertTrue((new \inputText(['dataset' => &$dataset, 'field' => 'rfc']))->uppercase);
        $this->assertTrue((new \inputText(['dataset' => &$legacy, 'field' => 'rfc']))->uppercase);
        $plain = new \dataset(['collection' => new FakeCollection()]);
        $this->assertEmpty((new \inputText(['dataset' => &$plain, 'field' => 'rfc']))->uppercase);
    }

    public function testDatasetToNfTablesAddsOnlyNewFields(): void
    {
        $m = new FakeMongo();
        $m->collections[$GLOBALS['config']['sitedb']] = $m;
        $GLOBALS['m'] = $m;
        $m->nftables->findOneResult = ['nffields' => [['field' => 'nombre', 'type' => 'inputText']]];

        $dataset = new \dataset(['collection' => new FakeCollection('productos'), 'nameprefix' => 'data']);
        new \inputText(['dataset' => &$dataset, 'field' => 'nombre', 'caption' => 'Nombre']);
        new \inputNumber(['dataset' => &$dataset, 'field' => 'precio', 'caption' => '<b>Precio</b>', 'required' => true]);

        $dataset->tonfTables();

        [$update] = $m->nftables->updates();
        $this->assertSame(['nfcollection' => 'productos'], $update[0]);
        $this->assertSame(['$push' => ['nffields' => ['$each' => [[
            'type' => 'inputNumber',
            'field' => 'precio',
            'short_description' => 'Precio',
            'required' => true,
        ]]]]], $update[1]);
        $this->assertSame(['upsert' => true], $update[2]);
    }

    public function testTreeViewRendersWithoutFatalError(): void
    {
        $hijo = new \TreeViewItem('Hijo', new \Icon('file'));
        $raiz = new \TreeViewItem('Raíz <x>', new \Icon('folder'), ['children' => [$hijo]]);
        $html = (string) new \TreeView(['id' => 'arbol', 'children' => [$raiz]]);

        $this->assertStringContainsString('<ul data-role="treeview" id="arbol">', $html);
        $this->assertStringContainsString('data-caption="Raíz &lt;x&gt;"', $html);
        $this->assertStringContainsString('data-icon="&lt;span class=&quot;icon mif-folder&quot;&gt;', $html);
        $this->assertStringContainsString('<ul><li class="item"', $html);
    }

    public function testBreadCrumbsBuildsCumulativeEscapedLinks(): void
    {
        require_once dirname(__DIR__, 2) . '/includes/class.BreadCrumbs.php';
        $html = (string) new \BreadCrumbs(['self' => 'reportes/ventas/mes"x.php']);

        $this->assertStringContainsString('href="/reportes"', $html);
        $this->assertStringContainsString('href="/reportes/ventas"', $html);
        $this->assertStringContainsString('href="/reportes/ventas/mes&quot;x.php"', $html);
        $this->assertStringContainsString('>Mes&quot;x</a>', $html);
        $this->assertStringNotContainsString('<li"', $html);

        $index = (string) new \BreadCrumbs(['root' => '/panel/', 'self' => 'clientes/index.php']);
        $this->assertStringContainsString('href="/panel/clientes"', $index);
        $this->assertStringNotContainsString('Index', $index);
    }
}
