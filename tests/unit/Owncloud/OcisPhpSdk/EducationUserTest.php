<?php

namespace unit\Owncloud\OcisPhpSdk;

use Owncloud\OcisPhpSdk\EducationUser;
use OpenAPI\Client\Model\EducationUser as EU;
use Owncloud\OcisPhpSdk\Ocis;
use PHPUnit\Framework\TestCase;

class EducationUserTest extends TestCase
{
    /**
     * @return void
     */
    public function testgetEducationUsers()
    {
        $ocis = $this->createMock(Ocis::class);
        $ocis->method("getEducationUsers")->willReturn([
            new EducationUser(new EU([
                "id" => "id",
                "display_name" => "displayname",
                "mail" => "mail@mail.com",
                "on_premises_sam_account_name" => "sd",
            ])),
        ]);
        /** @phan-suppress-next-line PhanUndeclaredMethod */
        $user = $ocis->getEducationUsers();
        $this->assertCount(1, $user);
        $this->assertContainsOnlyInstancesOf(EducationUser::class, $user);
    }

    /**
     * @return void
     */
    public function testListEducationUsers()
    {
        $ocis = $this->createMock(Ocis::class);
        /** @phan-suppress-next-line PhanUndeclaredMethod */
        $user = $ocis->getEducationUserById("user-id");
        $this->assertInstanceOf(EducationUser::class, $user);
    }
}
