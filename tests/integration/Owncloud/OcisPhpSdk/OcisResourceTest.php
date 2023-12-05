<?php

namespace integration\Owncloud\OcisPhpSdk;

require_once __DIR__ . '/OcisPhpSdkTestCase.php';

use Owncloud\OcisPhpSdk\Drive;
use Owncloud\OcisPhpSdk\DriveOrder;
use Owncloud\OcisPhpSdk\DriveType;
use Owncloud\OcisPhpSdk\Exception\ConflictException;
use Owncloud\OcisPhpSdk\Exception\ForbiddenException;
use Owncloud\OcisPhpSdk\Exception\NotFoundException;
use Owncloud\OcisPhpSdk\Ocis;
use Owncloud\OcisPhpSdk\OcisResource; // @phan-suppress-current-line PhanUnreferencedUseNormal it's used in a comment
use Owncloud\OcisPhpSdk\OrderDirection;

class OcisResourceTest extends OcisPhpSdkTestCase
{
    private Drive $personalDrive;
    private Ocis $ocis;
    public function setUp(): void
    {
        parent::setUp();
        $token = $this->getAccessToken('admin', 'admin');
        $this->ocis = new Ocis($this->ocisUrl, $token, ['verify' => false]);
        $this->personalDrive = $this->ocis->getMyDrives(
            DriveOrder::NAME,
            OrderDirection::ASC,
            DriveType::PERSONAL
        )[0];
        $this->personalDrive->uploadFile('somefile.txt', 'some content');
        $this->personalDrive->uploadFile('secondfile.txt', 'some other content');
        $this->personalDrive->createFolder('subfolder');

        $this->createdResources[$this->personalDrive->getId()][] = '/somefile.txt';
        $this->createdResources[$this->personalDrive->getId()][] = '/secondfile.txt';
        $this->createdResources[$this->personalDrive->getId()][] = '/subfolder';
    }

    /**
     * @param Ocis $ocis
     * @return array<Drive>
     */
    private function getMountpoints(Ocis $ocis): array
    {
        // we need to wait till the mountpoint appears
        $retryCounter = 0;
        do {
            $mountpoints = $ocis->getMyDrives(
                DriveOrder::NAME,
                OrderDirection::ASC,
                DriveType::MOUNTPOINT
            );
            if (count($mountpoints) === 0) {
                sleep(1);
            }
            $retryCounter++;
            $this->assertLessThan(10, $retryCounter);
        } while (count($mountpoints) === 0);
        return $mountpoints;
    }

    private function share(string $receiverName, string $roleName, string $resourceName): void
    {
        $receiver = $this->ocis->getUsers($receiverName)[0];
        $resources = $this->personalDrive->getResources();

        foreach ($resources as $resource) {
            if ($resource->getName() === $resourceName) {
                $role = $this->getRoleByName($resource, $roleName);
                $resource->invite([$receiver], $role);
                break;
            }
        }
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
        foreach ($this->createdResources[$this->personalDrive->getId()] as $resource) {
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

    public function testUploadFile(): void
    {
        $this->personalDrive->uploadFile('/subfolder/uploaded.txt', 'some content');
        $resources = $this->personalDrive->getResources('/subfolder');
        $this->assertCount(1, $resources);
        $this->assertEquals('uploaded.txt', $resources[0]->getName());
        $this->assertEquals('some content', $resources[0]->getContent());
    }

    public function testUploadFileOverwritingExisting(): void
    {
        $this->personalDrive->uploadFile('/subfolder/uploaded.txt', 'some content');
        $this->personalDrive->uploadFile('/subfolder/uploaded.txt', 'new content');
        $resources = $this->personalDrive->getResources('/subfolder');
        $this->assertCount(1, $resources);
        $this->assertEquals('uploaded.txt', $resources[0]->getName());
        $this->assertEquals('new content', $resources[0]->getContent());
    }

    public function testUploadFileNotExistingFolder(): void
    {
        $this->expectException(ConflictException::class);
        $this->personalDrive->uploadFile('/folder-not-existing/uploaded.txt', 'some content');
    }

    public function testUploadFileToReceivedFolderShare(): void
    {
        $einsteinOcis = $this->initUser('einstein', 'relativity');
        $this->share('einstein', 'Editor', 'subfolder');

        $sharesReceivedByEinstein = $this->getMountpoints($einsteinOcis);

        $sharesReceivedByEinstein[0]->uploadFile('/uploaded.txt', 'some content');

        $resources = $this->personalDrive->getResources('/subfolder');
        $this->assertCount(1, $resources);
        $this->assertEquals('uploaded.txt', $resources[0]->getName());
        $this->assertEquals('some content', $resources[0]->getContent());
    }

    public function testUploadFileNoPermission(): void
    {
        $einsteinOcis = $this->initUser('einstein', 'relativity');
        $einstein = $this->ocis->getUsers('einstein')[0];
        $resources = $this->personalDrive->getResources();

        foreach ($resources as $resource) {
            if ($resource->getName() === 'subfolder') {
                $role = $this->getRoleByName($resource, 'Viewer');
                $resource->invite([$einstein], $role);
                break;
            }
        }

        $sharesReceivedByEinstein = $this->getMountpoints($einsteinOcis);

        $this->expectException(ForbiddenException::class);
        $sharesReceivedByEinstein[0]->uploadFile('/uploaded.txt', 'some content');
    }

    public function testUploadFileStream(): void
    {
        $localFileName = tempnam(sys_get_temp_dir(), "FOO");
        $this->assertNotFalse($localFileName, 'could not create temp file');
        $fp = fopen($localFileName, "w");
        $this->assertNotFalse($fp, 'could not open temp file');
        $this->assertSame(12, fwrite($fp, 'some content'));
        fclose($fp);
        $fp = fopen($localFileName, "r");
        $this->assertNotFalse($fp, 'could not open temp file');
        $this->personalDrive->uploadFileStream('/subfolder/uploaded.txt', $fp);
        $resources = $this->personalDrive->getResources('/subfolder');
        $this->assertCount(1, $resources);
        $this->assertEquals('uploaded.txt', $resources[0]->getName());
        $this->assertEquals('some content', $resources[0]->getContent());
    }
}
