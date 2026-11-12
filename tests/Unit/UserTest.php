<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use User;

/**
 * Test suite for User class
 * Note: These tests use mocks for MongoDB operations
 */
class UserTest extends TestCase
{
    /**
     * Test User::can method with boolean permissions
     */
    public function testUserCanWithBooleanTrue(): void
    {
        $user = $this->getMockUser([
            'username' => 'testuser',
            'permissions' => [
                'edit' => true,
                'delete' => false
            ]
        ]);

        $this->assertTrue($user->can('edit'));
        $this->assertFalse($user->can('delete'));
    }

    /**
     * Test User::can method with string boolean values
     */
    public function testUserCanWithStringBoolean(): void
    {
        $user = $this->getMockUser([
            'username' => 'testuser',
            'permissions' => [
                'view' => 'true',
                'modify' => 'false',
                'admin' => 'on',
                'guest' => 'off'
            ]
        ]);

        $this->assertTrue($user->can('view'));
        $this->assertFalse($user->can('modify'));
        $this->assertTrue($user->can('admin'));
        $this->assertFalse($user->can('guest'));
    }

    /**
     * Test User::can method with numeric boolean values
     */
    public function testUserCanWithNumericBoolean(): void
    {
        $user = $this->getMockUser([
            'username' => 'testuser',
            'permissions' => [
                'read' => 1,
                'write' => 0
            ]
        ]);

        $this->assertTrue($user->can('read'));
        $this->assertFalse($user->can('write'));
    }

    /**
     * Test User::can method with non-existent permission
     */
    public function testUserCanWithNonExistentPermission(): void
    {
        $user = $this->getMockUser([
            'username' => 'testuser',
            'permissions' => []
        ]);

        $this->assertFalse($user->can('nonexistent'));
    }

    /**
     * Test User data method
     */
    public function testUserData(): void
    {
        $userData = [
            'username' => 'testuser',
            'email' => 'test@example.com',
            '_id' => '507f1f77bcf86cd799439011'
        ];

        $user = $this->getMockUser($userData);
        $data = $user->data();

        $this->assertIsArray($data);
        $this->assertEquals('testuser', $data['username']);
        $this->assertEquals('test@example.com', $data['email']);
    }

    protected function tearDown(): void
    {
        unset($_SESSION['nf']['browser']['avif']);
    }

    /**
     * Sin soporte AVIF registrado en la sesión la foto se pide en WebP.
     */
    public function testUserGravatarDefaultsToWebp(): void
    {
        unset($_SESSION['nf']['browser']['avif']);
        $user = $this->getMockUser([
            '_id' => '507f1f77bcf86cd799439011',
            'username' => 'testuser'
        ]);

        $this->assertSame('/images/pngtowebp/users/32/32/507f1f77bcf86cd799439011.webp', $user->gravatar());
    }

    /**
     * Si el navegador aceptó AVIF (y GD lo genera) la foto se pide en AVIF.
     */
    public function testUserGravatarUsesAvifWhenBrowserSupportsIt(): void
    {
        if (!nfSupportsAvif()) {
            $this->markTestSkipped('GD sin soporte AVIF');
        }
        $_SESSION['nf']['browser']['avif'] = true;
        $user = $this->getMockUser([
            '_id' => '507f1f77bcf86cd799439011',
            'username' => 'testuser'
        ]);

        $this->assertSame('/images/pngtoavif/users/32/32/507f1f77bcf86cd799439011.avif', $user->gravatar());
    }

    /**
     * Tamaño: el alto toma el ancho si se omite; ambos se limitan a 1..2048.
     */
    public function testUserGravatarWithDimensions(): void
    {
        $user = $this->getMockUser([
            '_id' => '507f1f77bcf86cd799439011',
            'username' => 'testuser'
        ]);

        $this->assertStringContainsString('/users/64/48/', $user->gravatar(64, 48));
        $this->assertStringContainsString('/users/120/120/', $user->gravatar(120));
        $this->assertStringContainsString('/users/2048/2048/', $user->gravatar(9999));
        $this->assertStringContainsString('/users/32/32/', $user->gravatar(''));
    }

    /**
     * Helper method to create a User object without touching MongoDB
     */
    private function getMockUser(array $userData): User
    {
        // Bypass the constructor (it queries MongoDB)
        $reflection = new \ReflectionClass(User::class);
        $user = $reflection->newInstanceWithoutConstructor();

        // Manually set the info property
        $property = $reflection->getProperty('info');
        $property->setValue($user, $userData);

        return $user;
    }
}

/**
 * Integration tests for User authentication flow
 * These tests would require a test MongoDB database
 */
class UserAuthenticationTest extends TestCase
{
    /**
     * Test that guest user requires authentication
     */
    public function testGuestUserRequiresAuth(): void
    {
        // This test would require actual MongoDB setup
        // Marking as incomplete for now
        $this->markTestIncomplete(
            'This test requires MongoDB setup and should be run as an integration test'
        );
    }

    /**
     * Test user creation with hashed password
     */
    public function testUserCreationHashesPassword(): void
    {
        // This test would require actual MongoDB setup
        $this->markTestIncomplete(
            'This test requires MongoDB setup and should be run as an integration test'
        );
    }

    /**
     * Test user group membership
     */
    public function testUserInGroup(): void
    {
        // This test would require actual MongoDB setup
        $this->markTestIncomplete(
            'This test requires MongoDB setup and should be run as an integration test'
        );
    }
}
