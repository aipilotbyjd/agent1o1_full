<?php

namespace App\Contracts;

use App\Engine\ExecutionPause;
use App\Engine\NodeInput;

interface Suspendable
{
    public function suspend(NodeInput $input): ExecutionPause;
}
