<?php

namespace App\Http\Middleware;

use App\Models\Event;
use App\Models\Judge;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 체험용 데모 행사(is_demo)의 모든 데이터 변경 요청을 차단한다.
 *
 * 데모는 누구나 링크만으로 들어오므로, 점수 저장·서명·설정 변경·행사 삭제가
 * 그대로 열려 있으면 샘플 데이터가 훼손된다. 조회와 출력은 그대로 허용한다.
 */
class BlockDemoWrites
{
    private const MESSAGE = '체험용 샘플 행사입니다. 화면은 실제와 동일하지만 저장·수정은 되지 않습니다.';

    public function handle(Request $request, Closure $next): Response
    {
        $event = $this->resolveEvent($request);

        if (! $event?->is_demo || $request->isMethodSafe()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => self::MESSAGE], 423);
        }

        return back()->withErrors(['demo' => self::MESSAGE]);
    }

    /** 관리자 라우트는 {event}, 심사위원 라우트는 {judge} 로 행사를 찾는다 */
    private function resolveEvent(Request $request): ?Event
    {
        $event = $request->route('event');

        if ($event instanceof Event) {
            return $event;
        }

        $judge = $request->route('judge');

        return $judge instanceof Judge ? $judge->event : null;
    }
}
