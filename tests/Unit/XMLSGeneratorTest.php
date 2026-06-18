<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/class.XMLS.php';

class XMLSGeneratorTest extends TestCase
{
  public function testToXmlStringThrowsWhenAttributeIsArray(): void
  {
    $xmlObject = new class() extends \XMLS {
      public $tagName = 'item';
      public $attributes = ['id'];
      public $id = ['invalid'];
    };

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Attribute id cannot be an array');

    $xmlObject->toXmlString();
  }

  public function testToStringReturnsVisibleErrorAndStoresLastSerializationError(): void
  {
    $xmlObject = new class() extends \XMLS {
      public $tagName = 'item';
      public $attributes = ['id'];
      public $id = ['invalid'];
    };

    $xml = (string)$xmlObject;

    $this->assertStringContainsString('XMLS serialization error', $xml);
    $this->assertStringContainsString('Attribute id cannot be an array', $xml);
    $this->assertSame('Attribute id cannot be an array', $xmlObject->lastSerializationError);
  }

  public function testValidateWithXsdReturnsTrueForValidXml(): void
  {
    $tmpDir = sys_get_temp_dir() . '/nframework_xmls_' . uniqid('', true);
    mkdir($tmpDir, 0775, true);

    $xsdPath = $tmpDir . '/schema.xsd';
    file_put_contents(
      $xsdPath,
      <<<XSD
<?xml version="1.0" encoding="UTF-8"?>
<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">
  <xs:element name="item">
    <xs:complexType>
      <xs:sequence>
        <xs:element name="name" type="xs:string"/>
      </xs:sequence>
      <xs:attribute name="id" type="xs:string" use="required"/>
    </xs:complexType>
  </xs:element>
</xs:schema>
XSD
    );

    $xmlObject = new class() extends \XMLS {
      public $tagName = 'item';
      public $attributes = ['id'];
      public $_sequence = ['name'];
      public $id = 'A1';
      public $name = '<name>Test</name>';
    };

    $errors = [];
    $isValid = $xmlObject->validateWithXsd($xsdPath, $errors);

    $this->assertTrue($isValid);
    $this->assertSame([], $errors);
  }

  public function testValidateWithXsdReturnsFalseForInvalidXml(): void
  {
    $tmpDir = sys_get_temp_dir() . '/nframework_xmls_' . uniqid('', true);
    mkdir($tmpDir, 0775, true);

    $xsdPath = $tmpDir . '/schema.xsd';
    file_put_contents(
      $xsdPath,
      <<<XSD
<?xml version="1.0" encoding="UTF-8"?>
<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">
  <xs:element name="item">
    <xs:complexType>
      <xs:sequence>
        <xs:element name="name" type="xs:string"/>
      </xs:sequence>
      <xs:attribute name="id" type="xs:string" use="required"/>
    </xs:complexType>
  </xs:element>
</xs:schema>
XSD
    );

    $xmlObject = new class() extends \XMLS {
      public $tagName = 'item';
      public $attributes = ['id'];
      public $_sequence = ['name'];
      public $id = '';
      public $name = '';
    };

    $errors = [];
    $isValid = $xmlObject->validateWithXsd($xsdPath, $errors);

    $this->assertFalse($isValid);
    $this->assertNotEmpty($errors);
  }

  public function testGenerateClassesFromXsdCreatesClassFiles(): void
  {
    $tmpDir = sys_get_temp_dir() . '/nframework_xmls_' . uniqid('', true);
    mkdir($tmpDir, 0775, true);

    $xsdPath = $tmpDir . '/schema.xsd';
    file_put_contents(
      $xsdPath,
      <<<XSD
<?xml version="1.0" encoding="UTF-8"?>
<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">
  <xs:element name="product">
    <xs:complexType>
      <xs:sequence>
        <xs:element name="title" type="xs:string"/>
        <xs:element name="tag" type="xs:string" maxOccurs="unbounded"/>
      </xs:sequence>
      <xs:attribute name="id" type="xs:string"/>
    </xs:complexType>
  </xs:element>
</xs:schema>
XSD
    );

    $outputDir = $tmpDir . '/generated';
    $generated = \XMLS::generateClassesFromXsd(
      $xsdPath,
      $outputDir,
      'Tmp\\Generated',
      ['overwrite' => true]
    );

    $this->assertNotEmpty($generated);

    $generatedFile = $outputDir . '/Product.php';
    $this->assertFileExists($generatedFile);

    require_once $generatedFile;

    $this->assertTrue(class_exists('Tmp\\Generated\\Product'));
    $object = new \Tmp\Generated\Product();

    $this->assertSame('product', $object->tagName);
    $this->assertSame(['id'], $object->attributes);
    $this->assertSame(['title', 'tag'], $object->_sequence);
    $this->assertSame([], $object->tag);
  }
}
