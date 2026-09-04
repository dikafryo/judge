<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Candidate;
use App\Models\Judge;
use App\Exceptions\ScoreRejected;
use Illuminate\Support\Facades\DB;

/**
 * 점수 저장 — 웹과 앱이 공유한다.
 *
 * 저장 의미: **대상 1건의 점수 전체 교체.** 값이 있는 항목은 저장하고, null 로 온 항목은
 * 기존 점수를 지운다. 즉 마지막 제출 상태가 그대로 최종 상태다.
 * `(judge_id, candidate_id, criterion_id)` UNIQUE 인덱스 + updateOrCreate + 트랜잭션이라
 * 같은 요청을 몇 번을 보내도 결과가 같다 — 오프라인 큐 재전송이 안전한 근거다.
 */
class ScoreWriter
{
    /**
     * @param  array<int|string, float|int|string|null>  $scores  criterion_id => 점수|null
     * @return array{message: string, candidate_id: int, total: float, updated_at: ?string}
     *
     * @throws ScoreRejected 배점 초과·잘못된 항목·전부 빈 값
     */
    public function save(Judge $judge, Candidate $candidate, array $scores): array
    {
        $event  = $judge->event;
        $leaves = $event->leafCriteria()->keyBy('id');
        $filled = 0;

        foreach ($scores as $criterionId => $value) {
            $criterion = $leaves->get((int) $criterionId);

            if (! $criterion) {
                throw new ScoreRejected('잘못된 평가 항목이 포함되어 있습니다.');
            }
            if ($value === null) {
                continue;
            }
            if ($value > $criterion->max_score) {
                throw new ScoreRejected("'{$criterion->name}' 항목은 배점 {$criterion->max_score}점을 초과할 수 없습니다.");
            }
            $filled++;
        }

        if ($filled === 0) {
            throw new ScoreRejected('최소 한 개 항목 이상 점수를 입력해 주세요.');
        }

        DB::transaction(function () use ($judge, $candidate, $scores) {
            foreach ($scores as $criterionId => $value) {
                if ($value === null) {
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

        $saved = $judge->scores()->where('candidate_id', $candidate->id);

        // 블라인드면 응답 메시지에도 이름 대신 심사번호만 쓴다
        $number = $event->candidateNumbers()[$candidate->id] ?? $candidate->id;
        $label  = $event->is_blind ? "심사번호 {$number}" : "{$number}. {$candidate->name}";

        return [
            'message'      => "{$label} 점수가 저장되었습니다.",
            'candidate_id' => $candidate->id,
            'total'        => (float) $saved->sum('score'),
            // 두 기기에서 채점했을 때 앱이 충돌을 알아챌 재료. 웹 응답에는 원래 없던 값이다.
            'updated_at'   => optional($saved->max('updated_at'))
                ? (string) $saved->max('updated_at')
                : null,
        ];
    }
}
