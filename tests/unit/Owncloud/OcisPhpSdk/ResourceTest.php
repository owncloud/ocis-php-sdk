<?php

namespace unit\Owncloud\OcisPhpSdk;

use Owncloud\OcisPhpSdk\Exception\InvalidResponseException;
use Owncloud\OcisPhpSdk\OcisResource;
use PHPUnit\Framework\TestCase;
use Sabre\DAV\Xml\Property\ResourceType;

class ResourceTest extends TestCase
{
    /**
     * @var array<int, array<string, string>>
     */
    private array $properties = [
        ['property' => 'id', "function" => 'getId'],
        ['property' => 'spaceid', "function" => 'getSpaceId'],
        ['property' => 'file-parent', "function" => 'getParent'],
        ['property' => 'name', "function" => 'getName'],
        ['property' => 'etag', "function" => 'getEtag'],
        ['property' => 'permissions', "function" => 'getPermission'],
        ['property' => 'lastmodified', "function" => 'getLastModifiedTime'],
    ];

    /**
     * @return array<int,array<int,string|null>>
     */
    public function dataProviderValidFileType(): array
    {
        return [
            ['{DAV:}collection', 'folder'],
            [null, 'file']
        ];
    }

    /**
     * @param array<int, array<mixed>> $metadata
     * @return OcisResource
     */
    private function createOcisResource(array $metadata): OcisResource
    {
        $accessToken = 'aaa';
        return new OcisResource(
            $metadata,
            [],
            '',
            $accessToken
        );
    }
    /**
     * @return void
     * @dataProvider dataProviderValidFileType
     */
    public function testGetFileTypeValid(string|null $resourceType, string $expectedResult): void
    {
        $metadata = [];
        $metadata[200]['{DAV:}resourcetype'] = new ResourceType($resourceType);
        $resource = $this->createOcisResource($metadata);
        $result = $resource->getType();
        $this->assertSame($expectedResult, $result);
    }

    /**
     * @return array<int, array<int, int|string>>
     */
    public function dataProviderInvalidFileType(): array
    {
        return ([
            ["as"],
            ["a"],
            [1],
            ["#$%^"],
        ]);
    }

    /**
     * @dataProvider dataProviderInvalidFileType
     * @param array<int,string|int>|string|null $resourceType
     *
     * @return void
     */
    public function testGetFileTypeInvalidResource($resourceType): void
    {
        $metadata = [];
        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage("Received invalid data for the key \"resourcetype\" in the response array");
        $metadata[200]['{DAV:}resourcetype'] = new ResourceType($resourceType);
        $resource = $this->createOcisResource($metadata);
        $result = $resource->getType();
    }

    /**
     * @return array<int, array<int, int|string|null>>
     */
    public function validSizes(): array
    {
        return [
            [0, 0, null, '{DAV:}getcontentlength'],
            ["0", 0, null, '{DAV:}getcontentlength'],
            [123, 123, null, '{DAV:}getcontentlength'],
            ["123", 123, null, '{DAV:}getcontentlength'],
            ["9223372036854775806", 9223372036854775806, null, '{DAV:}getcontentlength'],
            [0, 0, '{DAV:}collection', '{http://owncloud.org/ns}size'],
            [9223372036854775806, 9223372036854775806, '{DAV:}collection', '{http://owncloud.org/ns}size'],
            ["0", 0, '{DAV:}collection', '{http://owncloud.org/ns}size'],
            [123, 123, '{DAV:}collection', '{http://owncloud.org/ns}size'],
            ["123", 123, '{DAV:}collection', '{http://owncloud.org/ns}size'],
        ];
    }

    /**
     * @dataProvider validSizes
     * @param int|string $actualSize
     * @param int|string $expectedSize
     * @param null|string $data
     * @param string $sizeKey
     *
     * @return void
     */
    public function testGetSize(
        int|string $actualSize,
        int|string $expectedSize,
        null|string $data,
        string $sizeKey
    ): void {
        $metadata = [];
        $metadata[200]['{DAV:}resourcetype'] = new ResourceType($data);
        $metadata[200][$sizeKey] = $actualSize;
        $resource = $this->createOcisResource($metadata);
        $result = $resource->getSize();
        $this->assertSame($expectedSize, $result);
    }

    /**
     * @return array<int, array<int, int|string|null>>
     */
    public function inValidSizes(): array
    {
        return [
            ["g", '{DAV:}collection', '{http://owncloud.org/ns}size'],
            ["a", null, '{DAV:}getcontentlength'],
        ];
    }

    /**
     * @dataProvider inValidSizes
     */
    public function testGetSizeInvalid(string $actualSize, string|null $data, string $sizeKey): void
    {
        $metadata = [];
        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage("Received an invalid value for size in the response");
        $metadata[200]['{DAV:}resourcetype'] = new ResourceType($data);
        $metadata[200][$sizeKey] = $actualSize;
        $resource = $this->createOcisResource($metadata);
        $result = $resource->getSize();
    }

