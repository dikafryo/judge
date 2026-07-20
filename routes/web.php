<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\SetupController;
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
| 심사위원 — 접속 코드 기반, 로그인 없음
|--------------------------------------------------------------------------
*/
Route::post('/judge/enter', [JudgeController::class, 'enter'])->name('judge.enter');

Route::prefix('judge/{judge:code}')->group(function () {
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

    Route::middleware('event.admin')->group(function () {
        // 기본설정 (집계 방식 / 평가 항목 / 행사 삭제)
        Route::get('/setup', [SetupController::class, 'index'])->name('admin.setup');

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
