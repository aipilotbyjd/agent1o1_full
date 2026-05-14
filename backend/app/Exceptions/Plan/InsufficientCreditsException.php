<?php

namespace App\Exceptions\Plan;

use App\Exceptions\ApiException;

class InsufficientCreditsException extends ApiException
{
    public function __construct(string $message = 'Insufficient credits to perform this action.')
    {
        parent::__construct($message, 402);
    }
}
