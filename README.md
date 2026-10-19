# Glitter Policy Engine

GnuBoard7 Custom Module인 `glitter-policy_engine`은 업무 정책을 JSON DSL로 저장하고
결정적으로 평가합니다. 이 모듈은 G7의 인증·Role·Permission을 대체하지 않으며, 호출자가
G7 접근 제어를 통과한 뒤 업무 규칙을 추가로 판정하는 용도로 사용합니다.

- Module ID: `glitter-policy_engine`
- Current module metadata: `0.2.0`
- Release candidate: `0.2.0` (Revision Diff; follow-up JSON shape hardening implemented; production integration is not verified)
- HTTP and browser integration: NOT VERIFIED; Git release/tag and production deployment: not performed
- Vendor / Author: `Glitter.kr`
- License: MIT

변경 내역은 [CHANGELOG](CHANGELOG.md)에서 확인합니다. 버전 제목에는 날짜를 기록하지 않으며,
릴리스 날짜는 Git Tag 또는 GitHub Release 메타데이터로 관리합니다.

## 요구 환경

- GnuBoard7 7.1.0 이상 (`g7_version: >=7.1.0`)
- PHP 8.3 이상
- Laravel 12.x
- MySQL 8.0+ 또는 MariaDB 10.3+

## 주요 기능

- Policy Registry: 정책 키, 설명, 소유 확장, 상태 관리
- Draft 및 Optimistic Locking
- 게시 Revision과 definition hash 무결성 검증
- JSON DSL 구조·복잡도 검증
- 결정적 Policy Evaluator
- Deny Overrides와 Fail Closed
- Evaluation History
- 관리자 API와 G7 Layout
- Published / Revision / Draft 정책 정의 비교와 안전 진단
- 세분화된 관리자 Permission
- Activity Log 기록
- G7 Module Install / Activate / Deactivate lifecycle 연동

## Phase 2-B 안전성 계약

- `PolicyDefinitionSafetyInspector`는 호출 옵션을 생략하면 원본 JSON 형태를 검증할 증거가 없는 것으로 보고 Fail-Closed 판정합니다. 신뢰 가능한 원본 형태를 별도로 확인하는 내부 검사만 명시적으로 해당 모드를 요청할 수 있습니다.
- `PolicyRepository::publish()`도 엄격한 DSL·배열 비교 형태 검사를 수행하므로 Service를 우회한 직접 Publish가 안전성 검증을 건너뛰지 않습니다. 검증 실패 시 Revision 생성과 게시 포인터 갱신을 시작하지 않습니다.
- `definition_hash_version` 컬럼·Revision 테이블·데이터베이스가 없음을 나타내는 SQLSTATE/드라이버 오류만 `policy_schema_not_ready`로 분류합니다. 일반 SQL 오류, 연결 실패, 권한 오류, Deadlock 및 제약조건 오류는 기존 실패 계약을 유지하며 정상 게시로 처리되지 않습니다. 이 예외 정규화는 G7 ModuleManager의 파일 교체·Migration 원자성을 대신하지 않습니다.
- 평가·Diff·Draft Publishability는 저장된 Revision의 원본 JSON 형태 증거가 없다는 한계를 유지합니다. 레거시 array 비교는 INDETERMINATE 또는 unsafe/not-publishable이 될 수 있습니다. 운영 DB 영향 규모는 조사하지 않았습니다.
- HTTP·브라우저·MariaDB 통합 검증과 운영 반영은 완료되지 않았으며, v0.2.0은 릴리스 후보 메타데이터일 뿐 정식 배포를 의미하지 않습니다.

## 운영 반영 전 점검 및 영향 진단

### 운영 MariaDB 진단 인터페이스

`PolicyProductionDiagnosticService::diagnose()`는 기본적으로 DB에 접근하지 않고 진단 계획(`status: not_run`)만 반환합니다. 운영자가 별도 승인한 읽기 전용 점검에서만 `diagnose(true)`를 호출해야 하며, 이 호출은 G7의 기존 DB 연결을 사용해 다음을 확인합니다.

- 정책·Draft·Revision·Evaluation 테이블과 `definition_hash_version` 컬럼 메타데이터
- Migration 기록 확인 가능 여부
- Published Revision 수, Hash v1/v2/기타 버전 분포
- Revision Hash 검증 성공·실패 수
- array 비교 영향 수와 안전성 사유 코드 집계

