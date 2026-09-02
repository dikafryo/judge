<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
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
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
