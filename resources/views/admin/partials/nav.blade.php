{{-- 행사 관리 공통 상단 메뉴 — 모든 관리 페이지 최상단에 include --}}
@php
    $candidateCount = $event->candidates()->count();
    $judgeCount     = $event->judges()->count();

    $navTabs = [
        [
            'label'    => '기본설정',
            'required' => false,
            'sub'      => '집계방식·서명 정보',
            'urgent'   => false,
            'href'     => route('admin.setup', $event),
            'active'   => request()->routeIs('admin.setup'),
        ],
        [
            'label'    => '평가항목',
            'required' => true,
            'sub'      => $event->totalMaxScore() === 100 ? '배점 합계 100점' : '필수: 배점 합계 100점 설정',
            'urgent'   => $event->totalMaxScore() !== 100,
            'href'     => route('admin.criteria', $event),
            'active'   => request()->routeIs('admin.criteria'),
            'tour'     => 'criteria',
        ],
        [
            'label'    => '평가대상',
            'required' => true,
            'sub'      => $candidateCount > 0 ? "{$candidateCount}건 등록" : '필수: 평가대상자 입력',
            'urgent'   => $candidateCount === 0,
            'href'     => route('admin.candidates', $event),
            'active'   => request()->routeIs('admin.candidates'),
            'tour'     => 'candidates',
        ],
        [
            'label'    => '심사위원',
            'required' => true,
            'sub'      => $judgeCount > 0 ? "{$judgeCount}명 등록" : '필수: 심사위원 등록',
            'urgent'   => $judgeCount === 0,
            'href'     => route('admin.judges', $event),
            'active'   => request()->routeIs('admin.judges'),
            'tour'     => 'judges',
        ],
        [
            'label'    => '집계',
            'required' => true,
            'sub'      => '실시간 대시보드',
            'urgent'   => false,
            'href'     => route('admin.dashboard', $event),
            'active'   => request()->routeIs('admin.dashboard'),
        ],
    ];
@endphp

{{-- 상단 고정 탭 메뉴 — 스크롤해도 화면 위에 붙어 있음 (심사 마감/재개 버튼은 기본설정 안으로 이동) --}}
<div class="sticky top-0 z-40 -mx-4 px-4 -mt-8 pt-4 pb-3 bg-slate-100">
    <nav class="bg-white rounded-2xl border border-slate-200 shadow-sm p-1.5 grid grid-cols-2 sm:grid-cols-5 gap-1.5">
        @foreach ($navTabs as $tab)
            <a href="{{ $tab['href'] }}" @if (! empty($tab['tour'])) data-tour="{{ $tab['tour'] }}" @endif
               class="rounded-xl px-4 py-2.5 text-center transition
                   {{ $tab['active'] ? 'bg-indigo-600 text-white shadow' : 'text-slate-600 hover:bg-slate-100' }}">
                <span class="block text-sm font-bold">@if ($tab['required'])<span class="{{ $tab['active'] ? 'text-rose-300' : 'text-rose-500' }}">*</span>@endif{{ $tab['label'] }}</span>
                <span class="block text-xs mt-0.5 font-semibold"
                      @class([
                          'text-rose-200' => $tab['urgent'] && $tab['active'],
                          'text-rose-600' => $tab['urgent'] && ! $tab['active'],
                          'text-indigo-200' => ! $tab['urgent'] && $tab['active'],
                          'text-slate-400 font-normal' => ! $tab['urgent'] && ! $tab['active'],
                      ])>{{ $tab['sub'] }}</span>
            </a>
        @endforeach
    </nav>
</div>

@include('partials.demo-banner')

<div class="mb-6 mt-4">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div class="flex items-center gap-3">
            <h1 class="text-2xl font-bold text-slate-900">{{ $event->name }}</h1>
            @if ($event->is_open)
                <span class="inline-flex items-center rounded-full bg-emerald-50 text-emerald-700 px-3 py-1 text-sm font-semibold">심사 진행중</span>
            @else
                <span class="inline-flex items-center rounded-full bg-rose-50 text-rose-600 px-3 py-1 text-sm font-semibold">심사 마감됨</span>
            @endif
        </div>

        {{-- 마감/재개 버튼은 기본설정 화면에서만 (행사명 우측) --}}
        @if (request()->routeIs('admin.setup'))
            <form method="POST" action="{{ route('admin.toggle-open', $event) }}"
                  @if ($event->is_open) onsubmit="return confirm('심사를 마감하면 심사위원 접속 코드가 모두 회수됩니다. 계속할까요?')" @endif>
                @csrf
                <button class="rounded-lg px-4 py-2 text-sm font-semibold border transition
                    {{ $event->is_open ? 'border-rose-300 text-rose-600 hover:bg-rose-50' : 'border-emerald-300 text-emerald-600 hover:bg-emerald-50' }}">
                    {{ $event->is_open ? '심사 마감하기' : '심사 재개하기' }}
                </button>
            </form>
        @endif
    </div>

    @unless ($event->is_open)
        <div class="mt-3 rounded-lg bg-rose-50 border border-rose-200 text-rose-700 px-4 py-2.5 text-sm">
            🔒 심사가 마감되어 이 행사의 모든 설정·데이터 수정이 잠겼습니다. 조회와 출력만 가능하며, 수정하려면 <strong>기본설정</strong> 탭의 '심사 재개하기'를 누르세요.
        </div>
    @endunless
</div>

{{-- 행사 도우미 (시작 배너 + 하단 진행 카드) — 3단계 완료 전엔 항상 표시 --}}
@include('admin.partials.tour')
