<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/class.nfAI.php';
require_once __DIR__ . '/../../includes/class.TablefIA.php';

/**
 * Proveedores de IA: catálogo, normalización, cifrado de llaves y forma de las peticiones/respuestas de
 * OpenAI, compatibles y Gemini (sin llamar a la red). TablefIA: esquema y normalización de la respuesta.
 */
class NfAITest extends TestCase
{
    private const SCHEMA = [
        'type' => 'object',
        'properties' => ['ok' => ['type' => 'boolean']],
        'required' => ['ok'],
        'additionalProperties' => false,
    ];

    private function provider(string $preset, array $extra = []): array
    {
        $catalog = \nfAI::catalog();
        return \nfAI::normalize($extra + ['id' => $preset, 'preset' => $preset, 'type' => $catalog[$preset]['type'], 'model' => 'modelo-x']);
    }

    public function testCatalogIsConsistent(): void
    {
        foreach (\nfAI::catalog() as $key => $c) {
            $this->assertContains($c['type'], \nfAI::TYPES, $key);
            $this->assertContains($c['json_mode'], $c['json_modes'], $key);
            $this->assertContains($c['key'], ['required', 'optional', 'none'], $key);
            $this->assertNotSame('', $c['label'], $key);
        }
        $this->assertSame('object', \nfAI::catalog()['deepseek']['json_mode']);
    }

    public function testNormalizeFillsDefaults(): void
    {
        $p = \nfAI::normalize(['id' => 'local', 'preset' => 'ollama', 'type' => 'openai_compatible', 'max_tokens' => 10, 'timeout' => 9999]);
        $this->assertSame('http://127.0.0.1:11434/v1', $p['base_url']);
        $this->assertSame('Ollama (local)', $p['name']);
        $this->assertSame('schema', $p['json_mode']);
        $this->assertSame(256, $p['max_tokens']);
        $this->assertSame(600, $p['timeout']);
        $this->assertSame('', $p['effort']);

        // Preset que no corresponde al tipo: se usa el genérico del tipo.
        $p = \nfAI::normalize(['id' => 'x', 'preset' => 'ollama', 'type' => 'openai', 'effort' => 'max', 'json_mode' => 'nada']);
        $this->assertSame('openai', $p['preset']);
        $this->assertSame('', $p['effort'], 'max no es un esfuerzo de OpenAI');
        $this->assertSame('schema', $p['json_mode']);
        $this->assertSame('https://api.openai.com/v1', $p['base_url']);

        $p = \nfAI::normalize(['id' => 'c', 'type' => 'anthropic', 'base_url' => 'https://proxy.local/']);
        $this->assertSame('https://proxy.local', $p['base_url']);
        $this->assertSame('claude-opus-5-5', $p['model']);
    }

    public function testPickDefaultFirstOrById(): void
    {
        $a = ['id' => 'a', 'default' => false];
        $b = ['id' => 'b', 'default' => true];
        $this->assertSame('b', \nfAI::pick([$a, $b])['id']);
        $this->assertSame('a', \nfAI::pick([$a, ['id' => 'c', 'default' => false]])['id']);
        $this->assertSame('a', \nfAI::pick([$a, $b], 'a')['id']);
        try {
            \nfAI::pick([$a], 'z');
            $this->fail('Aceptó un id inexistente');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('«z»', $e->getMessage());
        }
        $this->expectException(\RuntimeException::class);
        \nfAI::pick([]);
    }

    public function testValidationError(): void
    {
        $ok = $this->provider('ollama');
        $this->assertSame('', \nfAI::validationError($ok));
        $this->assertNotSame('', \nfAI::validationError($ok, ['ollama']), 'id repetido');
        $this->assertNotSame('', \nfAI::validationError(['id' => 'Mal Id'] + $ok));
        $this->assertNotSame('', \nfAI::validationError(['base_url' => 'file:///etc/passwd'] + $ok));
        $this->assertNotSame('', \nfAI::validationError(['base_url' => 'https://u:p@host/v1'] + $ok));
        $this->assertNotSame('', \nfAI::validationError(['model' => 'con espacio'] + $ok));
        $this->assertNotSame('', \nfAI::validationError($this->provider('openai')), 'OpenAI exige llave');
        $this->assertSame('', \nfAI::validationError($this->provider('openai', ['api_key' => 'env:OPENAI_API_KEY'])));
    }

