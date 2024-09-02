<?php

namespace Owncloud\OcisPhpSdk;

use Owncloud\OcisPhpSdk\BaseUser;
use OpenAPI\Client\Model\EducationUser as EducationUserModel;

class EducationUser extends BaseUser
{
    private ?string $primaryRole;

    public function __construct(EducationUserModel $user)
    {
        parent::__construct($user);
        $this->primaryRole = $user->getPrimaryRole();
    }

    /**
     * Get the value of primaryRole
     */
    public function getPrimaryRole(): string|null
    {
        return $this->primaryRole;
    }
}
