<?php

namespace integration\Owncloud\OcisPhpSdk;

require_once __DIR__ . '/OcisPhpSdkTestCase.php';

use Owncloud\OcisPhpSdk\Exception\ForbiddenException;
use Owncloud\OcisPhpSdk\Group;
use Owncloud\OcisPhpSdk\Ocis;
use Owncloud\OcisPhpSdk\OrderDirection;
use OpenAPI\Client\Model\User;
use Owncloud\OcisPhpSdk\Exception\NotFoundException;
use Owncloud\OcisPhpSdk\Exception\UnauthorizedException;

class OcisTest extends OcisPhpSdkTestCase
{
    public function testServiceUrlTrailingSlash(): void
    {
        $token = $this->getAccessToken('admin', 'admin');
        $ocis = new Ocis($this->ocisUrl . '///', $token, ['verify' => false]);
        $drives = $ocis->getMyDrives();
        $this->assertTrue((is_array($drives) && count($drives) > 1));
    }

    public function testCreateDrive(): void
    {
        $token = $this->getAccessToken('admin', 'admin');
        $ocis = new Ocis($this->ocisUrl, $token, ['verify' => false]);
        $countDrivesAtStart = count(
            $ocis->getMyDrives()
        );
        $drive = $ocis->createDrive('first test drive');
        $this->createdDrives[] = $drive->getId();
        $this->assertMatchesRegularExpression(self::UUID_REGEX_PATTERN, $drive->getId());
        // there should be one more drive
        $this->assertCount($countDrivesAtStart + 1, $ocis->getMyDrives());
    }

    public function testCreateDriveNoPermissions(): void
    {
        $token = $this->getAccessToken('einstein', 'relativity');
        $ocis = new Ocis($this->ocisUrl, $token, ['verify' => false]);
        $this->expectException(ForbiddenException::class);
        $countDrivesAtStart = count($ocis->getMyDrives());
        $ocis->createDrive('first test drive');
        // no new drive should have been created
        $this->assertCount($countDrivesAtStart, $ocis->getMyDrives());
    }

    /**
     * @return array<int,array<int,int>>
     */
    public function invalidQuotaProvider()
    {
        return [
            [-1],
            [-100],
        ];
    }
    /**
     * @dataProvider invalidQuotaProvider
     */
    public function testCreateDriveInvalidQuota(int $quota): void
    {
        $token = $this->getAccessToken('admin', 'admin');
        $ocis = new Ocis($this->ocisUrl, $token, ['verify' => false]);
        $this->expectException(\InvalidArgumentException::class);
        $countDrivesAtStart = count($ocis->getMyDrives());
        $ocis->createDrive('drive with quota', $quota);
        // no new drive should have been created
        $this->assertCount($countDrivesAtStart, $ocis->getMyDrives());
    }

