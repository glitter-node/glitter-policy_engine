<?php

namespace Modules\Glitter\PolicyEngine;

use App\Extension\AbstractModule;
use Modules\Glitter\PolicyEngine\Listeners\PolicyActivityLogListener;

class Module extends AbstractModule
{
    public function getPermissions(): array
    {
        return [
            'name' => ['ko' => 'Glitter 정책 엔진', 'en' => 'Glitter Policy Engine'],
            'description' => ['ko' => '정책과 평가 이력 관리 권한', 'en' => 'Policy and evaluation history permissions'],
            'categories' => [
                [
                    'identifier' => 'policies',
                    'name' => ['ko' => '정책 관리', 'en' => 'Policy Management'],
                    'description' => ['ko' => '정책 레지스트리와 게시 관리', 'en' => 'Manage the policy registry and publication'],
                    'permissions' => [
                        [
                            'action' => 'read',
                            'name' => ['ko' => '정책 조회', 'en' => 'Read Policies'],
                            'description' => ['ko' => '정책 목록과 상세 정보를 조회', 'en' => 'View policy lists and details'],
                            'type' => 'admin',
                            'roles' => ['admin'],
                        ],
                        [
                            'action' => 'create',
                            'name' => ['ko' => '정책 생성', 'en' => 'Create Policies'],
                            'description' => ['ko' => '새 정책과 초안을 생성', 'en' => 'Create new policies and drafts'],
                            'type' => 'admin',
                            'roles' => ['admin'],
                        ],
                        [
                            'action' => 'update',
                            'name' => ['ko' => '정책 초안 수정', 'en' => 'Update Policy Drafts'],
                            'description' => ['ko' => '정책 초안과 메타데이터를 수정', 'en' => 'Update policy drafts and metadata'],
                            'type' => 'admin',
                            'roles' => ['admin'],
                        ],
                        [
                            'action' => 'publish',
                            'name' => ['ko' => '정책 게시', 'en' => 'Publish Policies'],
                            'description' => ['ko' => '검증된 정책 revision을 게시', 'en' => 'Publish validated policy revisions'],
                            'type' => 'admin',
                            'roles' => ['admin'],
                        ],
                    ],
                ],
                [
                    'identifier' => 'evaluations',
                    'name' => ['ko' => '정책 평가', 'en' => 'Policy Evaluations'],
                    'description' => ['ko' => '평가 시뮬레이션과 이력 조회', 'en' => 'Simulate evaluations and read history'],
                    'permissions' => [
                        [
                            'action' => 'read',
                            'name' => ['ko' => '평가 이력 조회', 'en' => 'Read Evaluation History'],
                            'description' => ['ko' => '저장된 정책 평가 이력을 조회', 'en' => 'View stored policy evaluation history'],
                            'type' => 'admin',
                            'roles' => ['admin'],
                        ],
                        [
                            'action' => 'simulate',
                            'name' => ['ko' => '정책 평가 시뮬레이션', 'en' => 'Simulate Evaluations'],
                            'description' => ['ko' => '게시된 정책을 입력값으로 시뮬레이션', 'en' => 'Simulate published policies with input data'],
                            'type' => 'admin',
                            'roles' => ['admin'],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function getAdminMenus(): array
    {
        return [[
            'name' => ['ko' => '정책 엔진', 'en' => 'Policy Engine'],
            'slug' => 'glitter-policy-engine',
            'url' => null,
            'icon' => 'fas fa-shield-halved',
            'order' => 70,
            'permission' => 'glitter-policy_engine.policies.read',
            'children' => [
                [
                    'name' => ['ko' => '정책', 'en' => 'Policies'],
                    'slug' => 'glitter-policy-engine-policies',
                    'url' => '/admin/policy-engine/policies',
                    'icon' => 'fas fa-list-check',
                    'order' => 1,
                    'permission' => 'glitter-policy_engine.policies.read',
                ],
                [
                    'name' => ['ko' => '평가 시뮬레이터', 'en' => 'Evaluation Simulator'],
                    'slug' => 'glitter-policy-engine-simulator',
                    'url' => '/admin/policy-engine/simulator',
                    'icon' => 'fas fa-flask',
                    'order' => 2,
                    'permission' => 'glitter-policy_engine.evaluations.simulate',
                ],
                [
                    'name' => ['ko' => '평가 이력', 'en' => 'Evaluation History'],
                    'slug' => 'glitter-policy-engine-history',
                    'url' => '/admin/policy-engine/evaluations',
                    'icon' => 'fas fa-clock-rotate-left',
                    'order' => 3,
                    'permission' => 'glitter-policy_engine.evaluations.read',
                ],
            ],
        ]];
    }

    public function getHookListeners(): array
    {
        return [PolicyActivityLogListener::class];
    }

    public function getMetadata(): array
    {
        return [
            'author' => 'Glitter.kr',
            'copyright' => 'Copyright (c) 2026 Glitter.kr',
            'license' => 'MIT',
        ];
    }
}