Revision은 cursor로 순회하며 정의 원문·조건 값·정책 식별자를 결과나 로그에 포함하지 않습니다. 결과 상태는 `not_run`(계획만 반환), `completed`(요청 범위 전체 완료), `incomplete`(건수·시간 제한 도달), `partial_failure`(스키마·Migration·오류로 전체 검증 불가)로 구분합니다. 스키마 오류, Migration 미적용·기록 확인 불가, 권한·연결 오류 또는 중간 실패가 발생하면 `completed`로 보고하지 않고 고정 오류 코드(`policy_schema_not_ready`, `policy_schema_mismatch`, `policy_migration_not_applied`, `policy_migration_status_unavailable`, `policy_diagnostic_failed`)만 반환합니다. 현재 모듈에는 별도 Artisan 명령 등록 구조가 없으므로 G7 코어를 수정하지 않고 서비스 호출 인터페이스로 제공합니다.

운영 반영 전에는 Migration 적용, v1 Revision 평가, 신규 v2 Publish 저장 왕복, Optimistic Locking, History 기록, Diff 응답 제한, 관리자 화면을 승인된 절차로 확인해야 합니다. 실패 시 점검을 중단하고 수동 복구를 검토하며, Migration·Lifecycle·Rollback 명령은 이 문서 검증 단계에서 실행하지 않습니다.

- 스키마 분류기는 테이블·컬럼 부재(`42S02`, `42S22`, `42703`, 1054, 1146)와 데이터베이스 자체 부재(`3D000`, 1049)를 구분합니다. 연결 실패, 권한 오류, Deadlock, Lock Timeout 및 제약조건 오류는 일반 실패 계약을 유지합니다.
- `PolicyDefinitionImpactAnalyzer`는 DB 조회 없이 Revision 정의를 입력받아 Hash v1/v2를 먼저 검증한 뒤 안전성 영향 여부, 사유 코드 및 조건 개수만 반환합니다. 정의 원문과 조건 값은 로그나 결과에 포함하지 않습니다.
- 운영 점검은 승인된 읽기 전용 절차로 수행해야 하며, Published Revision 수·array 비교 영향 규모·Evaluation History 영향은 현재 확인되지 않았습니다.
- 배포 전에는 `definition_hash_version` Migration 적용 여부, 신규 v2 Publish 저장 왕복, 기존 v1 Revision 평가, Optimistic Locking, Publish 트랜잭션, Diff 404/422/500, 262144-byte 응답 제한, 관리자 KO/EN 렌더링을 별도로 확인해야 합니다.
- Migration 실패, 스키마 불일치, 통합 검증 실패, 민감정보 노출 또는 Fail-Closed 우회가 발견되면 운영 반영을 중단하고 수동 복구 절차를 검토해야 합니다. Dry Run과 Rollback 기능은 구현되어 있지 않습니다.

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
- 정책 변경 비교: `/admin/policy-engine/policies/{id}/diff`
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

정책 변경 비교 화면은 `glitter-policy_engine.policies.read` 권한을 사용하며 기본적으로 현재
게시 Revision과 Draft를 비교합니다. Published, Revision, Draft selector를 지원하고, Revision
선택에는 양의 버전 번호가 필요합니다. API는 다음 GET 경로를 사용합니다.

`/api/modules/glitter-policy_engine/admin/policies/{policy}/diff`

응답에는 각 source의 메타데이터, DSL validation, Revision integrity, safety, Draft
publishability와 변경 요약이 포함됩니다. `comparison_mode`는 필드 키·규칙 ID·위치 등 실제
비교 방식을 나타내며, `comparison_complete`와 `limitations`는 변경 분류의 제한 여부를
알립니다. 전체 직렬화 JSON 응답 본문이 262144 bytes를 초과하면 HTTP 422로 거부하고 정상
Diff를 자르지 않습니다.

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

