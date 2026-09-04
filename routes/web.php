<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\AppDownloadController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\SetupController;
use App\Http\Controllers\DemoController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\JudgeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| 홈(심사위원 입장) / 행사 관리(목록·생성)
|--------------------------------------------------------------------------
*/
Route::get('/', [EventController::class, 'home'])->name('home');
Route::get('/events', [EventController::class, 'index'])->name('events.index');
Route::post('/events', [EventController::class, 'store'])->name('events.store');

/*
|--------------------------------------------------------------------------
| 체험(데모) — 로그인 없이 샘플 행사를 둘러보는 공개 페이지
|--------------------------------------------------------------------------
*/
Route::get('/demo', [DemoController::class, 'index'])->name('demo');
Route::get('/demo/admin', [DemoController::class, 'admin'])->name('demo.admin');
Route::get('/demo/print', [DemoController::class, 'print'])->name('demo.print');

/*
|--------------------------------------------------------------------------
| CSRF 토큰 재발급 — PWA 오프라인 큐 전용
|--------------------------------------------------------------------------
| 서비스워커가 캐시해 둔 화면으로 접속하면 HTML 안의 토큰이 이미 만료됐을 수 있다.
| 오프라인에 쌓인 점수·서명을 재전송하기 직전에 현재 세션 토큰을 다시 받아간다.
*/
Route::get('/csrf', fn () => response()->json(['token' => csrf_token()]))->name('csrf.token');

/*
|--------------------------------------------------------------------------
| 안드로이드 앱 내려받기 안내
|--------------------------------------------------------------------------
| APK 파일 자체는 nginx 가 public/downloads/ 에서 정적으로 내보낸다.
*/
Route::get('/app', [AppDownloadController::class, 'index'])->name('app.download');

/*
|--------------------------------------------------------------------------
| 심사위원 — 접속 코드 기반, 로그인 없음
|--------------------------------------------------------------------------
*/
Route::post('/judge/enter', [JudgeController::class, 'enter'])->name('judge.enter');

Route::prefix('judge/{judge:code}')->middleware('demo.readonly')->group(function () {
    Route::get('/', [JudgeController::class, 'show'])->name('judge.show');
    Route::post('/scores', [JudgeController::class, 'storeScores'])->name('judge.scores');       // AJAX
    Route::post('/signature', [JudgeController::class, 'storeSignature'])->name('judge.signature'); // AJAX
    Route::get('/print', [JudgeController::class, 'print'])->name('judge.print');                // 인쇄용 심사표
});

/*
|--------------------------------------------------------------------------
| 관리자 — 행사별 비밀번호 세션 인증 (별도 회원가입/로그인 없음)
|--------------------------------------------------------------------------
*/
Route::prefix('admin/{event}')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('admin.login');
    Route::post('/login', [AuthController::class, 'login'])->name('admin.login.post');
    Route::post('/logout', [AuthController::class, 'logout'])->name('admin.logout');

    Route::middleware(['event.admin', 'demo.readonly'])->group(function () {
        // 기본설정 (집계 방식 / 최종집계표 서명 / 행사 삭제)
        Route::get('/setup', [SetupController::class, 'index'])->name('admin.setup');

        // 평가 항목 관리 페이지
        Route::get('/criteria', [SetupController::class, 'criteria'])->name('admin.criteria');

        // 평가 대상 / 심사위원 관리 페이지
        Route::get('/candidates', [SetupController::class, 'candidates'])->name('admin.candidates');
        Route::get('/judges', [SetupController::class, 'judges'])->name('admin.judges');

        // 데이터 변경 라우트 — 심사 마감 시 전부 차단 (event.open)
        Route::middleware('event.open')->group(function () {
            Route::post('/candidates', [SetupController::class, 'storeCandidates'])->name('admin.candidates.store');
            Route::delete('/candidates/{candidate}', [SetupController::class, 'destroyCandidate'])->name('admin.candidates.destroy');
            Route::post('/criteria', [SetupController::class, 'storeCriterion'])->name('admin.criteria.store');
            Route::delete('/criteria/{criterion}', [SetupController::class, 'destroyCriterion'])->name('admin.criteria.destroy');
            Route::post('/judges', [SetupController::class, 'storeJudges'])->name('admin.judges.store');
            Route::delete('/judges/{judge}', [SetupController::class, 'destroyJudge'])->name('admin.judges.destroy');
            Route::post('/scoring-method', [SetupController::class, 'updateScoringMethod'])->name('admin.scoring-method');
        });

        Route::get('/judges/print', [SetupController::class, 'printJudges'])->name('admin.judges.print');           // QR·코드 접속안내 출력
        Route::get('/judges/{judge}/sheet', [DashboardController::class, 'printJudgeSheet'])->name('admin.judge.sheet'); // 심사위원별 개별심사표
        Route::post('/toggle-open', [SetupController::class, 'toggleOpen'])->name('admin.toggle-open');             // 재개는 마감 상태에서도 가능
        Route::post('/report-signers', [SetupController::class, 'updateReportSigners'])->name('admin.report-signers'); // 최종집계표 결재란 — 마감 후 출력 전에도 입력 가능
        Route::delete('/', [SetupController::class, 'destroyEvent'])->name('admin.destroy');                        // 행사 삭제는 마감 상태에서도 가능

        // 대시보드 (집계)
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');
        Route::get('/dashboard/data', [DashboardController::class, 'data'])->name('admin.dashboard.data'); // AJAX 폴링
        Route::get('/export', [DashboardController::class, 'exportCsv'])->name('admin.export');            // CSV 다운로드
        Route::get('/print', [DashboardController::class, 'print'])->name('admin.print');                  // 최종결과 결재 출력
    });
});
