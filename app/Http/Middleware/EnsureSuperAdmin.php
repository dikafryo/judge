<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\SuperAdmin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 전체 관리자 세션 확인.
 *
 * 비밀번호가 설정돼 있지 않으면 로그인 화면조차 열리지 않는다(404). 쓰지 않는
 * 설치본에 입구만 남겨 두면 대입 공격의 과녁이 될 뿐이다.
 */
class EnsureSuperAdmin
{
    public function __construct(private readonly SuperAdmin $superAdmin) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->superAdmin->isEnabled(), 404);

        if (! $this->superAdmin->isSignedIn($request)) {
            return redirect()->route('root.login');
        }

        return $next($request);
    }
}
