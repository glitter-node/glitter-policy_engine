<?php

namespace Modules\Glitter\PolicyEngine\Contracts;

use Modules\Glitter\PolicyEngine\Data\EvaluateRequest;
use Modules\Glitter\PolicyEngine\Data\EvaluateResult;

interface PolicyEvaluatorInterface
{
    public function evaluate(EvaluateRequest $request): EvaluateResult;
}
