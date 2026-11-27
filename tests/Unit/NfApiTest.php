<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/class.nfApiToken.php';
require_once __DIR__ . '/../../includes/class.nfApiException.php';
require_once __DIR__ . '/../../includes/class.nfApiResult.php';
require_once __DIR__ . '/../../includes/class.nfApi.php';

/**
 * API REST: rutas, formato de respuestas, conversión de tipos de MongoDB, entrada, paginación y tokens.
 */
class NfApiTest extends TestCase
{
    protected function setUp(): void
    {
        \nfApi::$scopes = [];
    }

    public function testPathJoinsVersionAndPath(): void
    {
        $this->assertSame('/api/v1/notas/[s:id]', \nfApi::path('v1', '/notas/[s:id]/'));
        $this->assertSame('/api/v2', \nfApi::path('v2', '/'));
    }

    public function testRejectsInvalidVersion(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new \nfApi('1');
    }

    public function testScopesAreValidatedAndListedSorted(): void
    {
        $api = new \nfApi('v1', ['ratelimit' => [10, 60]]);
        $api->scope('notas:write', 'Escribir')->scope('notas:read');
        $this->assertSame(['notas:read', 'notas:write'], \nfApi::scopes());
        $this->assertSame('Escribir', \nfApi::$scopes['notas:write']);
        $this->assertSame([10, 60], $api->ratelimit);
        $this->expectException(\InvalidArgumentException::class);
        $api->scope('Notas Read');
    }

    public function testEnvelopeShapes(): void
    {
        $this->assertSame([200, ['data' => ['a' => 1]], []], \nfApi::envelope(['a' => 1]));
        $this->assertSame([204, null, []], \nfApi::envelope(null));
        [$status, $body, $headers] = \nfApi::envelope(new \nfApiResult(['x'], 201, ['page' => 1], ['Location' => '/api/v1/x']));
        $this->assertSame(201, $status);
        $this->assertSame(['data' => ['x'], 'meta' => ['page' => 1]], $body);
        $this->assertSame(['Location' => '/api/v1/x'], $headers);
        $this->assertSame([204, null, []], \nfApi::envelope(new \nfApiResult(null, 204)));
        $this->assertSame(['data' => false], \nfApi::envelope(false)[1], 'false es un dato, no 204');
    }

    public function testErrorEnvelope(): void
    {
        [$status, $body, $headers] = \nfApi::errorEnvelope(new \nfApiException(422, 'validation_failed', 'Revise.', ['titulo' => 'Requerido']));
        $this->assertSame(422, $status);
        $this->assertSame(['error' => ['code' => 'validation_failed', 'message' => 'Revise.', 'details' => ['titulo' => 'Requerido']]], $body);
        $this->assertSame([], $headers);
        [, $body, $headers] = \nfApi::errorEnvelope(new \nfApiException(401, 'unauthorized', 'Falta'));
        $this->assertArrayNotHasKey('details', $body['error']);
        $this->assertArrayHasKey('WWW-Authenticate', $headers);
        $this->assertSame(500, (new \nfApiException(200, 'x'))->status, 'un estado que no es de error se vuelve 500');
    }

    public function testNormalizeConvertsMongoTypes(): void
    {
        $id = new \MongoDB\BSON\ObjectId('5f1d7f1d7f1d7f1d7f1d7f1d');
        $doc = new \MongoDB\Model\BSONDocument([
            '_id' => $id,
            'fecha' => new \MongoDB\BSON\UTCDateTime(1764268200123),
            'tags' => new \MongoDB\Model\BSONArray(['a', $id]),
            'total' => new \MongoDB\BSON\Decimal128('10.50'),
            'local' => new \DateTime('2026-01-01 10:00:00', new \DateTimeZone('America/Mexico_City')),
        ]);
        $this->assertSame([
            '_id' => '5f1d7f1d7f1d7f1d7f1d7f1d',
            'fecha' => '2025-11-27T18:30:00.123Z',
            'tags' => ['a', '5f1d7f1d7f1d7f1d7f1d7f1d'],
            'total' => '10.50',
            'local' => '2026-01-01T16:00:00.000Z',
        ], \nfApi::normalize($doc));
        $this->assertSame(['n' => 1], \nfApi::normalize((object) ['n' => 1]));
    }

