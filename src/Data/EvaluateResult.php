<?php

namespace Modules\Glitter\PolicyEngine\Data;

use Modules\Glitter\PolicyEngine\Enums\Decision;

final readonly class EvaluateResult
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public Decision $decision,
        public string $reasonCode,
        public ?int $policyVersion,
        public string $evaluationId,
        public array $metadata = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'decision' => $this->decision->value,
            'reason_code' => $this->reasonCode,
            'policy_version' => $this->policyVersion,
            'evaluation_id' => $this->evaluationId,
            'metadata' => $this->metadata,
            'blocks_execution' => $this->decision->blocksExecution(),
        ];
    }
}