    public function testKeyEncryptionRoundTrip(): void
    {
        $secret = random_bytes(32);
        $enc = \nfAI::encryptKey('sk-secreta-1234', $secret);
        $this->assertStringStartsWith('enc:', $enc);
        $this->assertStringNotContainsString('secreta', $enc);
        $this->assertSame('sk-secreta-1234', \nfAI::decryptKey($enc, $secret));
        $this->assertNotSame($enc, \nfAI::encryptKey('sk-secreta-1234', $secret), 'IV aleatorio');
        $this->assertSame('', \nfAI::encryptKey('', $secret));
        $this->assertSame('env:MI_LLAVE', \nfAI::encryptKey('env:MI_LLAVE', $secret));
        putenv('NF_AI_TEST_VAR=valor-env');
        $this->assertSame('valor-env', \nfAI::decryptKey('env:NF_AI_TEST_VAR'));
        putenv('NF_AI_TEST_VAR');
        $this->expectException(\RuntimeException::class);
        \nfAI::decryptKey($enc, random_bytes(32));
    }

    public function testOpenAiRequestWithSchema(): void
    {
        $p = $this->provider('openai', ['effort' => 'low']);
        $r = \nfAI::buildRequest($p, 'sk-1', 'Sistema', 'Usuario', self::SCHEMA, 'mi esquema');
        $this->assertSame('https://api.openai.com/v1/chat/completions', $r['url']);
        $this->assertContains('Authorization: Bearer sk-1', $r['headers']);
        $this->assertSame('modelo-x', $r['body']['model']);
        $this->assertSame([['role' => 'system', 'content' => 'Sistema'], ['role' => 'user', 'content' => 'Usuario']], $r['body']['messages']);
        $this->assertSame(16000, $r['body']['max_completion_tokens']);
        $this->assertArrayNotHasKey('max_tokens', $r['body']);
        $this->assertSame(['type' => 'json_schema', 'json_schema' => ['name' => 'mi_esquema', 'strict' => true, 'schema' => self::SCHEMA]], $r['body']['response_format']);
        $this->assertSame('low', $r['body']['reasoning_effort']);
    }

    public function testCompatibleModes(): void
    {
        $r = \nfAI::buildRequest($this->provider('ollama'), '', 'S', 'U', self::SCHEMA);
        $this->assertSame('http://127.0.0.1:11434/v1/chat/completions', $r['url']);
        $this->assertSame(16000, $r['body']['max_tokens']);
        $this->assertEmpty(preg_grep('/^Authorization/', $r['headers']), 'sin llave no se envía Authorization');

        $r = \nfAI::buildRequest($this->provider('deepseek'), 'k', 'S', 'U', self::SCHEMA);
        $this->assertSame(['type' => 'json_object'], $r['body']['response_format']);
        $this->assertStringContainsString('"additionalProperties":false', $r['body']['messages'][0]['content'], 'el esquema va en las instrucciones');

        $r = \nfAI::buildRequest($this->provider('compatible', ['base_url' => 'http://ia.local/v1', 'json_mode' => 'prompt']), '', 'S', 'U', self::SCHEMA);
        $this->assertArrayNotHasKey('response_format', $r['body']);
        $this->assertStringStartsWith("S\n\nResponda únicamente con un objeto JSON", $r['body']['messages'][0]['content']);
    }

    public function testGeminiRequest(): void
    {
        $r = \nfAI::buildRequest($this->provider('gemini', ['model' => 'gemini/x']), 'g-1', 'S', 'U', self::SCHEMA);
        $this->assertSame('https://generativelanguage.googleapis.com/v1beta/models/gemini%2Fx:generateContent', $r['url']);
        $this->assertContains('x-goog-api-key: g-1', $r['headers']);
        $this->assertSame(['parts' => [['text' => 'S']]], $r['body']['systemInstruction']);
        $this->assertSame([['role' => 'user', 'parts' => [['text' => 'U']]]], $r['body']['contents']);
        $this->assertSame(['maxOutputTokens' => 16000, 'responseMimeType' => 'application/json', 'responseJsonSchema' => self::SCHEMA], $r['body']['generationConfig']);
    }

