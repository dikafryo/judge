<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEventRequest;
use App\Models\Event;
use App\Services\EventSetup;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EventController extends Controller
{
    /** 홈 — 심사위원 코드 입장 전용 (행사 생성·관리는 /events) */
    public function home(): View
    {
        return view('home');
    }

    /** 행사 관리 — 새 행사 생성 폼 + 행사 목록(게시판형). 클릭 시 해당 행사 설정으로 이동(비밀번호 인증) */
    public function index(): View
    {
        $events = Event::query()
            ->real()
            ->withCount(['candidates', 'criteria', 'judges'])
            ->latest()
            ->paginate(15);

        return view('events.index', compact('events'));
    }

    /** 행사 생성 — 비밀번호만으로 관리 (별도 로그인 모듈 없음) */
    public function store(StoreEventRequest $request, EventSetup $setup): RedirectResponse
    {
        $event = $setup->createEvent($request->validated());

        // 생성 직후 해당 행사의 관리자 세션 부여 → 바로 설정 화면으로
        $request->session()->put($event->adminSessionKey(), true);

        return redirect()
            ->route('admin.setup', $event)
            ->with('status', '행사가 생성되었습니다. 평가 대상·항목·심사위원을 등록하세요.');
    }
}
