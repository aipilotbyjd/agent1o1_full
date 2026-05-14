<?php

namespace App\Exceptions\Workspace;

use App\Exceptions\ApiException;

class MemberLimitReachedException extends ApiException
{
    public function __construct()
    {
        parent::__construct('Workspace member limit reached. Upgrade your plan to add more members.', 402);
    }
}
