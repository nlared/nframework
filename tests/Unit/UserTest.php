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
     * El menú de usuario muestra el botón a /admin/ solo al grupo admins.
     */
    public function testAdminMenuButtonOnlyForAdmins(): void
    {
        $groups = (new \ReflectionClass(User::class))->getProperty('groupCache');

        $admin = $this->getMockUser(['_id' => '507f1f77bcf86cd799439011', 'username' => 'admin']);
        $groups->setValue($admin, ['admins' => true]);
        $this->assertStringContainsString('href="/admin/"', $admin->adminMenuButton());

        $capturista = $this->getMockUser(['_id' => '507f1f77bcf86cd799439012', 'username' => 'capturista']);
        $groups->setValue($capturista, ['capturistas' => true]);
        $this->assertSame('', $capturista->adminMenuButton());

        $this->assertSame('', $this->getMockUser(['username' => 'guest'])->adminMenuButton());
    }

    /**
     * 2FA obligatorio: por el sitio o por el usuario, y solo mientras no tenga secreto.
     */
    public function testTwofaPendingFor(): void
    {
        $this->assertFalse(User::twofaPendingFor(false, ['username' => 'a']));
        $this->assertTrue(User::twofaPendingFor(true, ['username' => 'a']));
        $this->assertTrue(User::twofaPendingFor(false, ['twofa_required' => true]));
        $this->assertFalse(User::twofaPendingFor(true, ['twofa_required' => true, 'twofa_secret' => 'JBSWY3DPEHPK3PXP']));
        $this->assertFalse(User::twofaPendingFor(false, ['twofa_required' => false]));
    }

    public function testTwofaPendingUsesSiteConfigAndIgnoresGuests(): void
    {
        $original = $GLOBALS['config'];
        try {
            $GLOBALS['config']['twofa_required'] = true;
            $this->assertTrue($this->getMockUser(['username' => 'ana'])->twofaPending());
            $this->assertFalse($this->getMockUser(['username' => 'guest'])->twofaPending());
            $GLOBALS['config']['twofa_required'] = false;
            $this->assertFalse($this->getMockUser(['username' => 'ana'])->twofaPending());
            $this->assertTrue($this->getMockUser(['username' => 'ana', 'twofa_required' => true])->twofaPending());
        } finally {
            $GLOBALS['config'] = $original;
        }
    }

    public function testRecoveryKey(): void
    {
        $key = User::recoveryKeyGenerate();
        $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{5}(-[A-HJ-NP-Z2-9]{5}){3}$/', $key);
        $this->assertNotSame($key, User::recoveryKeyGenerate());
        $hash = User::recoveryKeyHash($key);
        $this->assertSame(64, strlen($hash));
        // Tolera minúsculas, espacios y sin guiones.
        $this->assertTrue(User::recoveryKeyMatches(' ' . strtolower(str_replace('-', ' ', $key)) . ' ', $hash));
        $this->assertFalse(User::recoveryKeyMatches(User::recoveryKeyGenerate(), $hash));
        $this->assertFalse(User::recoveryKeyMatches('', $hash));
        $this->assertFalse(User::recoveryKeyMatches($key, null));
        $this->assertFalse(User::recoveryKeyMatches($key, ''));
        $this->assertSame('', User::recoveryKeyHash('ABCDE-ABCDE-ABCDE-ABCD0'));
    }

    public function testTwofaRequired(): void
    {
        $original = $GLOBALS['config'];
        try {
            $GLOBALS['config']['twofa_required'] = false;
            $this->assertFalse($this->getMockUser(['username' => 'ana', 'twofa_secret' => 'X'])->twofaRequired());
            $this->assertTrue($this->getMockUser(['username' => 'ana', 'twofa_required' => true])->twofaRequired());
            $GLOBALS['config']['twofa_required'] = true;
            $this->assertTrue($this->getMockUser(['username' => 'ana', 'twofa_secret' => 'X'])->twofaRequired());
        } finally {
            $GLOBALS['config'] = $original;
        }
    }

    public function testSessionTokenHidesTheSessionId(): void
    {
        $token = User::sessionToken('abc123sessionid');
        $this->assertMatchesRegularExpression('/^[a-f0-9]{20}$/', $token);
        $this->assertStringNotContainsString('abc123', $token);
        $this->assertSame($token, User::sessionToken('abc123sessionid'));
        $this->assertNotSame($token, User::sessionToken('abc123sessionie'));
    }

    public function testDescribeAgent(): void
    {
        $this->assertSame('Chrome en Windows', User::describeAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Safari/537.36'));
        $this->assertSame('Edge en Windows', User::describeAgent('Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 Chrome/131.0 Safari/537.36 Edg/131.0'));
        $this->assertSame('Safari en iPhone', User::describeAgent('Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Version/18.0 Mobile/15E148 Safari/604.1'));
        $this->assertSame('Firefox en Linux', User::describeAgent('Mozilla/5.0 (X11; Linux x86_64; rv:133.0) Gecko/20100101 Firefox/133.0'));
        $this->assertSame('Desconocido', User::describeAgent(''));
        $this->assertSame('curl/8.5.0', User::describeAgent('curl/8.5.0'));
    }

    public function testPicturesDir(): void
    {
        $original = $GLOBALS['config'];
        try {
            $GLOBALS['config']['users_pictures'] = '/var/data/fotos/';
            $this->assertSame('/var/data/fotos', User::picturesDir());
            $GLOBALS['config']['users_pictures'] = '';
            $this->assertStringEndsWith('/img/nf/users', User::picturesDir());
        } finally {
            $GLOBALS['config'] = $original;
        }
    }

    public function testTwofaExemptPath(): void
    {
        foreach (['/account/twofa-setup', '/account/logout', '/main.js', '/images/resize/x.png'] as $path) {
            $this->assertTrue(User::twofaExemptPath($path), $path);
        }
        foreach (['/', '/admin/', '/account/profile', '/account/twofa-setup/x', '/nframework/uploadfile.php'] as $path) {
            $this->assertFalse(User::twofaExemptPath($path), $path);
        }
    }

    /**
     * OAuth: sin 2FA o con un id inválido nfTwofaChallenge() no toca la sesión ni redirige.
     */
    public function testTwofaChallengeWithoutSecretKeepsSession(): void
    {
        require_once __DIR__ . '/CoreFixesTest.php';
        $savedM = $GLOBALS['m'] ?? null;
        $m = new FakeMongo();
        $m->collections[$GLOBALS['config']['sitedb']] = $m;
        $GLOBALS['m'] = $m;
        try {
            $_SESSION['user'] = '507f1f77bcf86cd799439011';
            $m->collections['users'] = new FakeCollection('users');
            $m->collections['users']->findOneResult = ['_id' => 'x'];
            nfTwofaChallenge('507f1f77bcf86cd799439011');
            $this->assertSame('507f1f77bcf86cd799439011', $_SESSION['user']);
            $this->assertArrayNotHasKey('tmp_user', $_SESSION);
            $this->assertSame(['twofa_secret' => 1], $m->collections['users']->calls[0][1][1]['projection']);

            nfTwofaChallenge('no-es-id');
            $this->assertCount(1, $m->collections['users']->calls);
        } finally {
            $GLOBALS['m'] = $savedM;
            unset($_SESSION['user']);
        }
    }

    /**
     * Helper method to create a User object without touching MongoDB
     */
    /**
     * Clasificación de sesiones: activa, inactiva (sigue en sessions pero rebasó gc_maxlifetime) y terminada.
     */
    public function testSessionRowsClassifiesAndSorts(): void
    {
        $now = 1_800_000_000;
        $rows = User::sessionRows(
            ['gone1', 'idle1', 'act1', 'cur', 'act1', 123],
            ['idle1' => $now - 5000, 'act1' => $now - 600, 'cur' => $now - 900],
            [
                'act1' => ['ip' => '10.0.0.1', 'agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/120 Safari/537', 'since' => $now - 7200],
                'gone1' => ['ip' => '10.0.0.9', 'since' => $now - 90000],
            ],
            1440,
            $now,
            'cur'
        );
        $this->assertSame(['cur', 'act1', 'idle1', 'gone1'], array_column($rows, 'id'));
        $this->assertSame(['active', 'active', 'idle', 'gone'], array_column($rows, 'status'));
        $this->assertTrue($rows[0]['current']);
        $this->assertFalse($rows[1]['current']);
        $this->assertSame('Chrome en Windows', $rows[1]['device']);
        $this->assertSame('10.0.0.1', $rows[1]['ip']);
        $this->assertSame($now - 600 + 1440, $rows[1]['expires']);
        $this->assertNull($rows[3]['last']);
        $this->assertNull($rows[3]['expires']);
        $this->assertSame(User::sessionToken('act1'), $rows[1]['token']);
        $this->assertSame([], User::sessionRows([], [], [], 1440, $now));
    }

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
