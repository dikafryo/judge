<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Models\Judge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
    public function show(Judge $judge): View
    {
        $event = $judge->event()->with(['candidates', 'criteria'])->firstOrFail();

        // 이미 입력한 점수: { candidate_id: { criterion_id: score } }
        $myScores = $judge->scores()
            ->get()
            ->groupBy('candidate_id')
            ->map(fn ($group) => $group->pluck('score', 'criterion_id'));

        // 평가항목 2단계 구조: 대분류(items = 서브항목들 또는 자기 자신) — 채점은 말단(items)에서만
        $byParent = $event->criteria->groupBy('parent_id');
        $mapItem  = fn ($c) => [
            'id'          => $c->id,
            'name'        => $c->name,
            'description' => $c->description,
            'max_score'   => (int) $c->max_score,
        ];
        $groups = $event->criteria->whereNull('parent_id')->values()->map(function ($top) use ($byParent, $mapItem) {
            $children = $byParent->get($top->id, collect());

            return [
                'id'           => $top->id,
                'name'         => $top->name,
                'max_score'    => (int) $top->max_score,
                'has_children' => $children->isNotEmpty(),
                'items'        => ($children->isEmpty() ? collect([$top]) : $children)->map($mapItem)->values(),
            ];
        })->values();

        $payload = [
            'judge' => ['id' => $judge->id, 'name' => $judge->name, 'code' => $judge->code],
            'event' => [
                'name'     => $event->name,
                'is_open'  => $event->is_open,
                'is_blind' => $event->is_blind,
            ],
            'groups' => $groups,
            // 심사위원 화면 노출 설정 — 블라인드면 심사번호만 (이름은 payload에도 싣지 않는다), 이름 공개면 이름·소속 포함
            'candidates' => $event->candidates->values()->map(fn ($c, $i) => array_merge([
                'id'     => $c->id,
                'number' => sprintf('%02d', $i + 1),
            ], $event->is_blind ? [] : [
                'name'        => $c->name,
                'affiliation' => $c->affiliation,
            ]))->values(),
            'scores'        => $myScores,
            'hasSignature'  => ! empty($judge->signature),
            'totalMax'      => (int) $event->criteria->whereNull('parent_id')->sum('max_score'),
            'urls'          => [
                'scores'    => route('judge.scores', $judge),
                'signature' => route('judge.signature', $judge),
                'print'     => route('judge.print', $judge),
            ],
        ];

        return view('judge.evaluate', compact('judge', 'event', 'payload'));
    }

    /**
     * 점수 저장 (AJAX) — 대상 1건의 항목 점수를 upsert. 부분 제출 허용:
     * 값이 있는 항목만 저장하고, 비워서 제출한 항목은 기존 저장값을 삭제한다
     * (마지막 제출 상태가 그대로 최종 점수).
     * 요청 형식: { candidate_id: 1, scores: { "<criterion_id>": 점수|null, ... } }
     */
    public function storeScores(Request $request, Judge $judge): JsonResponse
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

        // 대상이 같은 행사 소속인지 검증
        $candidate = Candidate::where('event_id', $event->id)->findOrFail($data['candidate_id']);

        // 항목이 이 행사의 말단 항목(채점 대상)인지 + 배점 초과 여부 검증
        $leaves = $event->leafCriteria()->keyBy('id');
        $filled = 0;

        foreach ($data['scores'] as $criterionId => $value) {
            $criterion = $leaves->get((int) $criterionId);

            if (! $criterion) {
                return response()->json(['message' => '잘못된 평가 항목이 포함되어 있습니다.'], 422);
            }
            if ($value === null) {
                continue;
            }
            if ($value > $criterion->max_score) {
                return response()->json([
                    'message' => "'{$criterion->name}' 항목은 배점 {$criterion->max_score}점을 초과할 수 없습니다.",
                ], 422);
            }
            $filled++;
        }

        if ($filled === 0) {
            return response()->json(['message' => '최소 한 개 항목 이상 점수를 입력해 주세요.'], 422);
        }

        DB::transaction(function () use ($judge, $candidate, $data) {
            foreach ($data['scores'] as $criterionId => $value) {
                if ($value === null) {
                    // 비워서 제출한 항목은 기존 점수 삭제 — 제출 상태 = 최종 상태
                    $judge->scores()
                        ->where('candidate_id', $candidate->id)
                        ->where('criterion_id', (int) $criterionId)
                        ->delete();

                    continue;
                }

                $judge->scores()->updateOrCreate(
                    ['candidate_id' => $candidate->id, 'criterion_id' => (int) $criterionId],
                    ['score' => $value],
                );
            }
        });

        $total = (float) $judge->scores()->where('candidate_id', $candidate->id)->sum('score');

        // 블라인드면 응답 메시지에도 이름 대신 심사번호만 사용
        $number = $event->candidateNumbers()[$candidate->id] ?? $candidate->id;
        $label  = $event->is_blind ? "심사번호 {$number}" : "{$number}. {$candidate->name}";

        return response()->json([
            'message'      => "{$label} 점수가 저장되었습니다.",
            'candidate_id' => $candidate->id,
            'total'        => $total,
        ]);
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