    public function testInputParsesJsonObjects(): void
    {
        $this->assertSame(['titulo' => 'x'], \nfApi::input('{"titulo":"x"}', 'application/json; charset=utf-8'));
        $this->assertSame([], \nfApi::input('', 'application/json'));
        $this->assertSame([], \nfApi::input('titulo=x', 'application/x-www-form-urlencoded'), 'sin JSON no se interpreta el texto');
        foreach (['{"a":', '[1,2]', '"texto"'] as $bad) {
            try {
                \nfApi::input($bad, 'application/json');
                $this->fail("Aceptó {$bad}");
            } catch (\nfApiException $e) {
                $this->assertSame(400, $e->status);
                $this->assertSame('invalid_json', $e->errorCode);
            }
        }
    }

    public function testPaginationForcesIntegers(): void
    {
        $this->assertSame(['page' => 1, 'per_page' => 20, 'skip' => 0, 'limit' => 20], \nfApi::pagination(20, 100, []));
        $this->assertSame(['page' => 3, 'per_page' => 100, 'skip' => 200, 'limit' => 100], \nfApi::pagination(20, 100, ['page' => '3', 'per_page' => '5000']));
        // ?page[$ne]=1 llega como arreglo; texto no numérico o negativo usa el valor por omisión.
        $this->assertSame(1, \nfApi::pagination(20, 100, ['page' => ['$ne' => 1]])['page']);
        $this->assertSame(20, \nfApi::pagination(20, 100, ['per_page' => '-5'])['per_page']);
        $this->assertSame(1, \nfApi::pagination(20, 100, ['per_page' => '0'])['per_page']);
    }

    public function testPaginatedMeta(): void
    {
        $r = \nfApi::paginated(new \ArrayIterator(['a' => 1, 'b' => 2]), 45, ['page' => 2, 'per_page' => 20]);
        $this->assertSame([1, 2], $r->data);
        $this->assertSame(['page' => 2, 'per_page' => 20, 'total' => 45, 'pages' => 3], $r->meta);
    }

    public function testTokenFormatAndHeader(): void
    {
        $token = \nfApiToken::generate();
        $this->assertTrue(\nfApiToken::wellFormed($token));
        $this->assertMatchesRegularExpression('/^nfat_[a-f0-9]{48}$/', $token);
        $this->assertNotSame($token, \nfApiToken::generate());
        $this->assertFalse(\nfApiToken::wellFormed('nfat_xyz'));
        $this->assertFalse(\nfApiToken::wellFormed(['$ne' => '']));
        $this->assertSame(hash('sha256', $token), \nfApiToken::hash($token));
        $this->assertSame($token, \nfApiToken::fromAuthorization('bearer ' . $token));
        $this->assertSame($token, \nfApiToken::fromAuthorization('  Bearer   ' . $token . ' '));
        $this->assertNull(\nfApiToken::fromAuthorization('Basic dXNlcjpwYXNz'));
        $this->assertNull(\nfApiToken::fromAuthorization(''));
    }

    public function testScopeRules(): void
    {
        $this->assertTrue(\nfApiToken::validScope('notas:read'));
        $this->assertTrue(\nfApiToken::validScope('*'));
        $this->assertFalse(\nfApiToken::validScope('Notas'));
        $this->assertFalse(\nfApiToken::validScope(['notas']));
        $known = ['notas:read', 'notas:write'];
        $this->assertSame(['notas:read'], \nfApiToken::normalizeScopes(['notas:read', 'notas:read', 'otro', ['x']], $known));
        $this->assertSame(['*'], \nfApiToken::normalizeScopes(['notas:read', '*'], $known));
        $this->assertSame([], \nfApiToken::normalizeScopes(['*'], $known, false));
        $this->assertSame([], \nfApiToken::normalizeScopes('notas:read', []));
        $this->assertTrue(\nfApiToken::allows(['notas:read'], 'notas:read'));
        $this->assertTrue(\nfApiToken::allows(['*'], 'notas:write'));
        $this->assertTrue(\nfApiToken::allows([], ''), 'una ruta sin scope acepta cualquier token');
        $this->assertFalse(\nfApiToken::allows(['notas:read'], 'notas:write'));
    }

    public function testTokenUsable(): void
    {
        $now = 2_000_000_000;
        $this->assertTrue(\nfApiToken::usable(['expires_at' => null, 'revoked_at' => null], $now));
        $this->assertTrue(\nfApiToken::usable(['expires_at' => new \MongoDB\BSON\UTCDateTime(($now + 60) * 1000)], $now));
        $this->assertFalse(\nfApiToken::usable(['expires_at' => new \MongoDB\BSON\UTCDateTime(($now - 1) * 1000)], $now));
        $this->assertFalse(\nfApiToken::usable(['revoked_at' => new \MongoDB\BSON\UTCDateTime()], $now));
    }
}
