<?php

namespace integration\Owncloud\OcisPhpSdk;

require_once __DIR__ . '/OcisPhpSdkTestCase.php';

use Owncloud\OcisPhpSdk\Drive;
use Owncloud\OcisPhpSdk\DriveOrder;
use Owncloud\OcisPhpSdk\DriveType;
use Owncloud\OcisPhpSdk\Exception\ConflictException;
use Owncloud\OcisPhpSdk\Exception\ForbiddenException;
use Owncloud\OcisPhpSdk\Exception\NotFoundException;
use Owncloud\OcisPhpSdk\Exception\TooEarlyException;
use Owncloud\OcisPhpSdk\Ocis;
use Owncloud\OcisPhpSdk\OcisResource; // @phan-suppress-current-line PhanUnreferencedUseNormal it's used in a comment
use Owncloud\OcisPhpSdk\OrderDirection;
use Owncloud\OcisPhpSdk\SharingRole;

class OcisResourceTest extends OcisPhpSdkTestCase
{
    private Drive $personalDrive;
    private Ocis $ocis;
    public function setUp(): void
    {
        parent::setUp();
        $this->ocis = $this->getOcis('admin', 'admin');
        $this->personalDrive = $this->getPersonalDrive($this->ocis);
        $this->personalDrive->uploadFile('somefile.txt', 'some content');
        $this->personalDrive->uploadFile('secondfile.txt', 'some other content');
        $this->personalDrive->createFolder('subfolder');
        $this->personalDrive->createFolder('secondfolder');

        $this->createdResources[$this->personalDrive->getId()][] = '/somefile.txt';
        $this->createdResources[$this->personalDrive->getId()][] = '/secondfile.txt';
        $this->createdResources[$this->personalDrive->getId()][] = '/subfolder';
        $this->createdResources[$this->personalDrive->getId()][] = '/secondfolder';
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
                $resource->invite($receiver, $role);
                break;
            }
        }
    }

    public function testGetResources(): void
    {
        $resources = $this->personalDrive->getResources();
        $this->assertCount(
            4,
            $resources,
            "Expected 4 resources but found ". count($resources)
        );

        /**
         * @var OcisResource $resource
         */
        foreach ($resources as $resource) {
            $this->assertMatchesRegularExpression(
                "/^" . $this->getFileIdRegex() . "$/i",
                $resource->getId(),
                "ResourceId doesn't match the expected format"
            );
            $this->assertMatchesRegularExpression(
                "/^\"[a-f0-9:\.]{1,32}\"$/",
                $resource->getEtag(),
                "Resource Etag doesn't match the expected format"
            );
            $this->assertMatchesRegularExpression(
                "?^" . $this->ocisUrl . '/f/'. $this->getFileIdRegex() . "$?i",
                $resource->getPrivatelink(),
                "Private Link of resource doesn't match the expected format"
            );
            $this->assertThat(
                $resource->getType(),
                $this->logicalOr(
                    $this->equalTo("file"),
                    $this->equalTo("folder")
                ),
                "Expected resource type be either file or folder but found ".$resource->getType()
            );

            $this->assertMatchesRegularExpression(
                "/^" . $this->getSpaceIdRegex() . "$/i",
                $resource->getSpaceId(),
                "SpaceId doesn't match the expected format"
            );
            $this->assertMatchesRegularExpression(
                "/^" . $this->getFileIdRegex() . "$/i",
                $resource->getParent(),
                "Resource Parent doesn't match the expected format"
            );
            $this->assertThat(
                $resource->getPermission(),
                $this->logicalOr(
                    $this->equalTo("RDNVWZP"),
                    $this->equalTo("RDNVCKZP")
                ),
                "Permission doesn't match"
            );

            $this->assertEqualsWithDelta(
                strtotime("now"),
                strtotime($resource->getLastModifiedTime()),
                60,
                "Resources wasn't modified within 60 seconds"
            );
            if ($resource->getType() === 'folder') {
                $this->assertEquals(
                    '',
                    $resource->getContentType(),
                    "Expected content type be empty but found ".$resource->getContentType()
                );
            } else {
                $this->assertEquals(
                    'text/plain',
                    $resource->getContentType(),
                    "Expected content type be text/plain but found ".$resource->getContentType()
                );
            }

            $this->assertEquals(
                false,
                $resource->isFavorited(),
                "Resource is not expected to be favorited"
            );
            $this->assertEquals(
                [],
                $resource->getTags(),
                "Expected resource tag be empty array but found ". count($resource->getTags())." elements"
            );
            $this->assertIsInt(
                $resource->getSize(),
                "Expected resource size be of type integer but found ".getType($resource->getSize())
            );
            if ($resource->getType() === 'folder') {
                $this->assertThat(
                    $resource->getName(),
                    $this->logicalOr(
                        $this->equalTo("subfolder"),
                        $this->equalTo("secondfolder")
                    ),
                    "Expected resource name be subfolder or secondfolder but found ".$resource->getName()
                );
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
            $content = $this->getContentOfResource425Save($resource);
            switch ($resource->getName()) {
                case 'somefile.txt':
                    $this->assertEquals(
                        'some content',
                        $content,
                        "File content doesn't match"
                    );
                    break;
                case 'secondfile.txt':
                    $this->assertEquals(
                        'some other content',
                        $content,
                        "File content doesn't match"
                    );
                    break;
                case 'subfolder':
                    $this->assertEquals(
                        '',
                        $content,
                        "Expected folder be empty but found ".$content
                    );
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
            $stream = null;
            while ($stream === null) {
                try {
                    $stream = $resource->getContentStream();
                } catch (TooEarlyException) {
                    sleep(1);
                }
            }
            // check for null is done above
            // @phan-suppress-next-line PhanTypeMismatchArgumentNullableInternal
            $content = fread($stream, 1024);
            switch ($resource->getName()) {
                case 'somefile.txt':
                    $this->assertEquals(
                        'some content',
                        $content,
                        "File content doesn't match"
                    );
                    break;
                case 'secondfile.txt':
                    $this->assertEquals(
                        'some other content',
                        $content,
                        "File content doesn't match"
                    );
                    break;
                case 'subfolder':
                    $this->assertEquals(
                        '',
                        $content,
                        "Expected folder be empty but found ".$content
                    );
                    break;
            }
        }
    }

    public function testUploadFile(): void
    {
        $this->personalDrive->uploadFile('/subfolder/uploaded.txt', 'some content');
        $resources = $this->personalDrive->getResources('/subfolder');
        $this->assertCount(
            1,
            $resources,
            "Expected one resource but found ".count($resources)
        );
        $this->assertEquals(
            'uploaded.txt',
            $resources[0]->getName(),
            "Expected 'uploaded.txt' file but found ".$resources[0]->getName()
        );
        $content = $this->getContentOfResource425Save($resources[0]);
        $this->assertEquals(
            'some content',
            $content,
            "File content doesn't match"
        );
    }

    public function testUploadFileOverwritingExisting(): void
    {
        $this->personalDrive->uploadFile('/subfolder/uploaded.txt', 'some content');
        $this->personalDrive->uploadFile('/subfolder/uploaded.txt', 'new content');
        $resources = $this->personalDrive->getResources('/subfolder');
        $this->assertCount(
            1,
            $resources,
            "Expected one resource but found ".count($resources)
        );
        $this->assertEquals(
            'uploaded.txt',
            $resources[0]->getName(),
            "Expected 'uploaded.txt' file but found ".$resources[0]->getName()
        );
        $content = $this->getContentOfResource425Save($resources[0]);
        $this->assertEquals(
            'new content',
            $content,
            "File content doesn't match"
        );
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
        $this->assertCount(
            1,
            $resources,
            "Expected one resource but found ".count($resources)
        );
        $this->assertEquals(
            'uploaded.txt',
            $resources[0]->getName(),
            "Expected 'uploaded.txt' file but found ".$resources[0]->getName()
        );
        $content = $this->getContentOfResource425Save($resources[0]);
        $this->assertEquals(
            'some content',
            $content,
            "File content doesn't match"
        );
    }

    public function testUploadFileNoPermission(): void
    {
        $einsteinOcis = $this->initUser('einstein', 'relativity');
        $einstein = $this->ocis->getUsers('einstein')[0];
        $resources = $this->personalDrive->getResources();

        foreach ($resources as $resource) {
            if ($resource->getName() === 'subfolder') {
                $role = $this->getRoleByName($resource, 'Viewer');
                $resource->invite($einstein, $role);
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
        $this->assertCount(
            1,
            $resources,
            "Expected one resource but found ".count($resources)
        );
        $this->assertEquals(
            'uploaded.txt',
            $resources[0]->getName(),
            "Expected 'uploaded.txt' file but found ".$resources[0]->getName()
        );
        $content = $this->getContentOfResource425Save($resources[0]);
        $this->assertEquals(
            'some content',
            $content,
            "File content doesn't match"
        );
    }


    /**
     * @return array<int, array<int, string>>
     */
    public static function resources()
    {
        return [
            ['somefile.txt','file'],
            ['secondfolder','folder']
        ];
    }
    /**
     * @dataProvider resources
     */
    public function testMoveResource(string $resourceName, string $type): void
    {
        $rootResources = $this->personalDrive->getResources();

        $isResourceMoved = $this->personalDrive->moveResource($resourceName, 'subfolder/'.$resourceName);
        $this->assertTrue($isResourceMoved, "couldn't move the resource");

        $resourceInsideFolder = $this->personalDrive->getResources('/subfolder');
        $this->assertCount(
            1,
            $resourceInsideFolder,
            "Expected one resources but found ".count($resourceInsideFolder)
        );

        $rootResourcesAfterMove = $this->personalDrive->getResources();
        $this->assertCount(
            count($rootResources) - 1,
            $rootResourcesAfterMove,
            "Resource wasn't moved inside the folder"
        );

        foreach ($rootResourcesAfterMove as $resource) {
            $this->assertNotEquals($resourceName, $resource->getName(), "Resource $resourceName should not exist at root after move");
        }

        if ($type === 'file') {
            $fileContent = $this->personalDrive->getFile('subfolder/'.$resourceName);
            $this->assertEquals(
                'some content',
                $fileContent,
                "File content doesn't match"
            );
        }
    }

    /**
     * @return array<int, array<int, string>>
     */
    public static function invalidResources()
    {
        return [
            ['nonExistentFile.txt'],
            ['nonExistentFile']
        ];
    }
    /**
     * @dataProvider invalidResources
     */
    public function testMoveNonExistentResource(string $invalidResources): void
    {
        $this->expectException(NotFoundException::class);
        $this->personalDrive->moveResource($invalidResources, 'subfolder/'. $invalidResources);
    }

    public function testGetRoles(): void
    {
        $resources = $this->personalDrive->getResources();
        foreach ($resources as $resource) {
            $role = $resource->getRoles();
            $this->assertContainsOnlyInstancesOf(
                SharingRole::class,
                $role,
                "Array contains not only 'SharingRole' items"
            );
        }
    }

    public function testGetRolesOfDeletedResources(): void
    {
        $this->personalDrive->uploadFile('/newResource.txt', 'new content');
        $resources = $this->personalDrive->getResources();
        $newResource = null;
        foreach ($resources as $resource) {
            if($resource->getName() === 'newResource.txt') {
                $newResource = $resource;
            }
        }
        $this->personalDrive->deleteResource('/newResource.txt');
        $this->expectException(NotFoundException::class);
        if($newResource !== null) {
            $newResource->getRoles();
        }
    }
}
