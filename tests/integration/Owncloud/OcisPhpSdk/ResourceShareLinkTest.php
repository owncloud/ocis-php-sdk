<?php

namespace integration\Owncloud\OcisPhpSdk;

require_once __DIR__ . '/OcisPhpSdkTestCase.php';

use OpenAPI\Client\Model\SharingLinkType;
use Owncloud\OcisPhpSdk\Exception\BadRequestException;
use Owncloud\OcisPhpSdk\Ocis;
use Owncloud\OcisPhpSdk\OcisResource;

class ResourceShareLinkTest extends OcisPhpSdkTestCase
{
    private OcisResource $fileToShare;
    private OcisResource $folderToShare;
    private Ocis $ocis;
    public function setUp(): void
    {
        parent::setUp();
        $this->ocis = $this->getOcis('admin', 'admin');
        $personalDrive = $this->getPersonalDrive($this->ocis);


        $personalDrive->uploadFile('to-share-test.txt', 'some content');
        $this->createdResources[$personalDrive->getId()][] = 'to-share-test.txt';
        $personalDrive->createFolder('folder-to-share');
        $this->createdResources[$personalDrive->getId()][] = 'folder-to-share';
        $resources = $personalDrive->getResources();
        $this->assertGreaterThan(0, count($resources), "Expected at least one resource but found " . count($resources));
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
    public static function sharingLinkTypeDataProvider(): array
    {
        return [
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
        string $issue,
    ): void {
        if ($issue !== '') {
            $this->markTestSkipped($issue);
        }
        $expectedCountShares = 0;
        if ($validForFile) {
            $link = $this->fileToShare->createSharingLink($type, null, self::VALID_LINK_PASSWORD);
            $this->assertSame($type, $link->getType(), "Link type mismatch");
            $expectedCountShares++;
        }
        if ($validForFolder) {
            $link = $this->folderToShare->createSharingLink($type, null, self::VALID_LINK_PASSWORD);
            $this->assertSame($type, $link->getType(), "Link type mismatch");
            $expectedCountShares++;
        }

        $createdShares = $this->ocis->getSharedByMe();
        $this->assertCount($expectedCountShares, $createdShares);
    }

    public function testCreateLinkWithExpiry(): void
    {
        $tomorrow = new \DateTimeImmutable('tomorrow');
        $link = $this->fileToShare->createSharingLink(SharingLinkType::VIEW, $tomorrow, self::VALID_LINK_PASSWORD);
        $createdLinkExpirationDateTime = $link->getExpiration();
        $this->assertInstanceOf(
            \DateTimeImmutable::class,
            $createdLinkExpirationDateTime,
            "Expected class to be 'DateTimeImmutable' but found "
            . print_r($createdLinkExpirationDateTime, true),
        );
        $expirationDate = $tomorrow->getTimestamp();
        $this->assertSame(
            $expirationDate,
            $createdLinkExpirationDateTime->getTimestamp(),
            "Link expiration timestamp mismatch",
        );
        $createdShares = $this->ocis->getSharedByMe();
        $createdSharesExpirationDateTime = $createdShares[0]->getExpiration();
        $this->assertInstanceOf(
            \DateTimeImmutable::class,
            $createdSharesExpirationDateTime,
            "Expected class to be 'DateTimeImmutable' but found "
            . print_r($createdSharesExpirationDateTime, true),
        );
        $this->assertSame(
            $expirationDate,
            $createdSharesExpirationDateTime->getTimestamp(),
            "Link expiration timestamp mismatch",
        );
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
        $createdLinkExpirationDateTime = $link->getExpiration();
        $expectedDate = "Thu, 01 Jan 2060 10:00:00 +0000";
        $this->assertInstanceOf(
            \DateTimeImmutable::class,
            $createdLinkExpirationDateTime,
            "Expected class to be 'DateTimeImmutable' but found "
            . print_r($createdLinkExpirationDateTime, true),
        );
        $this->assertSame(
            $expectedDate,
            $createdLinkExpirationDateTime->format('r'),
            "Expected expiration datetime of shared resource doesn't match",
        );
        $this->assertSame(
            "Z",
            $createdLinkExpirationDateTime->getTimezone()->getName(),
            "Expected timezone to be Z but found " . $createdLinkExpirationDateTime->getTimezone()->getName(),
        );

        $createdShares = $this->ocis->getSharedByMe();
        $this->assertCount(
            1,
            $createdShares,
            "Expected count of created share to be 1 but found " . count($createdShares),
        );
        $createdSharesExpirationDateTime = $createdShares[0]->getExpiration();
        $this->assertInstanceOf(
            \DateTimeImmutable::class,
            $createdSharesExpirationDateTime,
            "Expected class to be 'DateTimeImmutable' but found "
            . print_r($createdSharesExpirationDateTime, true),
        );
        // The returned expiry is in UTC timezone (2 hours earlier than the expiry time in Kyiv)
        $this->assertSame(
            $expectedDate,
            $createdSharesExpirationDateTime->format('r'),
            "Expected expiration datetime of created share of the resource doesn't match",
        );
        $this->assertSame(
            "Z",
            $createdSharesExpirationDateTime->getTimezone()->getName(),
            "Expected timezone to be Z but found " . $createdSharesExpirationDateTime->getTimezone()->getName(),
        );
    }

    public function testSetExpiration(): void
    {
        $link = $this->fileToShare->createSharingLink(SharingLinkType::VIEW, null, "p@$\$w0rD");
        $tomorrow = new \DateTimeImmutable('tomorrow');
        $expectedExpirationDate = $tomorrow;
        $link->setExpiration($tomorrow);
        $linkFromSharedByMe = $this->ocis->getSharedByMe()[0];
        $this->assertEquals(
            $expectedExpirationDate,
            $link->getExpiration(),
            "Expiration DateTime mismatch with original sharing link",
        );
        $this->assertEquals(
            $expectedExpirationDate,
            $linkFromSharedByMe->getExpiration(),
            "Expiration DateTime mismatch with link from shared-by-me",
        );
    }

    public function testSetPassword(): void
    {
        $link = $this->fileToShare->createSharingLink(SharingLinkType::VIEW, null, "p@$\$w0rD");
        $resetPassword = $link->setPassword("pp@$\$w0rD");
        $this->assertTrue($resetPassword, "Failed to set password");
    }

    public function testDeleteShareLink(): void
    {
        $link = $this->fileToShare->createSharingLink(SharingLinkType::VIEW, null, "p@$\$w0rD");
        $link->delete();
        $linkFromSharedByMe = $this->ocis->getSharedByMe();
        $this->assertCount(0, $linkFromSharedByMe, "Link couldn't be deleted");
    }
}
