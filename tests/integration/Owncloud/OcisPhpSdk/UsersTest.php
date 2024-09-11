<?php

namespace integration\Owncloud\OcisPhpSdk;

require_once __DIR__ . '/OcisPhpSdkTestCase.php';

use Owncloud\OcisPhpSdk\Exception\ForbiddenException;
use Owncloud\OcisPhpSdk\Exception\NotFoundException;
use Owncloud\OcisPhpSdk\User;

class UsersTest extends OcisPhpSdkTestCase
{
    public function testGetAllUsers(): void
    {
        $this->initUser('einstein', 'relativity');
        $this->initUser('marie', 'radioactivity');
        $ocis = $this->getOcis('admin', 'admin');
        $users = $ocis->getUsers();
        $this->assertContainsOnly(
            'Owncloud\OcisPhpSdk\User',
            $users,
            null,
            "Array contains not only 'User' items",
        );
        $this->assertGreaterThanOrEqual(
            3,
            count($users),
            "Expected at least 3 users, but found " . count($users),
        );
    }

    public function testGetUsersByNormalUser(): void
    {
        $this->initUser('einstein', 'relativity');
        $this->initUser('marie', 'radioactivity');
        $ocis = $this->getOcis('marie', 'radioactivity');
        $users = $ocis->getUsers("mar");
        $this->assertContainsOnly(
            'Owncloud\OcisPhpSdk\User',
            $users,
            null,
            "Array contains not only 'User' items",
        );
        $this->assertGreaterThanOrEqual(
            1,
            count($users),
            "Expected at least 1 user, but found " . count($users),
        );
        $this->assertSame(
            'Marie Curie',
            $users[0]->getDisplayName(),
            "Username should be 'Marie Curie' but found " . $users[0]->getDisplayName(),
        );
    }

    public function testSearchUsers(): void
    {
        $this->initUser('marie', 'radioactivity');
        $this->initUser('einstein', 'relativity');
        $this->initUser('moss', 'vista');
        $this->initUser('katherine', 'gemini');
        $ocis = $this->getOcis('admin', 'admin');
        $users = $ocis->getUsers('Albert');
        $this->assertContainsOnly(
            'Owncloud\OcisPhpSdk\User',
            $users,
            null,
            "Array contains not only 'User' items",
        );
        $this->assertGreaterThanOrEqual(
            1,
            count($users),
            "Expected at least 1 user, but found " . count($users),
        );
        $this->assertSame(
            'Albert Einstein',
            $users[0]->getDisplayName(),
            "Username should be 'Albert Einstein' but found " . $users[0]->getDisplayName(),
        );
    }

    public function testGetAllUsersAsUnprivilegedUser(): void
    {
        $this->initUser('marie', 'radioactivity');
        $ocis = $this->getOcis('einstein', 'relativity');
        $this->expectException(ForbiddenException::class);
        $ocis->getUsers();
    }

    public function testGetAUserUsingID(): void
    {
        $this->initUser('marie', 'radioactivity');
        $this->initUser('einstein', 'relativity');
        $this->initUser('moss', 'vista');
        $ocis = $this->getOcis('admin', 'admin');
        $users = $ocis->getUsers('Albert');
        foreach ($users as $user) {
            if ($user->getDisplayName() === 'Albert Einstein') {
                $einsteinUser = $ocis->getUserById($user->getId());
                $this->assertInstanceOf(
                    User::class,
                    $einsteinUser,
                    "Expected class to be User but found "
                    . print_r($einsteinUser, true),
                );
                $this->assertSame(
                    $user->getDisplayName(),
                    $einsteinUser->getDisplayName(),
                    "Expected display name to be Albert Einstein but found "
                    . $einsteinUser->getDisplayName(),
                );
                $this->assertSame(
                    $user->getOnPremisesSamAccountName(),
                    $einsteinUser->getOnPremisesSamAccountName(),
                    "Expected PremisesSamAccountName to be same but found "
                    . $einsteinUser->getOnPremisesSamAccountName(),
                );
                $this->assertEquals(
                    $user->getIdentities(),
                    $einsteinUser->getIdentities(),
                    "Expected Identity to be same but found "
                    . print_r($user->getIdentities(), true),
                );
            }
        }
    }

    public function testGetAUserUsingInvalidUserID(): void
    {
        $this->initUser('marie', 'radioactivity');
        $this->initUser('einstein', 'relativity');
        $ocis = $this->getOcis('admin', 'admin');
        $this->expectException(NotFoundException::class);
        $ocis->getUserById($this->getUUIDv4Regex());
    }
}
