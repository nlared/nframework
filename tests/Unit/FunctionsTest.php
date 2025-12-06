<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Test suite for utility functions in functions.php
 */
class FunctionsTest extends TestCase
{
    /**
     * Test assignArrayByPath function
     */
    public function testAssignArrayByPath(): void
    {
        $arr = ['foo' => ['bar' => 'baz']];
        assignArrayByPath($arr, 'foo.bar', 'updated');
        $this->assertEquals('updated', $arr['foo']['bar']);
    }

    /**
     * Test assignArrayByPath with nested path
     */
    public function testAssignArrayByPathNested(): void
    {
        $arr = [];
        assignArrayByPath($arr, 'level1.level2.level3', 'deep value');
        $this->assertEquals('deep value', $arr['level1']['level2']['level3']);
    }

    /**
     * Test remove_trailing_separator function
     */
    public function testRemoveTrailingSeparator(): void
    {
        $this->assertEquals('/path/to/dir', remove_trailing_separator('/path/to/dir/'));
        $this->assertEquals('/path/to/dir', remove_trailing_separator('/path/to/dir'));
        $this->assertEquals('', remove_trailing_separator('/'));
    }

    /**
     * Test array_diff_recursive function
     */
    public function testArrayDiffRecursive(): void
    {
        $array1 = [
            'key1' => 'value1',
            'key2' => [
                'nested1' => 'nested_value1',
                'nested2' => 'nested_value2'
            ],
            'key3' => 'value3'
        ];

        $array2 = [
            'key1' => 'value1',
            'key2' => [
                'nested1' => 'nested_value1',
                'nested2' => 'different_value'
            ]
        ];

        $diff = array_diff_recursive($array1, $array2);

        $this->assertArrayHasKey('key2', $diff);
        $this->assertArrayHasKey('nested2', $diff['key2']);
        $this->assertEquals('nested_value2', $diff['key2']['nested2']);
        $this->assertArrayHasKey('key3', $diff);
    }

    /**
     * Test ifset function with existing key
     */
    public function testIfsetWithExistingKey(): void
    {
        $array = ['key' => 'value'];
        $this->assertEquals('value', ifset($array, 'key'));
    }

    /**
     * Test ifset function with non-existing key
     */
    public function testIfsetWithNonExistingKey(): void
    {
        $array = ['key' => 'value'];
        $this->assertNull(ifset($array, 'nonexistent'));
    }

    /**
     * Test unsetNestedKey function
     */
    public function testUnsetNestedKey(): void
    {
        $array = [
            'level1' => [
                'level2' => [
                    'level3' => 'value'
                ]
            ]
        ];

        unsetNestedKey($array, 'level1\\level2\\level3');
        $this->assertArrayNotHasKey('level3', $array['level1']['level2']);
    }

    /**
     * Test unsetNestedKey with non-existent path
     */
    public function testUnsetNestedKeyNonExistent(): void
    {
        $array = ['key' => 'value'];
        unsetNestedKey($array, 'nonexistent\\path');
        // Should not throw an error and array should remain unchanged
        $this->assertArrayHasKey('key', $array);
        $this->assertEquals('value', $array['key']);
    }

    /**
     * Test buildMetroMenu function
     */
    public function testBuildMetroMenu(): void
    {
        $nodes = [
            [
                'text' => 'Home',
                'data' => ['link' => '/home']
            ],
            [
                'text' => 'About',
                'data' => ['link' => '/about']
            ]
        ];

        $html = buildMetroMenu($nodes, 'h-menu', 'menu');

        $this->assertStringContainsString('<ul class="h-menu" data-role="menu">', $html);
        $this->assertStringContainsString('Home', $html);
        $this->assertStringContainsString('About', $html);
        $this->assertStringContainsString('/home', $html);
        $this->assertStringContainsString('/about', $html);
    }

    /**
     * Test buildMetroMenu with nested children
     */
    public function testBuildMetroMenuWithChildren(): void
    {
        $nodes = [
            [
                'text' => 'Parent',
                'data' => ['link' => '/parent'],
                'children' => [
                    [
                        'text' => 'Child',
                        'data' => ['link' => '/child']
                    ]
                ]
            ]
        ];

        $html = buildMetroMenu($nodes);

        $this->assertStringContainsString('Parent', $html);
        $this->assertStringContainsString('Child', $html);
        $this->assertStringContainsString('dropdown-toggle', $html);
        $this->assertStringContainsString('d-menu', $html);
    }
}
