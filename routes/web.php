<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\RootController;
use App\Http\Controllers\Admin\SetupController;
use App\Http\Controllers\AppDownloadController;
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
| 개인정보처리방침
|--------------------------------------------------------------------------
| 플레이스토어 등록에 공개 URL 이 필수다. 앱과 웹이 같은 서버·같은 데이터를 쓰므로
| 문서도 하나만 둔다. 시행일과 문의처는 config/judge.php 에서 온다.
*/
Route::view('/privacy', 'privacy', [
    'contactEmail' => config('judge.contact_email'),
    'effectiveDate' => config('judge.privacy_effective_date'),
])->name('privacy');

/*
|--------------------------------------------------------------------------
| 심사위원 — 접속 코드 기반, 로그인 없음
|--------------------------------------------------------------------------
*/
// 접속 코드는 6자리 숫자(90만 가지)라 대입이 가능하다. API 와 같은 제한을 건다.
Route::post('/judge/enter', [JudgeController::class, 'enter'])
    ->middleware('throttle:judge-login')
    ->name('judge.enter');

Route::prefix('judge/{judge:code}')->middleware('demo.readonly')->group(function () {
    Route::get('/', [JudgeController::class, 'show'])->name('judge.show');
    Route::post('/scores', [JudgeController::class, 'storeScores'])->name('judge.scores');       // AJAX
    Route::post('/signature', [JudgeController::class, 'storeSignature'])->name('judge.signature'); // AJAX
    Route::get('/print', [JudgeController::class, 'print'])->name('judge.print');                // 인쇄용 심사표
});

/*
|--------------------------------------------------------------------------
| 전체 관리자 — 모든 행사를 보고 정리한다
|--------------------------------------------------------------------------
| 행사마다 비밀번호가 따로라, 만든 사람이 잊으면 아무도 지울 수 없는 행사가 남는다.
| 그것을 치우기 위한 열쇠 하나. config/judge.php 의 비밀번호를 비우면 전부 404 다.
|
| 로그인은 대입이 가능하므로 심사위원 입장과 같은 제한을 건다.
*/
Route::prefix('root')->group(function () {
    Route::get('/login', [RootController::class, 'showLogin'])->name('root.login');
    Route::post('/login', [RootController::class, 'login'])
        ->middleware('throttle:root-login')
        ->name('root.login.post');
    Route::post('/logout', [RootController::class, 'logout'])->name('root.logout');

    Route::middleware('super.admin')->group(function () {
        Route::get('/', [RootController::class, 'index'])->name('root.index');
        Route::post('/{event}/enter', [RootController::class, 'enter'])->name('root.enter');
        // 삭제도 POST 다. 목록 전체가 한 폼이고 그 안에 '들어가기' 버튼이 함께 있는데,
        // @method('DELETE') 를 쓰면 숨은 _method 가 들어가기 요청까지 DELETE 로 바꿔
        // 405 가 난다. 한 폼 안의 두 목적지는 같은 메서드여야 한다.
        Route::post('/delete', [RootController::class, 'destroy'])->name('root.destroy');
    });
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
