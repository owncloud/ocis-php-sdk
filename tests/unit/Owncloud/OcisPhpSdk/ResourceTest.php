<?php

namespace unit\Owncloud\OcisPhpSdk;

use DateTime;
use Owncloud\OcisPhpSdk\Exception\DateException;
use Owncloud\OcisPhpSdk\Exception\InvalidResponseException;
use Owncloud\OcisPhpSdk\Helper\DateHelper;
use Owncloud\OcisPhpSdk\OcisResource;
use PHPUnit\Framework\TestCase;
use Sabre\DAV\Xml\Property\ResourceType;
use TypeError;

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
    public static function dataProviderValidFileType(): array
    {
        return [
            ['{DAV:}collection', 'folder'],
            [null, 'file'],
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
            $accessToken,
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
    public static function dataProviderInvalidFileType(): array
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
     * @param int|string $resourceType
     *
     * @return void
     */
    public function testGetFileTypeInvalidResource(int|string $resourceType): void
    {
        $metadata = [];
        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage("Received invalid data for the key \"resourcetype\" in the response array");
        /* @phpstan-ignore-next-line because some test case(s) purposely pass an int */
        $metadata[200]['{DAV:}resourcetype'] = new ResourceType($resourceType);
        $resource = $this->createOcisResource($metadata);
        $resource->getType();
    }

    /**
     * @return array<int, array<int, int|string|null>>
     */
    public static function validSizes(): array
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
        string $sizeKey,
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
    public static function inValidSizes(): array
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
        $resource->getSize();
    }

    /**
     * @return array<int, array<int, int|string|null>>
     */
    public static function validContentType(): array
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
    public static function favoriteValue(): array
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
    public static function invalidFavoriteValues(): array
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
    public static function checkSumValue(): array
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
        $this->assertSame($value, $result);
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

    /**
     * @return array<int, array<int, string>>
     */
    public static function relativeDates(): array
    {
        return [
            ['2d', '2026-01-03'],
            ['2w', '2026-01-15'],
            ['2m', '2026-03-01'],
            ['2y', '2028-01-01'],
        ];
    }

    /**
     * @dataProvider relativeDates
     */
    public function testParseDeletionDate(string $relativeDate, string $expectedAbsoluteDate): void
    {
        $createdDate = new DateTime("2026-01-01");
        $parsedDate = DateHelper::getAbsoluteDateFromRelativeDate($relativeDate, $createdDate);
        $this->assertEquals($expectedAbsoluteDate, $parsedDate);
    }

    /**
     * @return array<int, array<int, string>>
     */
    public static function invalidRelativeDates(): array
    {
        return [
            ['2h'],
            ['-2w'],
            ['lm'],
            ['0d'],
            ['2'],
            ['w'],
        ];
    }

    /**
     * @dataProvider invalidRelativeDates
     */
    public function testParseDeletionDateInvalidRelativeDates(string $relativeDate): void
    {
        $createdDate = new DateTime("2026-01-01");
        $this->expectException(DateException::class);
        DateHelper::getAbsoluteDateFromRelativeDate($relativeDate, $createdDate);
    }

    /**
     * @return array<int, array<int, string|DateTime>>
     */
    public static function deletionDates(): array
    {
        return [
            ['3026-01-01'],
            [new DateTime('3026-01-01')],
            [(new DateTime('tomorrow'))->setTime(0, 0, 0)],
        ];
    }

    /**
     * @dataProvider deletionDates
     */
    public function testValidateDeletionDate(string|DateTime $deletionDate): void
    {
        DateHelper::validateDeletionDate($deletionDate);
        $this->addToAssertionCount(1);
    }

    /**
     * @return array<int, array<int, string|DateTime>>
     */
    public static function invalidDeletionDates(): array
    {
        return [
            ['2026-01-01'],
            [new DateTime('2026-01-01')],
            [(new DateTime('yesterday'))->setTime(0, 0, 0)],
            [(new DateTime('today'))->setTime(0, 0, 0)],
        ];
    }

    /**
     * @dataProvider invalidDeletionDates
     */
    public function testValidateDeletionDateWithIndalidDeletionDate(string|DateTime $deletionDate): void
    {
        $this->expectException(DateException::class);
        DateHelper::validateDeletionDate($deletionDate);
    }

    /**
     * @return array<int, array<int, string>>
     */
    public static function invalidDateFormats(): array
    {
        return [
            ['2026-1-1'],
            ['06-01-2026'],
            ['2026-13-01'],
            ['2026-01-35'],
            [''],
            ['abc'],
        ];
    }

    /**
     * @dataProvider invalidDateFormats
     */
    public function testValidateDeletionDateWithInvalidDateFormats(string|DateTime $deletionDate): void
    {
        $this->expectException(DateException::class);
        DateHelper::validateDeletionDate($deletionDate);
    }

    /**
     * @return array<int, array<int, int|null|bool>>
     */
    public static function invalidDateArgumentTypes(): array
    {
        return [
            [1],
            [-1],
            [null],
            [true],
        ];
    }

    /**
     * @dataProvider invalidDateArgumentTypes
     */
    public function testValidateDeletionDateWithInvalidArgumentTypes(mixed $deletionDate): void
    {
        if ($deletionDate === null) {
            $this->expectException(TypeError::class);
        } else {
            $this->expectException(DateException::class);
        }
        /**
         * validateDeletionDate only accepts either string or DateTime
         * but we are intentionally passing invalid types (int, bool or null)
         * Here, we get TypeError before our test runs,
         * so ignoring next line
         *
         * @phpstan-ignore-next-line
         * @phan-suppress-next-line PhanTypeMismatchArgumentNullable
         * @phan-suppress-next-line PhanTypeMismatchArgument
         */
        DateHelper::validateDeletionDate($deletionDate);
    }
}
