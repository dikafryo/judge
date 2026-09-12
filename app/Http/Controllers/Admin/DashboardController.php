<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Judge;
use App\Services\ScoreAggregator;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    public function __construct(private readonly ScoreAggregator $aggregator) {}

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

    /**
     * 집계 계산. 네이티브 앱 API(AdminApiController)도 같은 ScoreAggregator 를 쓴다 —
     * 앱과 웹이 다른 숫자를 보여주면 안 되므로 계산은 서비스 한 곳에만 둔다.
     *
     * 대시보드 폴링 / CSV / 인쇄가 이 메서드를 공유한다.
     */
    public function aggregate(Event $event): array
    {
        return $this->aggregator->aggregate($event);
    }

    /** 최종 결과 CSV 다운로드 (Excel 호환 — UTF-8 BOM) */
    public function exportCsv(Event $event): StreamedResponse
    {
        $data = $this->aggregate($event);
        $filename = preg_replace('/[\/\\\\:*?"<>|]/', '_', $event->name).'_심사결과_'.now()->format('Ymd_His').'.csv';

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
    public function printJudgeSheet(Event $event, Judge $judge): View
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
