# 온라인 심사 시스템 (Judge)

경진대회·공모전·발표회 등에서 **심사위원이 점수를 입력하는 즉시 자동 집계**되는
실시간 온라인 심사 시스템입니다. Laravel 13 기반이며, 회원가입 없이 **행사별 관리 비밀번호**와
**심사위원 접속 코드**만으로 운영합니다.

- 관리자: 행사 생성 → 평가 항목·평가 대상·심사위원 등록 → 실시간 집계 확인 → 결과 출력
- 심사위원: 코드/QR/링크로 접속해 점수 입력 + 전자서명 (로그인 불필요)

애플리케이션 사용법(관리자/심사위원 가이드, FAQ)은 배포 후 화면 우측 상단의 **`?` 버튼(사용 설명서)**에서
바로 확인할 수 있습니다. 이 문서는 **서버에 설치·운영하는 방법**을 다룹니다.

---

## 1. 요구 사항

| 항목 | 버전 / 비고 |
|---|---|
| PHP | 8.3 이상 (8.4 권장), CLI + FPM |
| PHP 확장 | `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `curl` (대부분 기본 포함) |
| DB | MariaDB 10.6+ 또는 MySQL 8+ |
| 웹서버 | Nginx 또는 Apache (PHP-FPM 연동) |
| Composer | 2.x |
| 캐시(선택) | Redis — 없으면 `CACHE_STORE=database`로도 동작 |
| Node/npm | **불필요** — 프런트엔드는 Tailwind CDN + Alpine.js CDN을 사용해 빌드 스텝이 없음 |

빌드 도구가 필요 없으므로 서버에는 PHP·Composer·DB만 있으면 됩니다.

---

## 2. 설치

```bash
# 1) 소스 받기
git clone https://github.com/dikafryo/judge judge
cd judge

# 2) PHP 의존성 설치 (배포 서버에서는 --no-dev 권장)
composer install --no-dev --optimize-autoloader

# 3) 환경설정 파일 생성
cp .env.example .env
php artisan key:generate
```

### `.env` 설정

`.env`를 열어 최소한 아래 값을 서버 환경에 맞게 채웁니다.

```env
APP_NAME="온라인 심사 시스템"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://judge.example.com      # 실제 도메인으로 변경

APP_LOCALE=ko
APP_FALLBACK_LOCALE=ko

DB_CONNECTION=mysql        # MariaDB도 mysql 드라이버 사용
DB_HOST=127.0.0.1          # DB 서버 주소 (도커 사용 시 서비스명, 예: mariadb)
DB_PORT=3306
DB_DATABASE=judge
DB_USERNAME=judge
DB_PASSWORD=원하는_강력한_비밀번호

SESSION_DRIVER=database
QUEUE_CONNECTION=database

# Redis를 쓰지 않는다면 CACHE_STORE=database 로 바꿔도 됨
CACHE_STORE=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

> DB 계정은 미리 생성해 둡니다. MariaDB 예시:
> ```sql
> CREATE DATABASE judge CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
> CREATE USER 'judge'@'%' IDENTIFIED BY '원하는_강력한_비밀번호';
> GRANT ALL PRIVILEGES ON judge.* TO 'judge'@'%';
> FLUSH PRIVILEGES;
> ```

### 마이그레이션

```bash
php artisan migrate --force
```

행사·평가대상·평가항목·심사위원·점수 테이블이 생성됩니다. 초기 데이터(시드)는 없으며,
첫 화면에서 바로 "행사 만들기"로 운영을 시작하면 됩니다.

### 권한

PHP-FPM 실행 계정(예: `www-data`)이 아래 디렉터리에 **쓰기 권한**을 가져야 합니다.

```bash
chown -R <배포계정>:www-data storage bootstrap/cache
chmod -R 2775 storage bootstrap/cache
```

### 배포 캐시 최적화

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

> ⚠️ 이후 `.env`, `routes/`, 뷰(`resources/views`)를 수정하면 위 세 명령을 **다시 실행**해야 반영됩니다.
> 한꺼번에 지우려면 `php artisan optimize:clear`.

---

## 3. 웹서버 설정

문서 루트(docroot)는 프로젝트의 **`public/`** 디렉터리입니다. 저장소 최상위를 docroot로 잡으면 안 됩니다.

### Nginx 예시

```nginx
server {
    listen 80;
    server_name judge.example.com;
    root /path/to/judge/public;
    index index.php;

    client_max_body_size 5M;   # 전자서명 이미지(base64) 업로드 여유

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_pass 127.0.0.1:9000;   # php-fpm 소켓/주소에 맞게 수정
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known) {
        deny all;
    }
}
```

