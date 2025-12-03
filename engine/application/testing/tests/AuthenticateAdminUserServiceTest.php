<?php

declare(strict_types=1);

final class AuthenticateAdminUserServiceTest extends TestCase
{
    public function testSuccessfulAuthenticationUpdatesSession(): void
    {
        $repo = new FakeAdminUserRepository();
        $passwordHash = password_hash('secret', PASSWORD_DEFAULT);
        $repo->addUser(new AdminUser(
            id: 1,
            username: 'admin',
            passwordHash: $passwordHash,
            sessionToken: null,
            lastActivity: null,
            isActive: true
        ));

        $service = new AuthenticateAdminUserService($repo);
        $result = $service->execute('admin', 'secret');

        $this->assertTrue($result->success);
        $this->assertEquals(1, $result->userId);
        $this->assertTrue(isset($repo->cleared[1]));
        $this->assertNotNull($repo->sessions[1]['token'] ?? null);
    }

    public function testInvalidPasswordFails(): void
    {
        $repo = new FakeAdminUserRepository();
        $repo->addUser(new AdminUser(
            id: 2,
            username: 'user',
            passwordHash: password_hash('secret', PASSWORD_DEFAULT),
            sessionToken: null,
            lastActivity: null,
            isActive: true
        ));

        $service = new AuthenticateAdminUserService($repo);
        $result = $service->execute('user', 'wrong');

        $this->assertFalse($result->success);
        $this->assertEquals('Невірний логін або пароль', $result->message);
    }
}
