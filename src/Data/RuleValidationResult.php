<?php

namespace Modules\Glitter\PolicyEngine\Data;

final readonly class RuleValidationResult
{
    /** @param array<int, array{path: string, code: string}> $errors */
    public function __construct(
        public bool $valid,
        public array $errors,
        public int $ruleCount,
        public int $conditionCount,
        public int $maxDepth,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'valid' => $this->valid,
            'errors' => $this->errors,
            'metrics' => [
                'rule_count' => $this->ruleCount,
                'condition_count' => $this->conditionCount,
                'max_depth' => $this->maxDepth,
            ],
        ];
    }
}
