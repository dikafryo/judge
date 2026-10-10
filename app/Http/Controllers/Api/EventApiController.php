<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEventRequest;
use App\Models\Event;
use App\Services\EventSetup;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * 행사 목록·생성. 토큰 없이 부를 수 있다 — 웹의 /events 화면이 이미 같은 목록을
 * 로그인 없이 보여주고 있어서, 앱만 감추면 같은 정보가 두 곳에서 다르게 보인다.
 *
 * 목록에는 행사명과 규모만 나가고, 들어가려면 관리 비밀번호가 필요하다.
 */
class EventApiController extends Controller
{
    public function index(): JsonResponse
    {
        // 앱 로그인 화면이 열릴 때마다 읽는 목록. 행사·대상·항목·심사위원이 바뀌면
        // Event::forgetCachesFor() 가 비우므로 숫자(규모)도 묵지 않는다.
        $events = Cache::remember(Event::LIST_CACHE_KEY, 300, fn (): array => Event::query()
            ->real()
            ->withCount(['candidates', 'criteria', 'judges'])
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (Event $event) => [
                'id' => $event->id,
                'name' => $event->name,
                'event_date' => $event->event_date?->toDateString(),
                'is_open' => $event->is_open,
                'candidates_count' => $event->candidates_count,
                'criteria_count' => $event->criteria_count,
                'judges_count' => $event->judges_count,
            ])->values()->all());

        return response()->json(['events' => $events]);
    }

    /**
     * 행사 생성. 웹과 마찬가지로 회원가입이 없고 관리 비밀번호가 유일한 열쇠다.
     * 만든 사람이 바로 이어서 설정할 수 있도록 관리자 토큰을 함께 돌려준다.
     */
    public function store(StoreEventRequest $request, EventSetup $setup): JsonResponse
    {
        $event = $setup->createEvent($request->validated());

        return response()->json([
            'token' => $event->issueAdminToken(),
            'event' => ['id' => $event->id, 'name' => $event->name, 'is_open' => $event->is_open],
        ], 201);
    }
}
