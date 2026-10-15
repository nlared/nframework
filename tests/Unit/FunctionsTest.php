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

    /**
     * Test nfMailer builds a PHPMailer from $config['smtp']
     */
    public function testNfMailerUsesSiteSmtpConfig(): void
    {
        $previous = $GLOBALS['config'];
        $GLOBALS['config']['smtp'] = [
            'host' => 'smtp.example.com',
            'port' => '587',
            'auth' => '1',
            'username' => 'mailer',
            'password' => 'secret',
            'secure' => 'tls',
            'fromemail' => 'noreply@example.com',
            'fromname' => 'Example',
        ];
        try {
            $mail = nfMailer();
        } finally {
            $GLOBALS['config'] = $previous;
        }

        $this->assertSame('smtp', $mail->Mailer);
        $this->assertSame('smtp.example.com', $mail->Host);
        $this->assertSame(587, $mail->Port);
        $this->assertTrue($mail->SMTPAuth);
        $this->assertSame('tls', $mail->SMTPSecure);
        $this->assertSame('noreply@example.com', $mail->From);
        $this->assertSame('Example', $mail->FromName);
        $this->assertSame('UTF-8', $mail->CharSet);
    }

    /**
     * Test nfMailer accepts an explicit SMTP config instead of $config['smtp']
     */
    public function testNfMailerAcceptsExplicitSmtpConfig(): void
    {
        $mail = nfMailer([
            'host' => 'mail.example.org',
            'port' => 465,
            'secure' => 'ssl',
            'timeout' => 15,
            'fromemail' => 'test@example.org',
        ]);

        $this->assertSame('mail.example.org', $mail->Host);
        $this->assertSame(465, $mail->Port);
        $this->assertFalse($mail->SMTPAuth);
        $this->assertSame('ssl', $mail->SMTPSecure);
        $this->assertSame(15, $mail->Timeout);
        $this->assertSame('test@example.org', $mail->From);
    }

    public function testUploadFilenameRejectsProtectedExtensionsByDefault(): void
    {
        $this->assertTrue(nfValidUploadFilename('foto.jpg'));
        $this->assertTrue(nfValidUploadFilename('reporte final.pdf'));
        $this->assertFalse(nfValidUploadFilename('script.py'));
        $this->assertFalse(nfValidUploadFilename('x.PHP'));
        $this->assertFalse(nfValidUploadFilename('x.php.jpg'));
        $this->assertFalse(nfValidUploadFilename('.htaccess'));
        $this->assertFalse(nfValidUploadFilename('../x.jpg'));
        $this->assertFalse(nfValidUploadFilename('a;b.jpg'));
        $this->assertFalse(nfValidUploadFilename(''));
    }

    public function testUploadFilenameAcceptsAllowedProtectedExtensions(): void
    {
        $this->assertTrue(nfValidUploadFilename('script.py', ['py']));
        $this->assertTrue(nfValidUploadFilename('instala.SH', ['.sh']));
        $this->assertFalse(nfValidUploadFilename('instala.sh', ['py']));
        // Cada extensión protegida debe estar permitida.
        $this->assertFalse(nfValidUploadFilename('x.py.exe', ['py']));
        // allowext no relaja las demás reglas.
        $this->assertFalse(nfValidUploadFilename('.x.py', ['py']));
        $this->assertFalse(nfValidUploadFilename('a$b.py', ['py']));
    }

    public function testUploadFilenameAllowsPhpOnlyOutsideDocumentRoot(): void
    {
        $base = sys_get_temp_dir() . '/nfupload_' . uniqid();
        mkdir($base . '/public/archivos', 0777, true);
        mkdir($base . '/privado', 0777, true);
        try {
            $docroot = $base . '/public';
            $this->assertFalse(nfValidUploadFilename('plugin.php', ['php'], $base . '/public/archivos', $docroot));
            // Carpeta que aún no existe (create_dir) dentro del document root.
            $this->assertFalse(nfValidUploadFilename('plugin.php', ['php'], $base . '/public/nueva/sub', $docroot));
            $this->assertTrue(nfValidUploadFilename('plugin.php', ['php'], $base . '/privado', $docroot));
            // Sin document root conocido se asume que la carpeta es pública.
            $this->assertFalse(nfValidUploadFilename('plugin.php', ['php'], $base . '/privado', ''));
            // Las demás extensiones protegidas no dependen de la carpeta.
            $this->assertTrue(nfValidUploadFilename('script.py', ['py'], $base . '/public/archivos', $docroot));
        } finally {
            delTree($base);
        }
    }
}
