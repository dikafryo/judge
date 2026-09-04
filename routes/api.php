<?php

use App\Http\Controllers\Api\AdminApiController;
use App\Http\Controllers\Api\EventApiController;
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

    // 웹의 /events 화면과 같은 목록. 앱 관리자 로그인에서 행사를 고르는 데 쓴다.
    Route::get('/events', [EventApiController::class, 'index'])->name('api.events');

    // 로그인 시도는 분당 5회로 묶는다. 웹의 /judge/enter 에도 같은 제한을 건다.
    Route::middleware('throttle:5,1')->group(function () {
        Route::post('/judge/session', [SessionController::class, 'judge'])->name('api.judge.session');
        Route::post('/admin/session', [SessionController::class, 'admin'])->name('api.admin.session');

        // 생성도 같은 제한 아래 둔다 — 열린 엔드포인트라 방치하면 행사 목록이 쓰레기로 찬다.
        Route::post('/events', [EventApiController::class, 'store'])->name('api.events.store');
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::delete('/session', [SessionController::class, 'destroy'])->name('api.session.destroy');

        /* 심사위원 — 토큰이 곧 심사위원이라 URL 에 코드가 드러나지 않는다 */
        Route::middleware('abilities:judge')->group(function () {
            Route::get('/judge/me', [JudgeApiController::class, 'me'])->name('api.judge.me');
            Route::put('/judge/candidates/{candidate}/scores', [JudgeApiController::class, 'storeScores'])->name('api.judge.scores');
            Route::put('/judge/signature', [JudgeApiController::class, 'storeSignature'])->name('api.judge.signature');
        });

        /* 관리자 — 토큰이 곧 행사라 URL 에 행사 id 를 싣지 않는다 */
        Route::middleware('abilities:admin')->prefix('admin')->group(function () {
            Route::get('/event', [AdminApiController::class, 'show'])->name('api.admin.event');
            Route::get('/setup', [AdminApiController::class, 'setup'])->name('api.admin.setup');
            Route::get('/dashboard', [AdminApiController::class, 'dashboard'])->name('api.admin.dashboard');
            Route::get('/print-url', [AdminApiController::class, 'printUrl'])->name('api.admin.print-url');

            // 재개는 마감 상태에서도 돼야 한다. 체험용 행사는 어느 쪽이든 막힌다.
            Route::post('/toggle-open', [AdminApiController::class, 'toggleOpen'])
                ->middleware('api.writable:closed-ok')->name('api.admin.toggle-open');

            // 설정 변경은 마감되면 막힌다 — 웹(event.open)과 같은 규칙이다.
            Route::middleware('api.writable')->group(function () {
                Route::put('/scoring-method', [AdminApiController::class, 'updateScoringMethod'])->name('api.admin.scoring-method');

                Route::post('/criteria', [AdminApiController::class, 'storeCriterion'])->name('api.admin.criteria.store');
                Route::delete('/criteria/{criterion}', [AdminApiController::class, 'destroyCriterion'])->name('api.admin.criteria.destroy');

                Route::post('/candidates', [AdminApiController::class, 'storeCandidates'])->name('api.admin.candidates.store');
                Route::delete('/candidates/{candidate}', [AdminApiController::class, 'destroyCandidate'])->name('api.admin.candidates.destroy');

                Route::post('/judges', [AdminApiController::class, 'storeJudges'])->name('api.admin.judges.store');
                Route::delete('/judges/{judge}', [AdminApiController::class, 'destroyJudge'])->name('api.admin.judges.destroy');
            });
        });
    });
});
