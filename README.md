# Glitter Policy Engine

GnuBoard7 Custom Module인 `glitter-policy_engine`은 업무 정책을 JSON DSL로 저장하고
결정적으로 평가합니다. 이 모듈은 G7의 인증·Role·Permission을 대체하지 않으며, 호출자가
G7 접근 제어를 통과한 뒤 업무 규칙을 추가로 판정하는 용도로 사용합니다.

- Module ID: `glitter-policy_engine`
- Version: `0.1.0`
- Vendor / Author: `Glitter.kr`
- License: MIT

변경 내역은 [CHANGELOG](CHANGELOG.md)에서 확인합니다. 버전 제목에는 날짜를 기록하지 않으며,
릴리스 날짜는 Git Tag 또는 GitHub Release 메타데이터로 관리합니다.

## 요구 환경

- GnuBoard7 7.1.0 이상 (`g7_version: >=7.1.0`)
- PHP 8.3 이상
- Laravel 12.x
- MySQL 8.0+ 또는 MariaDB 10.3+

검증 환경은 PHP 8.3.30, Laravel 12.69.3, G7 7.1.0, MariaDB 테스트 DB입니다.

## 주요 기능

- Policy Registry: 정책 키, 설명, 소유 확장, 상태 관리
- Draft 및 Optimistic Locking
- 게시 Revision과 definition hash
- JSON DSL 구조·복잡도 검증
- 결정적 Policy Evaluator
- Deny Overrides와 Fail Closed
- Evaluation History
- 관리자 API와 G7 Layout
- 세분화된 관리자 Permission
- Activity Log 기록
- G7 Module Install / Activate / Deactivate lifecycle 연동

## 설치 및 활성화

번들 소스가 G7 프로젝트의 `modules/_bundled/glitter-policy_engine`에 준비된 상태에서 다음
공식 CLI를 사용합니다. 설치·업데이트·활성화는 Migration, Permission, Menu, Layout 및 Route를
변경하는 운영 작업입니다. 운영 환경에서는 데이터베이스와 파일을 백업하고 호환성 및 변경 내용을
사전 검토한 뒤 실행하십시오.

```bash
/usr/local/bin/php83 artisan module:install glitter-policy_engine --vendor-mode=bundled
/usr/local/bin/php83 artisan module:activate glitter-policy_engine
```

설치 명령은 Migration, Permission, Menu를 등록하고, 활성화 명령은 등록 가능한 관리자
Layout과 Route를 활성화합니다. 비활성화 시 Module Route와 Layout 등록이 비활성화되며
정책 데이터는 삭제하지 않습니다.

이미 설치된 Bundled 모듈의 같은 버전 소스를 다시 반영해야 할 때는 변경 내용과 Layout 덮어쓰기
영향을 먼저 확인한 뒤 다음 업데이트 계약을 사용합니다.

```bash
/usr/local/bin/php83 artisan module:update glitter-policy_engine --source=bundled --layout-strategy=overwrite
```

소스 디렉터리나 활성 모듈 디렉터리를 직접 복사·수정하지 마십시오. Lifecycle 명령이 Layout 및
확장 캐시 무효화를 함께 처리하며, 브라우저에는 업데이트 후 새로 등록된 Layout과 번역이 제공되어야
합니다.

## 관리자 화면

활성화 후 관리자 화면은 다음 경로에서 접근합니다.

- 정책 목록: `/admin/policy-engine/policies`
- 정책 생성: `/admin/policy-engine/policies/create`
- 정책 편집: `/admin/policy-engine/policies/{id}`
- 평가 시뮬레이터: `/admin/policy-engine/simulator`
- 평가 이력: `/admin/policy-engine/evaluations`

평가 시뮬레이터는 `policies.read` 권한으로 조회한 게시 정책 중 게시 Revision이 있는
항목만 선택 목록에 표시합니다. 검색어를 입력하면 기존 정책 목록 API의 서버 측 검색을
사용하며, 선택한 정책의 소유 확장·정책 키·게시 버전을 평가 대상에 자동 반영합니다.
`Action`은 정책 키와 별도의 평가 입력입니다. Subject, Resource, Context는 JSON 객체로
입력하고 JSON 문법 검사와 2칸 들여쓰기 정렬을 사용할 수 있습니다.

평가 결과는 ALLOW, DENY, INDETERMINATE를 텍스트와 상태 기호로 구분하고 판정 사유,
실행 차단 여부, 게시 버전, 평가 ID, 규칙 일치 정보, 소요 시간, 이력 기록 여부를
표시합니다. 원본 API의 `duration_us`는 화면에서만 밀리초로 변환하며, 이력이 기록된
경우 정책 키와 소유 확장으로 평가 이력 화면에 연결합니다. 정책이 게시되지 않았거나
권한이 없으면 서버 계약에 따라 평가를 차단합니다.

