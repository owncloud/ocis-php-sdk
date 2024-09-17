<?php

namespace unit\Owncloud\OcisPhpSdk;

use OpenAPI\Client\Api\EducationUserApi;
use OpenAPI\Client\Model\CollectionOfEducationUser;
use Owncloud\OcisPhpSdk\EducationUser;
use OpenAPI\Client\Model\EducationUser as EU;
use Owncloud\OcisPhpSdk\Exception\InvalidResponseException;
use Owncloud\OcisPhpSdk\Ocis;
use PHPUnit\Framework\TestCase;
use OpenAPI\Client\Model\ObjectIdentity;

class EducationUserTest extends TestCase
{
    /**
     * @return array<int, array<int, array<string, array<int, string>|string>>>
     */
    public static function educationUserData(): array
    {
        return [
            [[
                "id" => "id",
                "display_name" => "demo",
                "mail" => "mail@mail.com",
                "on_premises_sam_account_name" => "demo",
                "surname" => "surname",
                "given_name" => "givenname",
                "identities" => [new ObjectIdentity(
                    ['issuer' => "idp", "issuer_assigned_id" => 'idpId'],
                ),
                ],
                "primary_role" => "manager",
            ]],
        ];
    }



    /**
     * @dataProvider educationUserData
     *
     * @param array<int, array<int, array<string, array<int, string>|string>>> $data
     * @return void
     */
    public function testGetEducationUsers(array $data)
    {
        $educationApi = $this->createMock(EducationUserApi::class);
        $educationApi->method("listEducationUsers")->willReturn(
            new CollectionOfEducationUser(["value" => [new EU($data)]]),
        );
        $ocis = new Ocis(serviceUrl: 'a', accessToken: 'a', educationAccessToken: 'a');
        /** @phan-suppress-next-line PhanTypeMismatchArgument*/
        $user = $ocis->getEducationUsers(apiInstance: $educationApi);
        $this->assertCount(1, $user);
        $this->assertContainsOnlyInstancesOf(EducationUser::class, $user);
        $this->assertSame("id", $user[0]->getId());
        $this->assertSame("demo", $user[0]->getDisplayName());
        $this->assertSame("mail@mail.com", $user[0]->getMail());
        $this->assertSame("surname", $user[0]->getSurname());
        $this->assertSame("givenname", $user[0]->getGivenName());
        $this->assertSame("manager", $user[0]->getPrimaryRole());
        $identities = $user[0]->getidentities();
        $this->assertNotNull($identities);
        $this->assertCount(1, $identities);
        $this->assertContainsOnlyInstancesOf(ObjectIdentity::class, $identities);
        $this->assertSame("idp", $identities[0]->getIssuer());
        $this->assertSame("idpId", $identities[0]->getIssuerAssignedId());
    }

    /**
     * @dataProvider educationUserData
     *
     * @param array<int, array<int, array<string, array<int, string>|string>>> $data
     * @return void
     */
    public function testListEducationUsers(array $data)
    {
        $educationApi = $this->createMock(EducationUserApi::class);
        $educationApi->method("getEducationUser")->willReturn(
            new EU(
                $data,
            ),
        );
        $ocis = new Ocis(serviceUrl: 'a', accessToken: 'a', educationAccessToken: 'a');
        /** @phan-suppress-next-line PhanTypeMismatchArgument*/
        $user = $ocis->getEducationUserById("user-id", apiInstance: $educationApi);
        $this->assertInstanceOf(EducationUser::class, $user);
        $this->assertSame("id", $user->getId());
        $this->assertSame("demo", $user->getDisplayName());
        $this->assertSame("mail@mail.com", $user->getMail());
        $this->assertSame("surname", $user->getSurname());
        $this->assertSame("givenname", $user->getGivenName());
        $this->assertSame("manager", $user->getPrimaryRole());
        $identities = $user->getidentities();
        $this->assertNotNull($identities);
        $this->assertCount(1, $identities);
        $this->assertContainsOnlyInstancesOf(ObjectIdentity::class, $identities);
        $this->assertSame("idp", $identities[0]->getIssuer());
        $this->assertSame("idpId", $identities[0]->getIssuerAssignedId());
    }

