<?php

use App\Http\Controllers\Api\AdminApiController;
use App\Http\Controllers\Api\JudgeApiController;
use App\Http\Controllers\Api\SessionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| 네이티브 앱 전용 API (/api/v1)
|--------------------------------------------------------------------------
| 웹(routes/web.php)은 세션 + CSRF 로 동작하고, 여기는 Sanctum Bearer 토큰만 쓴다.
| 두 계층은 같은 서비스·모델을 쓰되 인증 방식만 다르다.
|
| 접속 코드는 6자리 숫자(90만 가지)라 대입이 가능하다. 세션 발급에 반드시
| 레이트 리밋을 건다 — 이게 이 API 의 유일한 관문이다.
*/

Route::prefix('v1')->group(function () {

    Route::get('/meta', [SessionController::class, 'meta'])->name('api.meta');

    // 로그인 시도는 분당 5회로 묶는다. 웹의 /judge/enter 에도 같은 제한을 건다.
    Route::middleware('throttle:5,1')->group(function () {
        Route::post('/judge/session', [SessionController::class, 'judge'])->name('api.judge.session');
        Route::post('/admin/session', [SessionController::class, 'admin'])->name('api.admin.session');
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::delete('/session', [SessionController::class, 'destroy'])->name('api.session.destroy');

        /* 심사위원 — 토큰이 곧 심사위원이라 URL 에 코드가 드러나지 않는다 */
        Route::middleware('abilities:judge')->group(function () {
            Route::get('/judge/me', [JudgeApiController::class, 'me'])->name('api.judge.me');
            Route::put('/judge/candidates/{candidate}/scores', [JudgeApiController::class, 'storeScores'])->name('api.judge.scores');
            Route::put('/judge/signature', [JudgeApiController::class, 'storeSignature'])->name('api.judge.signature');
        });

        /* 관리자 */
        Route::middleware('abilities:admin')->prefix('admin')->group(function () {
            Route::get('/event', [AdminApiController::class, 'show'])->name('api.admin.event');
            Route::get('/dashboard', [AdminApiController::class, 'dashboard'])->name('api.admin.dashboard');
            Route::get('/print-url', [AdminApiController::class, 'printUrl'])->name('api.admin.print-url');
        });
    });
});
