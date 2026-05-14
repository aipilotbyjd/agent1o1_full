<?php

namespace App\Exceptions\Role;

use App\Exceptions\ApiException;

class CannotDeleteRoleWithMembersException extends ApiException
{
    public function __construct()
    {
        parent::__construct('Cannot delete a role that still has members assigned to it.', 422);
    }
}