    public function testParseOpenAiResponses(): void
    {
        $ok = json_encode([
            'model' => 'gpt-x',
            'choices' => [['finish_reason' => 'stop', 'message' => ['content' => '{"ok":true}']]],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 3, 'prompt_tokens_details' => ['cached_tokens' => 4]],
        ]);
        $this->assertSame(['data' => ['ok' => true], 'usage' => ['input' => 10, 'output' => 3, 'cache' => 4], 'model' => 'gpt-x'], \nfAI::parseResponse('openai', 200, $ok));

        $cases = [
            [200, ['choices' => [['finish_reason' => 'stop', 'message' => ['refusal' => 'No', 'content' => null]]]], 'no respondió'],
            [200, ['choices' => [['finish_reason' => 'length', 'message' => ['content' => '{"ok"']]]], 'no terminó'],
            [401, ['error' => ['message' => 'bad key']], 'no es válida'],
            [429, ['error' => ['message' => 'quota']], 'saturado'],
            [400, ['error' => ['message' => 'response_format no soportado']], 'response_format no soportado'],
        ];
        foreach ($cases as [$status, $body, $expected]) {
            try {
                \nfAI::parseResponse('openai_compatible', $status, json_encode($body));
                $this->fail("Aceptó HTTP {$status}");
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString($expected, $e->getMessage());
            }
        }
    }

    public function testParseGeminiResponses(): void
    {
        $ok = json_encode([
            'modelVersion' => 'gemini-x',
            'candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [
                ['text' => 'pensando…', 'thought' => true],
                ['text' => '{"ok":'], ['text' => 'false}'],
            ]]]],
            'usageMetadata' => ['promptTokenCount' => 7, 'candidatesTokenCount' => 2, 'thoughtsTokenCount' => 5, 'cachedContentTokenCount' => 1],
        ]);
        $this->assertSame(['data' => ['ok' => false], 'usage' => ['input' => 7, 'output' => 7, 'cache' => 1], 'model' => 'gemini-x'], \nfAI::parseResponse('gemini', 200, $ok));

        foreach ([['promptFeedback' => ['blockReason' => 'SAFETY']], ['candidates' => [['finishReason' => 'SAFETY']]]] as $body) {
            try {
                \nfAI::parseResponse('gemini', 200, json_encode($body));
                $this->fail('Aceptó una respuesta bloqueada');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('no respondió', $e->getMessage());
            }
        }
    }

    public function testExtractJson(): void
    {
        $this->assertSame(['a' => 1], \nfAI::extractJson(' {"a":1} '));
        $this->assertSame(['a' => 1], \nfAI::extractJson("Claro:\n```json\n{\"a\":1}\n```\nListo."));
        $this->assertSame(['a' => ['b' => 2]], \nfAI::extractJson('Respuesta: {"a":{"b":2}} gracias'));
        foreach (['', '[1,2]', 'sin json'] as $bad) {
            try {
                \nfAI::extractJson($bad);
                $this->fail("Aceptó {$bad}");
            } catch (\RuntimeException $e) {
                $this->assertNotSame('', $e->getMessage());
            }
        }
    }

    public function testTablefiaAnswerSchemaAndNormalization(): void
    {
        $schema = \TablefIA::answerSchema(['collections' => ['pedidos' => [], 'clientes' => []]]);
        $this->assertSame(['pedidos', 'clientes'], $schema['properties']['coleccion']['enum']);
        $this->assertFalse($schema['additionalProperties']);
        $this->assertSame(array_keys($schema['properties']), $schema['required'], 'todos requeridos (modo estricto de OpenAI)');

        // Un modelo sin esquema estricto devuelve el pipeline como arreglo y columnas mezcladas.
        $this->assertSame([
            'coleccion' => 'pedidos',
            'pipeline' => '[{"$match":{"estado":"pagado"}}]',
            'columnas' => ['total'],
            'explicacion' => '',
        ], \TablefIA::normalizeAnswer(['coleccion' => 'pedidos', 'pipeline' => [['$match' => ['estado' => 'pagado']]], 'columnas' => ['total', 3, null]]));
        $this->assertSame(['coleccion' => '', 'pipeline' => '[]', 'columnas' => [], 'explicacion' => ''], \TablefIA::normalizeAnswer([]));
    }
}
