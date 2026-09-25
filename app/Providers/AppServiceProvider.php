<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 안드로이드 앱(Flutter WebView)은 User-Agent 끝에 'JudgeApp/<버전>' 을 붙인다.
        // 앱 안에서는 "앱 설치" 버튼과 인쇄 버튼을 감추는 데만 쓴다.
        View::share('isJudgeApp', str_contains(request()->userAgent() ?? '', 'JudgeApp/'));

        $this->configureRateLimiting();
    }

    /**
     * 로그인·생성 제한.
     *
     * 예전엔 모두 익명 throttle:5,1 이라 IP 하나에 한 통이었다 — 공용 IP 하나를 쓰는 행사장에서
     * 여섯 번째 심사위원이 막히고, 심사위원 입장이 관리자 로그인까지 막았다.
     * 이제 용도마다 통을 나누고(이름이 키에 들어간다), IP 제한은 넉넉히 두되
     * 대입 대상(코드·행사)마다 따로 좁게 묶는다.
     */
    private function configureRateLimiting(): void
    {
        // 심사위원 입장 — 앱(/api/v1/judge/session)과 웹(/judge/enter) 공통
        RateLimiter::for('judge-login', fn (Request $request): array => [
            Limit::perMinute(60)->by('ip:'.$request->ip()),
            Limit::perMinute(10)->by('code:'.self::inputKey($request, 'code')),
        ]);

        // 앱 관리자 로그인 — 행사별 비밀번호 대입을 행사 단위로 묶는다
        RateLimiter::for('admin-login', fn (Request $request): array => [
            Limit::perMinute(30)->by('ip:'.$request->ip()),
            Limit::perMinute(10)->by('event:'.self::inputKey($request, 'event_id')),
        ]);

        // 행사 생성 — 열린 엔드포인트라 방치하면 목록이 쓰레기로 찬다
        RateLimiter::for('event-create', fn (Request $request): Limit => Limit::perMinute(10)->by('ip:'.$request->ip()));

        // 전체 관리자 로그인 — 비밀번호 하나가 모든 행사의 열쇠라 예전처럼 좁게 둔다
        RateLimiter::for('root-login', fn (Request $request): Limit => Limit::perMinute(5)->by('ip:'.$request->ip()));
    }

    /** 제한 키로 쓸 입력값. 문자열·숫자가 아니면(배열 등) 한 통으로 모은다. */
    private static function inputKey(Request $request, string $field): string
    {
        $value = $request->input($field);

        return is_string($value) || is_int($value) ? trim((string) $value) : '';
    }
}
