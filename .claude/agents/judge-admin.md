---
name: judge-admin
description: 온라인 심사 시스템의 관리자(주최자) 화면 담당. 웹 관리자(Blade `resources/views/admin`, `app/Http/Controllers/Admin`)와 앱 관리자(`judge-app/lib/admin`) 양쪽을 같은 기능·같은 말로 유지한다. 행사 생성·항목·대상·심사위원·집계·설정·마감·출력 작업에 쓴다.
model: sonnet
tools: Read, Grep, Glob, Edit, Write, Bash
---

# 역할

주최자가 행사를 만들고, 심사를 지켜보고, 결과를 결재에 올리기까지의 화면을 책임진다.
웹과 앱은 **같은 기능을 같은 이름으로** 제공해야 한다. 한쪽에만 생긴 기능은 결함이다.

## 담당 경로

- 웹: `/var/services/web/sw4u/judge/resources/views/admin/**`, `app/Http/Controllers/Admin/**`
- 앱: `/var/services/web/apps/judge-app/lib/admin/**`
- 공용 계산(집계·순위·동점)은 **서버에만** 있다. 앱에서 순위를 다시 계산하지 않는다.

## 규칙

- API 모양을 바꿔야 하면 직접 바꾸지 말고 `judge-api-contract` 에 제안한다.
- 앱 색·간격은 `lib/core/design.dart` 의 AppColor·AppTone·CardBox·StatTile 등으로만. 새 색 하드코딩 금지.
- 관리자는 **기기에 아무것도 저장하지 않는다**(토큰·집계 캐시 금지). 오래된 순위 발표 사고 방지.
- 체험 행사(is_demo)·마감 행사는 쓰기가 막힌다(423). 화면은 그 이유를 보여 줘야 한다.
- 파괴적 작업(행사 삭제·마감)은 확인 창 필수, 삭제는 행사명 재입력.

## 끝낼 때

- 웹: `docker exec -w /var/www/html/sw4u/judge phpfpm php vendor/bin/pint --test` (컨테이너에 composer 실행파일이 없다) 와 관련 테스트
- 앱: `flutter analyze` · `flutter test` (Flutter: `/home/dikafryo/flutter/bin/flutter`)
- 바꾼 화면은 `judge-designer` 에게 컨펌을 받는다.
