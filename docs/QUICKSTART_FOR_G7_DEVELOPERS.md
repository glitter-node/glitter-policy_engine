## 개요 — G7 개발자 15분 빠른 시작

> **대상:** GnuBoard7 Official 7.1.0+ 모듈 개발자  
> **기준:** `glitter-policy_engine` v0.2.0 / PHP 8.3+ / Laravel 12  
> **목표:** 정책 하나를 관리자에서 게시하고, 내 G7 모듈에서 `ALLOW` / `DENY` 판정을 받아 안전하게 처리하기

이 문서는 **이미 설치·활성화된** Glitter Policy Engine을 사용하는 가이드입니다. 설치 안내와 전체 DSL 사양은 [README](../README.md)를 참고하세요. 예상 소요 시간은 환경이 준비된 경우의 목표치이며, 실제 소요 시간은 G7 권한 설정과 개발 환경에 따라 달라집니다.

## 0. 시작 전 확인 (1분)

- G7 관리자에서 정책 생성·수정·게시 권한이 있는지 확인합니다. 시뮬레이터에는 `glitter-policy_engine.policies.read` 및 `glitter-policy_engine.evaluations.simulate` 권한이 필요합니다.
- 정책 엔진 모듈이 **활성화**되어 있고 DB Migration이 적용되어 있어야 합니다.
- 연동할 G7 모듈의 PHP 코드에서 Laravel 의존성 주입(DI)을 사용할 수 있어야 합니다.
- 이 예시는 `community`라는 **예시 소유 확장**을 사용합니다. 실제 연동 시 자신의 모듈 식별자로 바꾸고, 관리자 설정과 PHP 코드에서 같은 값을 사용하세요.

**중요:** 정책 엔진은 G7 인증·Role·Permission을 대체하지 않습니다. 기존 접근 제어를 통과한 후 업무 규칙을 **추가 판정**하는 용도로 사용합니다.

## 1. 관리자에서 정책 생성 (2분)

관리자 정책 생성 화면으로 이동합니다.

`/admin/policy-engine/policies/create`

다음과 같이 정책 식별 정보를 입력합니다. 실제 화면의 표시명은 번역/관리자 레이아웃에 따라 다를 수 있습니다.

| 항목                          | 예시                         | 의미                         |
| ----------------------------- | ---------------------------- | ---------------------------- |
| 소유 확장 (`owner_extension`) | `community`                  | 정책을 사용하는 확장 식별자  |
| 정책 키 (`policy_key`)        | `post.action`                | 해당 확장에서 정책을 찾는 키 |
| 설명                          | `게시글 읽기·삭제 정책 예제` | 관리자용 설명                |

생성 후 해당 정책의 **Draft 편집 화면**으로 이동합니다. 이 예시는 읽기를 허용하고 삭제를 거부하며, 그 밖의 동작은 일치 규칙이 없어 거부합니다.

## 2. Draft JSON DSL 입력 (2분)

다음 JSON을 Draft 정의에 입력하고 저장합니다.

```json
{
  "schema_version": "1.0",
  "fields": {
    "action": "string"
  },
  "rules": [
    {
      "id": "deny-delete",
      "effect": "deny",
      "when": {
        "field": "action",
        "operator": "eq",
        "value": "delete"
      }
    },
    {
      "id": "allow-read",
      "effect": "allow",
      "when": {
        "field": "action",
        "operator": "eq",
        "value": "read"
      }
    }
  ]
}
```

- `fields`는 평가 입력에서 참조할 필드를 선언합니다. `action`은 문자열입니다.
- `rules[].effect`는 `allow` 또는 `deny`입니다.
- `when`은 조건입니다. 여기서는 `action` 문자열을 `eq`로 비교합니다.
- `DENY` 규칙이 일치하면 `ALLOW`보다 우선합니다. 아무 규칙도 일치하지 않으면 `DENY`입니다.

실제 개인정보·비밀번호·토큰을 정책 상수에 넣지 마세요. 정책 정의는 영구 저장됩니다.

## 3. 검증 후 게시 (2분)

