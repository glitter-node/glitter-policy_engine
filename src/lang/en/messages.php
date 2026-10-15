<?php

return [
    'policy' => [
        'fetch_success' => 'Policies retrieved.',
        'fetch_failed' => 'Policies could not be retrieved.',
        'create_success' => 'Policy created.',
        'create_failed' => 'Policy could not be created.',
        'update_success' => 'Policy metadata updated.',
        'update_failed' => 'Policy metadata could not be updated.',
        'publish_success' => 'Policy published.',
        'publish_failed' => 'Policy could not be published.',
        'not_found' => 'Policy not found.',
    ],
    'draft' => [
        'update_success' => 'Policy draft saved.',
        'update_failed' => 'Policy draft could not be saved.',
        'conflict' => 'The draft changed elsewhere. Refresh and try again.',
        'validation_complete' => 'Policy draft validation completed.',
        'validation_failed' => 'Policy draft could not be validated.',
        'invalid' => 'The policy draft structure is invalid.',
    ],
    'evaluation' => [
        'fetch_success' => 'Evaluation history retrieved.',
        'complete' => 'Policy evaluation completed.',
        'failed' => 'Policy evaluation could not be completed.',
    ],
];
