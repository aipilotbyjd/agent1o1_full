<?php

namespace App\Exceptions\Plan;

use App\Exceptions\ApiException;

class QuotaExceededException extends ApiException
{
    public function __construct(string $resource = 'Usage quota')
    {
        parent::__construct("{$resource} exceeded for your current billing period.", 429);
    }
}
