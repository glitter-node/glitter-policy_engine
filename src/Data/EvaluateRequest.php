<?php

namespace Modules\Glitter\PolicyEngine\Data;

final readonly class EvaluateRequest
{
    /**
     * @param  array<string, mixed>  $subject
     * @param  array<string, mixed>  $resource
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public string $ownerExtension,
        public string $policyKey,
        public array $subject,
        public array $resource,
        public string $action,
        public array $context = [],
    ) {}
}
