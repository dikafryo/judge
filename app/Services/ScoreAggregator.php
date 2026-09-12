<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Event;
use App\Models\Score;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 심사 결과 집계.
 *
 * 웹 대시보드·최종집계표·CSV 와 네이티브 앱 API 가 **같은 코드**를 쓴다.
 * 절사평균 제외, 동점 순위, 선정자 판정이 두 곳으로 갈라지면 같은 행사를
 * 앱에서 볼 때와 웹에서 볼 때 숫자가 달라진다.
 *
 * 반환 배열의 형태는 공개 계약이다 — 앱 API 가 그대로 내려보내고,
 * 최종집계표 Blade 와 대시보드 폴링 JS 가 키 이름으로 직접 읽는다.
 * 키를 바꾸면 세 소비자가 함께 깨진다 (tests/Feature/AggregateContractTest.php).
 */
class ScoreAggregator
{
    /** 절사평균을 적용할 최소 채점 인원. 이보다 적으면 제외할 여유가 없다. */
    public const TRIMMED_MIN_JUDGES = 3;

    /**
     * 집계 계산.
     *
     * 통계 쿼리: scores를 judge/candidate 별로 GROUP BY 하여 심사위원별 부여 총점을 구하고,
     * 이를 기반으로 대상별 합계·평균·순위를 계산한다.
     */
    public function aggregate(Event $event): array
    {
        $event->load(['candidates', 'criteria', 'judges']);

        // 완료 판정은 채점 대상인 말단 항목(서브항목 또는 서브 없는 대분류) 수 기준
        $criteriaCount = $event->leafCriteria()->count();

        $matrix = $this->matrix($event, $criteriaCount);
        $ranked = $this->rank($this->rows($event, $matrix));

        [$ranked, $passTie] = $this->markPass($ranked, (int) ($event->pass_count ?? 0));

        return [
            'event' => [
                'name' => $event->name,
                'is_open' => $event->is_open,
                'total_max' => (int) $event->criteria->whereNull('parent_id')->sum('max_score'),
                'scoring_method' => $event->scoring_method,
                'scoring_note' => $event->scoringMethodNote(),
                'pass_count' => (int) ($event->pass_count ?? 0) ?: null,
            ],
            'pass_tie' => $passTie,
            'judges' => $this->judgeProgress($event, $matrix),
            'rows' => $ranked->values(),
            'generated_at' => now()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * 심사위원 × 대상 별 부여 총점 + 완료 여부.
     *
     * @return array<int, array<int, array{total: float, complete: bool}>> [candidate_id][judge_id]
     */
    private function matrix(Event $event, int $criteriaCount): array
    {
        $totals = Score::query()
            ->whereIn('judge_id', $event->judges->pluck('id'))
            ->groupBy('judge_id', 'candidate_id')
            ->select([
                'judge_id',
                'candidate_id',
                DB::raw('SUM(score) AS total'),
                DB::raw('COUNT(*) AS scored_items'),
            ])
            ->get();

        $matrix = [];

        foreach ($totals as $row) {
            $matrix[$row->candidate_id][$row->judge_id] = [
                'total' => (float) $row->total,
                'complete' => $criteriaCount > 0 && (int) $row->scored_items >= $criteriaCount,
            ];
        }

        return $matrix;
    }

    /** 대상별 합계/평균 (모든 항목을 채점한 심사만 반영) */
    private function rows(Event $event, array $matrix): Collection
    {
        $trimmed = $event->scoring_method === 'trimmed';

        // 심사번호 (블라인드 심사 — 심사위원 화면과 동일한 등록순 번호)
        $numbers = $event->candidateNumbers();

        return $event->candidates->map(function ($candidate) use ($event, $matrix, $trimmed, $numbers) {
            $byJudge = [];
            $completed = []; // judge_id => 총점 (모든 항목 채점 완료한 심사만)

            foreach ($event->judges as $judge) {
                $cell = $matrix[$candidate->id][$judge->id] ?? null;
                $byJudge[$judge->id] = $cell ? round($cell['total'], 1) : null;

                if ($cell && $cell['complete']) {
                    $completed[$judge->id] = $cell['total'];
                }
            }

            [$counted, $excludedByJudge] = $trimmed
                ? $this->trim($completed)
                : [$completed, []];

            return [
                'candidate_id' => $candidate->id,
                'number' => $numbers[$candidate->id] ?? null,
                'name' => $candidate->name,
                'affiliation' => $candidate->affiliation,
                'by_judge' => $byJudge,
                'by_judge_excluded' => $excludedByJudge,
                'sum' => round(array_sum($counted), 1),
                'avg' => count($counted) ? round(array_sum($counted) / count($counted), 2) : null,
                'judged_count' => count($completed),
            ];
        });
    }

    /**
     * 절사: 평가 대상별로 총점을 가장 높게 준 심사위원 1명 + 가장 낮게 준
     * 심사위원 1명의 점수를 통째로 제외한다. 채점 3인 이상일 때만 적용한다.
     *
     * @param  array<int, float>  $completed  judge_id => 총점
     * @return array{0: array<int, float>, 1: array<int, float>} [집계에 쓸 점수, 제외된 점수]
     */
    private function trim(array $completed): array
    {
        if (count($completed) < self::TRIMMED_MIN_JUDGES) {
            return [$completed, []];
        }

        $counted = $completed;
        asort($counted); // 총점 오름차순 (키 = judge_id 유지)
        $judgeOrder = array_keys($counted);

        $minJudge = $judgeOrder[0];
        $maxJudge = $judgeOrder[count($judgeOrder) - 1];

        // 최종집계표 취소선 표기용
        $excludedByJudge = [
            $minJudge => round($counted[$minJudge], 1),
            $maxJudge => round($counted[$maxJudge], 1),
        ];

        unset($counted[$minJudge], $counted[$maxJudge]);

        return [$counted, $excludedByJudge];
    }

    /** 평균 점수 내림차순 순위 (동점은 같은 순위, 다음 순위는 건너뜀) */
    private function rank(Collection $rows): Collection
    {
        $rank = 0;
        $prevAvg = null;

        return $rows->sortByDesc(fn ($r) => $r['avg'] ?? -1)
            ->values()
            ->map(function ($row, $i) use (&$rank, &$prevAvg) {
                if ($row['avg'] !== $prevAvg) {
                    $rank = $i + 1;
                    $prevAvg = $row['avg'];
                }
                $row['rank'] = $row['avg'] === null ? null : $rank;

                return $row;
            });
    }

    /**
     * 선정자(선정기관) 수 기준 선정/동점 판정.
     *
     * pass: 확정 선정 / tie: 마지막 선정 순위 동점(선정자 수 초과 → 해소 필요) / null: 해당 없음
     *
     * @return array{0: Collection, 1: array{rank: int, tied: int, slots: int}|null}
     */
    private function markPass(Collection $ranked, int $passCount): array
    {
        if ($passCount <= 0) {
            return [$this->withPass($ranked, fn () => null), null];
        }

        $eligible = $ranked->filter(fn ($r) => $r['avg'] !== null)->values();

        if ($eligible->count() <= $passCount) {
            // 평가된 대상이 선정자 수 이하 — 전원 선정, 동점 문제 없음
            return [
                $this->withPass($ranked, fn ($row) => $row['avg'] === null ? null : 'pass'),
                null,
            ];
        }

        $cutoffAvg = $eligible[$passCount - 1]['avg'];                   // 마지막 선정 자리의 평균
        $aboveCount = $eligible->where('avg', '>', $cutoffAvg)->count(); // 커트라인보다 위 (확정 선정)
        $tiedCount = $eligible->where('avg', $cutoffAvg)->count();       // 커트라인 동점자 수
        $conflict = $aboveCount + $tiedCount > $passCount;               // 동점 때문에 선정자 수 초과

        $passTie = $conflict ? [
            'rank' => $aboveCount + 1,           // 동점이 발생한 순위
            'tied' => $tiedCount,                // 그 순위의 동점자 수
            'slots' => $passCount - $aboveCount, // 남은 선정 자리
        ] : null;

        $ranked = $this->withPass($ranked, fn ($row) => match (true) {
            $row['avg'] === null => null,
            $row['avg'] > $cutoffAvg => 'pass',
            $row['avg'] === $cutoffAvg => $conflict ? 'tie' : 'pass',
            default => null,
        });

        return [$ranked, $passTie];
    }

    /** 각 행에 pass 키를 붙인다 — 어느 분기로 가든 키 자체는 항상 존재해야 한다 */
    private function withPass(Collection $ranked, callable $decide): Collection
    {
        return $ranked->map(function ($row) use ($decide) {
            $row['pass'] = $decide($row);

            return $row;
        });
    }

    /** 심사위원별 진행 현황 (전체 대상 중 완료한 대상 수) */
    private function judgeProgress(Event $event, array $matrix): Collection
    {
        return $event->judges->map(function ($judge) use ($event, $matrix) {
            $done = $event->candidates
                ->filter(fn ($c) => ($matrix[$c->id][$judge->id]['complete'] ?? false))
                ->count();

            return [
                'judge_id' => $judge->id,
                'name' => $judge->name,
                'code' => $judge->code,
                'signed' => ! empty($judge->signature),
                'done' => $done,
                'total' => $event->candidates->count(),
            ];
        })->values();
    }
}