    /**
     * @return array<int, array<int, int|string|null>>
     */
    public function validContentType(): array
    {
        return [
            ['text/plain', null],
            ["sad$", null],
            ['',  '{DAV:}collection'],
        ];
    }

    /**
     * @dataProvider validContentType
     *
     * @param string $expectedResult
     * @param null|string $fileType
     *
     * @return void
     */
    public function testGetContentType(string $expectedResult, null|string $fileType): void
    {
        $metadata = [];
        $metadata[200]['{DAV:}resourcetype'] = new ResourceType($fileType);
        $metadata[200]['{DAV:}getcontenttype'] = $expectedResult;
        $resource = $this->createOcisResource($metadata);
        $result = $resource->getContentType();
        $this->assertSame($expectedResult, $result);
    }

    /**
     * @return void
     */
    public function testGetTags(): void
    {
        $metadata = [];
        $tags = [["mytag"], ["asd", "asd"], [''], [null]];
        foreach ($tags as $tag) {
            $metadata[200]['{http://owncloud.org/ns}tags'] = implode(',', $tag);
            $resource = $this->createOcisResource($metadata);
            $result = $resource->getTags();
            if ($tag === [null] || $tag === ['']) {
                $tag = [];
            }
            $this->assertSame($tag, $result);
        }
    }

    /**
     * @return array<int, array<int, int|string|null>>
     */
    public function favoriteValue(): array
    {
        return [
            [0],
            [1],
            ['1'],
        ];
    }

    /**
     * @dataProvider favoriteValue
     */
    public function testGetFavorite(string|int $value): void
    {
        $metadata = [];
        $metadata[200]['{http://owncloud.org/ns}favorite'] = $value;
        $resource = $this->createOcisResource($metadata);
        $result = $resource->isFavorited();
        $this->assertIsBool($result);
        $this->assertSame($result, (bool)$value);
    }

    /**
     * @return array<int, array<int, string>>
     */
    public function invalidFavoriteValues(): array
    {
        return [
            ['2'],
            ['wqe'],
        ];
    }

    /**
     * @dataProvider invalidFavoriteValues
     */
    public function testGetFavoriteInvalid(string $value): void
    {
        $metadata = [];
        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage("Value of property \"favorite\" invalid in the server response");
        $metadata[200]['{http://owncloud.org/ns}favorite'] = $value;
        $resource = $this->createOcisResource($metadata);
        $resource->isFavorited();
    }

    /**
     * @return array<int, array<int, array<string, string>|string|null>>
     */
    public function checkSumValue(): array
    {
        return [
            [["aa" => "aa"], null],
            [[], null],
            [[], "{DAV:}collection"],
        ];
    }

    /**
     * @dataProvider checkSumValue
     * @param array<int,array<int,null>> $value
     * @param string|null $fileType
     *
     * @return void
     */
    public function testGetCheckSum(array $value, string|null $fileType): void
    {
        $metadata = [];
        $metadata[200]['{DAV:}resourcetype'] = new ResourceType($fileType);
        $metadata[200]['{DAV:}getcontentlength'] = 1;
        $metadata[200]['{http://owncloud.org/ns}checksums'] = $value;
        $resource = $this->createOcisResource($metadata);
        $result = $resource->getCheckSums();
        $this->assertEquals($value, $result);
    }

    /**
     * @return void
     */
    public function testGettersCorrectResponse(): void
    {
        $metadata = [];
        foreach ($this->properties as ['property' => $property, "function" => $propertyFunc]) {
            if (in_array($property, ['etag', 'lastmodified'])) {
                $metadata[200]['{DAV:}get' . $property] = $property;
            } else {
                $metadata[200]['{http://owncloud.org/ns}' . $property] = $property;
            }
            $resource = $this->createOcisResource($metadata);
            $result = $resource->$propertyFunc();
            $this->assertSame($property, $result);
        }
    }

    /**
     * @return void
     */
    public function testGettersEmptyResponseKey(): void
    {
        $metadata = [];
        foreach ($this->properties as ['property' => $property, "function" => $propertyFunc]) {
            $metadata[200][''] = $property;
            $resource = $this->createOcisResource($metadata);
            $result = null;
            try {
                $result = $resource->$propertyFunc();
            } catch (InvalidResponseException $e) {
                $this->assertSame('Could not find property "' . $property . '" in response', $e->getMessage());
            }
            $this->assertNull($result);
        }
    }

    /**
     * @return void
     */
    public function testGettersValuesAreNull(): void
    {
        $metadata = [];
        foreach ($this->properties as ['property' => $property, "function" => $propertyFunc]) {
            if (in_array($property, ["etag", "lastmodified"])) {
                $metadata[200]['{DAV:}get' . $property] = null;
            } else {
                $metadata[200]['{http://owncloud.org/ns}' . $property] = null;
            }
            $resource = $this->createOcisResource($metadata);
            $result = null;
            try {
                $result = $resource->$propertyFunc();
            } catch (InvalidResponseException $e) {
                $this->assertSame('Invalid response from server', $e->getMessage());
            }
            $this->assertNull($result);
        }
    }
}
