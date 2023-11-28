<?php

namespace integration\Owncloud\OcisPhpSdk;

require_once __DIR__ . '/OcisPhpSdkTestCase.php';

use Owncloud\OcisPhpSdk\Drive;
use Owncloud\OcisPhpSdk\DriveOrder;
use Owncloud\OcisPhpSdk\DriveType;
use Owncloud\OcisPhpSdk\Exception\ForbiddenException;
use Owncloud\OcisPhpSdk\Group;
use Owncloud\OcisPhpSdk\Ocis;
use Owncloud\OcisPhpSdk\OrderDirection;
use OpenAPI\Client\Model\User;
use Owncloud\OcisPhpSdk\Exception\NotFoundException;
use Owncloud\OcisPhpSdk\Exception\UnauthorizedException;

class OcisTest extends OcisPhpSdkTestCase
{
    private function getOcis(string $username, string $password): Ocis
    {
        $token = $this->getAccessToken($username, $password);
        return new Ocis($this->ocisUrl, $token, ['verify' => false]);
    }

    public function testServiceUrlTrailingSlash(): void
    {
        $ocis = $this->getOcis('admin', 'admin');
        $drives = $ocis->getMyDrives();
        $this->assertTrue((is_array($drives) && count($drives) > 1));
    }

    public function testCreateDrive(): void
    {
        $ocis = $this->getOcis('admin', 'admin');
        $countDrivesAtStart = count(
            $ocis->getMyDrives()
        );
        $drive = $ocis->createDrive('first test drive');
        $this->createdDrives[] = $drive->getId();
        $this->assertMatchesRegularExpression(
            "/^" . $this->getUUIDv4Regex() . '\$' . $this->getUUIDv4Regex() . "$/i",
            $drive->getId()
        );
        // there should be one more drive
        $this->assertCount($countDrivesAtStart + 1, $ocis->getMyDrives());
    }

    public function testCreateDriveNoPermissions(): void
    {
        $ocis = $this->getOcis('einstein', 'relativity');
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
        $ocis = $this->getOcis('admin', 'admin');
        $this->expectException(\InvalidArgumentException::class);
        $countDrivesAtStart = count($ocis->getMyDrives());
        $ocis->createDrive('drive with quota', $quota);
        // no new drive should have been created
        $this->assertCount($countDrivesAtStart, $ocis->getMyDrives());
    }

