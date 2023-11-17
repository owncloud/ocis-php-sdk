<?php

namespace integration\Owncloud\OcisPhpSdk;

require_once __DIR__ . '/OcisPhpSdkTestCase.php';

use Owncloud\OcisPhpSdk\Exception\ForbiddenException;
use Owncloud\OcisPhpSdk\Group;
use Owncloud\OcisPhpSdk\Ocis;
use Owncloud\OcisPhpSdk\OrderDirection;

class OcisTest extends OcisPhpSdkTestCase
{
    private const GROUP_COUNT = 0;

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
        $drive = $ocis->createDrive('first test drive',1);
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

    public function testGetGroups(): void
    {
        $token = $this->getAccessToken('admin', 'admin');
        $ocis = new Ocis($this->ocisUrl, $token, ['verify' => false]);
        $groups = $ocis->getGroups();
        $this->assertCount(self::GROUP_COUNT, $groups);
        $this->assertContainsOnlyInstancesOf(Group::class, $groups);
        foreach ($groups as $group) {
            $this->assertIsString($group->getId());
            $this->assertIsString($group->getDisplayName());
            $this->assertIsArray($group->getGroupTypes());
            $this->assertIsArray($group->getMembers());
        }
    }
    /**
     * @return void
     */
    public function testGetGroupsExpanded()
    {
        $token = $this->getAccessToken('admin', 'admin');
        $ocis = new Ocis($this->ocisUrl, $token, ['verify' => false]);
        $groups = $ocis->getGroups(expandMembers: true);
        $this->assertCount(self::GROUP_COUNT, $groups);
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
            ["lovers", 0, ["violin-lovers", "physics-lovers"]],
        ];
    }
    /**
     * @dataProvider searchText
     *
     * @param string $searchText
     * @param integer $count
     * @param array<int,string> $groupDisplayName
     * @return void
     */
    public function testGetGroupSerch(string $searchText, int $count, array $groupDisplayName)
    {
        $token = $this->getAccessToken('admin', 'admin');
        $ocis = new Ocis($this->ocisUrl, $token, ['verify' => false]);
        $groups = $ocis->getGroups(search: $searchText);
        $this->assertCount($count, $groups);
        foreach ($groups as $group) {
            $this->assertSame($groupDisplayName, $group->getDisplayName());
        }
    }
    /**
     * @return array<int, array<int, array<int,  string>|int|OrderDirection|string>>
     */
    public function orderDirection(): array
    {
        return [
            [OrderDirection::ASC, "ph", ["philosophy-haters", "physics-lovers"]],
            [OrderDirection::DESC, "ph", ["physics-lovers", "philosophy-haters"]],
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
    public function testGetGroupSort(OrderDirection $orderDirection, string $searchText, array $resultGroups)
    {
        $token = $this->getAccessToken("admin", "admin");
        $ocis = new Ocis($this->ocisUrl, $token, ["verify" => false]);
        $groups = $ocis->getGroups(search: "user", orderBy: $orderDirection);
        if(count($groups) <= 0) {
            $this->markTestSkipped("no groups created");
        }
        $this->assertCount(count($resultGroups), $groups);
        for ($i = 0; $i < count($groups); $i++) {
            $this->assertSame($resultGroups[$i], $groups[$i]->getDisplayName());
        }
    }
}
