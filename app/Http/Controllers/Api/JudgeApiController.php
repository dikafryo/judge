<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\ScoreRejected;
use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\Judge;
use App\Services\JudgePayloadService;
use App\Services\ScoreWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 심사위원용 API. 토큰이 곧 심사위원이라 URL 에 코드가 드러나지 않는다.
 *
 * 마감 처리와 코드 회수는 웹과 동작이 다르다는 점에 주의:
 * 웹은 코드가 회수되면 라우트 바인딩이 실패해 404 가 나지만,
 * 앱은 토큰으로 붙으므로 여기서 명시적으로 423(마감) 을 돌려준다.
 */
class JudgeApiController extends Controller
{
    private function judge(Request $request): Judge
    {
        /** @var Judge $judge */
        $judge = $request->user();

        return $judge;
    }

    /** 행사·평가항목·대상·내 점수·서명 여부 — 앱이 오프라인에서 쓸 전부 */
    public function me(Request $request, JudgePayloadService $payloads): JsonResponse
    {
        $judge = $this->judge($request);
        $event = $judge->event()->with(['candidates', 'criteria'])->firstOrFail();

        return response()->json($payloads->build($judge, $event));
    }

    /**
     * 대상 1건의 점수 전체 교체.
     *
     * 웹의 POST 와 달리 부분 갱신을 두지 않는다 — 앱은 로컬에 전체 상태를 들고 있으므로
     * 말단 항목 전부를 실어 보낸다. 이렇게 하면 'null 은 삭제' 규칙이 모호해지지 않고,
     * 오프라인 큐가 재전송해도 결과가 같다.
     */
    public function storeScores(Request $request, Candidate $candidate, ScoreWriter $writer): JsonResponse
    {
        $judge = $this->judge($request);
        $event = $judge->event;

        if (! $event->is_open) {
            return response()->json(['message' => '심사가 마감되어 점수를 수정할 수 없습니다.'], 423);
        }

        if ($candidate->event_id !== $event->id) {
            return response()->json(['message' => '이 행사의 평가 대상이 아닙니다.'], 404);
        }

        $data = $request->validate([
            'scores'   => ['required', 'array', 'min:1'],
            'scores.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            return response()->json($writer->save($judge, $candidate, $data['scores']));
        } catch (ScoreRejected $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /** 전자서명 (PNG dataURL) */
    public function storeSignature(Request $request): JsonResponse
    {
        $data = $request->validate([
            'signature' => ['required', 'string', 'starts_with:data:image/png;base64,', 'max:200000'],
        ]);

        $judge = $this->judge($request);
        $judge->update(['signature' => $data['signature'], 'signed_at' => now()]);

        return response()->json([
            'message'   => '서명이 저장되었습니다.',
            'signed_at' => $judge->signed_at?->toIso8601String(),
        ]);
    }
}
