<?php

namespace unit\Owncloud\OcisPhpSdk;

use OpenAPI\Client\Model\User;
use Owncloud\OcisPhpSdk\Exception\InvalidResponseException;
use Owncloud\OcisPhpSdk\User as SdkUser;
use PHPUnit\Framework\TestCase;
use OpenAPI\Client\Model\ObjectIdentity;

class UserTest extends TestCase
{
    /**
     * @return array<int,array<int,array<string,array<int,ObjectIdentity>|string>>>
     */
    public static function validUserData(): array
    {
        return [
            [
                [
                    "id" => "id",
                    "display_name" => "displayname",
                    "mail" => "mail@mail.com",
                    "on_premises_sam_account_name" => "sd",
                    "surname" => "ds",
                    "given_name" => "ds",
                    "identities" => [new ObjectIdentity(['issuer' => "idp", "issuer_assigned_id" => 'idpId'])],
                ],
            ],
            [
                [
                    "id" => "0",
                    "display_name" => "0",
                    "mail" => "0@mail.com",
                    "on_premises_sam_account_name" => "sd",
                    "surname" => "ds",
                    "given_name" => "ds",
                    "identities" => [new ObjectIdentity(['issuer' => "idp", "issuer_assigned_id" => 'idpId'])],
                ],
            ],
        ];
    }

    /**
     * @dataProvider validUserData
     * @param array<string,string|array<int,string>> $data
     * @return void
     */
    public function testUserClass(array $data)
    {
        $libUser = new User($data);
        $user = new SdkUser($libUser);
        $this->assertInstanceOf(SdkUser::class, $user);
        $this->assertSame($data["id"], $user->getId());
        $this->assertSame($data["display_name"], $user->getDisplayName());
        $this->assertSame($data["mail"], $user->getMail());
        $this->assertSame($data["on_premises_sam_account_name"], $user->getOnPremisesSamAccountName());
        $this->assertSame($data["identities"], $user->getIdentities());
    }
    /**
     * @return array<int, array<int, array<string, string|null>>>
     */
    public static function invalidUserData(): array
    {
        return [

            [[
                "id" => null,
                "display_name" => "name",
                "mail" => "a@a.au",
                "on_premises_sam_account_name" => "acc",
                "key" => "id",
                "errorKey" => "id",
            ]], [[
                "id" => "",
                "display_name" => "name",
                "mail" => "a@a.au",
                "on_premises_sam_account_name" => "acc",
                "key" => "id",
                "errorKey" => "id",
            ]], [[
                "id" => "id",
                "display_name" => null,
                "mail" => "a@a.au",
                "on_premises_sam_account_name" => "aa",
                "key" => "display_name",
                "errorKey" => "displayName",
            ]], [[
                "id" => "id",
                "display_name" => "",
                "mail" => "a@a.au",
                "on_premises_sam_account_name" => "aa",
                "key" => "display_name",
                "errorKey" => "displayName",
            ]], [[
                "id" => "id",
                "display_name" => "name",
                "mail" => null,
                "on_premises_sam_account_name" => "aa",
                "key" => "mail",
                "errorKey" => "mail",
            ]], [[
                "id" => "id",
                "display_name" => "name",
                "mail" => "",
                "on_premises_sam_account_name" => "aa",
                "key" => "mail",
                "errorKey" => "mail",
            ]],
        ];
    }

    /**
     * @dataProvider invalidUserData
     * @param array<string, string|null> $data
     * @return void
     */
    public function testInvalidUserData(array $data)
    {
        $this->expectException(InvalidResponseException::class);
        $errorKey = $data["errorKey"];
        $this->expectExceptionMessage(
            "Invalid $errorKey returned for user '" . print_r($data[$data["key"] ?? ""], true) . "'",
        );
        $libUser = new User(
            [
                "id" => $data["id"],
                "display_name" => $data["display_name"],
                "mail" => $data["mail"],
                "on_premises_sam_account_name" => $data["on_premises_sam_account_name"],
            ],
        );
        $user = new SdkUser($libUser);
        $user->getDisplayName();
        $user->getMail();
        $user->getId();
    }

}