1. 정책 키와 JSON DSL을 포함해 정책을 생성합니다. 이 요청은 Published Revision이 아니라 Draft를 저장합니다.
2. Draft는 미완성 정의로 저장할 수 있으며 Validator로 별도 검증합니다.
3. Optimistic Locking으로 동시 수정 충돌을 감지합니다.
4. 검증된 Draft를 Revision으로 게시합니다. Draft 수정과 Publish는 트랜잭션에서 Policy 행을 먼저 잠그고 Draft를 잠그며, Draft 수정은 기존 `lock_version` 조건부 갱신을 유지합니다.
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
- 게시 Revision은 DSL 평가 전에 저장된 definition hash와 현재 정의의 해시를 비교합니다. 정의를 검증할 수 없거나 해시가 일치하지 않으면 `revision_integrity_failed` 사유 코드와 함께 `indeterminate`를 반환합니다.
- Revision별 `definition_hash_version`으로 해시 알고리즘을 선택합니다. 기존 Revision은 컬럼 Migration의 기본값 `1`로 Hash v1을 사용하고, 신규 Publish는 버전 `2`를 명시합니다. v1 계산은 기존 Revision 호환성을 위해 유지됩니다. v2는 객체 키를 정렬하고 리스트 순서를 보존하며 `JSON_PRESERVE_ZERO_FRACTION`과 고정 domain separation을 사용합니다.
- Draft와 Revision의 definition JSON 저장은 정수형 실수의 `.0` 표현을 보존합니다. 과거 JSON 처리 과정에서 객체 `{}`와 배열 `[]`의 구분이 손실되었을 가능성이 있고, 저장된 정의만으로 원래 입력 형태를 복원할 수 없습니다.
- 빈 `in: []` 또는 `not_in: []` 조건이 있는 Draft는 저장될 수 있습니다. `validateDraft()`는 엄격 Validator를 사용하므로 검증 결과는 invalid이고, Publish도 이를 거부합니다. 해당 조건이 있는 기존 Published Revision은 `unsafe_empty_membership_condition` 사유와 함께 INDETERMINATE로 처리됩니다. 기존 Revision 정의와 해시는 자동 수정하거나 재계산하지 않습니다.
- array 필드의 `eq`/`neq` 비교값 또는 `in`/`not_in` 원소 트리에 빈 배열이 있으면 과거 JSON 객체 `{}`와 배열 `[]`의 형태 손실 가능성 때문에 안전하지 않은 정의로 처리합니다. `ambiguous_empty_array_comparison`은 Published Revision 평가를 INDETERMINATE로 중단하고 Diff Safety를 unsafe로 표시합니다. 빈 `eq: []`, `neq: []`, `in: [[]]`, `not_in: [[]]` 및 중첩 빈 배열 비교는 strict Draft 검증과 Publish에서 거부되며 Validator 코드 `ambiguous_empty_array_comparison_not_allowed`를 사용합니다. 이는 기존 정상 빈 배열 비교의 호환성을 의도적으로 제한합니다.
- JSON 요청의 Raw Shape 검사는 비교값 트리 안의 빈 객체를 거부합니다. 이 검사는 JSON Content-Type 요청에 적용됩니다. 비 JSON 입력 및 배열만 받는 직접 Service 호출은 원본 JSON 객체·리스트 형태를 보장하지 않으므로 strict Validator와 Published Revision Safety 검사도 함께 적용합니다.
- Raw Shape 검사는 array 필드 비교값 트리의 비어 있지 않은 JSON 객체도 거부합니다. 숫자형 연속 키 객체가 리스트로 변환되는 경로를 신규 JSON 경계에서 차단하지만, 이미 저장된 Revision의 원본 형태를 복원하지는 않습니다.
- 저장된 Revision에는 원본 JSON 객체·리스트 형태의 신뢰 증거가 없으므로 array 필드의 `eq`, `neq`, `in`, `not_in` 비교는 보수적으로 `unverified_array_comparison_shape` 안전 오류로 차단됩니다. 이 때문에 원래 정상적인 리스트를 사용한 레거시 array 정책도 `indeterminate`가 될 수 있습니다. 신규 Draft의 strict 검증과 Publish에서는 `unverified_array_comparison_shape_not_allowed`로 게시를 거부합니다. Hash v1/v2는 원본 JSON 형태를 복원하지 않으며, 형태 증거를 저장하는 계약은 후속 과제입니다.
- Diff의 Integrity, Validation, Safety 진단은 각각 Revision 해시 검증, DSL 유효성, 현재 평가 안전성을 나타냅니다. Draft의 Publishability는 현재 DSL 및 안전성 검증 상태이며 권한, 버전 충돌, DB 상태 등을 포함한 실제 Publish 성공을 보장하지 않습니다.
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
- `action`은 평가 이력과 관리자 응답에 포함되므로 비밀정보나 개인정보가 아닌 업무 동작 식별자를 전달하십시오.
- 정책 정의와 Revision은 영구 저장되므로 비밀번호, 토큰, 개인키 또는 실제 사용자 개인정보를 규칙
  상수로 입력하지 마십시오.
