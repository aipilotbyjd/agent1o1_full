<?php

namespace App\Engine\Contracts;

use App\Engine\Execution\Suspension;
use App\Engine\Execution\NodePayload;

interface SuspendsExecution
{
    /**
     * Determine whether the node should suspend and return suspension details.
     */
    public function suspend(NodePayload $payload): Suspension;
}