1. Draft 화면에서 **검증(Validate)**을 실행합니다.
2. 검증 성공을 확인합니다. 실패했다면 오류 경로와 코드를 보고 JSON을 수정하세요.
3. **게시(Publish)**를 실행합니다.
4. 게시된 Revision 버전(첫 게시라면 보통 `1`)을 확인합니다.

**Draft 저장만으로는 평가에 적용되지 않습니다.** 반드시 게시된 Revision이 있어야 합니다. 이후 Draft를 변경해도 재게시 전에는 기존 게시 Revision으로 평가합니다.

## 4. 관리자 평가 시뮬레이터로 확인 (2분)

`/admin/policy-engine/simulator`로 이동해 방금 게시한 `community` / `post.action` 정책을 선택합니다.

| 입력          | 값     |
| ------------- | ------ |
| Action        | `read` |
| Subject JSON  | `{}`   |
| Resource JSON | `{}`   |
| Context JSON  | `{}`   |

평가 실행 후 결과가 **ALLOW**인지 확인합니다. Action을 `delete`로 바꾸면 **DENY**, `write`로 바꿔도 **DENY**여야 합니다.

| Action   | 기대 판정 | 기대 사유 코드       |
| -------- | --------- | -------------------- |
| `read`   | `allow`   | `allow_rule_matched` |
| `delete` | `deny`    | `deny_rule_matched`  |
| `write`  | `deny`    | `no_rule_matched`    |

화면의 판정 표기는 대문자일 수 있지만, PHP `Decision` enum의 값은 소문자(`allow`, `deny`, `indeterminate`)입니다. **`{}`는 JSON 객체이고 `[]`는 배열이므로**, Subject/Resource/Context 입력에 `[]`를 사용하지 마세요.

## 5. 내 G7 모듈 PHP 코드에서 호출 (3분)

G7 모듈의 **서비스 또는 컨트롤러**에서 다음 공개 계약을 사용합니다. 아래 코드는 `PolicyEvaluatorInterface`를 주입받는 서비스 메서드의 완전한 예시입니다. 클래스 이름과 네임스페이스는 자신의 모듈에 맞춰 바꾸세요.

```php
<?php

declare(strict_types=1);

namespace Modules\YourModule\Services;

use Modules\Glitter\PolicyEngine\Contracts\PolicyEvaluatorInterface;
use Modules\Glitter\PolicyEngine\Data\EvaluateRequest;
use Modules\Glitter\PolicyEngine\Enums\Decision;

final class PostPolicyGuard
{
    public function __construct(
        private readonly PolicyEvaluatorInterface $policyEvaluator,
    ) {}

    public function canPerform(string $action, string $userId, string $postId): bool
    {
        // 먼저 G7 인증/Permission 검사를 통과한 호출자만 이 메서드로 전달하세요.
        $result = $this->policyEvaluator->evaluate(new EvaluateRequest(
            ownerExtension: 'community',
            policyKey: 'post.action',
            subject: ['id' => $userId],
            resource: ['id' => $postId],
            action: $action,
            context: [],
        ));

        // fail closed: DENY와 INDETERMINATE는 모두 실행 불가
        return $result->decision === Decision::Allow;
    }
}
```

호출 예시(기존 G7 인증·권한 검사 **이후**):

```php
if (! $postPolicyGuard->canPerform('read', (string) $user->id, (string) $post->id)) {
    abort(403, '정책에 따라 작업이 허용되지 않습니다.');
}

// 여기서부터 실제 게시글 읽기 업무를 수행합니다.
```

**예제의 `community` / `post.action`은 1단계에서 생성한 값과 정확히 같아야 합니다.** `subject`·`resource`의 `id`는 이번 DSL에서는 평가 조건으로 사용하지 않지만, 추후 `fields`에 `subject.id` 등을 선언해 확장할 수 있습니다. 실제 업무에선 신뢰할 수 있는 서버 측 식별자만 전달하세요.

> 이 예제는 기존 모듈에 붙일 서비스 코드이며, G7 모듈 전체를 생성하는 템플릿은 아닙니다. 의존성 주입이 작동하려면 정책 엔진 모듈이 활성화되고 서비스 바인딩이 등록되어 있어야 합니다.

## 6. 판정과 장애를 안전하게 처리 (1분)

`evaluate()`의 반환형은 `EvaluateResult`이며 다음 공개 속성을 제공합니다.