관리자 API 기본 경로는 `/api/modules/glitter-policy_engine/admin`입니다. 모든 API는
Sanctum 인증과 G7 관리자 Permission을 요구합니다.

## Permission

| Permission | 설명 |
|---|---|
| `glitter-policy_engine.policies.read` | 정책 목록과 상세 정보 조회 |
| `glitter-policy_engine.policies.create` | 정책과 초안 생성 |
| `glitter-policy_engine.policies.update` | 정책 초안과 메타데이터 수정 |
| `glitter-policy_engine.policies.publish` | 검증된 Revision 게시 |
| `glitter-policy_engine.evaluations.read` | 평가 이력 조회 |
| `glitter-policy_engine.evaluations.simulate` | 게시된 정책 시뮬레이션 |

정책 목록·상세 조회에는 `policies.read`, 생성에는 `policies.create`, 초안·메타데이터
수정에는 `policies.update`, 게시에는 `policies.publish`가 필요합니다. 평가 이력 조회와
시뮬레이션도 각각 별도의 Permission을 사용합니다. 시뮬레이터 화면은 게시 정책 선택 목록을
조회하므로 `evaluations.simulate`와 `policies.read` 권한이 모두 필요하지만, 평가 실행 API 자체는
`evaluations.simulate` 권한으로 분리됩니다.

## 정책 흐름

1. 정책 키와 JSON DSL을 포함해 정책을 생성합니다.
2. Draft를 수정하고 Validator로 DSL을 검증합니다.
3. Optimistic Locking으로 동시 수정 충돌을 감지합니다.
4. 검증된 Draft를 Revision으로 게시합니다.
5. 게시된 Revision만 평가합니다.
6. 생성·수정·게시 이벤트는 Activity Log에 기록합니다.

## Rule DSL v1

정책의 `fields`에 입력 필드를 먼저 선언해야 합니다. 지원 타입은 `string`, `integer`,
`float`, `boolean`, `array`입니다.

지원 비교 연산자는 `eq`, `neq`, `gt`, `gte`, `lt`, `lte`, `in`, `not_in`, `exists`이며,
논리 연산자는 `all`, `any`입니다.

```json
{
  "schema_version": "1.0",
  "fields": {
    "action": "string",
    "subject.id": "string"
  },
  "rules": [
    {
      "id": "allow-owner",
      "effect": "allow",
      "when": {
        "all": [
          {"field": "action", "operator": "eq", "value": "reservation.cancel"},
          {"field": "subject.id", "operator": "eq", "value": "owner"}
        ]
      }
    },
    {
      "id": "deny-non-owner",
      "effect": "deny",
      "when": {
        "all": [
          {"field": "action", "operator": "eq", "value": "reservation.cancel"},
          {"field": "subject.id", "operator": "neq", "value": "owner"}
        ]
      }
    }
  ]
}
```

`effect`는 `allow` 또는 `deny`입니다. DSL 크기와 중첩 복잡도에는 제한이 적용됩니다.
ALLOW와 DENY가 동시에 일치하면 DENY가 우선하며, 어느 규칙도 일치하지 않으면
`no_rule_matched`와 함께 DENY를 반환합니다.

## 평가 결과와 안전 동작

평가 결과는 `allow`, `deny`, `indeterminate` 중 하나입니다.

- `deny` 규칙이 일치하면 Allow보다 우선합니다(Deny Overrides).
- 일치하는 규칙이 없으면 `deny`를 반환합니다(Fail Closed).
- 게시된 정책이 없거나 정책이 비활성화되면 평가하지 않습니다.
- 평가 이력 저장에 실패하면 성공으로 위장하지 않고 `indeterminate`를 반환합니다.
- 평가 과정에서 현재 시간, 외부 HTTP, 임의 SQL, 사용자 제공 PHP 코드를 실행하지 않습니다.

다른 모듈은 다음 공개 계약을 주입받아 평가합니다.

```php
use Modules\Glitter\PolicyEngine\Contracts\PolicyEvaluatorInterface;

public function __construct(private PolicyEvaluatorInterface $evaluator) {}
```

내부 테이블과 Repository는 모듈 외부 공개 계약이 아닙니다.

## Evaluation History 및 Activity Log

시뮬레이션과 실제 평가의 결과 이력은 G7 Activity Log와 별도의 정책 평가 이력으로
관리됩니다. Activity Log에는 정책 생성, 메타데이터·Draft 수정, 게시 이벤트가 저장되며
한국어·영어 설명 키를 사용합니다. Listener는 이벤트 시점의 상태가 기록되도록 동기
Activity Hook으로 등록되어 있습니다.

