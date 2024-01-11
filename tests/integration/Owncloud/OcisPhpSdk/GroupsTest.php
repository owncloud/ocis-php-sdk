<?php

namespace integration\Owncloud\OcisPhpSdk;

require_once __DIR__ . '/OcisPhpSdkTestCase.php';

use OpenAPI\Client\Model\User;
use Owncloud\OcisPhpSdk\Group;
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
            $this->assertCount(
                1,
                $group->getMembers(),
                "The group ".$group->getDisplayName()
                . " should have 1 member but found "
                .count($group->getMembers())." members"
            );
            $this->assertEquals(
                $userName,
                $group->getMembers()[0]->getDisplayName(),
                $userName . " user be the first member". $group->getDisplayName() . " group but found "
                . $group->getMembers()[0]->getDisplayName()
            );
        }
    }

    /**
     * @return void
     */
    public function testRemoveExistingUserFromGroup(): void
    {
        $ocis = $this->getOcis('admin', 'admin');
        $this->initUser('einstein', 'relativity');
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
        $this->assertEquals(
            $initialMemberCount - 1,
            count($createdGroup[0]->getMembers()),
            "Expected " .($initialMemberCount - 1)
            . " group member(s) but got "
            . count($createdGroup[0]->getMembers())
        );
        $this->assertEquals(
            $adminUserName,
            $createdGroup[0]->getMembers()[0]->getDisplayName(),
            "Username of group member should be "
            . $adminUserName . " but found ".$createdGroup[0]->getMembers()[0]->getDisplayName()
        );
    }

    /**
     * @return void
     */
    public function testNormalUserRemoveExistingUserFromGroup(): void
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
        $einsteinPholosophyHatersGroup = $einsteinUserOcis->getGroups("philosophyhaters")[0];
        $this->expectException(UnauthorizedException::class);
        foreach ($users as $user) {
            if($user->getDisplayName() === "Admin") {
                $einsteinPholosophyHatersGroup->removeUser($user);
            }
        }
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
        $this->createdGroups = [$physicsLoversGroup];
        $this->assertCount(
            1,
            $ocis->getGroups(),
            "Expected one group but found ". count($ocis->getGroups())
        );
        $this->assertEquals(
            "physicslovers",
            $ocis->getGroups()[0]->getDisplayName(),
            "Group should be deleted but exists "
            .$ocis->getGroups()[0]->getDisplayName()
        );

    }

    /**
     * @return void
     */
    public function testGetGroupsByNormalUser(): void
    {
        $ocis = $this->getOcis('admin', 'admin');
        $philosophyHatersGroup =  $ocis->createGroup("philosophyhaters", "philosophy haters group");
        $this->createdGroups = [$philosophyHatersGroup];
        $marieOcis = $this->getOcis('marie', 'radioactivity');
        $groups = $marieOcis->getGroups("philosophyhaters");
        $this->assertCount(
            1,
            $groups,
            "Expected one group but found ". count($groups)
        );
        foreach ($groups as $group) {
            $this->assertInstanceOf(
                Group::class,
                $group,
                "Expected class ".Group::class
                . " but got " . get_class($group)
            );
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
        $ocis = $this->getOcis('admin', 'admin');
        $philosophyHatersGroup = $ocis->createGroup("philosophyhaters", "philosophy haters group");
        $this->createdGroups = [$philosophyHatersGroup];
        $einsteinOcis = $this->getOcis('einstein', 'relativity');
        $philosophyHatersGroupEinestine = $einsteinOcis->getGroups("philosophy");
        $groupId = $philosophyHatersGroupEinestine[0]->getId();
        $this->expectException(UnauthorizedException::class);
        $einsteinOcis->deleteGroupByID($groupId);
    }

    public function testDeleteNonExistingGroup(): void
    {
        $ocis = $this->getOcis('admin', 'admin');
        $this->expectException(NotFoundException::class);
        $ocis->deleteGroupByID("thisgroupdosenotexist");
    }

}