    /**
     * @return void
     */
    public function testGetGroups(): void
    {
        $token = $this->getAccessToken('admin', 'admin');
        $ocis = new Ocis($this->ocisUrl, $token, ['verify' => false]);
        $ocis->createGroup("philosophy-haters", "philosophy haters group");
        $ocis->createGroup("physics-lovers", "physics lover group");
        $groups = $ocis->getGroups();
        $this->assertCount(2, $groups);
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
    public function testAddUserToGroup(): void
    {
        $token = $this->getAccessToken('admin', 'admin');
        $ocis = new Ocis($this->ocisUrl, $token, ['verify' => false]);
        $users = $ocis->getUsers('admin');
        $userName = $users[0]->getDisplayName();
        $groups = $ocis->getGroups(search:"philosophy");
        $this->assertGreaterThanOrEqual(1, $groups);
        $groupName = $groups[0]->getDisplayName();
        $groups[0]->addUser($users[0]);

        $groups = $ocis->getGroups(expandMembers: true, search:"philosophy");
        foreach ($groups as $group) {
            if($group->getDisplayName() === $groupName) {
                $this->assertGreaterThan(0, count($group->getMembers()));
                $this->assertEquals($userName, $group->getMembers()[0]->getDisplayName());
            }
        }
    }

    /**
     * @return void
     */
    public function testAddUserToGroupInvalid(): void
    {
        $this->expectException(NotFoundException::class);
        $token = $this->getAccessToken('admin', 'admin');
        $ocis = new Ocis($this->ocisUrl, $token, ['verify' => false]);
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
        $token = $this->getAccessToken('marie', 'radioactivity');
        $ocis = new Ocis($this->ocisUrl, $token, ['verify' => false]);

        $users = $ocis->getUsers('marie');
        $groups = $ocis->getGroups(search:"physics");
        $this->assertGreaterThanOrEqual(1, $groups);
        $groups[0]->addUser($users[0]);
    }

    /**
     * @return void
     */
    public function testGetGroupsExpanded(): void
    {
        $token = $this->getAccessToken('admin', 'admin');
        $ocis = new Ocis($this->ocisUrl, $token, ['verify' => false]);
        $ocis->createGroup("physics-lovers", "physics lover group");
        $groups = $ocis->getGroups(expandMembers: true);
        $this->assertCount(1, $groups);
        if (count($groups[0]->getMembers()) <= 0) {
            $this->markTestSkipped("no users added");
        }
        foreach ($groups as $group) { // first implement add user to group
            if($group->getDisplayName() === "philosophy-haters") {
                $this->assertGreaterThan(0, count($group->getMembers()));
            }
        }
    }
    /**
     * @return array<int, array<int, array<int, string>|int|string>>
     */
    public function searchText(): array
    {
        return [
            ["ph",["philosophy-haters", "physics-lovers"]],
        ];
    }
    /**
     * @dataProvider searchText
     *
     * @param string $searchText
     * @param array<int,string> $groupDisplayName
     * @return void
     */
    public function testGetGroupSearch(string $searchText, array $groupDisplayName): void
    {
        $token = $this->getAccessToken('admin', 'admin');
        $ocis = new Ocis($this->ocisUrl, $token, ['verify' => false]);
        $ocis->createGroup("philosophy-haters", "philosophy haters group");
        $ocis->createGroup("physics-lovers", "physics lover group");
        $groups = $ocis->getGroups(search: $searchText);
        $this->assertCount(count($groupDisplayName), $groups);
        for ($i = 0; $i < count($groups); $i++) {
            $this->assertEquals($groups[$i]->getDisplayName(), $groupDisplayName[$i]);
        }
    }
    /**
     * @return array<int, array<int, array<int,  string>|int|OrderDirection|string>>
     */
    public function orderDirection(): array
    {
        return [
            [OrderDirection::ASC, "ph", ["philosophy-haters", "physics-lovers"]],
            [OrderDirection::DESC, "ph", ["physics-lovers", "philosophy-haters"]]
        ];
    }
    /**
     * @dataProvider orderDirection
     *
     * @param OrderDirection $orderDirection
     * @param string $searchText
     * @param array<int,string> $resultGroups
     * @return void
     */
    public function testGetGroupSort(OrderDirection $orderDirection, string $searchText, array $resultGroups): void
    {
        $token = $this->getAccessToken("admin", "admin");
        $ocis = new Ocis($this->ocisUrl, $token, ["verify" => false]);
        $ocis->createGroup("philosophy-haters", "philosophy haters group");
        $ocis->createGroup("physics-lovers", "physics lover group");
        $groups = $ocis->getGroups(search: $searchText, orderBy: $orderDirection);
        if(count($groups) <= 0) {
            $this->markTestSkipped("no groups created");
        }
        $this->assertCount(count($resultGroups), $groups);
        for ($i = 0; $i < count($groups); $i++) {
            $this->assertEquals($resultGroups[$i], $groups[$i]->getDisplayName());
        }
    }

    /**
     *
     * @return void
     */
    public function testDeleteGroup()
    {
        $token = $this->getAccessToken("admin", "admin");
        $ocis = new Ocis($this->ocisUrl, $token, ["verify" => false]);
        $ocis->createGroup("philosophy-haters", "philosophy haters group");
        $ocis->createGroup("physics-lovers", "physics lover group");
        foreach($ocis->getGroups() as $group) {
            if($group->getDisplayName() === "philosophy-haters") {
                $ocis->deleteGroup($group);
            }
        }
        $this->assertCount(1, $ocis->getGroups());
    }

}
