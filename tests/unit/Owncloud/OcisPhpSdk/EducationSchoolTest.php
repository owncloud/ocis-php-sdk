<?php

namespace unit\Owncloud\OcisPhpSdk;

use DateTime;
use OpenAPI\Client\Api\EducationSchoolApi;
use OpenAPI\Client\Model\EducationSchool as ES;
use OpenAPI\Client\Model\CollectionOfSchools;
use Owncloud\OcisPhpSdk\EducationSchool;
use Owncloud\OcisPhpSdk\Ocis;
use PHPUnit\Framework\TestCase;

class EducationSchoolTest extends TestCase
{
    /**
     * @return array<int, array<int, array<string, DateTime|string>>>
     */
    public static function educationSchoolData(): array
    {
        return [
            [[
                "id" => "id",
                "display_name" => "demo",
                "school_number" => "1",
                "termination_date" => new DateTime('2020-12-12'),
            ]],
        ];
    }

    /**
     * @dataProvider educationSchoolData
     *
     * @param array<string,string|int> $data
     * @return void
     */
    public function testGetEducationSchool(array $data): void
    {
        $educationApi = $this->createMock(EducationSchoolApi::class);
        $educationApi->method("listSchools")->willReturn(
            new CollectionOfSchools(["value" => [new ES($data)]]),
        );
        $ocis = new Ocis(serviceUrl: 'a', accessToken: 'a', educationAccessToken: 'a');
        /** @phan-suppress-next-line PhanTypeMismatchArgument*/
        $schools = $ocis->getEducationSchools($educationApi);
        $this->assertCount(1, $schools);
        $this->assertContainsOnlyInstancesOf(EducationSchool::class, $schools);
        $this->assertSame("id", $schools[0]->getId());
        $this->assertSame("demo", $schools[0]->getDisplayName());
        $this->assertSame("1", $schools[0]->getNumber());
        $this->assertInstanceOf(DateTime::class, $schools[0]->getTerminationDate());
        $this->assertSame('2020-12-12', $schools[0]->getTerminationDate()->format('Y-m-d'));
    }

    /**
     * @dataProvider educationSchoolData
     *
     * @param array<int, array<int, array<string, array<int, string>|string>>> $data
     * @return void
     */
    public function testListEducationSchools(array $data): void
    {
        $educationApi = $this->createMock(EducationSchoolApi::class);
        $educationApi->method("getSchool")->willReturn(
            new ES($data),
        );
        $ocis = new Ocis(serviceUrl: 'a', accessToken: 'a', educationAccessToken: 'a');
        /** @phan-suppress-next-line PhanTypeMismatchArgument*/
        $school = $ocis->getEducationSchoolById('id', $educationApi);
        $this->assertSame("id", $school->getId());
        $this->assertSame("demo", $school->getDisplayName());
        $this->assertSame("1", $school->getNumber());
        $this->assertInstanceOf(DateTime::class, $school->getTerminationDate());
        $this->assertSame('2020-12-12', $school->getTerminationDate()->format('Y-m-d'));
    }
}
