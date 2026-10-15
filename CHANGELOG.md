# Changelog

이 문서는 [Keep a Changelog](https://keepachangelog.com/ko-KR/1.0.0/) 형식을 따릅니다.
버전 제목에는 날짜를 넣지 않으며, 릴리스 날짜는 Git Tag 또는 GitHub Release 메타데이터로 관리합니다.

## [0.1.0]

### Added

- Policy Registry, Draft, Revision 및 Optimistic Locking
- JSON DSL Validator와 Policy Evaluator
- Deny Overrides 및 Fail Closed 평가
- Evaluation History
- G7 관리자 API와 관리자 Layout
- 게시 정책 검색·선택 기반 평가 시뮬레이터 UX
- 시뮬레이터 JSON 입력 검사·정렬과 ALLOW/DENY/INDETERMINATE 결과 패널
- 세분화된 G7 Permission
- Activity Log Listener
- G7 Module Install, Activate, Deactivate lifecycle 연동

### Changed

- 평가 이력 화면에서 `duration_us`를 밀리초로 변환해 소수점 셋째 자리까지 표시
- 평가 이력 시각을 사용자·G7 타임존과 현재 언어에 맞춰 표시
- 평가 시뮬레이터에서 게시된 정책을 검색·선택하고 소유 확장, 정책 키, 게시 버전을 자동 반영

### Fixed

- Permission leaf의 한국어·영어 `description` 계약 추가
- 관리자 Layout Route의 Permission 연결
- 정책 생성 직후 Resource에서 `status`가 비어 500이 발생하던 문제 수정
- Activity Log Hook을 동기 실행해 이벤트 시점 상태가 보존되도록 수정
- 게시 버전 번역 파라미터가 프런트 번역 엔진에서 실제 값으로 치환되도록 수정
- 정책 상세 화면은 조회 권한으로 열고 수정·검증·게시 동작은 각 전용 권한에 따라 노출되도록 수정
- 평가 시뮬레이터 Layout이 실행 권한과 게시 정책 조회 권한을 함께 요구하도록 수정
- Subject, Resource, Context JSON 입력 변경 이벤트가 각 입력의 독립 상태를 갱신하도록 수정
- JSON 입력 변경 시 이전 오류와 평가 결과를 초기화하도록 수정
- 유효한 JSON의 검사·2칸 들여쓰기 정렬 동작 및 평가 요청 입력 계약 정비

### Security

- 관리자 인증 및 Permission 검증
- Deny Overrides와 Fail Closed
- DSL 입력 크기·중첩 복잡도 제한
- 평가 이력에 민감한 인증정보 저장 방지

### Known Limitations

- 승인 Workflow, 외부 Webhook, Visual Rule Builder는 포함하지 않음
- 공개 정책 평가 API를 제공하지 않음
- 평가 이력 보존 기간 및 자동 삭제 정책은 포함하지 않음
