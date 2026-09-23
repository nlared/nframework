<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class EmbeddedValidationTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION = [];
        $_SERVER['PHP_SELF'] = '/admin/test.php';

        $GLOBALS['nframework'] = new class {
            public array $onces = [];
            public array $language = ['Drag files here to upload' => 'Drag files here to upload'];
            public function addjqueryui(): void {}
            public function addfileupload(): void {}
        };
        $GLOBALS['javas'] = new class {
            public array $js = [];
            public function addjs($js): void
            {
                $this->js[] = $js;
            }
        };
    }

    public function testBaseInputValidationRulesAreIncluded(): void
    {
        $input = new \baseInput([
            'field' => 'email',
            'pattern' => '^[a-z0-9._%+-]+@[a-z0-9.-]+\\.[a-z]{2,}$',
            'required' => true,
        ]);

        $validation = $input->data_validate();

        $this->assertStringContainsString('required', $validation);
        $this->assertStringContainsString('pattern=', $validation);
        $this->assertStringContainsString('^[a-z0-9._%+-]+', $validation);
    }

    public function testBaseInputDoesNotEmitEmptyPatternAttribute(): void
    {
        $input = new \baseInput([
            'field' => 'name',
            'caption' => 'Nombre',
            'value' => 'Ana',
        ]);

        $this->assertStringNotContainsString('data-mask-pattern=""', $input->inputtags());
    }

    public function testInputTextEmailValidationAcceptsValidAndRejectsInvalidValues(): void
    {
        $input = new \inputText([
            'field' => 'email',
            'type' => 'email',
            'required' => true,
        ]);

        $this->assertStringContainsString('email', $input->data_validate());
        $this->assertTrue($input->is_valid('user@example.com'));
        $this->assertFalse($input->is_valid('not-an-email'));
    }

    public function testInputNumberValidationHandlesIntegersAndFloats(): void
    {
        $integer = new \inputNumber(['field' => 'quota', 'validate' => 'integer']);
        $float = new \inputNumber(['field' => 'price', 'validate' => 'float']);

        $this->assertTrue($integer->is_valid('42'));
        $this->assertFalse($integer->is_valid('42.5'));
        $this->assertTrue($float->is_valid('42.5'));
        $this->assertFalse($float->is_valid('abc'));
    }

    public function testEmbeddedArraySessionStateIsScopedPerDocument(): void
    {
        $createDataset = function (string $id): object {
            $collection = new class {
                public function getDatabaseName(): string
                {
                    return 'app';
                }

                public function getCollectionName(): string
                {
                    return 'documents';
                }
            };

            $dataset = new \stdClass();
            $dataset->collection = $collection;
            $dataset->nameprefix = 'person';
            $dataset->historic = false;
            $dataset->simpleid = false;
            $dataset->_id = $id;
            $dataset->info = [];

            return $dataset;
        };

        $firstDataset = $createDataset('64f000000000000000000001');
        $secondDataset = $createDataset('64f000000000000000000002');

        $firstArray = new \embededArray([
            'dataset' => $firstDataset,
            'field' => 'phones',
            'id' => 'ArrayFront_1',
            'containerid' => 'container_1',
            'dialogid' => 'dialog_1',
            'template' => '<div>{{ items|length }}</div>',
        ]);
        $secondArray = new \embededArray([
            'dataset' => $secondDataset,
            'field' => 'phones',
            'id' => 'ArrayFront_2',
            'containerid' => 'container_2',
            'dialogid' => 'dialog_2',
            'template' => '<div>{{ items|length }}</div>',
        ]);

        $firstArray->__toString();
        $secondArray->__toString();

        $this->assertArrayHasKey($firstArray->id, $_SESSION['nfembeded']);
        $this->assertArrayHasKey($secondArray->id, $_SESSION['nfembeded']);
        $this->assertNotSame(
            $_SESSION['nfembeded'][$firstArray->id]['document_key'],
            $_SESSION['nfembeded'][$secondArray->id]['document_key']
        );
    }

    public function testEmbeddedArrayKeepsOriginalPageInDocumentKeyScope(): void
    {
        $_SERVER['PHP_SELF'] = '/docs/arraylist.php';

        $dataset = new \stdClass();
        $dataset->collection = new class {
            public function getDatabaseName(): string
            {
                return 'app';
            }

            public function getCollectionName(): string
            {
                return 'documents';
            }
        };
        $dataset->nameprefix = 'person';
        $dataset->historic = false;
        $dataset->simpleid = false;
        $dataset->_id = '64f000000000000000000001';
        $dataset->info = [];

        $array = new \embededArray([
            'dataset' => $dataset,
            'field' => 'phones',
            'id' => 'ArrayFront_page_scope',
            'containerid' => 'container_page_scope',
            'dialogid' => 'dialog_page_scope',
            'template' => '<div>{{ items|length }}</div>',
        ]);

        $array->__toString();

        $sessionEntry = $_SESSION['nfembeded'][$array->id];
        $expectedKey = hash('sha256', 'app.documents.64f000000000000000000001./docs/arraylist.php');

        $this->assertSame('/docs/arraylist.php', $sessionEntry['page']);
        $this->assertSame($expectedKey, $sessionEntry['document_key']);
        $this->assertNotSame(hash('sha256', 'app.documents.64f000000000000000000001./nframework/embeded.php'), $sessionEntry['document_key']);
    }

    public function testEmbeddedArrayEscapesNewlineInValidationErrors(): void
    {
        $dataset = new \stdClass();
        $dataset->collection = new class {
            public function getDatabaseName(): string
            {
                return 'app';
            }

            public function getCollectionName(): string
            {
                return 'documents';
            }
        };
        $dataset->nameprefix = 'person';
        $dataset->historic = false;
        $dataset->simpleid = false;
        $dataset->_id = '64f000000000000000000001';
        $dataset->info = [];

        $array = new \embededArray([
            'dataset' => $dataset,
            'field' => 'phones',
            'id' => 'ArrayFront_js_escape',
            'containerid' => 'container_js_escape',
            'dialogid' => 'dialog_js_escape',
            'template' => '<div>{{ items|length }}</div>',
        ]);

        $array->__toString();
        $generatedJs = implode('', $GLOBALS['javas']->js);

        $this->assertStringContainsString("errormsg ? '\\\\n' : ''", $generatedJs);
        $this->assertStringNotContainsString("errormsg ? '\n' : ''", $generatedJs);
    }

    public function testFileUploadSessionCanBeReusedForSameControlId(): void
    {
        $_SESSION['uploads4'] = [
            'doc_file' => [
                'dir' => '/tmp/old-path',
                'formname' => 'doc_file',
                'countlimit' => 1,
                'limit_time_end' => 1,
            ],
        ];

        $upload = new \inputFiles([
            'id' => 'doc_file',
            'name' => 'doc_file',
            'dir' => '/tmp/new-path',
            'countlimit' => 3,
            'path' => '/tmp/new-path',
        ]);

        $upload->__toString();

        $this->assertSame('/tmp/new-path', $_SESSION['uploads4']['doc_file']['dir']);
        $this->assertSame(3, (int) $_SESSION['uploads4']['doc_file']['countlimit']);
        $this->assertSame('doc_file', $_SESSION['uploads4']['doc_file']['formname']);
    }
}
