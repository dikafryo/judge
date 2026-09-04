<?php

namespace App\Http\Middleware;

use App\Models\Event;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 행사별 관리자 세션 확인.
 * 로그인 성공 시 session("event_admin_{id}") = true 가 세팅된다.
 *
 * 예외가 하나 있다: **유효한 서명이 붙은 GET 요청.**
 * 네이티브 앱은 세션이 없어 인쇄물(최종집계표·CSV·심사위원 카드)을 시스템 브라우저로 넘기는데,
 * 그 브라우저에도 세션이 없다. 그래서 앱이 관리자 토큰으로 단기 서명 URL 을 받아 열게 한다.
 * GET 으로 한정하는 이유 — 서명 URL 이 설정 변경·삭제까지 대신하게 두면 안 된다.
 */
class EnsureEventAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $event = $request->route('event');

        if (! $event instanceof Event) {
            abort(404);
        }

        if ($request->isMethod('GET') && $request->hasValidSignature()) {
            return $next($request);
        }

        if (! $request->session()->get('event_admin_' . $event->id, false)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => '관리자 인증이 필요합니다.'], 401);
            }

            return redirect()->route('admin.login', $event);
        }

        return $next($request);
    }
}
