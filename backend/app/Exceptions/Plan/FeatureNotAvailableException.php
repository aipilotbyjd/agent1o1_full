<?php

namespace App\Exceptions\Plan;

use App\Exceptions\ApiException;

class FeatureNotAvailableException extends ApiException
{
    public function __construct(string $feature = 'This feature')
    {
        parent::__construct("{$feature} is not available on your current plan.", 402);
    }
}
