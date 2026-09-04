<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // 네이티브 앱 전용. 웹은 세션+CSRF, 앱은 Bearer 토큰으로 완전히 분리한다.
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // NPM(리버스 프록시) 뒤에서 서빙됨 — X-Forwarded-Proto 를 신뢰해야
        // route()/asset() 이 https URL을 생성한다 (없으면 혼합 콘텐츠/비보안 폼 경고 발생).
        // 내부 nginx가 realip 모듈(real_ip_header X-Forwarded-For)로 REMOTE_ADDR을
        // "실제 방문자 IP"로 복원하므로 프록시 IP 목록 방식은 쓸 수 없다 → '*' 가 정답.
        // 외부 유입은 80/443을 독점한 NPM뿐이고 NPM이 X-Forwarded-Proto를 덮어쓰므로 스푸핑 불가.
        $middleware->trustProxies(at: '*');

        // 행사별 관리자 세션 미들웨어 별칭
        $middleware->alias([
            'event.admin' => \App\Http\Middleware\EnsureEventAdmin::class,
            'event.open'  => \App\Http\Middleware\EnsureEventOpen::class,
            'demo.readonly' => \App\Http\Middleware\BlockDemoWrites::class,

            // Sanctum 토큰 능력 검사 — 심사위원 토큰(judge)과 관리자 토큰(admin)을 가른다.
            // Laravel 12+ 는 이 별칭을 자동 등록하지 않으므로 직접 넣어야 한다.
            'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            'ability'   => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
