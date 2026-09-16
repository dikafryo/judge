<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * 사이트 전체 관리자.
 *
 * 이 앱에는 회원 계정이 없고 행사마다 비밀번호가 따로 있다. 그래서 누가 만들어 두고
 * 비밀번호를 잊은 테스트 행사는 **아무도 지울 수 없는 상태**로 남았다. 그것을 치우고
 * 행사 상태를 들여다보기 위한 열쇠가 하나 필요하다.
 *
 * 비밀번호는 설정에서 온다. 없으면 기능 자체가 없는 것으로 친다 — 쓰지 않는 설치본에
 * 입구만 남겨 두면 대입 공격의 과녁이 될 뿐이다.
 */
class SuperAdmin
{
    /** 전체 관리자로 인증했다는 표시 */
    public const SESSION_KEY = 'super_admin';

    public function isEnabled(): bool
    {
        return $this->secret() !== '';
    }

    public function isSignedIn(Request $request): bool
    {
        return $request->session()->get(self::SESSION_KEY, false) === true;
    }

    /**
     * 비밀번호 확인.
     *
     * 설정값이 해시면 해시로, 평문이면 시간이 일정한 비교로 본다.
     * 평문 비교에 `===` 를 쓰면 응답 시간 차이로 앞자리를 알아낼 수 있다.
     */
    public function matches(string $password): bool
    {
        $secret = $this->secret();

        if ($secret === '') {
            return false;
        }

        if (str_starts_with($secret, '$2y$') || str_starts_with($secret, '$argon')) {
            return Hash::check($password, $secret);
        }

        return hash_equals($secret, $password);
    }

    public function signIn(Request $request): void
    {
        // 로그인 전 세션 ID 를 그대로 쓰면 세션 고정 공격에 열린다
        $request->session()->regenerate();
        $request->session()->put(self::SESSION_KEY, true);
    }

    public function signOut(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }

    /**
     * 전체 관리자가 행사 하나를 열 수 있게 그 행사의 관리자 세션을 준다.
     *
     * 행사 비밀번호를 우회하는 유일한 통로다. 로그인 화면과 같은 세션 키를 쓰므로
     * 들어간 뒤의 화면·권한은 원래 관리자와 완전히 같다.
     */
    public function grantEventAccess(Request $request, Event $event): void
    {
        $request->session()->put($event->adminSessionKey(), true);
    }

    private function secret(): string
    {
        return trim((string) config('judge.super_admin_password'));
    }
}
