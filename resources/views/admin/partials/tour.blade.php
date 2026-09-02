{{-- 행사 도우미 — nav.blade.php 끝에서 include (모든 관리 페이지 공통)
     · 3단계(평가항목→평가대상→심사위원) 미완인 동안은 무조건 표시, 닫기 불가 (사용자 결정 2026-08-13)
     · 3단계를 모두 마치면 "준비 끝" 카드로 바뀌고 그때만 닫을 수 있다
     · 시작 배너: 기본설정 페이지 + 심사위원 0명일 때 3단계 개요 안내 (서버 조건만으로 표시)
     · 진행 카드: 하단 고정(z-30 — nav z-40·설명서 모달 z-50보다 아래), 서버 상태로 현재 단계 판정
     · localStorage: judgeHelperClosed:{id}(행사별, 완료 후 닫음) 하나만 사용 — 미완 상태에는 어떤 플래그도 안 먹는다 --}}
@php
    $tourTotalMax   = $event->totalMaxScore();
    $tourCandidates = $candidateCount ?? $event->candidates()->count();
    $tourJudges     = $judgeCount ?? $event->judges()->count();

    $tourStep1Done = $tourTotalMax === 100;
    $tourStep2Done = $tourCandidates > 0;
    $tourStep3Done = $tourJudges > 0;

    $tourStep = ! $tourStep1Done ? 1 : (! $tourStep2Done ? 2 : (! $tourStep3Done ? 3 : 4));

    $tourSteps = [
        1 => [
            'tab'    => 'criteria',
            'title'  => '평가항목 입력',
            'desc'   => "항목을 추가해 배점 합계를 100점으로 맞추세요. (현재 {$tourTotalMax}점 / 100점)",
            'href'   => route('admin.criteria', $event),
            'onPage' => request()->routeIs('admin.criteria'),
        ],
        2 => [
            'tab'    => 'candidates',
            'title'  => '평가대상 입력',
            'desc'   => "평가 대상을 한 줄에 하나씩 '이름, 소속' 형식으로 등록하세요. (현재 {$tourCandidates}건 등록됨)",
            'href'   => route('admin.candidates', $event),
            'onPage' => request()->routeIs('admin.candidates'),
        ],
        3 => [
            'tab'    => 'judges',
            'title'  => '심사위원 등록',
            'desc'   => "심사위원 이름만 등록하면 6자리 접속 코드가 자동 발급됩니다. (현재 {$tourJudges}명 등록됨)",
            'href'   => route('admin.judges', $event),
            'onPage' => request()->routeIs('admin.judges'),
        ],
    ];

    // 3단계 미완 + 심사위원 0명이면 기본설정 화면에 개요 배너 노출 — 진행하면 서버 조건이 저절로 꺼진다
    $tourShowBannerServer = request()->routeIs('admin.setup') && $event->is_open
        && $tourStep < 4 && $tourJudges === 0;

    // 현재 단계 페이지에 이미 들어와 있으면 탭 강조 불필요 — "가야 할 곳"만 가리킨다
    $tourOnStepPage = $tourStep <= 3 && $tourSteps[$tourStep]['onPage'];
@endphp

@if ($event->is_open)

{{-- ===== 시작 배너 (기본설정 · 3단계 개요) — 서버 조건만으로 표시, 숨기기 없음 ===== --}}
@if ($tourShowBannerServer)
    <section role="region" aria-label="행사 도우미 안내"
             class="mb-6 rounded-2xl border border-indigo-200 bg-indigo-50/70 p-5">
        <div class="flex items-start justify-between flex-wrap gap-4">
            <div>
                <div class="font-bold text-indigo-900 text-base">처음이신가요? 딱 3단계면 준비 끝</div>
                <p class="mt-1 text-sm text-indigo-800">
                    ① <strong>평가항목</strong> 입력 → ② <strong>평가대상</strong> 입력 → ③ <strong>심사위원</strong> 등록 — 이게 전부입니다.<br>
                    화면 아래 <strong>행사 도우미</strong>가 3단계를 마칠 때까지 안내합니다.
                    접속안내 출력, 집계표 서명 같은 나머지 옵션은 나중에 보셔도 됩니다.
                </p>
            </div>
            <div class="shrink-0">
                <a href="{{ $tourSteps[$tourStep]['href'] }}"
                   class="inline-block rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 text-sm font-semibold transition">
                    {{ $tourStep }}단계 · {{ $tourSteps[$tourStep]['title'] }} 시작 →
                </a>
            </div>
        </div>
    </section>
@endif

