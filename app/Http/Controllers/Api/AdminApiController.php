<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\SetupRejected;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\Criterion;
use App\Models\Event;
use App\Models\Judge;
use App\Services\EventSetup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * 관리자용 API. 토큰이 곧 행사라 URL 에 행사 id 를 다시 싣지 않는다.
 *
 * 집계는 DashboardController::aggregate() 를 그대로 재사용한다 —
 * 앱과 웹 대시보드가 다른 숫자를 보여주면 안 되므로 계산을 두 번 구현하지 않는다.
 */
class AdminApiController extends Controller
{
    public function __construct(private readonly EventSetup $setup)
    {
    }

    private function event(Request $request): Event
    {
        /** @var Event $event */
        $event = $request->user();

        return $event;
    }

    public function show(Request $request): JsonResponse
    {
        $event = $this->event($request);

        return response()->json([
            'id'               => $event->id,
            'name'             => $event->name,
            'description'      => $event->description,
            'event_date'       => $event->event_date?->toDateString(),
            'is_open'          => $event->is_open,
            'is_demo'          => $event->is_demo,
            'is_blind'         => $event->is_blind,
            'scoring_method'   => $event->scoring_method,
            'scoring_note'     => $event->scoringMethodNote(),
            'pass_count'       => $event->pass_count,
            'show_judge_signs' => $event->show_judge_signs,
        ]);
    }

    /**
     * 설정 화면이 필요로 하는 전부를 한 번에 준다.
     * 항목·대상·심사위원을 따로 부르면 왕복이 세 번이 되는데, 심사장 회선에서는 그 차이가 크다.
     */
    public function setup(Request $request): JsonResponse
    {
        $event = $this->event($request);

        return response()->json([
            'criteria'   => $event->criteria()->get()
                ->map(fn (Criterion $c) => [
                    'id'          => $c->id,
                    'name'        => $c->name,
                    'description' => $c->description,
                    'max_score'   => (int) $c->max_score,
                    'parent_id'   => $c->parent_id,
                    'has_scores'  => $c->scores()->exists(),
                ])->values(),
            'candidates' => $event->candidates()->get()
                ->map(fn (Candidate $c) => [
                    'id'          => $c->id,
                    'name'        => $c->name,
                    'affiliation' => $c->affiliation,
                ])->values(),
            'judges'     => $event->judges()->get()
                ->map(fn (Judge $j) => [
                    'id'        => $j->id,
                    'name'      => $j->name,
                    'code'      => $j->code,
                    'signed_at' => $j->signed_at?->toIso8601String(),
                    // 앱이 심사위원 카드를 화면에 띄워 바로 보여줄 수 있게 한다(인쇄 없이 배포).
                    'entry_url' => $j->code ? route('judge.show', $j) : null,
                ])->values(),
            'total_max'  => (int) $event->topCriteria()->sum('max_score'),
        ]);
    }

    /** 집계 방식 · 블라인드 · 선정자 수 */
    public function updateScoringMethod(Request $request): JsonResponse
    {
        $data = $request->validate([
            'scoring_method' => ['required', 'in:all,trimmed'],
            'is_blind'       => ['required', 'boolean'],
            'pass_count'     => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        $this->setup->updateScoringMethod($this->event($request), $data);

        return $this->show($request);
    }

    /** 심사 진행/마감. 마감하면 접속 코드와 발급된 앱 토큰이 함께 회수된다. */
    public function toggleOpen(Request $request): JsonResponse
    {
        $event = $this->event($request);

        return response()->json([
            'message' => $this->setup->toggleOpen($event),
            'is_open' => $event->is_open,
        ]);
    }

    public function storeCriterion(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'max_score'   => ['required', 'integer', 'min:1', 'max:100'],
            'parent_id'   => ['nullable', 'integer'],
        ]);

        try {
            $this->setup->addCriterion($this->event($request), $data);
        } catch (SetupRejected $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return $this->setup($request);
    }

    public function destroyCriterion(Request $request, Criterion $criterion): JsonResponse
    {
        abort_unless($criterion->event_id === $this->event($request)->id, 404);

        $criterion->delete();

        return $this->setup($request);
    }

    public function storeCandidates(Request $request): JsonResponse
    {
        $data = $request->validate(['bulk' => ['required', 'string', 'max:10000']]);

        $this->setup->importCandidates($this->event($request), $data['bulk']);

        return $this->setup($request);
    }

    public function destroyCandidate(Request $request, Candidate $candidate): JsonResponse
    {
        abort_unless($candidate->event_id === $this->event($request)->id, 404);

        $candidate->delete();

        return $this->setup($request);
    }

    public function storeJudges(Request $request): JsonResponse
    {
        $data = $request->validate(['bulk' => ['required', 'string', 'max:5000']]);

        $this->setup->importJudges($this->event($request), $data['bulk']);

        return $this->setup($request);
    }

    public function destroyJudge(Request $request, Judge $judge): JsonResponse
    {
        abort_unless($judge->event_id === $this->event($request)->id, 404);

        $judge->delete();

        return $this->setup($request);
    }

    public function dashboard(Request $request, DashboardController $dashboard): JsonResponse
    {
        return response()->json($dashboard->aggregate($this->event($request)));
    }

    /**
     * 최종집계표·CSV 는 네이티브로 다시 그리지 않는다.
     *
     * 결재란이 있는 A4 공식 문서라 지금 Blade 출력이 유일한 정답이고,
     * Flutter PDF 로 재현하면 미묘하게 달라질 위험만 크다. 대신 단기 서명 URL 을 발급해
     * 시스템 브라우저로 넘긴다.
     */
    public function printUrl(Request $request): JsonResponse
    {
        $event = $this->event($request);

        $data = $request->validate([
            'kind' => ['required', 'in:report,csv,judge-cards'],
        ]);

        $route = match ($data['kind']) {
            'report'      => 'admin.print',
            'csv'         => 'admin.export',
            'judge-cards' => 'admin.judges.print',
        };

        return response()->json([
            'url'        => URL::temporarySignedRoute($route, now()->addMinutes(10), $event),
            'expires_in' => 600,
        ]);
    }
}
