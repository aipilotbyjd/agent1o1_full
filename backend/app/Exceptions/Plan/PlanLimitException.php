<?php

namespace App\Exceptions\Plan;

use App\Exceptions\ApiException;

class PlanLimitException extends ApiException
{
    public function __construct(string $message = 'Your current plan does not support this.')
    {
        parent::__construct($message, 402);
    }
}
