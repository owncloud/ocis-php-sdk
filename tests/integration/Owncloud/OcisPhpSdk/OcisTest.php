<?php

namespace integration\Owncloud\OcisPhpSdk;

require_once __DIR__ . '/OcisPhpSdkTestCase.php';

use OpenAPI\Client\Model\SharingLinkType;
use Owncloud\OcisPhpSdk\Drive;
use Owncloud\OcisPhpSdk\DriveOrder;
use Owncloud\OcisPhpSdk\DriveType;
use Owncloud\OcisPhpSdk\Exception\ForbiddenException;
use Owncloud\OcisPhpSdk\Group;
use Owncloud\OcisPhpSdk\Ocis;
use Owncloud\OcisPhpSdk\OrderDirection;
use Owncloud\OcisPhpSdk\Exception\NotFoundException;
use Owncloud\OcisPhpSdk\Exception\UnauthorizedException;
use Owncloud\OcisPhpSdk\ShareCreated;
use Owncloud\OcisPhpSdk\ShareLink;
use Owncloud\OcisPhpSdk\ShareReceived;

class OcisTest extends OcisPhpSdkTestCase
{
    public function testServiceUrlTrailingSlash(): void
    {
        $ocis = $this->getOcis('admin', 'admin');
        $drives = $ocis->getMyDrives();
        $this->assertTrue((is_array($drives) && count($drives) > 1));
    }

    public function testGetMyDrives(): void
    {
        $adminOcis = $this->getOcis('admin', 'admin');
        $marieOcis = $this->getOcis('marie', 'radioactivity');

        $adminDrive = $adminOcis->createDrive('Admin Project Drive');
        $this->createdDrives[] = $adminDrive->getId();

        $adminPersonalDrive = $adminOcis->getMyDrives(
            DriveOrder::NAME,
            OrderDirection::ASC,
            DriveType::PERSONAL
        )[0];

        $adminPersonalDrive->createFolder('sharedAdminFolder');
        $this->createdResources[$adminPersonalDrive->getId()][] = '/sharedAdminFolder';
        $resources = $adminPersonalDrive->getResources();

        $sharedResource = null;

        foreach ($resources as $resource) {
            if ($resource->getName() === 'sharedAdminFolder') {
                $sharedResource = $resource;
            }
        }
        if(empty($sharedResource)) {
            throw new \Error(
                "resource not found "
            );
        }
        $marie = $adminOcis->getUsers('marie')[0];

        $managerRole = null;
        foreach($sharedResource->getRoles() as $role) {
            if($role->getDisplayName() === 'Manager') {
                $managerRole = $role;
            }
        }

        if(empty($managerRole)) {
            throw new \Error(
                "manager role not found "
            );
        }
        $sharedResource->invite([$marie], $managerRole);

        $marieDrive = $marieOcis->getMyDrives();
        $this->assertContainsOnlyInstancesOf(Drive::class, $marieDrive);
        foreach ($marieDrive as $drive) {
            $this->assertNotSame('Admin Project Drive', $drive->getName());
            if ($drive->getType() === DriveType::MOUNTPOINT) {
                $this->assertEquals('sharedAdminFolder', $drive->getName());
            }
        }
    }