- Activity Log에는 정책 정의나 평가 입력 원문을 저장하지 않고 정책 식별자, 상태, 잠금 버전,
  게시 버전 및 definition hash만 기록합니다.
- 애플리케이션 오류 응답에는 예외 원문을 노출하지 않으며, 서버 로그에도 평가 입력 원문을 기록하지
  않습니다.

## Diff API 오류 및 진단 코드

아래 Diff API 오류 표는 Controller가 생성하는 오류 코드의 전체 목록입니다. FormRequest 입력 검증은
Controller에 도달하기 전에 처리되며, Laravel의 기본 HTTP 422 JSON 검증 응답을 반환합니다. 이 입력
검증 응답은 아래의 모듈 Diff API `errors.code` 계약과 별개입니다.

| HTTP | Diff API 오류 코드 | 계약 |
|---|---|---|
| 404 | `policy_not_found` | 정책을 찾을 수 없습니다. |
| 404 | `policy_diff_revision_not_found` | 해당 정책에 속한 지정 Revision을 찾을 수 없습니다. |
| 404 | `policy_diff_draft_not_found` | 정책 Draft를 찾을 수 없습니다. |
| 404 | `policy_diff_published_revision_not_found` | 게시된 Revision을 찾을 수 없습니다. |
| 422 | `policy_diff_invalid_selector` | 비교 source selector 조합이 유효하지 않습니다. |
| 422 | `policy_diff_invalid_revision_version` | Revision version이 유효하지 않습니다. |
| 422 | `policy_diff_definition_uncomparable` | 정의를 비교할 수 없습니다. |
| 422 | `policy_definition_diff_result_too_large` | Diff 또는 최종 JSON 응답이 262144 bytes 상한을 초과했습니다. 결과를 자르지 않고 오류를 반환합니다. |
| 500 | `revision_integrity_failed` | Revision hash 무결성 검증에 실패했습니다. |
| 500 | `policy_definition_diff_encoding_failed` | Diff JSON 인코딩에 실패했습니다. |
| 500 | `policy_schema_not_ready` | 저장소 스키마가 준비되지 않아 Publish 또는 Diff를 진행할 수 없습니다. |
| 500 | `policy_diff_failed` | 예상하지 못한 Diff API 실패의 일반 오류 코드입니다. |

이 표는 FormRequest가 반환하는 필드별 검증 오류를 열거하지 않습니다. `empty_membership_not_allowed`는
엄격 Rule Validator 코드입니다. Draft 검증 응답의 오류 항목에 포함될 수 있으며, Publish 검증 실패는
HTTP 422로 반환됩니다. 이는 Diff API 오류 코드가 아닙니다.

`unsafe_empty_membership_condition`은 Evaluator에서 INDETERMINATE 판정 사유이고 Diff 응답에서는
Safety 진단 코드입니다. Diff API HTTP 오류 코드가 아닙니다. `revision_integrity_failed`는 Evaluator의
INDETERMINATE 사유로도 사용되며, Diff API에서는 위 표와 같이 HTTP 500 오류입니다.
`policy_schema_not_ready`는 식별 가능한 스키마 부재 오류만 Publish Controller와 Diff API에서 정상
결과로 변환하지 않기 위한 모듈 오류 코드입니다. 일반 DB 오류는 각 기존 실패 계약을 따르며, 이 코드는
G7 ModuleManager의 Migration 실패를 원자적으로 해결한다는 뜻이 아닙니다.

## Hash Version Migration 및 배포 호환성

신규 `definition_hash_version` 컬럼 Migration이 필요합니다. Migration 적용 시 기존 Revision은
기본값 `1`로 해석되고 신규 Publish는 `2`를 저장합니다. 새 코드는 이 컬럼으로 알고리즘을 선택하므로
Migration 미실행 상태에서 정상 동작한다고 보장할 수 없습니다.

새 코드는 평가와 Diff에서 이 컬럼을 즉시 읽으므로, 새 코드가 요청을 처리하기 전에 Migration이
적용되어야 합니다. 기존 코드는 컬럼 기본값 1을 사용해 Migration 단독 적용 중 기존 방식으로
Revision을 만들 수 있지만, 실제 모듈 lifecycle이 Migration과 코드 전환을 어떤 순서로 수행하는지는
이 문서 감사에서 실행하거나 검증하지 않았습니다. 무중단 배포를 보장한다고 간주하지 마십시오.
v2 Revision이 생성된 뒤 구버전 코드로 단순 롤백하면 구버전에서 v2 해시 검증이 실패할 수 있습니다.
컬럼 제거를 포함하는 Migration rollback도 새 코드의 Revision 검증을 깨뜨릴 수 있어 위험합니다.
Revision 정의나 해시를 자동 변환하지 않습니다.

