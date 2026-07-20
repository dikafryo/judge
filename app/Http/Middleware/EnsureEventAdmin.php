<?php

namespace App\Http\Middleware;

use App\Models\Event;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 행사별 관리자 세션 확인.
 * 로그인 성공 시 session("event_admin_{id}") = true 가 세팅된다.
 */
class EnsureEventAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $event = $request->route('event');

        if (! $event instanceof Event) {
            abort(404);
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
