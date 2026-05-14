<?php

namespace App\Exceptions\Role;

use App\Exceptions\ApiException;

class CannotDeleteSystemRoleException extends ApiException
{
    public function __construct()
    {
        parent::__construct('System roles cannot be deleted.', 422);
    }
}
