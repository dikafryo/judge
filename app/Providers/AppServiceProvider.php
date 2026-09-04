<?php

namespace App\Providers;

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
    }
}
