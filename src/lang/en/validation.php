<?php

return [
    'evaluation' => [
        'object' => 'The :attribute field must be a JSON object.',
        'invalid_json' => 'The request body must contain valid JSON.',
    ],
    'definition' => [
        'array' => 'The policy definition must be a JSON object.',
        'bounds' => 'The policy definition exceeds the allowed size or structural limits.',
        'invalid' => 'The policy definition does not satisfy the Rule DSL structure or limits.',
        'shape' => 'The policy definition contains a JSON object or list in an unsupported location.',
    ],
];
