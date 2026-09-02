<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 체험(데모) 안내 — 시스템을 처음 보는 외부 사용자가 실제 화면을 그대로 둘러보는 통로.
 *
 * 샘플 데이터는 `php artisan judge:demo` 로 만든다.
 * 데모 행사의 모든 변경 요청은 BlockDemoWrites 미들웨어가 차단하므로 읽기 전용이다.
 */
class DemoController extends Controller
{
    /** 데모 안내 페이지 — 심사위원 화면·관리자 화면·출력물로 가는 링크 모음 */
    public function index(): View
    {
        $event = $this->demoEvent();

        if (! $event) {
            return view('demo', ['event' => null, 'judges' => collect(), 'candidates' => collect()]);
        }

        $event->load(['judges', 'candidates', 'criteria']);

        return view('demo', [
            'event'      => $event,
            'judges'     => $event->judges,
            'candidates' => $event->candidates,
        ]);
    }

    /**
     * 관리자 화면 체험 — 데모 행사에 한해 비밀번호 없이 관리자 세션을 부여한다.
     * (변경 요청은 미들웨어가 막으므로 조회·출력만 가능)
     */
    public function admin(Request $request): RedirectResponse
    {
        $event = $this->demoEvent();

        if (! $event) {
            return redirect()->route('demo')->withErrors(['demo' => '체험용 샘플 행사가 아직 준비되지 않았습니다.']);
        }

        $request->session()->put('event_admin_' . $event->id, true);

        return redirect()->route('admin.dashboard', $event);
    }

    /** 현재 공개 중인 데모 행사 (가장 최근 것 1건) */
    private function demoEvent(): ?Event
    {
        return Event::where('is_demo', true)->latest('id')->first();
    }
}
