<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Score;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    /** 대시보드 화면 (데이터는 폴링 AJAX로 갱신) */
    public function index(Event $event): View
    {
        return view('admin.dashboard', compact('event'));
    }

    /**
     * 집계 데이터 (AJAX 폴링 엔드포인트)
     *
     * 통계 쿼리: scores를 judge/candidate 별로 GROUP BY 하여 심사위원별 부여 총점을 구하고,
     * 이를 기반으로 대상별 합계·평균·순위를 계산한다.
     */
    public function data(Event $event): JsonResponse
    {
        return response()->json($this->aggregate($event));
    }

    /** 공용 집계 로직 — 대시보드 폴링 / CSV / 인쇄가 공유 */
    private function aggregate(Event $event): array
    {
        $event->load(['candidates', 'criteria', 'judges']);

        // 완료 판정은 채점 대상인 말단 항목(서브항목 또는 서브 없는 대분류) 수 기준
        $criteriaCount = $event->leafCriteria()->count();
        $judgeIds      = $event->judges->pluck('id');

        // 심사위원 × 대상 별 부여 총점 + 채점한 항목 수 (완료 여부 판정용)
        $totals = Score::query()
            ->whereIn('judge_id', $judgeIds)
            ->groupBy('judge_id', 'candidate_id')
            ->select([
                'judge_id',
                'candidate_id',
                DB::raw('SUM(score) AS total'),
                DB::raw('COUNT(*) AS scored_items'),
            ])
            ->get();

        // matrix[candidate_id][judge_id] = 총점 (모든 항목 채점 완료된 것만 집계 대상)
        $matrix = [];
        foreach ($totals as $row) {
            $matrix[$row->candidate_id][$row->judge_id] = [
                'total'    => (float) $row->total,
                'complete' => $criteriaCount > 0 && (int) $row->scored_items >= $criteriaCount,
            ];
        }

        $trimmed = $event->scoring_method === 'trimmed';

        // 심사번호 (블라인드 심사 — 심사위원 화면과 동일한 등록순 번호)
        $numbers = $event->candidateNumbers();

        // 대상별 합계/평균 (완료된 심사만 반영)
        $rows = $event->candidates->map(function ($candidate) use ($event, $matrix, $trimmed, $numbers) {
            $byJudge   = [];
            $completed = []; // judge_id => 총점 (모든 항목 채점 완료한 심사만)

            foreach ($event->judges as $judge) {
                $cell = $matrix[$candidate->id][$judge->id] ?? null;
                $byJudge[$judge->id] = $cell ? round($cell['total'], 1) : null;

                if ($cell && $cell['complete']) {
                    $completed[$judge->id] = $cell['total'];
                }
            }

            // trimmed: 평가 대상별로 총점을 가장 높게 준 심사위원 1명 + 가장 낮게 준
            // 심사위원 1명의 점수를 통째로 제외 (채점 3인 이상일 때만)
            $excludedByJudge = []; // judge_id => 제외된 총점 (최종집계표 취소선 표기용)
            $counted = $completed;

            if ($trimmed && count($completed) >= 3) {
                asort($counted); // 총점 오름차순 (키 = judge_id 유지)
                $judgeOrder = array_keys($counted);

                $minJudge = $judgeOrder[0];
                $maxJudge = $judgeOrder[count($judgeOrder) - 1];

                $excludedByJudge[$minJudge] = round($counted[$minJudge], 1);
                $excludedByJudge[$maxJudge] = round($counted[$maxJudge], 1);

                unset($counted[$minJudge], $counted[$maxJudge]);
            }

            return [
                'candidate_id'      => $candidate->id,
                'number'            => $numbers[$candidate->id] ?? null,
                'name'              => $candidate->name,
                'affiliation'       => $candidate->affiliation,
                'by_judge'          => $byJudge,
                'by_judge_excluded' => $excludedByJudge,
                'sum'               => round(array_sum($counted), 1),
                'avg'               => count($counted) ? round(array_sum($counted) / count($counted), 2) : null,
                'judged_count'      => count($completed),
            ];
        });

        // 평균 점수 내림차순 순위 (동점은 같은 순위)
        $ranked = $rows->sortByDesc(fn ($r) => $r['avg'] ?? -1)->values();
        $rank = 0;
        $prevAvg = null;
        $ranked = $ranked->map(function ($row, $i) use (&$rank, &$prevAvg) {
            if ($row['avg'] !== $prevAvg) {
                $rank = $i + 1;
                $prevAvg = $row['avg'];
            }
            $row['rank'] = $row['avg'] === null ? null : $rank;

            return $row;
        });

        // 선정자(선정기관) 수 기준 선정/동점 판정
        // pass: 확정 선정 / tie: 마지막 선정 순위 동점(선정자 수 초과 → 해소 필요) / null: 해당 없음
        $passCount = (int) ($event->pass_count ?? 0);
        $passTie   = null;

        if ($passCount > 0) {
            $eligible = $ranked->filter(fn ($r) => $r['avg'] !== null)->values();

            if ($eligible->count() <= $passCount) {
                // 평가된 대상이 선정자 수 이하 — 전원 선정, 동점 문제 없음
                $ranked = $ranked->map(function ($row) {
                    $row['pass'] = $row['avg'] === null ? null : 'pass';

                    return $row;
                });
            } else {
                $cutoffAvg  = $eligible[$passCount - 1]['avg'];               // 마지막 선정 자리의 평균
                $aboveCount = $eligible->where('avg', '>', $cutoffAvg)->count(); // 커트라인보다 위 (확정 선정)
                $tiedCount  = $eligible->where('avg', $cutoffAvg)->count();      // 커트라인 동점자 수
                $conflict   = $aboveCount + $tiedCount > $passCount;             // 동점 때문에 선정자 수 초과

                if ($conflict) {
                    $passTie = [
                        'rank'  => $aboveCount + 1,          // 동점이 발생한 순위
                        'tied'  => $tiedCount,               // 그 순위의 동점자 수
                        'slots' => $passCount - $aboveCount, // 남은 선정 자리
                    ];
                }

                $ranked = $ranked->map(function ($row) use ($cutoffAvg, $conflict) {
                    $row['pass'] = match (true) {
                        $row['avg'] === null       => null,
                        $row['avg'] > $cutoffAvg   => 'pass',
                        $row['avg'] === $cutoffAvg => $conflict ? 'tie' : 'pass',
                        default                    => null,
                    };

                    return $row;
                });
            }
        } else {
            $ranked = $ranked->map(function ($row) {
                $row['pass'] = null;

                return $row;
            });
        }

        // 심사위원별 진행 현황 (전체 대상 중 완료한 대상 수)
        $judgeProgress = $event->judges->map(function ($judge) use ($event, $matrix) {
            $done = $event->candidates
                ->filter(fn ($c) => ($matrix[$c->id][$judge->id]['complete'] ?? false))
                ->count();

            return [
                'judge_id' => $judge->id,
                'name'     => $judge->name,
                'code'     => $judge->code,
                'signed'   => ! empty($judge->signature),
                'done'     => $done,
                'total'    => $event->candidates->count(),
            ];
        })->values();

        return [
            'event' => [
                'name'           => $event->name,
                'is_open'        => $event->is_open,
                'total_max'      => (int) $event->criteria->whereNull('parent_id')->sum('max_score'),
                'scoring_method' => $event->scoring_method,
                'scoring_note'   => $event->scoringMethodNote(),
                'pass_count'     => $passCount ?: null,
            ],
            'pass_tie'     => $passTie,
            'judges'       => $judgeProgress,
            'rows'         => $ranked->values(),
            'generated_at' => now()->format('Y-m-d H:i:s'),
        ];
    }

    /** 최종 결과 CSV 다운로드 (Excel 호환 — UTF-8 BOM) */
    public function exportCsv(Event $event): StreamedResponse
    {
        $data     = $this->aggregate($event);
        $filename = preg_replace('/[\/\\\\:*?"<>|]/', '_', $event->name) . '_심사결과_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // Excel 한글 인식용 BOM

            $header = ['심사번호', '순위', '평가 대상', '소속'];
            foreach ($data['judges'] as $judge) {
                $header[] = $judge['name'];
            }
            array_push($header, '합계', '평균');
            fputcsv($out, $header);

            foreach ($data['rows'] as $row) {
                $line = [$row['number'] ?? '', $row['rank'] ?? '-', $row['name'], $row['affiliation'] ?? ''];
                foreach ($data['judges'] as $judge) {
                    $line[] = $row['by_judge'][$judge['judge_id']] ?? '';
                }
                $line[] = $row['sum'];
                $line[] = $row['avg'] ?? '';
                fputcsv($out, $line);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /** 심사위원별 개별심사표 인쇄 (관리자용 — 코드 회수 후에도 출력 가능) */
    public function printJudgeSheet(Event $event, \App\Models\Judge $judge): View
    {
        abort_unless($judge->event_id === $event->id, 404);

        $event->load(['candidates', 'criteria']);

        $myScores = $judge->scores()
            ->get()
            ->groupBy('candidate_id')
            ->map(fn ($group) => $group->pluck('score', 'criterion_id'));

        return view('judge.print', compact('judge', 'event', 'myScores'));
    }

    /** 최종 집계표 인쇄 (A4 1장 + 담당자/확인자 결재란) — 연번(등록순) 정렬, 순위는 열로 표시 */
    public function print(Event $event): View
    {
        $data = $this->aggregate($event);

        // aggregate()는 순위순 정렬이므로, 인쇄용은 평가 대상 등록순(연번)으로 재정렬
        $order = $event->candidates->pluck('id')->flip();
        $data['rows'] = collect($data['rows'])
            ->sortBy(fn ($row) => $order[$row['candidate_id']] ?? PHP_INT_MAX)
            ->values()
            ->all();

        return view('admin.print', compact('event', 'data'));
    }
}