    /**
     * @dataProvider educationUserData
     *
     * @param array<int, array<int, array<string, array<int, string>|string>>> $data
     * @return void
     */
    public function testCreateEducationUsers(array $data)
    {
        $educationApi = $this->createMock(EducationUserApi::class);
        $educationApi->method("createEducationUser")->willReturn(
            new EU($data),
        );
        $ocis = new Ocis(serviceUrl: 'a', accessToken: 'a', educationAccessToken: 'a');
        $user = $ocis->createEducationUser(
            displayName: 'demo',
            onPremisesSAMAccountName: 'demo',
            issuer: 'idp.school.com',
            issuerAssignedId: 'de.mo',
            primaryRole: 'student',
            /** @phan-suppress-next-line PhanTypeMismatchArgument*/
            apiInstance: $educationApi,
        );
        $this->assertInstanceOf(EducationUser::class, $user);
        $this->assertSame("id", $user->getId());
        $this->assertSame("demo", $user->getDisplayName());
        $this->assertSame("mail@mail.com", $user->getMail());
        $this->assertSame("surname", $user->getSurname());
        $this->assertSame("givenname", $user->getGivenName());
        $this->assertSame("manager", $user->getPrimaryRole());
        $identities = $user->getidentities();
        $this->assertNotNull($identities);
        $this->assertCount(1, $identities);
        $this->assertContainsOnlyInstancesOf(ObjectIdentity::class, $identities);
        $this->assertSame("idp", $identities[0]->getIssuer());
        $this->assertSame("idpId", $identities[0]->getIssuerAssignedId());
    }

    /**
     * @dataProvider educationUserData
     *
     * @param array<int, array<int, array<string, array<int, string>|string>>> $data
     * @return void
     */
    public function testGetEducationUserWithOutToken(array $data)
    {
        $educationApi = $this->createMock(EducationUserApi::class);
        $this->expectExceptionMessage(
            "This function cannot be used because no authentication token was provided for the educationUser endpoints.",
        );
        $educationApi->method("createEducationUser")->willReturn(
            new EU($data),
        );
        $ocis = new Ocis(serviceUrl: 'a', accessToken: 'a');
        $user = $ocis->createEducationUser(displayName: 'demo', onPremisesSAMAccountName: 'demo', issuer: 'idp.school.com', issuerAssignedId: 'de.mo', primaryRole: 'student');
        $this->expectException(InvalidResponseException::class);
    }

    /**
     * @dataProvider educationUserData
     *
     * @param array<int, array<int, array<string, array<int, string>|string>>> $data
     * @return void
     */
    public function testListEducationUserWithOutToken(array $data)
    {
        $educationApi = $this->createMock(EducationUserApi::class);
        $this->expectExceptionMessage(
            "This function cannot be used because no authentication token was provided for the educationUser endpoints.",
        );
        $educationApi->method("createEducationUser")->willReturn(
            new EU($data),
        );
        $ocis = new Ocis(serviceUrl: 'a', accessToken: 'a');
        $user = $ocis->createEducationUser(displayName: 'demo', onPremisesSAMAccountName: 'demo', issuer: 'idp.school.com', issuerAssignedId: 'de.mo', primaryRole: 'student');
        $this->expectException(InvalidResponseException::class);
    }

    /**
     * @dataProvider educationUserData
     *
     * @param array<int, array<int, array<string, array<int, string>|string>>> $data
     * @return void
     */
    public function testCreateEducationUserWithoutToken(array $data)
    {
        $educationApi = $this->createMock(EducationUserApi::class);
        $this->expectExceptionMessage(
            "This function cannot be used because no authentication token was provided for the educationUser endpoints.",
        );
        $educationApi->method("createEducationUser")->willReturn(
            new EU($data),
        );
        $ocis = new Ocis(serviceUrl: 'a', accessToken: 'a');
        $user = $ocis->createEducationUser(displayName: 'demo', onPremisesSAMAccountName: 'demo', issuer: 'idp.school.com', issuerAssignedId: 'de.mo', primaryRole: 'student');
        $this->expectException(InvalidResponseException::class);
    }
}