| 속성                     | 의미                                                                    |
| ------------------------ | ----------------------------------------------------------------------- |
| `$result->decision`      | `Decision::Allow`, `Decision::Deny`, `Decision::Indeterminate`          |
| `$result->reasonCode`    | `allow_rule_matched`, `no_rule_matched`, `revision_integrity_failed` 등 |
| `$result->policyVersion` | 평가에 사용한 게시 Revision 번호 또는 `null`                            |
| `$result->evaluationId`  | 평가 추적용 UUID                                                        |
| `$result->metadata`      | 일치 규칙 및 이력 기록 여부 등 추가 정보                                |

**반드시 `Decision::Allow`일 때만 실제 업무를 진행**하세요. 정책 미존재/미게시 상태는 거부되며, 무결성 오류·평가 실패·평가 이력 기록 실패는 `INDETERMINATE`가 될 수 있습니다. `INDETERMINATE`를 허용으로 바꾸거나, 정책 엔진 장애 시 무조건 통과시키지 마세요.

참고로 `EvaluateResult::toArray()`에는 `blocks_execution` 필드도 포함되며 `allow`가 아닌 모든 판정에서 `true`입니다. 서비스 코드에서는 enum 비교가 명확합니다.

## 7. 변경 비교와 이력 확인 (1분)

- 정책 Draft를 수정했다면 정책 상세의 **Revision Diff**에서 게시 Revision과 Draft를 비교합니다.
- 변경을 적용하려면 다시 **Validate → Publish**합니다.
- `/admin/policy-engine/evaluations`에서 평가 이력과 판정 사유를 확인합니다.

평가 이력에는 Subject/Resource/Context 원문을 저장하지 않지만 `action` 및 정책 식별자 등 메타데이터는 저장됩니다. 이 값에 비밀정보를 넣지 마세요.

## 완료 체크리스트

- [ ] 관리자에서 `community` / `post.action` 정책 생성
- [ ] JSON DSL Draft 저장 및 검증 통과
- [ ] 게시 Revision 생성 확인
- [ ] 시뮬레이터에서 `read → ALLOW`, `delete → DENY`, `write → DENY` 확인
- [ ] 내 G7 모듈 서비스에 `PolicyEvaluatorInterface` 주입
- [ ] `Decision::Allow`일 때만 업무 실행하도록 연결
- [ ] 평가 이력에서 실행 기록 확인

## 자주 막히는 부분

| 증상                              | 먼저 확인할 사항                                             |
| --------------------------------- | ------------------------------------------------------------ |
| 정책이 시뮬레이터 목록에 없음     | 정책이 게시되었는지, `policies.read` 권한이 있는지           |
| `policy_not_found`                | `ownerExtension`과 `policyKey`가 관리자 설정과 같은지        |
| `published_revision_not_found`    | Draft만 저장하고 게시하지 않았는지                           |
| `no_rule_matched`                 | Action 값과 `when.value`가 일치하는지                        |
| `revision_integrity_failed`       | 게시 Revision 해시/스키마 상태; 임의 DB 수정을 중단하고 진단 |
| `evaluation_history_write_failed` | 이력 DB 저장 오류; 성공으로 처리하지 말 것                   |
| DI 해석 실패                      | 모듈 활성화, 서비스 프로바이더 등록, 의존성 확인             |
| JSON 입력 오류                    | `{}` 객체를 사용했는지 (`[]`는 불가)                         |

## 연동 범위와 참고 자료

이 가이드는 **정책 생성부터 PHP 서비스에서 평가 결과를 받아 안전하게 분기하는 최소 통합**을 다룹니다. 실제 컨트롤러 연결, 권한 부여, 테스트 DB 구성, 배포·Migration·Rollback은 각 G7 모듈의 운영 절차에 맞게 별도 검증해야 합니다. 예제의 예상 판정은 v0.2.0 구현 계약을 기준으로 하며, 새 배포 환경에서의 통합 실행 결과를 보증하지 않습니다.

- [Glitter Policy Engine README](../README.md)
- [GitHub Repository](https://github.com/glitter-node/glitter-policy_engine)
- [v0.2.0 Release](https://github.com/glitter-node/glitter-policy_engine/releases/tag/v0.2.0)
