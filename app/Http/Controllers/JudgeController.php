<?php

namespace App\Http\Controllers;

use App\Exceptions\ScoreRejected;
use App\Models\Candidate;
use App\Models\Judge;
use App\Services\JudgePayloadService;
use App\Services\ScoreWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class JudgeController extends Controller
{
    /** 홈에서 코드를 입력해 입장 */
    public function enter(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string']], [], ['code' => '심사위원 코드']);

        $judge = Judge::where('code', strtoupper(trim($request->input('code'))))->first();

        if (! $judge) {
            return back()->withErrors(['code' => '유효하지 않은 심사위원 코드입니다.']);
        }

        return redirect()->route('judge.show', $judge);
    }

    /** 심사 페이지 — 대상 목록 + 항목별 점수 입력 폼 */
    public function show(Judge $judge, JudgePayloadService $payloads): View
    {
        $event = $judge->event()->with(['candidates', 'criteria'])->firstOrFail();

        // payload 조립은 서비스에 있다 — 네이티브 앱 API 가 같은 코드를 쓴다
        $payload = $payloads->build($judge, $event) + [
            'urls' => [
                'scores'    => route('judge.scores', $judge),
                'signature' => route('judge.signature', $judge),
                'print'     => route('judge.print', $judge),
            ],
        ];

        return view('judge.evaluate', compact('judge', 'event', 'payload'));
    }

    /**
     * 점수 저장 (AJAX) — 대상 1건의 항목 점수를 통째로 교체한다.
     * 저장 규칙 자체는 ScoreWriter 에 있고 네이티브 앱 API 도 같은 코드를 쓴다.
     */
    public function storeScores(Request $request, Judge $judge, ScoreWriter $writer): JsonResponse
    {
        $event = $judge->event;

        if (! $event->is_open) {
            return response()->json(['message' => '심사가 마감되어 점수를 수정할 수 없습니다.'], 423);
        }

        $data = $request->validate([
            'candidate_id' => ['required', 'integer'],
            'scores'       => ['required', 'array', 'min:1'],
            'scores.*'     => ['nullable', 'numeric', 'min:0'],
        ]);

        $candidate = Candidate::where('event_id', $event->id)->findOrFail($data['candidate_id']);

        try {
            return response()->json($writer->save($judge, $candidate, $data['scores']));
        } catch (ScoreRejected $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /** 전자서명 저장 (AJAX) — canvas PNG dataURL */
    public function storeSignature(Request $request, Judge $judge): JsonResponse
    {
        $data = $request->validate([
            'signature' => ['required', 'string', 'starts_with:data:image/png;base64,', 'max:200000'],
        ]);

        $judge->update([
            'signature' => $data['signature'],
            'signed_at' => now(),
        ]);

        return response()->json(['message' => '서명이 저장되었습니다.']);
    }

    /** 인쇄용 개인 심사표 — 서명 삽입, 출력 후 자필 서명도 가능 */
    public function print(Judge $judge): View
    {
        $event = $judge->event()->with(['candidates', 'criteria'])->firstOrFail();

        $myScores = $judge->scores()
            ->get()
            ->groupBy('candidate_id')
            ->map(fn ($group) => $group->pluck('score', 'criterion_id'));

        return view('judge.print', compact('judge', 'event', 'myScores'));
    }
}