    public function testGetAllDrives(): void
    {
        $adminOcis = $this->getOcis('admin', 'admin');
        $katherineOcis = $this->getOcis('katherine', 'gemini');
        $katherineDrive = $katherineOcis->createDrive('katherine Project Drive');
        $this->createdDrives[] = $katherineDrive->getId();

        $sportDrive = $adminOcis->createDrive('Sport Project Drive');
        $this->createdDrives[] = $sportDrive->getId();
        $managementDrive = $adminOcis->createDrive('Management Project Drive');
        $this->createdDrives[] = $managementDrive->getId();

        $adminPersonalDrive = $adminOcis->getMyDrives(
            DriveOrder::NAME,
            OrderDirection::ASC,
            DriveType::PERSONAL
        )[0];

        $adminPersonalDrive->createFolder('sharedAdminFolder');
        $this->createdResources[$adminPersonalDrive->getId()][] = '/sharedAdminFolder';
        $resources = $adminPersonalDrive->getResources();

        foreach ($resources as $resource) {
            if ($resource->getName() === 'sharedAdminFolder') {
                $sharedResource = $resource;
            }
        }

        if(empty($sharedResource)) {
            throw new \Error(
                "resource not found "
            );
        }

        $katherine = $adminOcis->getUsers('katherine')[0];

        $managerRole = null;
        foreach($sharedResource->getRoles() as $role) {
            if ($role->getDisplayName() === 'Manager') {
                $managerRole = $role;
            }
        }
        if(empty($managerRole)) {
            throw new \Error(
                "manager role not found "
            );
        }
        $sharedResource->invite([$katherine], $managerRole);

        $drives = $adminOcis->getAllDrives();
        foreach ($drives as $drive) {
            $this->assertInstanceOf(Drive::class, $drive);
            $this->assertThat(
                $drive->getType(),
                $this->logicalOr(
                    $this->equalTo(DriveType::PROJECT),
                    $this->equalTo(DriveType::PERSONAL),
                    $this->equalTo(DriveType::VIRTUAL),
                )
            );
            if($drive->getType() === DriveType::PROJECT) {
                $this->assertThat(
                    $drive->getName(),
                    $this->logicalOr(
                        $this->equalTo('katherine Project Drive'),
                        $this->equalTo('Sport Project Drive'),
                        $this->equalTo('Management Project Drive'),
                    )
                );
            }
            if($drive->getType() === DriveType::MOUNTPOINT) {
                $this->assertEquals('sharedAdminFolder', $drive->getName());
            }
        }
    }

    /**
     * @return array<int, array<int, int|DriveType>>
     */
    public function drivesType()
    {
        return [
            [DriveType::PROJECT],
            [DriveType::PERSONAL],
            [DriveType::VIRTUAL],
            [DriveType::MOUNTPOINT],
        ];
    }

    /**
     * @dataProvider drivesType
     */
    public function testGetAllDrivesType(DriveType $driveType): void
    {
        $adminOcis = $this->getOcis('admin', 'admin');

        $managementDrive = null;
        $sportDrive = null;
        if($driveType === DriveType::PROJECT) {
            $sportDrive = $adminOcis->createDrive('Sport Project Drive');
            $this->createdDrives[] = $sportDrive->getId();
            $managementDrive = $adminOcis->createDrive('Management Project Drive');
            $this->createdDrives[] = $managementDrive->getId();
        }

        if($driveType === DriveType::MOUNTPOINT) {
            $adminPersonalDrive = $adminOcis -> getMyDrives(
                DriveOrder::NAME,
                OrderDirection::ASC,
                DriveType::PERSONAL
            )[0];

            $adminPersonalDrive->createFolder('sharedAdminFolder');
            $this->createdResources[$adminPersonalDrive->getId()][] = '/sharedAdminFolder';
            $resources = $adminPersonalDrive->getResources();

            foreach ($resources as $resource) {
                if ($resource->getName() === 'sharedAdminFolder') {
                    $sharedResource = $resource;
                }
            }
            if(empty($sharedResource)) {
                throw new \Error(
                    "resource not found "
                );
            }

            $katherine = $adminOcis->getUsers('katherine')[0];

            $managerRole = null;
            foreach ($sharedResource->getRoles() as $role) {
                if ($role->getDisplayName() === 'Manager') {
                    $managerRole = $role;
                }
            }

            if(empty($managerRole)) {
                throw new \Error(
                    "manager role not found "
                );
            }

            $sharedResource->invite([$katherine], $managerRole);
        }

        $drives = $adminOcis->getAllDrives(
            DriveOrder::NAME,
            OrderDirection::ASC,
            $driveType
        );
        $this->assertContainsOnlyInstancesOf(Drive::class, $drives);
        foreach ($drives as $drive) {
            $this->assertEquals($drive->getType(), $driveType);
            if ($drive->getType() === DriveType::PROJECT) {
                $this->assertThat(
                    $drive->getName(),
                    $this->logicalOr(
                        // @phpstan-ignore-next-line because the test is skipped
                        $this->equalTo($managementDrive->getName()),
                        // @phpstan-ignore-next-line because the test is skipped
                        $this->equalTo($sportDrive->getName())
                    )
                );
            }
            if ($drive->getType() === DriveType::MOUNTPOINT) {
                $this->assertEquals('sharedAdminFolder', $drive->getName());
            }
            if ($drive->getType() === DriveType::VIRTUAL) {
                $this->assertEquals('Shares', $drive->getName());
            }
        }
    }

