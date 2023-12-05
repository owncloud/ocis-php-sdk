<?php

namespace integration\Owncloud\OcisPhpSdk;

require_once __DIR__ . '/OcisPhpSdkTestCase.php';

use OpenAPI\Client\Model\User;
use Owncloud\OcisPhpSdk\Exception\NotFoundException;
use Owncloud\OcisPhpSdk\Exception\UnauthorizedException;
use Owncloud\OcisPhpSdk\Ocis;

class GroupsTest extends OcisPhpSdkTestCase
{
    /**
     * @return void
     */
    public function testAddUserToGroup(): void
    {
        $token = $this->getAccessToken('admin', 'admin');
        $ocis = new Ocis($this->ocisUrl, $token, ['verify' => false]);
        $users = $ocis->getUsers('admin');
        $userName = $users[0]->getDisplayName();
        $philosophyHatersGroup =  $ocis->createGroup(
            "philosophy-haters",
            "philosophy haters group"
        );
        $this->createdGroups = [$philosophyHatersGroup];
        $philosophyHatersGroup->addUser($users[0]);
        foreach ($ocis->getGroups(expandMembers: true) as $group) {
            $this->assertGreaterThan(0, count($group->getMembers()));
            $this->assertEquals($userName, $group->getMembers()[0]->getDisplayName());
        }
    }

    /**
     * @return void
     */
    public function testRemoveExistingUserFromGroup(): void
    {
        $token = $this->getAccessToken('admin', 'admin');
        $ocis = new Ocis($this->ocisUrl, $token, ['verify' => false]);
        $einsteinUserToken = $this->getAccessToken('einstein', 'relativity');
        $einsteinUserOcis = new Ocis($this->ocisUrl, $einsteinUserToken, ["verify" => false]);
        $users = $ocis->getUsers();
        $philosophyHatersGroup =  $ocis->createGroup(
            "philosophy-haters",
            "philosophy haters group"
        );
        $this->createdGroups = [$philosophyHatersGroup];
        foreach($users as $user) {
            $philosophyHatersGroup->addUser($user);
        }
        $initialMemberCount = count($philosophyHatersGroup->getMembers());
        foreach ($users as $user) {
            if($user->getDisplayName() === "Albert Einstein") {
                $philosophyHatersGroup->removeUser($user);
            }
        }
        $adminUserName = $users[0]->getDisplayName();
        $createdGroup = $ocis->getGroups(expandMembers: true);
        $this->assertEquals($initialMemberCount - 1, count($createdGroup[0]->getMembers()));
        $this->assertEquals($adminUserName, $createdGroup[0]->getMembers()[0]->getDisplayName());
    }

    /**
     * @return void
     */
    public function testRemoveUserNotAddedToGroup(): void
    {
        $this->expectException(NotFoundException::class);
        $token = $this->getAccessToken('admin', 'admin');
        $ocis = new Ocis($this->ocisUrl, $token, ['verify' => false]);
        $users = $ocis->getUsers('admin');
        $philosophyHatersGroup =  $ocis->createGroup(
            "philosophy-haters",
            "philosophy haters group"
        );
        $this->createdGroups = [$philosophyHatersGroup];
        $philosophyHatersGroup->removeUser($users[0]);
    }

    /**
    * @return void
    */
    public function testAddUserToGroupInvalid(): void
    {
        $this->expectException(NotFoundException::class);
        $ocis = $this->getOcis('admin', 'admin');
        $philosophyHatersGroup =  $ocis->createGroup(
            "philosophy-haters",
            "philosophy haters group"
        );
        $this->createdGroups = [$philosophyHatersGroup];
        $user = new User(
            [
                "id" => "id",
                "display_name" => "displayname",
                "mail" => "mail@mail.com",
                "on_premises_sam_account_name" => "sd",
            ]
        );
        $sdkUser = new \Owncloud\OcisPhpSdk\User($user);
        $groups = $ocis->getGroups(search:"philosophy");
        $this->assertGreaterThanOrEqual(1, $groups);
        $groups[0]->addUser($sdkUser);
    }

    /**
     * @return void
     */
    public function testAddUserToGroupUnauthorizedUser(): void
    {
        $this->expectException(UnauthorizedException::class);
        $ocis = $this->getOcis('marie', 'radioactivity');
        $physicsLoversGroup =  $ocis->createGroup(
            "physics-lovers",
            "physics lovers group"
        );
        $this->createdGroups = [$physicsLoversGroup];
        $users = $ocis->getUsers('marie');
        $groups = $ocis->getGroups(search:"physics");
        $this->assertGreaterThanOrEqual(1, $groups);
        $groups[0]->addUser($users[0]);
    }


    /**
     * @return void
     */
    public function testDeleteGroup(): void
    {
        $token = $this->getAccessToken("admin", "admin");
        $ocis = new Ocis($this->ocisUrl, $token, ["verify" => false]);
        $philosophyHatersGroup =  $ocis->createGroup(
            "philosophy-haters",
            "philosophy haters group"
        );
        $physicsLoversGroup = $ocis->createGroup(
            "physics-lovers",
            "physics lover group"
        );
        $philosophyHatersGroup->delete();
        $this->assertCount(1, $ocis->getGroups());
        $this->assertEquals("physics-lovers", $ocis->getGroups()[0]->getDisplayName());
        $this->createdGroups = [$physicsLoversGroup];
    }
}