리버스 프록시(로드밸런서, Nginx Proxy Manager 등) 뒤에 두는 경우 `bootstrap/app.php`에
`trustProxies(at: '*')`가 이미 설정되어 있어 `X-Forwarded-Proto` 등을 신뢰하므로,
HTTPS 종료를 프록시가 담당해도 폼 action 등이 정상적으로 `https://`로 생성됩니다.

### Apache 예시

```apache
<VirtualHost *:80>
    ServerName judge.example.com
    DocumentRoot /path/to/judge/public

    <Directory /path/to/judge/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

`mod_rewrite`가 활성화되어 있어야 합니다(`public/.htaccess`에 규칙 포함).

### HTTPS

운영 환경에서는 반드시 HTTPS를 적용하세요(Let's Encrypt/Certbot, 또는 리버스 프록시의 TLS 종료).
관리 비밀번호와 전자서명이 오가므로 평문 HTTP 운영은 권장하지 않습니다.

---

## 4. 자동 정리(cron) 설정 — 필수

행사 데이터가 무한정 쌓이지 않도록 **자동 삭제 정책**이 내장되어 있습니다.

- 진행중(미마감) 행사: 행사일(미지정 시 등록일) 기준 **30일** 경과 시 삭제
- 마감 처리한 행사: **2년** 경과 시 삭제

이 정책은 `php artisan events:prune` 명령이 실행되어야 실제로 동작합니다. 서버 crontab에 매일 한 번 등록하세요.

```bash
crontab -e
```

```cron
# 매일 새벽 3시 23분에 보관 기한 지난 행사 정리
23 3 * * * cd /path/to/judge && php artisan events:prune >> storage/logs/events-prune.log 2>&1
```

삭제 대상만 미리 확인하려면:

```bash
php artisan events:prune --dry-run
```

> Laravel 스케줄러(`schedule:run`)를 이미 크론에 등록해 쓰는 서버라면, 대신
> `routes/console.php`에 `Schedule::command('events:prune')->dailyAt('03:23');`를
> 추가해 통합해도 됩니다.

---

## 5. 첫 사용 흐름

1. 배포된 주소로 접속 → 하단 **"행사 만들기 · 관리"** 클릭
2. 행사명, 행사일(선택), 관리 비밀번호(4자 이상) 입력 → 행사 생성
   - **비밀번호는 분실 시 복구 불가**합니다. 반드시 별도로 기록해 두세요.
3. 기본설정에서 평가 항목(배점 합계 100점 필수), 심사위원 화면 노출 방식(블라인드/이름 공개),
   집계 방식, 최종집계표 서명 방식 등을 설정
4. "평가대상" · "심사위원" 탭에서 대상과 심사위원을 일괄 등록 (심사위원은 코드 자동 발급)
   - 평가대상은 한 줄에 하나씩 **`이름, 소속`** 형식으로 입력합니다. (예: `김철수, OO대학교`)
     소속이 없으면 이름만 적으면 되고, 파이프(`|`)·탭 구분자도 그대로 인식합니다.
5. "심사위원 접속안내 출력"으로 QR 카드를 인쇄해 배부
6. 심사 당일 "집계" 탭에서 5초 간격 실시간 집계 확인
7. 종료 후 "심사 마감하기" → CSV 다운로드 / 최종집계표 A4 출력

상세 조작법은 앱 내 **사용 설명서(`?` 버튼)**를 참고하세요 — 관리자 가이드, 심사위원 가이드,
FAQ가 모두 들어 있습니다.

---

## 6. 체험용 샘플 행사(선택)

외부에 시스템을 소개할 때 쓰는 **읽기 전용 데모 행사**를 만들 수 있습니다. 심사위원 5명이
평가 대상 6건을 채점한 상태가 재현되며, `/demo` 경로에서 안내 페이지를 볼 수 있습니다.

```bash
php artisan judge:demo
```

- 실행할 때마다 기존 데모 행사를 지우고 **같은 내용으로 다시 생성**합니다(멱등, 점수는 고정 시드).
- 데모 행사에서는 저장·수정·삭제가 차단되어(`BlockDemoWrites` 미들웨어) 방문자가 데이터를 바꿀 수 없습니다.
- 데모가 필요 없다면 실행하지 않으면 됩니다. 이미 만들었다면 관리 화면에서 해당 행사를 삭제하세요.

---

## 7. 운영 참고 사항

- **큐 워커 불필요**: 현재 백그라운드 작업(Job)을 사용하지 않으므로 `queue:work`를 상시 구동할 필요는 없습니다.
- **세션/캐시**: 세션은 DB 테이블(`sessions`)에 저장됩니다(`SESSION_DRIVER=database`, 마이그레이션에 포함).
  Redis가 없으면 `.env`에서 `CACHE_STORE=database`로 바꾸면 됩니다.
- **로그**: `storage/logs/laravel.log` — 장애 시 가장 먼저 확인하세요.
- **비밀번호 백업 불가**: 관리 비밀번호는 해시로 저장되어 서버에서도 복구할 수 없으므로,
  행사 운영자가 각자 안전하게 별도 보관해야 합니다.
- **DB 백업**: 정기 백업(`mysqldump` 등)을 권장합니다.
- **전자서명 데이터**: 심사위원 서명은 PNG data URL로 DB에 직접 저장됩니다(용량 작음, 별도 파일 스토리지 불필요).

---

## 8. PWA — 앱으로 설치해 쓰기

이 서비스는 **PWA(Progressive Web App)** 입니다. 앱스토어 등록이나 별도 빌드 없이,
브라우저에서 홈 화면에 추가하면 주소창 없는 전체화면 앱으로 실행됩니다.

| 파일 | 역할 |
|---|---|
| `public/manifest.json` | 앱 이름·아이콘·시작 URL·표시 모드. **확장자 주의** — nginx 기본 `mime.types`에 `webmanifest`가 없어 `.json`을 쓴다 |
| `public/sw.js` | 서비스워커. 앱 셸·CDN 자산 캐시 + 심사위원 화면 오프라인 폴백 |
| `public/offline.html` | 네트워크가 없고 캐시에도 없을 때 보여주는 안내 화면 (CSS 인라인) |
| `public/icons/*.png` | 앱 아이콘 6종 |
| `tools/make-icons.php` | 아이콘 생성기 — `php tools/make-icons.php` |

### 설치 조건

- **HTTPS 필수.** 서비스워커는 `https://` 또는 `localhost`에서만 등록됩니다.
  리버스 프록시 뒤라면 `.env`의 `APP_URL`이 `https://`인지 확인하세요.
- 별도 설치 작업은 없습니다. 위 파일들이 문서 루트(`public/`)에 있고 200으로 서빙되면 끝입니다.

```bash
# 정상 서빙 확인
curl -sI https://<도메인>/manifest.json   # → 200 / application/json
curl -sI https://<도메인>/sw.js           # → 200 / application/javascript
```

### 오프라인 동작

행사장 와이파이가 불안정한 상황을 전제로 설계했습니다.

- **심사위원 화면**(`/judge/{코드}`)은 network-first로 캐시됩니다 — 연결이 끊겨도 채점 화면이 열립니다.
- 입력값은 제출 전에도 `localStorage`에 보관되고, 끊긴 상태로 제출하면 **대기열**에 담겼다가
  연결 복귀 시 자동 전송됩니다. 서버의 점수 저장 API가 대상 단위 upsert라 재전송이 안전합니다.
- 재전송 직전에 `GET /csrf`로 세션 토큰을 다시 받습니다(캐시된 화면의 토큰은 만료됐을 수 있음).
- **관리자 화면은 캐시하지 않습니다.** 오래된 집계를 최신으로 오인해 발표하는 사고를 막기 위해서입니다.

### 유지보수 시 주의

> 캐시 대상이나 서비스워커 로직을 바꾸면 **`public/sw.js`의 `SW_VERSION`을 반드시 올리세요.**
> 이 값이 캐시 이름이 되고, 구버전 캐시는 `activate`에서 삭제됩니다.
> 올리지 않으면 사용자 브라우저에 옛 자산이 남아 "고쳤는데 안 바뀐다"가 됩니다.
>
> **CDN 캐시도 함께 봐야 합니다.** 이 사이트는 Cloudflare 뒤에 있어 정적 파일이 엣지에
> 4시간(`max-age=14400`) 묶입니다. `sw.js` 와 `downloads/judge-latest.apk` 는 이름이 그대로인 채
> 내용만 바뀌므로, nginx 에서 `Cache-Control: no-cache` 를 붙여 매번 검증하게 해 두었습니다
> (`sw4u.kr.conf` 의 judge 블록). 버전이 이름에 박힌 APK 는 불변이라 그대로 캐시해도 됩니다.

아이콘 색·형태를 바꾸려면 `tools/make-icons.php` 상단의 색 상수를 고치고 다시 실행하면 됩니다.

### 안드로이드 앱 (APK)

PWA 와 별개로, 같은 사이트를 감싸는 **안드로이드 앱**을 자체 배포한다.
플레이스토어에는 올리지 않는다.

- 앱 프로젝트: **https://github.com/dikafryo/judge-app** (Flutter WebView 래퍼) — 빌드·서명 방법은 그쪽 README
- 배포 결과물: `public/downloads/*.apk` + `public/app-release.json`
- 안내 페이지: `https://<도메인>/app`

앱은 화면을 스스로 그리지 않고 이 사이트를 그대로 띄운다. 따라서 **화면 수정은 앱을 다시 빌드하지 않고
이 저장소만 고치면 즉시 반영된다.** 앱을 다시 배포해야 하는 경우는 껍데기 동작(뒤로가기, 업데이트 확인 등)을
바꿀 때뿐이다.

> **앱은 접속 주소가 `judge.sw4u.kr` 로 고정되어 있다.** 이 심사 시스템을 다른 도메인에 설치했다면
> 그 APK 는 그대로 쓸 수 없고, 앱 저장소에서 주소를 바꿔 직접 빌드(+ 자기 서명 키)해야 한다.
> 반면 **PWA 는 도메인에 의존하지 않으므로** 어디에 설치하든 "홈 화면에 추가"로 바로 앱처럼 쓸 수 있다.

앱은 User-Agent 끝에 `JudgeApp/<버전>` 을 붙이고, 서버는 이걸 보고 앱 안에서
"앱 설치" 버튼과 인쇄 버튼을 감춘다 (`AppServiceProvider` 의 `$isJudgeApp`).

> nginx 에서 APK MIME 을 지정할 때 **server 블록에 `types {}` 를 쓰면 안 된다.**
> 상속된 MIME 맵 전체를 덮어써서 `sw.js` 가 `octet-stream` 이 되고 서비스워커 등록이 깨진다.
> `location ~ \.apk$` 에 `default_type` 을 주는 방식으로 좁힐 것.

---

## 9. 문제 해결

| 증상 | 확인 사항 |
|---|---|
| 화면에 500 오류 | `storage/logs/laravel.log` 확인, `storage/`·`bootstrap/cache/` 쓰기 권한 확인 |
| `.env` 수정이 반영 안 됨 | `php artisan config:clear` 또는 `config:cache` 재실행 |
| 새 라우트/뷰 수정이 반영 안 됨 | `php artisan route:clear view:clear` 또는 각각 `:cache` 재실행 |
| 폼 제출 시 "안전하지 않은 폼" 경고 | 리버스 프록시 뒤라면 `bootstrap/app.php`의 `trustProxies` 설정과 `APP_URL`이 `https://`인지 확인 |
| DB 접속 오류 | `.env`의 `DB_HOST`/`DB_PORT`/`DB_DATABASE`/계정 권한 확인, DB 서비스 기동 여부 확인 |
| 행사가 예상보다 일찍 삭제됨 | `events:prune`은 미마감 행사를 30일 후 삭제합니다 — 보관하려면 심사 종료 후 반드시 **마감 처리**하세요 |
| "앱 설치" 버튼이 안 보임 | HTTPS인지, `/manifest.json`과 `/sw.js`가 200인지 확인. iOS 사파리는 버튼 대신 "공유 → 홈 화면에 추가" 안내가 뜹니다 |
| 코드를 고쳤는데 화면이 그대로 | 서비스워커 캐시입니다. `public/sw.js`의 `SW_VERSION`을 올리세요 |
| `/app`에 "아직 게시된 앱이 없습니다" | `public/app-release.json`이 없거나, 가리키는 APK 파일이 실제로 없습니다 |
| 앱에서 오프라인 저장이 안 됨 | WebView의 DOM storage 문제입니다. `judge-app/README.md`의 실기기 확인 항목 4번 참고 |

---

## 10. 기술 스택 요약

- PHP 8.3+ / Laravel 13
- MariaDB / MySQL (Eloquent + 마이그레이션)
- Blade + Tailwind CSS(Play CDN) + Alpine.js — 빌드 스텝 없음
- 실시간 갱신: AJAX 폴링(5초 간격) — WebSocket/Reverb 불필요, 인프라 부담 최소화
- PWA: 매니페스트 + 서비스워커(정적 파일) — 번들러·Node 불필요

## 라이선스

공모전 참여를 위해 제작한 목적의 프로젝트입니다. 사용상 제한은 없지만, 소스 수정 및 재 배포는 금합니다.