    public function testGetDriveById(): void
    {
        $ocis = $this->getOcis('admin', 'admin');
        $sportDrive = $ocis->createDrive('Sport Project Drive');
        $this->createdDrives[] = $sportDrive->getId();
        $drive = $ocis->getDriveById($sportDrive->getId());
        $this->assertInstanceOf(Drive::class, $drive);
        $this->assertEquals($drive->getId(), $sportDrive->getId());
        $this->assertEquals($drive->getName(), $sportDrive->getName());
        $this->assertEquals($drive->getType(), $sportDrive->getType());
        $this->assertEquals($drive->getRoot(), $sportDrive->getRoot());
        $this->markTestIncomplete(
            'libre graph issue-149 sends broken quota object while creating drive'
        );
        //  $this->assertEquals($sportDrive, $drive);
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
        $philosophyHatersGroup = $ocis->createGroup($groupName[0], "philosophy haters group");
        $physicsLoversGroup = $ocis->createGroup($groupName[1], "physics lover group");
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
    public function testDeleteGroupById(): void
    {
        $ocis = $this->getOcis('admin', 'admin');
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
        $ocis = $this->getOcis('admin', 'admin');
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
        $this->createdResources[$personalDrive->getId()][] = '/somefile.txt';
        $expectedResource = $personalDrive->getResources()[0];
        $resource = $ocis->getResourceById($expectedResource->getId());
        $this->assertSame($expectedResource->getId(), $resource->getId());
        $this->assertSame('somefile.txt', $resource->getName());
        $this->assertSame('file', $resource->getType());
        $this->assertSame(12, $resource->getSize());
        $content = $this->getContentOfResource425Save($resource);
        $this->assertSame('some content', $content);
        $this->assertSame($personalDrive->getId(), $resource->getSpaceId());
    }

    public function testGetResourceByIdEmptyFolder(): void
    {
        $ocis = $this->getOcis('admin', 'admin');
        $personalDrive = $this->getPersonalDrive($ocis);
        $personalDrive->createFolder('myfolder');
        $this->createdResources[$personalDrive->getId()][] = '/myfolder';
        $expectedResource = $personalDrive->getResources()[0];
        $resource = $ocis->getResourceById($expectedResource->getId());
        $this->assertSame($expectedResource->getId(), $resource->getId());
        $this->assertSame('myfolder', $resource->getName());
        $this->assertSame('folder', $resource->getType());
        $this->assertSame(0, $resource->getSize());
        $this->assertSame('', $resource->getContent()); // getting a folder does not return any content
        $this->assertSame($personalDrive->getId(), $resource->getSpaceId());
    }

    public function testGetResourceByIdFolderWithContent(): void
    {
        $ocis = $this->getOcis('admin', 'admin');
        $personalDrive = $this->getPersonalDrive($ocis);
        $personalDrive->createFolder('myfolder');
        $this->createdResources[$personalDrive->getId()][] = '/myfolder';
        $personalDrive->uploadFile('myfolder/somefile.txt', 'some content');
        $expectedResource = $personalDrive->getResources()[0];
        $resource = $ocis->getResourceById($expectedResource->getId());
        $this->assertSame($expectedResource->getId(), $resource->getId());
        $this->assertSame('myfolder', $resource->getName());
        $this->assertSame('folder', $resource->getType());
        $this->assertSame(12, $resource->getSize());
        $this->assertSame('', $resource->getContent()); // getting a folder does not return any content
        $this->assertSame($personalDrive->getId(), $resource->getSpaceId());
    }

    public function testGetResourceByIdFileInAFolder(): void
    {
        $ocis = $this->getOcis('admin', 'admin');
        $personalDrive = $this->getPersonalDrive($ocis);
        $personalDrive->createFolder('myfolder');
        $this->createdResources[$personalDrive->getId()][] = '/myfolder';
        $personalDrive->uploadFile('myfolder/somefile.txt', 'some content');
        $expectedResource = $personalDrive->getResources('/myfolder')[0];
        $resource = $ocis->getResourceById($expectedResource->getId());
        $this->assertSame($expectedResource->getId(), $resource->getId());
        $this->assertSame('somefile.txt', $resource->getName());
        $this->assertSame('file', $resource->getType());
        $this->assertSame(12, $resource->getSize());
        $content = $this->getContentOfResource425Save($resource);
        $this->assertSame('some content', $content);
        $this->assertSame($personalDrive->getId(), $resource->getSpaceId());
    }

    public function testGetResourceInvalidId(): void
    {
        $this->expectException(NotFoundException::class);
        $ocis = $this->getOcis('admin', 'admin');
        $ocis->getResourceById('not-existing-id');
    }

    public function testGetShareCreatedByMe(): void
    {
        $ocis = $this->getOcis('admin', 'admin');
        $einstein = $ocis->getUsers('einstein')[0];
        $personalDrive = $this->getPersonalDrive($ocis);
        $personalDrive->createFolder('newFolder');
        $this->createdResources[$personalDrive->getId()][] = '/newFolder';
        $resources = $personalDrive->getResources();
        $sharedResource = null;
        foreach ($resources as $resource) {
            if ($resource->getName() === 'newFolder') {
                $sharedResource = $resource;
            }
        }
        if(empty($sharedResource)) {
            throw new \Error(
                "resource not found "
            );
        }
        $editorRole = null;
        foreach($sharedResource->getRoles() as $role) {
            if($role->getDisplayName() === 'Editor') {
                $editorRole = $role;
            }
        }
        if(empty($editorRole)) {
            throw new \Error(
                "Editor role not found "
            );
        }
        $sharedResource->invite([$einstein], $editorRole);
        $myShare = $ocis->getSharedByMe();
        $this->assertInstanceOf(ShareCreated::class, $myShare[0]);
        $this->assertEquals($myShare[0]->getReceiver()->getDisplayName(), 'Albert Einstein');
    }

    public function testGetShareCreatedAndShareCreatedLinkByMe(): void
    {
        $ocis = $this->getOcis('admin', 'admin');
        $einstein = $ocis->getUsers('einstein')[0];
        $personalDrive = $this->getPersonalDrive($ocis);
        $personalDrive->createFolder('newFolder');
        $this->createdResources[$personalDrive->getId()][] = '/newFolder';
        $resources = $personalDrive->getResources();
        $sharedResource = null;
        foreach ($resources as $resource) {
            if ($resource->getName() === 'newFolder') {
                $sharedResource = $resource;
            }
        }
        if(empty($sharedResource)) {
            throw new \Error(
                "resource not found "
            );
        }
        $editorRole = null;
        foreach($sharedResource->getRoles() as $role) {
            if($role->getDisplayName() === 'Editor') {
                $editorRole = $role;
            }
        }
        if(empty($editorRole)) {
            throw new \Error(
                "Editor role not found "
            );
        }
        $sharedResource->invite([$einstein], $editorRole);
        $sharedResource->createSharingLink(SharingLinkType::VIEW, new \DateTimeImmutable('2023-12-31 01:02:03.456789'), 'Test@123', '');
        $myShare = $ocis->getSharedByMe();
        $this->assertInstanceOf(ShareCreated::class, $myShare[0]);
        $this->assertInstanceOf(ShareLink::class, $myShare[1]);
    }

    public function testGetSharedWithMe(): void
    {
        $ocis = $this->getOcis('admin', 'admin');
        $einsteinOcis = $this->initUser('einstein', 'relativity');
        $einstein = $ocis->getUsers('einstein')[0];
        $personalDrive = $this->getPersonalDrive($ocis);
        $personalDrive->createFolder('newFolder');
        $this->createdResources[$personalDrive->getId()][] = '/newFolder';
        $resources = $personalDrive->getResources();
        $sharedResource = null;
        foreach ($resources as $resource) {
            if ($resource->getName() === 'newFolder') {
                $sharedResource = $resource;
            }
        }
        if(empty($sharedResource)) {
            throw new \Error(
                "resource not found "
            );
        }
        $editorRole = null;
        foreach($sharedResource->getRoles() as $role) {
            if($role->getDisplayName() === 'Editor') {
                $editorRole = $role;
            }
        }
        if(empty($editorRole)) {
            throw new \Error(
                "Editor role not found "
            );
        }
        $sharedResource->invite([$einstein], $editorRole);
        $myShare = $einsteinOcis->getSharedWithMe();
        $this->assertInstanceOf(ShareReceived::class, $myShare[0]);
        $this->assertEquals($myShare[0]->getName(), 'newFolder');
        $this->assertEquals($myShare[0]->getOwnerName(), "Admin");
    }
}
