<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Controller;
use App\Models\Event;
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
