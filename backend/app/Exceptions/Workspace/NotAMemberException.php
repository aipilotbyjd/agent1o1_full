<?php

namespace App\Exceptions\Workspace;

use App\Exceptions\ApiException;

class NotAMemberException extends ApiException
{
    public function __construct()
    {
        parent::__construct('You are not a member of this workspace.', 403);
    }
}
