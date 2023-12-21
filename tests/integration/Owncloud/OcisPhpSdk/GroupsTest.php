<?php

namespace integration\Owncloud\OcisPhpSdk;

require_once __DIR__ . '/OcisPhpSdkTestCase.php';

use OpenAPI\Client\Model\User;
use Owncloud\OcisPhpSdk\Group;
use Owncloud\OcisPhpSdk\Ocis;
use Owncloud\OcisPhpSdk\Exception\NotFoundException;
use Owncloud\OcisPhpSdk\Exception\UnauthorizedException;

class GroupsTest extends OcisPhpSdkTestCase
{
    /**
     * @return void
     */
    public function testAddUserToGroup(): void
    {
        $ocis = $this->getOcis('admin', 'admin');
        $users = $ocis->getUsers('admin');
        $userName = $users[0]->getDisplayName();
        $philosophyHatersGroup =  $ocis->createGroup(
            "philosophyhaters",
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
        $ocis = $this->getOcis('admin', 'admin');
        $einsteinUserOcis = $this->initUser('einstein', 'relativity');
        $users = $ocis->getUsers();
        $philosophyHatersGroup =  $ocis->createGroup(
            "philosophyhaters",
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
        $ocis = $this->getOcis('admin', 'admin');
        $users = $ocis->getUsers('admin');
        $philosophyHatersGroup =  $ocis->createGroup(
            "philosophyhaters",
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
            "philosophyhaters",
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
        $groups = $ocis->getGroups(search:"philosophyhaters");
        $this->assertGreaterThanOrEqual(1, $groups);
        $groups[0]->addUser($sdkUser);
    }

    /**
     * @return void
     */
    public function testAddUserToGroupUnauthorizedUser(): void
    {
        $ocis = $this->getOcis('admin', 'admin');
        $marieOcis = $this->getOcis('marie', 'radioactivity');
        $physicsLoversGroup =  $ocis->createGroup("physicslovers", "physics lovers group");
        $this->createdGroups = [$physicsLoversGroup];
        $users = $marieOcis->getUsers('marie');
        $groups = $marieOcis->getGroups(search:"physicslovers");
        $this->assertGreaterThanOrEqual(1, $groups);
        $this->expectException(UnauthorizedException::class);
        $groups[0]->addUser($users[0]);
    }


    /**
     * @return void
     */
    public function testDeleteGroup(): void
    {
        $ocis = $this->getOcis('admin', 'admin');
        $philosophyHatersGroup =  $ocis->createGroup(
            "philosophyhaters",
            "philosophy haters group"
        );
        $physicsLoversGroup = $ocis->createGroup(
            "physicslovers",
            "physics lover group"
        );
        $philosophyHatersGroup->delete();
        $this->assertCount(1, $ocis->getGroups());
        $this->assertEquals("physicslovers", $ocis->getGroups()[0]->getDisplayName());
        $this->createdGroups = [$physicsLoversGroup];
    }

    /**
     * @return void
     */
    public function testGetGroupsByNormalUser(): void
    {
        $ocis = $this->getOcis('admin', 'admin');
        $philosophyHatersGroup =  $ocis->createGroup("philosophyhaters", "philosophy haters group");
        $this->createdGroups = [$philosophyHatersGroup];
        $ocis = $this->getOcis('marie', 'radioactivity');
        $groups = $ocis->getGroups("philosophyhaters");
        $this->assertCount(1, $groups);
        foreach ($groups as $group) {
            $this->assertInstanceOf(Group::class, $group);
            $this->assertIsString($group->getId());
            $this->assertIsString($group->getDisplayName());
            $this->assertIsArray($group->getGroupTypes());
            $this->assertIsArray($group->getMembers());
        }
    }

    /**
     * @return void
     */
    public function testDeleteGroupByIdNoPermission(): void
    {
        $token = $this->getAccessToken("admin", "admin");
        $ocis = new Ocis($this->ocisUrl, $token, ["verify" => false]);
        $philosophyHatersGroup = $ocis->createGroup("philosophyhaters", "philosophy haters group");
        $this->createdGroups = [$philosophyHatersGroup];
        $token = $this->getAccessToken('einstein', 'relativity');
        $ocisEinstein = new Ocis($this->ocisUrl, $token, ["verify" => false]);
        $philosophyHatersGroupEinestine = $ocisEinstein->getGroups("philosophy");
        $groupId = $philosophyHatersGroupEinestine[0]->getId();
        $this->expectException(UnauthorizedException::class);
        $ocisEinstein->deleteGroupByID($groupId);
    }

    public function testDeleteNonExistingGroup(): void
    {
        $token = $this->getAccessToken("admin", "admin");
        $ocis = new Ocis($this->ocisUrl, $token, ["verify" => false]);
        $this->expectException(NotFoundException::class);
        $ocis->deleteGroupByID("thisgroupdosenotexist");
    }

}