평가 이력 API는 저장 계약을 유지하기 위해 `duration_us`와 ISO 8601 `evaluated_at`을
반환합니다. 관리자 화면에서는 소요 시간을 마이크로초에서 밀리초로 변환해 소수점 셋째
자리까지 표시하며, 평가 시각은 로그인 사용자·브라우저·G7 기본값 순서의 타임존과 현재
언어에 맞춰 읽기 쉬운 형식으로 표시합니다. 원본 API와 DB 값은 변경하지 않습니다.

예를 들어 `16086` 마이크로초는 관리자 화면에서 `16.086 ms`로 보입니다.

## 보안 설계

- 관리자 API는 Laravel Sanctum 인증을 사용합니다.
- API와 관리자 Layout은 G7 Permission으로 접근을 분리합니다.
- 정책 입력은 FormRequest와 DSL Validator를 통과해야 합니다.
- Deny Overrides와 Fail Closed를 적용합니다.
- DSL 복잡도와 입력 크기를 제한합니다.
- 외부 HTTP, 임의 SQL, 임의 PHP 코드 실행을 허용하지 않습니다.
- 평가 이력에는 Subject, Resource, Context 원문을 저장하지 않습니다.
- 평가 이력에는 정책 식별자, 게시 버전, 판정, 사유 코드, action, 일치한 규칙 ID와 실행 시간 등
  최소 메타데이터만 저장합니다.
- 정책 정의와 Revision은 영구 저장되므로 비밀번호, 토큰, 개인키 또는 실제 사용자 개인정보를 규칙
  상수로 입력하지 마십시오.
- Activity Log에는 정책 정의나 평가 입력 원문을 저장하지 않고 정책 식별자, 상태, 잠금 버전,
  게시 버전 및 definition hash만 기록합니다.
- 애플리케이션 오류 응답에는 예외 원문을 노출하지 않으며, 서버 로그에도 평가 입력 원문을 기록하지
  않습니다.

## 테스트와 검증

운영 G7 체크아웃에서는 테스트를 실행하지 마십시오. 독립 저장소의 `tests/`는 개발·CI 자산으로
보존하고, 운영 Bundled 배포물에서는 제외할 수 있습니다. 회귀 테스트는 운영 DB와 분리된 전용 G7
테스트 체크아웃 및 승인된 테스트 DB에서만 실행합니다.

호스트 테스트 하네스가 `phpunit.glitter-policy.xml`과 DB Guard를 제공하는 경우 다음 순서를 따릅니다.
실행 전에 셸의 모든 `DB_*` override를 제거하고 Guard가 승인된 테스트 DB를 가리키는지 확인해야
합니다.

```bash
/usr/local/bin/php83 tools/isolation-db-guard.php --preflight
/usr/local/bin/php83 tools/isolation-db-guard.php --runtime
/usr/local/bin/php83 vendor/bin/phpunit -c phpunit.glitter-policy.xml
```

PRECHECK 또는 RUNTIME Guard가 PASS가 아니면 PHPUnit, Migration, Module lifecycle 명령을 실행하지
마십시오. 단위·통합 회귀 범위에는 DSL 검증, ALLOW/DENY/Deny Overrides/Fail Closed, 게시 Revision,
Optimistic Locking, 최소 평가 이력, 관리자 Layout 계약이 포함됩니다.

## 제한 사항

현재 버전에는 다음 기능이 포함되지 않습니다.

- 승인 Workflow
- 외부 Webhook
- 외부 시스템 자동 실행
- 임의 PHP 코드 실행
- 공개 정책 평가 API
- Visual Rule Builder
- 정책 보존 기간 및 평가 이력 자동 삭제 정책
- 독립적인 정책 승인자/게시자 분리 Workflow

## 독립 저장소

독립 배포 및 개발 이력은
[glitter-node/glitter-policy_engine](https://github.com/glitter-node/glitter-policy_engine)에서 관리합니다.
독립 저장소의 런타임 소스는 G7 Bundled 원본과 일치해야 하며, 테스트·CI 자산은 독립 저장소에
보존하되 `.env`, DB dump, 로그, 캐시, 실제 사용자 데이터 및 인증정보를 포함해서는 안 됩니다.

## 유지관리 및 라이선스

유지관리 주체는 `Glitter.kr`입니다. 저작권은 다음과 같습니다.

`Copyright (c) 2026 Glitter.kr`

이 모듈은 MIT License로 배포됩니다. 자세한 조건은 [LICENSE](LICENSE)를 확인하십시오.
