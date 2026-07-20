<?php

namespace App\Http\Middleware;

use App\Models\Event;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 심사 마감(is_open=false) 시 해당 행사의 데이터 변경 요청을 차단.
 * 기본설정·평가대상·심사위원·평가항목의 추가/수정/삭제 라우트에 적용한다.
 * (심사 재개 토글과 행사 삭제는 제외 — 마감 상태에서도 가능해야 함)
 */
class EnsureEventOpen
{
    public function handle(Request $request, Closure $next): Response
    {
        $event = $request->route('event');

        if (! $event instanceof Event) {
            abort(404);
        }

        if (! $event->is_open) {
            $message = '심사가 마감되어 수정할 수 없습니다. 수정하려면 먼저 심사를 재개하세요.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 423);
            }

            return back()->withErrors(['event' => $message]);
        }

        return $next($request);
    }
}
