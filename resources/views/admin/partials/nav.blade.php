{{-- 행사 관리 공통 상단 메뉴 — 모든 관리 페이지 최상단에 include --}}
@php
    $navTabs = [
        ['label' => '기본설정', 'sub' => '집계방식·평가항목', 'href' => route('admin.setup', $event),      'active' => request()->routeIs('admin.setup')],
        ['label' => '평가대상', 'sub' => $event->candidates()->count() . '건 등록',  'href' => route('admin.candidates', $event), 'active' => request()->routeIs('admin.candidates')],
        ['label' => '심사위원', 'sub' => $event->judges()->count() . '명 등록',      'href' => route('admin.judges', $event),     'active' => request()->routeIs('admin.judges')],
        ['label' => '집계',     'sub' => '실시간 대시보드',    'href' => route('admin.dashboard', $event),  'active' => request()->routeIs('admin.dashboard')],
    ];
@endphp

{{-- 상단 고정 탭 메뉴 — 스크롤해도 화면 위에 붙어 있음 (심사 마감/재개 버튼은 기본설정 안으로 이동) --}}
<div class="sticky top-0 z-40 -mx-4 px-4 -mt-8 pt-4 pb-3 bg-slate-100">
    <nav class="bg-white rounded-2xl border border-slate-200 shadow-sm p-1.5 grid grid-cols-2 sm:grid-cols-4 gap-1.5">
        @foreach ($navTabs as $tab)
            <a href="{{ $tab['href'] }}"
               class="rounded-xl px-4 py-2.5 text-center transition
                   {{ $tab['active'] ? 'bg-indigo-600 text-white shadow' : 'text-slate-600 hover:bg-slate-100' }}">
                <span class="block text-sm font-bold">{{ $tab['label'] }}</span>
                <span class="block text-xs mt-0.5 {{ $tab['active'] ? 'text-indigo-200' : 'text-slate-400' }}">{{ $tab['sub'] }}</span>
            </a>
        @endforeach
    </nav>
</div>

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
