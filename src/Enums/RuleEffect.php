<?php

namespace Modules\Glitter\PolicyEngine\Enums;

enum RuleEffect: string
{
    case Allow = 'allow';
    case Deny = 'deny';
}
