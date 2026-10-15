<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/class.Signature_pad.php';

class SignaturePadTest extends TestCase
{
    public function testSessionConfigKeepsServerSideOptions(): void
    {
        $pad = new \Signature_pad([
            'name' => 'firma1',
            'path' => '/var/data/firmas/1.png',
            'extension' => '/var/www/sitio/firma_ext.php',
            'onsave' => 'firmaGuardada',
        ]);

        $this->assertSame('firma1', $pad->id);
        $this->assertSame([
            'path' => '/var/data/firmas/1.png',
            'extension' => '/var/www/sitio/firma_ext.php',
            'onsave' => 'firmaGuardada',
        ], $pad->sessionConfig());
    }

    public function testFormSaveNeverOverwritesTheSignatureField(): void
    {
        $pad = new \Signature_pad(['name' => 'firma']);

        // dataset->save() omite los elementos con backreadonly: la firma la escribe imageup.php.
        $this->assertTrue($pad->backreadonly);
        $this->assertTrue($pad->is_valid(null));
    }

    public function testIdComesFromFieldWithDatasetPrefix(): void
    {
        $dataset = new class {
            public $nameprefix = 'data';
            public $elements = [];
            public $info = [];

            public function addElement(&$element): void
            {
                $this->elements[] = $element;
            }
        };

        $pad = new \Signature_pad(['dataset' => $dataset, 'field' => 'firma']);

        // Antes, la propiedad $name redeclarada como null dejaba el id en "data_".
        $this->assertSame('data[firma]', $pad->name);
        $this->assertSame('data_firma', $pad->id);
    }
}