    /**
     * @return array<int, array<int,array<int, string>>>
     */
    public function groupNameList(): array
    {
        return[
            [["philosophy-haters", "physics-lovers"]],
        ];
    }
    /**
     * @dataProvider groupNameList
     *
     * @param array<string> $groupName
     * @return void
     */
    public function testGetGroups(array $groupName): void
    {
        $ocis = $this->getOcis('admin', 'admin');
        $philosophyHatersGroup =  $ocis->createGroup($groupName[0], "philosophy haters group");
        $physicsLoversGroup =  $ocis->createGroup($groupName[1], "physics lover group");
        $this->createdGroups = [$philosophyHatersGroup,$physicsLoversGroup];
        $groups = $ocis->getGroups();
        $this->assertCount(2, $groups);
        foreach ($groups as $group) {
            $this->assertInstanceOf(Group::class, $group);
            $this->assertIsString($group->getId());
            $this->assertIsString($group->getDisplayName());
            $this->assertIsArray($group->getGroupTypes());
            $this->assertIsArray($group->getMembers());
        }
        $groupDisplayName = [$philosophyHatersGroup->getDisplayName(),$physicsLoversGroup->getDisplayName()];
        $this->assertTrue($groupDisplayName === [$groupName[0],$groupName[1]]);
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
        $philosophyHatersGroup =  $ocis->createGroup("philosophy-haters", "philosophy haters group");
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
    public function testAddUserToGroupInvalid(): void
    {
        $this->expectException(NotFoundException::class);
        $ocis = $this->getOcis('admin', 'admin');
        $philosophyHatersGroup =  $ocis->createGroup("philosophy-haters", "philosophy haters group");
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
        $physicsLoversGroup =  $ocis->createGroup("physics-lovers", "physics lovers group");
        $this->createdGroups = [$physicsLoversGroup];
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
        $ocis = $this->getOcis('admin', 'admin');
        $physicsLoversGroup =  $ocis->createGroup("physics-lovers", "physics lovers group");
        $this->createdGroups = [$physicsLoversGroup];
        $physicsLoversGroup->addUser($ocis->getUsers()[0]);
        $groups = $ocis->getGroups(expandMembers: true);
        $this->assertCount(1, $groups);
        foreach ($groups as $group) {
            $this->assertGreaterThan(0, count($group->getMembers()));
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
        $ocis = $this->getOcis('admin', 'admin');
        $philosophyHatersGroup =  $ocis->createGroup("philosophy-haters", "philosophy haters group");
        $physicsLoversGroup = $ocis->createGroup("physics-lovers", "physics lover group");
        $this->createdGroups = [$philosophyHatersGroup,$physicsLoversGroup];
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
        $ocis = $this->getOcis('admin', 'admin');
        $philosophyHatersGroup =  $ocis->createGroup("philosophy-haters", "philosophy haters group");
        $physicsLoversGroup = $ocis->createGroup("physics-lovers", "physics lover group");
        $this->createdGroups = [$philosophyHatersGroup,$physicsLoversGroup];
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
     * @return void
     */
    public function testDeleteGroup(): void
    {
        $token = $this->getAccessToken("admin", "admin");
        $ocis = new Ocis($this->ocisUrl, $token, ["verify" => false]);
        $philosophyHatersGroup =  $ocis->createGroup("philosophy-haters", "philosophy haters group");
        $physicsLoversGroup = $ocis->createGroup("physics-lovers", "physics lover group");
        $philosophyHatersGroup->delete();
        $this->assertCount(1, $ocis->getGroups());
        $this->assertEquals("physics-lovers", $ocis->getGroups()[0]->getDisplayName());
        $this->createdGroups = [$physicsLoversGroup];
    }

    /**
     * @return void
     */
    public function testDeleteGroupById(): void
    {
        $token = $this->getAccessToken("admin", "admin");
        $ocis = new Ocis($this->ocisUrl, $token, ["verify" => false]);
        $ocis->createGroup("philosophy-haters", "philosophy haters group");
        $physicsLoversGroup = $ocis->createGroup("physics-lovers", "physics lover group");
        foreach($ocis->getGroups() as $group) {
            if($group->getDisplayName() === "philosophy-haters") {
                $ocis->deleteGroupByID($group->getId());
            }
        }
        $this->assertCount(1, $ocis->getGroups());
        $this->assertEquals("physics-lovers", $ocis->getGroups()[0]->getDisplayName());
        $this->createdGroups = [$physicsLoversGroup];
    }

    /**
     * @return void
     */
    public function testDeleteGroupByIdNoPermission(): void
    {
        $token = $this->getAccessToken("admin", "admin");
        $ocis = new Ocis($this->ocisUrl, $token, ["verify" => false]);
        $philosophyHatersGroup = $ocis->createGroup("philosophy-haters", "philosophy haters group");
        //any user other than admin can't get Group ID because of bug, thus bypassing this step
        //Todo : make Einstein get Group ID after this bug is solved.
        $groupId = $philosophyHatersGroup->getId();
        $this->createdGroups = [$philosophyHatersGroup];
        $token = $this->getAccessToken('einstein', 'relativity');
        $ocis = new Ocis($this->ocisUrl, $token, ["verify" => false]);
        $this->expectException(UnauthorizedException::class);
        $ocis->deleteGroupByID($groupId);
    }

    private function getPersonalDrive(Ocis $ocis): Drive
    {
        return $ocis->getMyDrives(
            DriveOrder::NAME,
            OrderDirection::ASC,
            DriveType::PERSONAL
        )[0];
    }

    public function testGetResourceById(): void
    {
        $ocis = $this->getOcis('admin', 'admin');
        $personalDrive = $this->getPersonalDrive($ocis);
        $personalDrive->uploadFile('somefile.txt', 'some content');
        $this->createdResources[] = '/somefile.txt';
        $expectedResource = $personalDrive->getResources()[0];
        $resource = $ocis->getResourceById($expectedResource->getId());
        $this->assertSame($expectedResource->getId(), $resource->getId());
        $this->assertSame('somefile.txt', $resource->getName());
        $this->assertSame('file', $resource->getType());
        $this->assertSame(12, $resource->getSize());
        $this->assertSame('some content', $resource->getContent());
        $this->assertSame($personalDrive->getId(), $resource->getDriveId());
    }

    public function testGetResourceByIdEmptyFolder(): void
    {
        $ocis = $this->getOcis('admin', 'admin');
        $personalDrive = $this->getPersonalDrive($ocis);
        $personalDrive->createFolder('myfolder');
        $this->createdResources[] = '/myfolder';
        $expectedResource = $personalDrive->getResources()[0];
        $resource = $ocis->getResourceById($expectedResource->getId());
        $this->assertSame($expectedResource->getId(), $resource->getId());
        $this->assertSame('myfolder', $resource->getName());
        $this->assertSame('folder', $resource->getType());
        $this->assertSame(0, $resource->getSize());
        $this->assertSame('', $resource->getContent()); // getting a folder does not return any content
        $this->assertSame($personalDrive->getId(), $resource->getDriveId());
    }

    public function testGetResourceByIdFolderWithContent(): void
    {
        $ocis = $this->getOcis('admin', 'admin');
        $personalDrive = $this->getPersonalDrive($ocis);
        $personalDrive->createFolder('myfolder');
        $this->createdResources[] = '/myfolder';
        $personalDrive->uploadFile('myfolder/somefile.txt', 'some content');
        $expectedResource = $personalDrive->getResources()[0];
        $resource = $ocis->getResourceById($expectedResource->getId());
        $this->assertSame($expectedResource->getId(), $resource->getId());
        $this->assertSame('myfolder', $resource->getName());
        $this->assertSame('folder', $resource->getType());
        $this->assertSame(12, $resource->getSize());
        $this->assertSame('', $resource->getContent()); // getting a folder does not return any content
        $this->assertSame($personalDrive->getId(), $resource->getDriveId());
    }

    public function testGetResourceByIdFileInAFolder(): void
    {
        $ocis = $this->getOcis('admin', 'admin');
        $personalDrive = $this->getPersonalDrive($ocis);
        $personalDrive->createFolder('myfolder');
        $this->createdResources[] = '/myfolder';
        $personalDrive->uploadFile('myfolder/somefile.txt', 'some content');
        $expectedResource = $personalDrive->getResources('/myfolder')[0];
        $resource = $ocis->getResourceById($expectedResource->getId());
        $this->assertSame($expectedResource->getId(), $resource->getId());
        $this->assertSame('somefile.txt', $resource->getName());
        $this->assertSame('file', $resource->getType());
        $this->assertSame(12, $resource->getSize());
        $this->assertSame('some content', $resource->getContent());
        $this->assertSame($personalDrive->getId(), $resource->getDriveId());
    }

    public function testGetResourceInvalidId(): void
    {
        $this->expectException(NotFoundException::class);
        $ocis = $this->getOcis('admin', 'admin');
        $ocis->getResourceById('not-existing-id');
    }
}
