<?php

namespace integration\Owncloud\OcisPhpSdk;

require_once __DIR__ . '/OcisPhpSdkTestCase.php';

use Owncloud\OcisPhpSdk\Drive;
use Owncloud\OcisPhpSdk\DriveOrder;
use Owncloud\OcisPhpSdk\DriveType;
use Owncloud\OcisPhpSdk\Exception\NotFoundException;
use Owncloud\OcisPhpSdk\Ocis;
use Owncloud\OcisPhpSdk\OcisResource; // @phan-suppress-current-line PhanUnreferencedUseNormal it's used in a comment
use Owncloud\OcisPhpSdk\OrderDirection;

class OcisResourceTest extends OcisPhpSdkTestCase
{
    private Drive $personalDrive;
    public function setUp(): void
    {
        parent::setUp();
        $token = $this->getAccessToken('admin', 'admin');
        $ocis = new Ocis($this->ocisUrl, $token, ['verify' => false]);
        $this->personalDrive = $ocis->getMyDrives(
            DriveOrder::NAME,
            OrderDirection::ASC,
            DriveType::PERSONAL
        )[0];
        $this->personalDrive->uploadFile('somefile.txt', 'some content');
        $this->personalDrive->uploadFile('secondfile.txt', 'some other content');
        $this->personalDrive->createFolder('subfolder');

        $this->createdResources[] = '/somefile.txt';
        $this->createdResources[] = '/secondfile.txt';
        $this->createdResources[] = '/subfolder';
    }

    public function testGetResources(): void
    {
        $resources = $this->personalDrive->getResources();
        $this->assertCount(3, $resources);

        /**
         * @var OcisResource $resource
         */
        foreach ($resources as $resource) {
            $this->assertMatchesRegularExpression(
                "/^" . $this->getFileIdRegex() . "$/i",
                $resource->getId()
            );
            $this->assertMatchesRegularExpression(
                "/^\"[a-f0-9:\.]{1,32}\"$/",
                $resource->getEtag()
            );
            $this->assertMatchesRegularExpression(
                "?^" . $this->ocisUrl . '/f/'. $this->getFileIdRegex() . "$?i",
                $resource->getPrivatelink()
            );
            $this->assertThat(
                $resource->getType(),
                $this->logicalOr(
                    $this->equalTo("file"),
                    $this->equalTo("folder")
                )
            );

            $this->assertMatchesRegularExpression(
                "/^" . $this->getUUIDv4Regex() . "$/i",
                $resource->getSpaceId()
            );
            $this->assertMatchesRegularExpression(
                "/^" . $this->getFileIdRegex() . "$/i",
                $resource->getParent()
            );
            $this->assertThat(
                $resource->getPermission(),
                $this->logicalOr(
                    $this->equalTo("RDNVWZP"),
                    $this->equalTo("RDNVCKZP")
                )
            );

            $this->assertEqualsWithDelta(
                strtotime("now"),
                strtotime($resource->getLastModifiedTime()),
                60
            );
            if ($resource->getType() === 'folder') {
                $this->assertEquals('', $resource->getContentType());
            } else {
                $this->assertEquals('text/plain', $resource->getContentType());
            }

            $this->assertEquals(false, $resource->isFavorited());
            $this->assertEquals([], $resource->getTags());
            $this->assertIsInt($resource->getSize());
            if ($resource->getType() === 'folder') {
                $this->assertEquals('subfolder', $resource->getName());
            } else {
                $this->assertStringContainsString('file.txt', $resource->getName());
            }
            if ($resource->getType() === 'file') {
                $this->assertStringContainsString('SHA1', $resource->getCheckSums()[0]['value']);
                $this->assertStringContainsString('MD5', $resource->getCheckSums()[0]['value']);
            }
        }
    }

    public function testGetResourceContent(): void
    {
        $resources = $this->personalDrive->getResources();
        foreach ($resources as $resource) {
            $content = $resource->getContent();
            switch ($resource->getName()) {
                case 'somefile.txt':
                    $this->assertEquals('some content', $content);
                    break;
                case 'secondfile.txt':
                    $this->assertEquals('some other content', $content);
                    break;
                case 'subfolder':
                    $this->assertEquals('', $content);
                    break;
            }
        }
    }

    public function testGetNotExistingResourceContent(): void
    {
        $this->expectException(NotFoundException::class);
        $resources = $this->personalDrive->getResources();
        foreach ($this->createdResources as $resource) {
            $this->personalDrive->deleteResource($resource);
        }
        $resources[0]->getContent();
    }

    public function testGetResourceContentStream(): void
    {
        $resources = $this->personalDrive->getResources();
        foreach ($resources as $resource) {
            $stream = $resource->getContentStream();
            $content = fread($stream, 1024);
            switch ($resource->getName()) {
                case 'somefile.txt':
                    $this->assertEquals('some content', $content);
                    break;
                case 'secondfile.txt':
                    $this->assertEquals('some other content', $content);
                    break;
                case 'subfolder':
                    $this->assertEquals('', $content);
                    break;
            }
        }
    }
}
