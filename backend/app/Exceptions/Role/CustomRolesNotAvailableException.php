<?php

namespace App\Exceptions\Role;

use App\Exceptions\ApiException;

class CustomRolesNotAvailableException extends ApiException
{
    public function __construct()
    {
        parent::__construct('Custom roles are not available on your current plan.', 402);
    }
}
