# CLAUDE.md

심사(judge) 앱 — Laravel. `/var/services/web/sw4u/judge` 가 소스이자 서빙 경로다
(docker 스택이 `/var/www/html/sw4u/judge` 로 bind mount 한다).

## 테스트 실행

프로젝트 루트에서:

```bash
php artisan test          # 전체 (Unit + Feature)
composer test             # config:clear 후 전체 — 설정 캐시가 꼬였을 때
composer lint             # pint --test --dirty (수정한 파일만 스타일 검사)
```

일부만 돌릴 때:

```bash
php artisan test --filter=AdminApiTest
php artisan test tests/Feature/AdminFlowTest.php
```

- 테스트 DB 는 `phpunit.xml` 이 지정하는 **sqlite `:memory:`** 다. 실제 MariaDB 를 건드리지 않으므로
  운영 데이터에 영향이 없고, `.env` 의 DB 설정과도 무관하다.
- 그래서 CLI PHP 에 **`pdo_sqlite` 확장이 필요**하다. 없으면 전 테스트가
  `could not find driver (Connection: sqlite …)` 로 죽는다. 호스트에는
  `php8.4-sqlite3` 로 설치돼 있다 (2026-09-12 확인, `php -m | grep sqlite` 로 점검).
- 호스트 PHP 가 망가졌을 때의 대안 — 컨테이너에도 같은 확장이 있다:

```bash
docker exec -w /var/www/html/sw4u/judge phpfpm php artisan test
```

## 커밋 전

`composer lint` 와 `php artisan test` 를 모두 통과시킨다.
