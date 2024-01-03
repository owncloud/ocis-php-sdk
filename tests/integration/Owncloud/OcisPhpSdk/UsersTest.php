<?php

namespace integration\Owncloud\OcisPhpSdk;

require_once __DIR__ . '/OcisPhpSdkTestCase.php';

use Owncloud\OcisPhpSdk\Exception\ForbiddenException;

class UsersTest extends OcisPhpSdkTestCase
{
    public function testGetAllUsers(): void
    {
        $this->initUser('einstein', 'relativity');
        $this->initUser('marie', 'radioactivity');
        $ocis = $this->getOcis('admin', 'admin');
        $users = $ocis->getUsers();
        $this->assertContainsOnly('Owncloud\OcisPhpSdk\User', $users);
        $this->assertGreaterThanOrEqual(3, $users);
    }

    public function testGetUsersByNormalUser(): void
    {
        $this->initUser('einstein', 'relativity');
        $this->initUser('marie', 'radioactivity');
        $ocis = $this->getOcis('marie', 'radioactivity');
        $users = $ocis->getUsers("mar");
        $this->assertContainsOnly('Owncloud\OcisPhpSdk\User', $users);
        $this->assertGreaterThanOrEqual(1, $users);
        $this->assertEquals('Marie Curie', $users[0]->getDisplayName());
    }

    public function testSearchUsers(): void
    {
        $this->initUser('marie', 'radioactivity');
        $this->initUser('einstein', 'relativity');
        $this->initUser('moss', 'vista');
        $this->initUser('katherine', 'gemini');
        $ocis = $this->getOcis('admin', 'admin');
        $users = $ocis->getUsers('Albert');
        $this->assertContainsOnly('Owncloud\OcisPhpSdk\User', $users);
        $this->assertCount(1, $users);
        $this->assertSame('Albert Einstein', $users[0]->getDisplayName());
    }

    public function testGetAllUsersAsUnprivilegedUser(): void
    {
        $this->initUser('marie', 'radioactivity');
        $ocis = $this->getOcis('einstein', 'relativity');
        $this->expectException(ForbiddenException::class);
        $ocis->getUsers();
    }
}
