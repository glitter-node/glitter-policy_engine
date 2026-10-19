<?php

return [
    'evaluation' => [
        'object' => ':attribute 필드는 JSON 객체여야 합니다.',
        'invalid_json' => '요청 본문은 올바른 JSON이어야 합니다.',
    ],
    'definition' => [
        'array' => '정책 정의는 JSON 객체여야 합니다.',
        'bounds' => '정책 정의가 허용된 크기 또는 구조 제한을 초과했습니다.',
        'invalid' => '정책 정의가 Rule DSL 구조 또는 제한을 충족하지 않습니다.',
        'shape' => '정책 정의에 허용되지 않는 JSON 객체 또는 리스트 구조가 포함되어 있습니다.',
    ],
];
