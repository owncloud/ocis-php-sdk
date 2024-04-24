<?php

namespace unit\Owncloud\OcisPhpSdk;

use OpenAPI\Client\Model\Group;
use Owncloud\OcisPhpSdk\User as SdkUser;
use OpenAPI\Client\Model\User;
use Owncloud\OcisPhpSdk\Exception\InvalidResponseException;
use Owncloud\OcisPhpSdk\Group as SdkGroup;
use PHPUnit\Framework\TestCase;

class GroupTest extends TestCase
{
    /**
     * @return array<int, array<int, array<string, array<int, string>|string>>>
     */
    public static function validGroupData(): array
    {
        return [
            [[
                "id" => "id",
                "description" => "desc",
                "display_name" => "displayname",
                "group_types" => ["aa"],
                "members" => [],
            ]],
            [[ // group_type is empty
                "id" => "id",
                "description" => "desc",
                "display_name" => "displayname",
                "group_types" => [],
                "members" => [],
            ]],
        ];
    }
    /**
     * @dataProvider validGroupData
     *
     * @param array<string,string|array<int,string>> $data
     * @return void
     */
    public function testListGroup(array $data)
    {
        $libGroup = new Group($data);
        $accessToken = "acstok";
        $group = new SdkGroup($libGroup, "url", [], $accessToken);
        $this->assertInstanceOf(SdkGroup::class, $group);
        $this->assertSame($data["id"], $group->getId());
        $this->assertSame($data["description"], $group->getDescription());
        $this->assertSame($data["display_name"], $group->getDisplayName());
        $this->assertSame($data["group_types"], $group->getGroupTypes());
        $this->assertSame($data["members"], $group->getMembers());
    }
    /**
     * @return array<int, array<int, array<string,array<int, string>|string|null>|string>>
     *
     *      [
     *          [
     *              "id" => 'id', // id of the group
     *              "description" => "description", // description of the group
     *              "display_name" => "display name", // display name of the group
     *              "group_types" => ["type"], // group type of a group
     *              "members" => [$user1,$user2], // list of members in the group
     *          ], "id" // key of the id of the mock data
     *          , "id" // key in the error message
     *      ],
     *
     */
    public static function invalidGroupData(): array
    {
        return [
            [[ // id is null
                "id" => null,
                "description" => "",
                "display_name" => "asd",
                "group_types" => ["aa"],
                "members" => [],
            ], "id", "id"],
            [[ // id is empty string
                "id" => "",
                "description" => "a",
                "display_name" => "as",
                "group_types" => ["aa"],
                "members" => [],
            ], "id", "id"],
            [[ // display_name is null
                "id" => "as",
                "description" => "a",
                "display_name" => null,
                "group_types" => ["aa"],
                "members" => [],
            ], "display_name", "displayName"],
            [[ //display_name is empty string
                "id" => "sad",
                "description" => "a",
                "display_name" => "",
                "group_types" => ["aa"],
                "members" => [],
            ], "display_name", "displayName"],
        ];
    }

    /**
     * @dataProvider invalidGroupData
     *
     * @param array<string,string|array<int,string>> $data
     * @param string $key
     * @param string $errorMsg
     * @return void
     */
    public function testInvalidDataInListGroup(array $data, string $key, string $errorMsg)
    {
        $this->expectExceptionMessage("Invalid $errorMsg returned for group '" . print_r($data[$key], true));
        $libGroup = new Group($data);
        $accessToken = "acstok";
        $this->expectException(InvalidResponseException::class);
        $group = new SdkGroup($libGroup, "url", [], $accessToken);
        $group->getId();
        $group->getDisplayName();
        $group->getMembers();
    }

    public function testSetMembers(): void
    {
        $libGroup = new Group(
            [
                "id" => "as",
                "description" => "a",
                "display_name" => "name",
                "group_types" => ["aa"],
                "members" => [],
            ]
        );
        $accessToken = "acstok";
        $group = new SdkGroup($libGroup, "url", [], $accessToken);
        $this->assertCount(0, $group->getMembers());
        $group->setMembers(
            new SdkUser(
                new User(
                    [
                        "id" => "id",
                        "display_name" => "displayname",
                        "mail" => "mail@mail.com",
                        "on_premises_sam_account_name" => "sd",
                    ],
                )
            )
        );
        $this->assertCount(1, $group->getMembers());
    }
}
