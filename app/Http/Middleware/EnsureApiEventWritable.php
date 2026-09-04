<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Event;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 네이티브 앱의 설정 변경 가드.
 *
 * 웹은 라우트의 {event} 를 보는 미들웨어 두 개(demo.readonly, event.open)로 같은 일을 하는데,
 * 앱은 토큰 자체가 행사라 그 미들웨어를 쓸 수 없다. 대신 여기서 같은 규칙을 적용하고
 * **문구는 웹 미들웨어의 상수를 그대로 가져다 쓴다** — 두 경로가 다르게 말하면 안 된다.
 *
 * 심사 재개(toggle-open)는 마감 상태에서도 되어야 하므로 'closed-ok' 로 예외를 준다.
 */
class EnsureApiEventWritable
{
    public function handle(Request $request, Closure $next, ?string $mode = null): Response
    {
        $event = $request->user();

        if (! $event instanceof Event) {
            abort(401);
        }

        if ($event->is_demo) {
            return response()->json(['message' => BlockDemoWrites::MESSAGE], 423);
        }

        if (! $event->is_open && $mode !== 'closed-ok') {
            return response()->json(['message' => EnsureEventOpen::MESSAGE], 423);
        }

        return $next($request);
    }
}
