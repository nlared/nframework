<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Base;

/**
 * Test suite for Base and baseInput classes
 */
class BaseClassTest extends TestCase
{
    /**
     * Test Base class instantiation with options
     */
    public function testBaseConstructorWithOptions(): void
    {
        $options = [
            'name' => 'test_name',
            'value' => 'test_value',
            'id' => 'test_id'
        ];

        $base = new Base($options);

        $this->assertEquals('test_name', $base->name);
        $this->assertEquals('test_value', $base->value);
        $this->assertEquals('test_id', $base->id);
    }

    /**
     * Test Base class with empty options
     */
    public function testBaseConstructorEmpty(): void
    {
        $base = new Base();
        $this->assertIsArray($base->tags);
        $this->assertEmpty($base->tags);
    }

    /**
     * Test Base class dynamic property assignment
     */
    public function testBaseDynamicProperties(): void
    {
        $base = new Base([
            'custom_prop' => 'custom_value',
            'another_prop' => 123
        ]);

        $this->assertEquals('custom_value', $base->custom_prop);
        $this->assertEquals(123, $base->another_prop);
    }
}

/**
 * Test suite for helper functions
 */
class HelperFunctionsTest extends TestCase
{
    /**
     * Test booltotag function with true value
     */
    public function testBooltotagTrue(): void
    {
        $result = booltotag('disabled', true);
        $this->assertEquals(' disabled="true"', $result);
    }

    /**
     * Test booltotag function with false value
     */
    public function testBooltotagFalse(): void
    {
        $result = booltotag('disabled', false);
        $this->assertEquals(' disabled="false"', $result);
    }

    /**
     * Test strtotag function with value
     */
    public function testStrtotagWithValue(): void
    {
        $result = strtotag('id', 'myId');
        $this->assertEquals(' id="myId"', $result);
    }

    /**
     * Test strtotag function with empty value
     */
    public function testStrtotagEmpty(): void
    {
        $result = strtotag('id', '');
        $this->assertEquals('', $result);
    }

    /**
     * Test strtotag function with null value
     */
    public function testStrtotagNull(): void
    {
        $result = strtotag('id', null);
        $this->assertEquals('', $result);
    }

    /**
     * Test icontotag function with value
     */
    public function testIcontotagWithValue(): void
    {
        $result = icontotag('data-icon', 'fa fa-home');
        $this->assertEquals(' data-icon=\'fa fa-home\'', $result);
    }

    /**
     * Test icontotag function with quotes in value
     */
    public function testIcontotagWithQuotes(): void
    {
        $result = icontotag('data-icon', 'icon with "quotes"');
        $this->assertEquals(' data-icon=\'icon with \'quotes\'\'', $result);
    }

    /**
     * Test icontotag function with empty tag
     */
    public function testIcontotagEmptyTag(): void
    {
        $result = icontotag('', 'value');
        $this->assertEquals('', $result);
    }
}

class EmbeddedArrayValidationTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
        $_SERVER['PHP_SELF'] = '/admin/test.php';

        $GLOBALS['nframework'] = new \stdClass();
        $GLOBALS['nframework']->onces = [];
        $GLOBALS['javas'] = new class {
            public function addjs($js): void
            {
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

}