{{-- ===== 행사 도우미 진행 카드 (하단 고정) ===== --}}
<div x-data="{
        active: false,
        expanded: false,
        step: {{ $tourStep }},
        init() {
            {{-- 3단계 미완이면 무조건 표시(닫기 불가) — 완료(step 4) 카드만 닫을 수 있다 --}}
            this.active = this.step < 4
                || localStorage.getItem('judgeHelperClosed:{{ $event->id }}') !== '1';
            {{-- 좁은 화면(모바일)은 접힌 1줄 바로 시작 — 키보드와 겹침 방지. 완료 단계는 항상 펼침 --}}
            this.expanded = this.step === 4 || window.innerWidth >= 640;
            this.$watch('active', () => this.reserve(this.active));
            this.$watch('expanded', () => this.reserve(this.active));
            this.reserve(this.active);
            if (this.active && this.step <= 3 && ! {{ $tourOnStepPage ? 'true' : 'false' }}) this.highlightTab();
        },
        reserve(on) {
            {{-- 카드 높이만큼 본문 여백 예약 (펼침 시 더 크게) — 페이지 맨 아래 버튼이 가려지지 않게 --}}
            document.body.style.paddingBottom = on
                ? 'calc(' + (this.expanded ? '12rem' : '4.5rem') + ' + env(safe-area-inset-bottom))'
                : '';
        },
        highlightTab() {
            const tabs = { 1: 'criteria', 2: 'candidates', 3: 'judges' };
            const tab = document.querySelector('[data-tour=' + tabs[this.step] + ']');
            if (tab) tab.classList.add('ring-2', 'ring-indigo-400', 'ring-offset-2');
        },
        close() {
            localStorage.setItem('judgeHelperClosed:{{ $event->id }}', '1');
            this.active = false;
        },
    }">

    {{-- Tailwind Play CDN이 동적 추가 클래스의 CSS를 미리 생성하도록 하는 선언용 요소 --}}
    <span class="hidden ring-2 ring-indigo-400 ring-offset-2"></span>

    <div x-show="active" x-cloak
         class="fixed inset-x-0 bottom-0 z-30 px-4"
         style="padding-bottom: env(safe-area-inset-bottom)">
        <div class="max-w-6xl mx-auto mb-3">
            <div class="rounded-2xl border border-indigo-200 bg-white shadow-lg overflow-hidden">

                {{-- 접힌 1줄 바 --}}
                <button type="button" x-on:click="expanded = !expanded"
                        x-bind:aria-expanded="expanded" aria-controls="tour-panel"
                        class="w-full flex items-center gap-3 px-4 py-2.5 text-left">
                    <span class="flex items-center gap-1" aria-hidden="true">
                        <span class="w-2 h-2 rounded-full {{ $tourStep1Done ? 'bg-indigo-600' : ($tourStep === 1 ? 'bg-indigo-400 animate-pulse motion-reduce:animate-none' : 'bg-slate-300') }}"></span>
                        <span class="w-2 h-2 rounded-full {{ $tourStep2Done ? 'bg-indigo-600' : ($tourStep === 2 ? 'bg-indigo-400 animate-pulse motion-reduce:animate-none' : 'bg-slate-300') }}"></span>
                        <span class="w-2 h-2 rounded-full {{ $tourStep3Done ? 'bg-indigo-600' : ($tourStep === 3 ? 'bg-indigo-400 animate-pulse motion-reduce:animate-none' : 'bg-slate-300') }}"></span>
                    </span>
                    <span class="flex-1 text-sm font-bold text-slate-800">
                        @if ($tourStep === 4)
                            🎉 행사 도우미 · 준비 끝!
                        @else
                            행사 도우미 {{ $tourStep }}/3 · {{ $tourSteps[$tourStep]['title'] }}
                        @endif
                    </span>
                    <span class="text-slate-500 hover:text-slate-700 text-sm" x-text="expanded ? '▾ 접기' : '▴ 펼치기'"></span>
                </button>

                {{-- 펼친 안내 --}}
                <div x-show="expanded" id="tour-panel" class="px-4 pb-3 border-t border-slate-100 pt-3">
                    @if ($tourStep === 4)
                        <p class="text-sm text-slate-700">
                            심사위원에게 <strong>접속 코드</strong>를 전달하면 바로 심사가 시작됩니다.<br>
                            <strong>기본설정</strong>에서 집계 방식을 확인하고, <strong>집계</strong>에서 심사 진행 상황을 실시간으로 지켜보세요.
                        </p>
                        <div class="mt-3 flex items-center gap-3">
                            <button type="button" x-on:click="close()"
                                    class="rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 text-sm font-semibold transition">
                                닫기
                            </button>
                        </div>
                    @else
                        <p class="text-sm text-slate-700">{{ $tourSteps[$tourStep]['desc'] }}</p>
                        @unless ($tourSteps[$tourStep]['onPage'])
                            <div class="mt-3">
                                <a href="{{ $tourSteps[$tourStep]['href'] }}"
                                   class="inline-block rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 text-sm font-semibold transition">
                                    {{ $tourSteps[$tourStep]['title'] }}으로 이동 →
                                </a>
                            </div>
                        @endunless
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endif
