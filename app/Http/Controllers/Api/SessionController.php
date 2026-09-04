<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Judge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * 네이티브 앱의 로그인 — 접속 코드/비밀번호를 Bearer 토큰으로 교환한다.
 *
 * 코드를 URL 에 계속 싣는 웹 방식과 달리, 앱은 토큰 하나만 들고 다닌다.
 * 이 두 엔드포인트에는 routes/api.php 에서 throttle:5,1 이 걸려 있다 —
 * 6자리 숫자 코드는 대입이 가능하므로 이 제한이 사실상 유일한 방어선이다.
 */
class SessionController extends Controller
{
    /** 앱이 붙어도 되는 서버인지 확인하는 용도 */
    public function meta(): JsonResponse
    {
        return response()->json([
            'api_version'   => 'v1',
            // 서버 API 가 바뀌었는데 옛 앱이 조용히 오작동하는 것을 막는다
            'min_app_build' => 1,
        ]);
    }

    /** 심사위원 접속 코드 → 토큰 */
    public function judge(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:16']]);

        $judge = Judge::where('code', trim($data['code']))->first();

        if (! $judge) {
            return response()->json(['message' => '유효하지 않은 심사위원 코드입니다.'], 422);
        }

        // 코드는 심사 마감 시 회수되므로, 코드가 살아 있다 = 아직 유효한 심사다
        $token = $judge->createToken('judge-app', ['judge'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'judge' => ['id' => $judge->id, 'name' => $judge->name],
            'event' => ['id' => $judge->event->id, 'name' => $judge->event->name],
        ]);
    }

    /** 행사 관리 비밀번호 → 토큰 */
    public function admin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event_id' => ['required', 'integer'],
            'password' => ['required', 'string'],
        ]);

        $event = Event::find($data['event_id']);

        if (! $event || ! Hash::check($data['password'], $event->admin_password)) {
            return response()->json(['message' => '행사 또는 비밀번호가 올바르지 않습니다.'], 422);
        }

        $token = $event->createToken('judge-app-admin', ['admin'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'event' => ['id' => $event->id, 'name' => $event->name, 'is_open' => $event->is_open],
        ]);
    }

    /** 로그아웃 — 지금 쓰고 있는 토큰만 폐기한다 */
    public function destroy(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => '로그아웃되었습니다.']);
    }
}
