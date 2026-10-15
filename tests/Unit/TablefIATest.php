<?php

namespace Tests\Unit;

use InvalidArgumentException;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/class.TablefIA.php';

/**
 * Validación del pipeline que genera la IA en TablefIA: es la barrera de seguridad, no depende del modelo.
 */
class TablefIATest extends TestCase
{
    private static function cfg(): array
    {
        return [
            'collections' => \TablefIA::normalizeCollections([
                'pedidos' => ['match' => ['empresa' => 7]],
                'clientes' => ['hidden' => ['password']],
                'productos',
            ]),
            'relations' => [
                ['from' => 'pedidos', 'localField' => 'cliente', 'to' => 'clientes', 'foreignField' => '_id', 'as' => 'cliente'],
            ],
            'instructions' => '',
        ];
    }

    public function testNormalizeCollectionsAcceptsListAndOptions(): void
    {
        $normal = \TablefIA::normalizeCollections(['a', 'b' => ['description' => 'B']]);

        $this->assertSame(['a', 'b'], array_keys($normal));
        $this->assertSame('', $normal['a']['description']);
        $this->assertSame('B', $normal['b']['description']);
        $this->assertSame([], $normal['b']['hidden']);
    }

    public function testParsePipelineReadsExtendedJson(): void
    {
        $stages = \TablefIA::parsePipeline('[{"$match":{"fecha":{"$gte":{"$date":"2026-06-01T00:00:00Z"}},"cliente":{"$oid":"65a000000000000000000001"}}}]');

        $this->assertInstanceOf(UTCDateTime::class, $stages[0]['$match']['fecha']['$gte']);
        $this->assertInstanceOf(ObjectId::class, $stages[0]['$match']['cliente']);
    }

    public function testParsePipelineRejectsInvalidJsonAndObjects(): void
    {
        foreach (['no es json', '{"$match":{}}', '"texto"'] as $json) {
            try {
                \TablefIA::parsePipeline($json);
                $this->fail('Se aceptó: ' . $json);
            } catch (InvalidArgumentException $e) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testBuildPipelineAddsRestrictionsAndLimit(): void
    {
        $pipeline = \TablefIA::buildPipeline(self::cfg(), 'pedidos', [
            ['$group' => ['_id' => '$cliente', 'total' => ['$sum' => '$importe']]],
            ['$project' => ['_id' => 0, 'Cliente' => '$_id', 'Total' => '$total']],
        ], 100);

        $this->assertSame(['$match' => ['empresa' => 7]], $pipeline[0]);
        $this->assertSame('$group', array_key_first($pipeline[1]));
        $this->assertSame(['$limit' => 100], end($pipeline));
    }

    public function testHiddenFieldsAreRemovedBeforeTheGeneratedStages(): void
    {
        $pipeline = \TablefIA::buildPipeline(self::cfg(), 'clientes', [['$match' => ['password' => 'x']]], 10);

        $this->assertSame(['$unset' => ['password']], $pipeline[0]);
        $this->assertSame(['$match' => ['password' => 'x']], $pipeline[1]);
    }

    public function testLookupOnDeclaredRelationGetsTargetRestrictions(): void
    {
        $pipeline = \TablefIA::buildPipeline(self::cfg(), 'pedidos', [
            ['$lookup' => ['from' => 'clientes', 'localField' => 'cliente', 'foreignField' => '_id', 'as' => 'c']],
        ], 10);

        $this->assertSame([['$unset' => ['password']]], $pipeline[1]['$lookup']['pipeline']);
    }

    public function testLookupInInverseDirectionIsAllowed(): void
    {
        $pipeline = \TablefIA::buildPipeline(self::cfg(), 'clientes', [
            ['$lookup' => ['from' => 'pedidos', 'localField' => '_id', 'foreignField' => 'cliente', 'as' => 'pedidos']],
        ], 10);

        // Los pedidos unidos también llevan el filtro obligatorio de su colección.
        $this->assertSame([['$match' => ['empresa' => 7]]], $pipeline[1]['$lookup']['pipeline']);
    }

    public static function rejectedPipelines(): array
    {
        return [
            'colección no permitida' => ['users', [['$match' => []]]],
            'etapa de escritura $out' => ['pedidos', [['$out' => 'copia']]],
            'etapa $merge' => ['pedidos', [['$merge' => ['into' => 'copia']]]],
            'etapa $unionWith' => ['pedidos', [['$unionWith' => 'users']]],
            '$facet' => ['pedidos', [['$facet' => ['a' => [['$match' => []]]]]]],
            '$where anidado' => ['pedidos', [['$match' => ['$where' => 'sleep(1000)']]]],
            '$function en $addFields' => ['pedidos', [['$addFields' => ['x' => ['$function' => ['body' => 'f', 'args' => [], 'lang' => 'js']]]]]],
            '$accumulator' => ['pedidos', [['$group' => ['_id' => null, 'x' => ['$accumulator' => []]]]]],
            'dos operadores en una etapa' => ['pedidos', [['$match' => [], '$sort' => ['a' => 1]]]],
            '$lookup fuera de relaciones' => ['pedidos', [['$lookup' => ['from' => 'productos', 'localField' => 'producto', 'foreignField' => '_id', 'as' => 'p']]]],
            '$lookup a colección no declarada' => ['pedidos', [['$lookup' => ['from' => 'users', 'localField' => 'cliente', 'foreignField' => '_id', 'as' => 'u']]]],
            '$lookup con pipeline propio' => ['pedidos', [['$lookup' => ['from' => 'clientes', 'localField' => 'cliente', 'foreignField' => '_id', 'as' => 'c', 'pipeline' => []]]]],
            '$lookup con campo cambiado' => ['pedidos', [['$lookup' => ['from' => 'clientes', 'localField' => 'vendedor', 'foreignField' => '_id', 'as' => 'c']]]],
            '$lookup anidado en $project' => ['pedidos', [['$project' => ['$lookup' => []]]]],
            '$limit negativo' => ['pedidos', [['$limit' => -1]]],
            'mayúsculas para evadir' => ['pedidos', [['$match' => ['$WHERE' => '1']]]],
        ];
    }

    #[DataProvider('rejectedPipelines')]
    public function testDangerousPipelinesAreRejected(string $base, array $stages): void
    {
        $this->expectException(InvalidArgumentException::class);
        \TablefIA::buildPipeline(self::cfg(), $base, $stages, 10);
    }

    public function testTooManyStagesAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        \TablefIA::buildPipeline(self::cfg(), 'pedidos', array_fill(0, \TablefIA::MAX_STAGES + 1, ['$match' => []]), 10);
    }

    public function testSystemPromptListsCollectionsRelationsAndFields(): void
    {
        $text = \TablefIA::systemPrompt(self::cfg(), ['pedidos' => ['importe' => 'double'], 'clientes' => ['nombre' => 'string']]);

        $this->assertStringContainsString('### pedidos', $text);
        $this->assertStringContainsString('- importe: double', $text);
        $this->assertStringContainsString('pedidos.cliente → clientes._id', $text);
        $this->assertStringNotContainsString('password', $text);
    }

    public function testFormatValue(): void
    {
        $this->assertSame('', \TablefIA::formatValue(null));
        $this->assertSame('Sí', \TablefIA::formatValue(true));
        $this->assertSame('1234.5', \TablefIA::formatValue(1234.5));
        $this->assertSame('42', \TablefIA::formatValue(42));
        $this->assertSame('65a000000000000000000001', \TablefIA::formatValue(new ObjectId('65a000000000000000000001')));
        $this->assertSame('[ 1, 2 ]', \TablefIA::formatValue([1, 2]));
    }
}
