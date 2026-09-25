<?php

use App\Exceptions\ScoreRejected;
use App\Exceptions\SetupRejected;
use App\Http\Middleware\BlockDemoWrites;
use App\Http\Middleware\EnsureApiEventWritable;
use App\Http\Middleware\EnsureEventAdmin;
use App\Http\Middleware\EnsureEventOpen;
use App\Http\Middleware\EnsureSuperAdmin;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
        // 경로: Cloudflare → NPM(80/443) → nginx(realip) → phpfpm.
        // nginx 의 realip 은 172.18.0.0/16(NPM)만 걷어내므로 REMOTE_ADDR 은 Cloudflare 엣지 IP 가 되고,
        // 실제 방문자는 X-Forwarded-For 의 그 앞 칸에 있다. 그래서 Laravel 이 한 번 더 걷어내야 한다.
        //
        // 예전의 '*'(REMOTE_ADDR 을 무조건 신뢰)는 Cloudflare 를 거친 요청엔 맞지만,
        // 원서버에 직접 붙는 요청이 X-Forwarded-For 를 지어내면 request()->ip() 가 그 값이 된다
        // → IP 기준 로그인 제한을 IP 를 바꿔 가며 우회할 수 있었다.
        // 이제 Cloudflare 대역과 내부망(LAN·tailscale·docker·loopback)만 프록시로 신뢰한다.
        // 목록 출처: https://www.cloudflare.com/ips/ (대역이 바뀌면 여기만 고친다)
        $middleware->trustProxies(at: [
            // Cloudflare IPv4
            '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
            '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
            '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
            '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
            // Cloudflare IPv6
            '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32',
            '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
            // 내부망 — LAN·docker(172.16/12 에 webserver-net 포함)·tailscale(100.64/10)·loopback
            '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', '100.64.0.0/10', '127.0.0.1', '::1',
        ]);

        // 행사별 관리자 세션 미들웨어 별칭
        $middleware->alias([
            'event.admin' => EnsureEventAdmin::class,
            'super.admin' => EnsureSuperAdmin::class,
            'event.open' => EnsureEventOpen::class,
            'demo.readonly' => BlockDemoWrites::class,

            // 앱 전용 — 토큰이 곧 행사라 위 두 미들웨어(라우트의 {event} 를 봄)를 쓸 수 없다.
            'api.writable' => EnsureApiEventWritable::class,

            // Sanctum 토큰 능력 검사 — 심사위원 토큰(judge)과 관리자 토큰(admin)을 가른다.
            // Laravel 12+ 는 이 별칭을 자동 등록하지 않으므로 직접 넣어야 한다.
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // 도메인 규칙 위반을 한 곳에서 응답으로 옮긴다.
        // 규칙은 서비스(EventSetup·ScoreWriter)에만 있고, 표현만 여기서 갈린다 —
        // 웹은 폼으로 되돌리고 앱은 422 JSON 을 받는다.

        $exceptions->render(function (SetupRejected $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
            }

            return back()->withErrors($e->errors())->withInput();
        });

        // 점수 저장은 웹도 AJAX 라 양쪽 모두 JSON 이다.
        $exceptions->render(fn (ScoreRejected $e) => response()->json(['message' => $e->getMessage()], 422));

        // 앱(api/*)이 받는 오류 문구 — 앱은 message 를 그대로 사용자에게 보여 준다.
        // 프레임워크 기본값은 영어이거나("Unauthenticated.") 모델 클래스명까지 드러낸다.

        // 토큰이 없거나 폐기됨. 심사 마감·코드 재발급 때 서버가 심사위원 토큰을 지우므로
        // 사용자가 "왜 튕겼는지" 알 수 있게 이유를 적는다.
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => '접속이 만료되었습니다. 심사가 마감되었거나 코드가 바뀌었습니다.'], 401);
            }

            return null;
        });

        // 라우트 모델 바인딩 실패(ModelNotFoundException 은 여기 오기 전에 404 로 바뀐다)와 없는 경로.
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => '대상을 찾을 수 없습니다. 삭제되었을 수 있습니다.'], 404);
            }

            return null;
        });

        // 로그인 제한에 걸림. Retry-After 등 헤더는 그대로 살린다 — 앱이 대기 시간을 읽을 수 있게.
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(
                    ['message' => '시도가 너무 많습니다. 잠시 후 다시 시도해 주세요.'],
                    429,
                    $e->getHeaders(),
                );
            }

            return null;
        });
    })->create();
