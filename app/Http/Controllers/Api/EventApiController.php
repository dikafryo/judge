<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

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
        $events = Event::query()
            ->where('is_demo', false)   // 체험용 샘플은 웹의 /demo 에서만 안내한다
            ->withCount(['candidates', 'criteria', 'judges'])
            ->latest()
            ->limit(50)
            ->get();

        return response()->json([
            'events' => $events->map(fn (Event $event) => [
                'id'               => $event->id,
                'name'             => $event->name,
                'event_date'       => $event->event_date?->toDateString(),
                'is_open'          => $event->is_open,
                'candidates_count' => $event->candidates_count,
                'criteria_count'   => $event->criteria_count,
                'judges_count'     => $event->judges_count,
            ])->values(),
        ]);
    }

    /**
     * 행사 생성. 웹과 마찬가지로 회원가입이 없고 관리 비밀번호가 유일한 열쇠다.
     * 만든 사람이 바로 이어서 설정할 수 있도록 관리자 토큰을 함께 돌려준다.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'           => ['required', 'string', 'max:100'],
            'description'    => ['nullable', 'string', 'max:2000'],
            'event_date'     => ['nullable', 'date'],
            'admin_password' => ['required', 'string', 'min:4', 'max:50'],
        ]);

        $event = Event::create([
            'name'           => $data['name'],
            'description'    => $data['description'] ?? null,
            'event_date'     => $data['event_date'] ?? null,
            'admin_password' => Hash::make($data['admin_password']),
        ]);

        return response()->json([
            'token' => $event->createToken('judge-app-admin', ['admin'])->plainTextToken,
            'event' => ['id' => $event->id, 'name' => $event->name, 'is_open' => $event->is_open],
        ], 201);
    }
}
