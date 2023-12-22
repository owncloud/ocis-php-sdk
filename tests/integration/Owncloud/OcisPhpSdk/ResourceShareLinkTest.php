<?php

namespace integration\Owncloud\OcisPhpSdk;

require_once __DIR__ . '/OcisPhpSdkTestCase.php';

use OpenAPI\Client\Model\SharingLinkType;
use Owncloud\OcisPhpSdk\DriveOrder;
use Owncloud\OcisPhpSdk\DriveType;
use Owncloud\OcisPhpSdk\Exception\BadRequestException;
use Owncloud\OcisPhpSdk\Ocis;
use Owncloud\OcisPhpSdk\OcisResource;
use Owncloud\OcisPhpSdk\OrderDirection;

class ResourceShareLinkTest extends OcisPhpSdkTestCase
{
    private OcisResource $fileToShare;
    private OcisResource $folderToShare;
    private Ocis $ocis;
    public function setUp(): void
    {
        parent::setUp();
        $token = $this->getAccessToken('admin', 'admin');
        $this->ocis = new Ocis($this->ocisUrl, $token, ['verify' => false]);
        $personalDrive = $this->ocis->getMyDrives(
            DriveOrder::NAME,
            OrderDirection::ASC,
            DriveType::PERSONAL
        )[0];


        $personalDrive->uploadFile('to-share-test.txt', 'some content');
        $this->createdResources[$personalDrive->getId()][] = 'to-share-test.txt';
        $personalDrive->createFolder('folder-to-share');
        $this->createdResources[$personalDrive->getId()][] = 'folder-to-share';
        $resources = $personalDrive->getResources();
        /**
         * @var OcisResource $resource
         */
        foreach ($resources as $resource) {
            if ($resource->getName() === 'to-share-test.txt') {
                $this->fileToShare = $resource;
            }
            if ($resource->getName() === 'folder-to-share') {
                $this->folderToShare = $resource;
            }
        }
    }

    /**
     * @return array<int,array<int,SharingLinkType|bool|string>>
     */
    public function sharingLinkTypeDataProvider(): array
    {
        return [
            [SharingLinkType::INTERNAL, true, true, ''],
            [SharingLinkType::VIEW, true, true, ''],
            [SharingLinkType::UPLOAD, false, true, ''],
            [SharingLinkType::EDIT, false, true, ''],
            [SharingLinkType::CREATE_ONLY, false, true, ''],
            [SharingLinkType::BLOCKS_DOWNLOAD, true, false, 'https://github.com/owncloud/ocis/issues/7879'],
        ];
    }

    /**
     * @dataProvider sharingLinkTypeDataProvider
     */
    public function testCreateLink(
        SharingLinkType $type,
        bool $validForFile,
        bool $validForFolder,
        string $issue
    ): void {
        if ($issue !== '') {
            $this->markTestSkipped($issue);
        }
        $expectedCountShares = 0;
        if ($validForFile) {
            $link = $this->fileToShare->createSharingLink($type, null, self::VALID_LINK_PASSWORD);
            $this->assertSame($type, $link->getType());
            $expectedCountShares++;
        }
        if ($validForFolder) {
            $link = $this->folderToShare->createSharingLink($type, null, self::VALID_LINK_PASSWORD);
            $this->assertSame($type, $link->getType());
            $expectedCountShares++;
        }

        $createdShares = $this->ocis->getSharedByMe();
        $this->assertCount($expectedCountShares, $createdShares);
    }

    public function testCreateLinkWithExpiry(): void
    {
        $tomorrow = new \DateTimeImmutable('tomorrow');
        $link = $this->fileToShare->createSharingLink(SharingLinkType::VIEW, $tomorrow, self::VALID_LINK_PASSWORD);
        $this->assertInstanceOf(\DateTimeImmutable::class, $link->getExpiration());
        $this->assertSame($tomorrow->modify("+1 day -1 second")->getTimestamp(), $link->getExpiration()->getTimestamp());
        $createdShares = $this->ocis->getSharedByMe();
        $this->assertInstanceOf(\DateTimeImmutable::class, $createdShares[0]->getExpiration());
        $this->assertSame($tomorrow->modify("+1 day -1 second")->getTimestamp(), $createdShares[0]->getExpiration()->getTimestamp());
    }

    public function testCreateLinkPastExpiry(): void
    {
        $this->expectException(BadRequestException::class);
        $yesterday = new \DateTimeImmutable('yesterday');
        $this->fileToShare->createSharingLink(SharingLinkType::VIEW, $yesterday, self::VALID_LINK_PASSWORD);
    }

    public function testCreateLinkWithExpiryTimezone(): void
    {
        $expiry = new \DateTimeImmutable('2060-01-01 12:00:00', new \DateTimeZone('Europe/Kyiv'));
        $link = $this->fileToShare->createSharingLink(SharingLinkType::VIEW, $expiry, self::VALID_LINK_PASSWORD);
        $this->assertInstanceOf(\DateTimeImmutable::class, $link->getExpiration());
        $this->assertSame("Thu, 01 Jan 2060 21:59:59 +0000", $link->getExpiration()->format('r'));
        $this->assertSame("Z", $link->getExpiration()->getTimezone()->getName());

        $createdShares = $this->ocis->getSharedByMe();
        $this->assertCount(1, $createdShares);
        $this->assertInstanceOf(\DateTimeImmutable::class, $createdShares[0]->getExpiration());
        // The returned expiry is in UTC timezone (2 hours earlier than the expiry time in Kyiv)
        $this->assertSame("Thu, 01 Jan 2060 21:59:59 +0000", $createdShares[0]->getExpiration()->format('r'));
        $this->assertSame("Z", $createdShares[0]->getExpiration()->getTimezone()->getName());
    }

    public function testSetExperation(): void
    {
        $link = $this->fileToShare->createSharingLink(SharingLinkType::VIEW, null, "p@$\$w0rD");
        $tomorrow = new \DateTimeImmutable('tomorrow');
        $expectedExpirationDate = $tomorrow->modify("+1 day -1 second");
        $link->setExpiration($tomorrow);
        $linkFromSharedByMe = $this->ocis->getSharedByMe()[0];
        $this->assertEquals($expectedExpirationDate, $link->getExpiration());
        $this->assertEquals($expectedExpirationDate, $linkFromSharedByMe->getExpiration());
    }
}