이번 문서 정리에서는 운영 DB에 접속하지 않았으므로 Migration 적용 여부와 운영 Revision 상태는
확인되지 않았습니다. v0.2.0은 Revision Diff를 포함하는 릴리스 후보이며, 운영 반영 또는 Migration
실행 완료를 뜻하지 않습니다. `module.json`과 `composer.json`은 릴리스 후보 정합성을 위해 `0.2.0`을 선언하지만,
Git tag/release와 Production deployment는 수행하지 않았습니다. Migration도 실행하지 않았으며 운영
DB의 Migration 적용 여부는 확인되지 않았습니다. HTTP 및 브라우저 통합은 아직 검증되지 않았습니다.

## 테스트와 검증

개발·검증·운영은 프로젝트 루트 `.env`와 그 설정의 데이터베이스를 공유합니다. 별도 테스트 DB,
SQLite, `.env.testing`, 별도 체크아웃 또는 컨테이너를 사용하지 않습니다. DB 기반 테스트는 실제
설정과 테스트 부작용을 먼저 확인하고, 운영 데이터의 영속적 변경이 필요한 경우 명시적 승인을 받은
뒤 실행해야 합니다. 안전한 테스트 구성을 확인할 수 없으면 실행하지 않은 테스트와 미검증 항목을
보고하십시오. 정적 검증만으로 기능 테스트를 완료했다고 간주하지 않습니다.

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
- Draft Dry Run
- Policy Rollback

### 레거시 Diff 해석

과거 저장 과정에서 JSON 객체 `{}`와 배열 `[]`가 같은 PHP 배열 표현으로 디코딩되어 구분이 손실됐을
가능성이 있습니다. 원래 입력 형태가 달랐더라도 현재 저장된 정규화 정의가 같으면 Diff가 `changed: false`를
반환할 수 있습니다. 이는 현재 비교 가능한 정규화 정의에서 차이가 발견되지 않았다는 뜻이며, 원본 JSON
바이트가 같거나 과거 객체·배열 형태까지 같다는 보장은 아닙니다.

Revision의 `integrity.status: verified`는 저장된 Hash Version에 따른 해시 검증을 통과했다는 뜻이지,
원본 입력 JSON 구조를 복원했다는 뜻이 아닙니다. `safety.status: safe`는 현재 해석 가능한 정의에서
알려진 빈 membership 위험 조건이 발견되지 않았다는 뜻이며, 과거 원본 JSON 형태가 완전히 보존됐다는
증명이 아닙니다. 기존 Revision은 자동 수정하거나 재해시하지 않습니다. 운영 데이터의 실제 영향 건수는
별도 승인된 읽기 전용 조사를 수행하지 않아 확인되지 않았습니다.

array 필드 비교값의 빈 배열은 원래 빈 객체였는지 빈 배열이었는지 저장 데이터만으로 판별할 수 없어 차단됩니다. 따라서 과거에 의도적으로 사용한 정상 `eq: []` 또는 `neq: []` 정책도 평가가 중단될 수 있습니다. `in: [[]]`, `not_in: [[]]`와 더 깊은 중첩 빈 배열도 같은 규칙을 적용합니다. 운영 Revision 중 새 안전 검사에 영향을 받는 항목의 규모는 확인되지 않았습니다. Phase 2-A.23-B의 정적 검증은 MariaDB 저장 왕복, HTTP 또는 브라우저 통합 검증 완료를 의미하지 않습니다.

## 독립 저장소

독립 배포 및 개발 이력은
[glitter-node/glitter-policy_engine](https://github.com/glitter-node/glitter-policy_engine)에서 관리합니다.
독립 저장소의 런타임 소스는 G7 Bundled 원본과 일치해야 하며, 테스트·CI 자산은 독립 저장소에
보존하되 `.env`, DB dump, 로그, 캐시, 실제 사용자 데이터 및 인증정보를 포함해서는 안 됩니다.

## 유지관리 및 라이선스

유지관리 주체는 `Glitter.kr`입니다. 저작권은 다음과 같습니다.

`Copyright (c) 2026 Glitter.kr`

이 모듈은 MIT License로 배포됩니다. 자세한 조건은 [LICENSE](LICENSE)를 확인하십시오.
