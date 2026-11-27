<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Índice tal como lo entrega listIndexes(): solo se usa getKey(). */
class IndexInfoStub
{
    public function __construct(private array $key)
    {
    }

    public function getKey(): array
    {
        return $this->key;
    }
}

/** Base en memoria: $db->{colección}->listIndexes(). */
class IndexDbStub
{
    /** @param array<string, list<array<string, int|float>>> $keys */
    public function __construct(private array $keys)
    {
    }

    public function __get(string $coll): object
    {
        $keys = $this->keys[$coll] ?? [];
        return new class ($keys) {
            public function __construct(private array $keys)
            {
            }

            public function listIndexes(): array
            {
                return array_map(fn ($k) => new IndexInfoStub($k), $this->keys);
            }
        };
    }
}

class NfIndexesTest extends TestCase
{
    public function testRequiredIncludesSecurityEfirmaAndTablefia(): void
    {
        $names = array_map(fn ($i) => $i['collection'] . ':' . implode(',', array_keys($i['key'])), \nfIndexes::required([]));
        foreach (['nfsecurityrules:enabled', 'efirmas:owner,nocert', 'nftablesfia:uso,ultimaejecucion',
            'nftablesfia:uso,prompt,confighash', 'nftablesfia:uso,promptnorm,confighash'] as $expected) {
            $this->assertContains($expected, $names);
        }
        foreach (\nfIndexes::required([]) as $idx) {
            $this->assertIsArray($idx['options']);
        }
    }

    public function testOptionalTtlIndexesFollowConfig(): void
    {
        $base = count(\nfIndexes::required([]));
        $withTtl = \nfIndexes::required(['tablefia_ttl_days' => 30, 'nfuristats_ttl_days' => 90]);
        $this->assertCount($base + 2, $withTtl);
        $byName = array_column(array_column($withTtl, 'options'), 'expireAfterSeconds');
        $this->assertContains(30 * 86400, $byName);
        $this->assertContains(90 * 86400, $byName);
    }

    public function testMissingReportsAbsentIndexes(): void
    {
        $all = [];
        foreach (\nfIndexes::required([]) as $idx) {
            $all[$idx['collection']][] = $idx['key'];
        }
        $this->assertSame([], \nfIndexes::missing(new IndexDbStub($all), []));

        unset($all['efirmas']);
        array_pop($all['nftablesfia']);
        $this->assertSame(
            ['efirmas(owner, nocert)', 'nftablesfia(uso, promptnorm, confighash)'],
            \nfIndexes::missing(new IndexDbStub($all), [])
        );
    }

    public function testHasKeyRespectsFieldOrderAndDirection(): void
    {
        $this->assertTrue(\nfIndexes::hasKey([['users' => 1.0, 'name' => 1]], ['users' => 1, 'name' => 1]));
        $this->assertFalse(\nfIndexes::hasKey([['name' => 1, 'users' => 1]], ['users' => 1, 'name' => 1]));
        $this->assertFalse(\nfIndexes::hasKey([['uso' => 1, 'ultimaejecucion' => 1]], ['uso' => 1, 'ultimaejecucion' => -1]));
    }
}
