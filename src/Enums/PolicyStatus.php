<?php

namespace Modules\Glitter\PolicyEngine\Enums;

enum PolicyStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Disabled = 'disabled';
}
