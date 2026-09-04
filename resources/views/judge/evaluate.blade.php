@extends('layouts.app')

@section('title', $event->name . ' 심사')

@section('header-right')
    <span class="font-medium text-slate-700">{{ $judge->name }}</span> 심사위원님
@endsection

@section('content')
<div x-data="judgeApp()" x-cloak>

    @include('partials.demo-banner')

    {{-- 연결 상태 — 오프라인이거나 전송 대기가 남아 있을 때만 나타난다 --}}
    <div x-show="! online || queue.length > 0" x-cloak
         class="mb-6 flex items-start gap-2 rounded-lg border px-4 py-3 text-sm"
         x-bind:class="online ? 'bg-amber-50 border-amber-200 text-amber-800' : 'bg-slate-100 border-slate-300 text-slate-700'">
        <span aria-hidden="true" x-text="online ? '↻' : '⚠'"></span>
        <span>
            <template x-if="! online">
                <span>
                    <strong>오프라인 상태입니다.</strong>
                    입력한 점수는 기기에 저장되며 연결되면 자동으로 전송됩니다.
                    화면의 다른 정보는 마지막 접속 시점 기준입니다.
                </span>
            </template>
            <template x-if="online && queue.length > 0">
                <span><strong x-text="queue.length"></strong>건을 전송하는 중입니다…</span>
            </template>
        </span>
    </div>

    {{-- 데스크톱 상단 바 (lg 이상) — 모바일에서는 아래 고정 바가 대신한다 --}}
    <div class="mb-6 hidden flex-wrap items-start justify-between gap-4 lg:flex">
        <div>
            <h1 class="flex items-center gap-2 text-2xl font-bold text-slate-900">
                {{ $event->name }}
                @if ($event->is_open)
                    <span class="inline-flex items-center rounded-full bg-emerald-50 text-emerald-700 px-2.5 py-1 text-xs font-semibold align-middle">진행중</span>
                @else
                    <span class="inline-flex items-center rounded-full bg-rose-50 text-rose-600 px-2.5 py-1 text-xs font-semibold align-middle">마감</span>
                @endif
            </h1>
            <p class="mt-1 text-sm text-slate-500">
                총 <strong x-text="candidates.length"></strong>개 대상 ·
                항목 <strong x-text="criteria.length"></strong>개 (100점 만점) ·
                완료 <strong class="text-indigo-600" x-text="completedCount() + ' / ' + candidates.length"></strong>
            </p>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" x-on:click="signatureOpen = true"
                    class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold hover:bg-slate-50 transition"
                    x-text="pendingSignature ? '✍️ 서명 전송 대기' : (hasSignature ? '✍️ 서명 다시하기' : '✍️ 서명하기')"></button>
            @unless ($isJudgeApp)
                <a href="{{ route('judge.print', $judge) }}" target="_blank"
                   class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold hover:bg-slate-50 transition">🖨️ 심사표 출력</a>
            @endunless
        </div>
    </div>

    @unless ($event->is_open)
        <div class="mb-6 rounded-lg bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">
            이 행사의 심사가 마감되었습니다. 점수 조회와 출력만 가능합니다.
        </div>
    @endunless

    @if ($payload['totalMax'] !== 100)
        <div class="mb-6 rounded-lg bg-amber-50 border border-amber-300 text-amber-800 px-4 py-3 text-sm">
            평가 항목 배점 합계가 100점이 아닙니다. 관리자에게 문의하세요. (현재 {{ $payload['totalMax'] }}점)
        </div>
    @endif

    {{-- ================= 모바일 상단 고정 바 (lg 미만) =================
         대상 목록을 드로어로 접었기 때문에, 지금 누구를 심사 중인지와 합계를
         스크롤 내내 볼 수 있어야 한다. 합계는 항목을 내리면 시야에서 사라지던 값이다. --}}
    <div class="sticky top-0 z-30 -mx-4 mb-4 border-b border-slate-200 bg-white/95 px-4 py-2.5 backdrop-blur lg:hidden"
         x-show="current()">
        <div class="flex items-center gap-3">
            <button type="button" x-on:click="drawerOpen = true"
                    class="shrink-0 inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-300 text-slate-600 active:bg-slate-100"
                    aria-label="평가 대상 목록 열기">
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <path d="M3 5h14M3 10h14M3 15h14" />
                </svg>
            </button>

            <div class="min-w-0 flex-1">
                <div class="truncate font-bold text-slate-900" x-text="current() ? label(current()) : ''"></div>
                <div class="text-xs text-slate-400" x-text="positionLabel() + ' · 완료 ' + completedCount()"></div>
            </div>

            <div class="shrink-0 text-right">
                <div class="text-xl font-extrabold leading-none tabular-nums"
                     x-bind:class="draftTotal() > 100 ? 'text-rose-600' : 'text-indigo-600'"
                     x-text="draftTotal()"></div>
                <div class="text-[11px] text-slate-400">/ 100점</div>
            </div>
        </div>

        <div class="mt-2 h-1 overflow-hidden rounded-full bg-slate-200">
            <div class="h-full rounded-full bg-indigo-500 transition-all" x-bind:style="'width:' + progress() + '%'"></div>
        </div>
    </div>

    {{-- ================= 대상 목록 드로어 (lg 미만) ================= --}}
    <div x-show="drawerOpen" x-cloak class="fixed inset-0 z-40 lg:hidden"
         x-on:keydown.escape.window="drawerOpen = false">
        <div class="absolute inset-0 bg-slate-900/50" x-on:click="drawerOpen = false"></div>

        <div class="absolute inset-y-0 left-0 flex w-[85%] max-w-sm flex-col bg-white shadow-xl"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full">

            <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                <h2 class="font-bold text-slate-800">평가 대상</h2>
                <button type="button" x-on:click="drawerOpen = false"
                        class="text-2xl leading-none text-slate-400 hover:text-slate-600" aria-label="닫기">&times;</button>
            </div>

            <div class="flex min-h-0 flex-1 flex-col px-4 pt-3">
                @include('judge.partials.candidate-list')
            </div>

            <div class="space-y-2 border-t border-slate-100 px-4 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
                <button type="button" x-on:click="drawerOpen = false; signatureOpen = true"
                        class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold hover:bg-slate-50"
                        x-text="pendingSignature ? '✍️ 서명 전송 대기' : (hasSignature ? '✍️ 서명 다시하기' : '✍️ 서명하기')"></button>
                @unless ($isJudgeApp)
                    <a href="{{ route('judge.print', $judge) }}" target="_blank"
                       class="block w-full rounded-lg border border-slate-300 px-4 py-2.5 text-center text-sm font-semibold hover:bg-slate-50">🖨️ 심사표 출력</a>
                @endunless
            </div>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-[280px_1fr]">

        {{-- 데스크톱 사이드바 --}}
        <aside class="hidden h-fit max-h-[calc(100vh-2rem)] flex-col rounded-2xl border border-slate-200 bg-white p-4 shadow-sm lg:sticky lg:top-4 lg:flex">
            <h2 class="mb-2 text-sm font-bold text-slate-500">평가 대상</h2>
            @include('judge.partials.candidate-list')
        </aside>

        {{-- 점수 입력 폼 --}}
        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6" x-show="current()">
            {{-- 데스크톱 폼 헤더 — 모바일은 위 고정 바가 같은 역할을 한다 --}}
            <div class="mb-6 hidden flex-wrap items-start justify-between gap-3 border-b border-slate-100 pb-4 lg:flex">
                <div>
                    <h2 class="text-xl font-bold" x-text="current() ? label(current()) : ''"></h2>
                    @if ($event->is_blind)
                        <p class="text-sm text-slate-400">평가 대상은 심사번호로만 표시됩니다 (블라인드 심사)</p>
                    @else
                        <p class="text-sm text-slate-400" x-text="current()?.affiliation || ''"></p>
                    @endif
                </div>
                <div class="text-right">
                    <div class="text-xs text-slate-400">합계</div>
                    <div class="text-3xl font-extrabold"
                         x-bind:class="draftTotal() > 100 ? 'text-rose-600' : 'text-indigo-600'">
                        <span x-text="draftTotal()"></span><span class="text-base font-normal text-slate-400"> / 100</span>
                    </div>
                </div>
            </div>

            <div class="space-y-5">
                <template x-for="g in groups" :key="'g' + g.id">
                    <div>
                        {{-- 서브항목이 있는 대분류: 그룹 헤더 + 소계 --}}
                        <template x-if="g.has_children">
                            <div class="mb-2 flex items-center justify-between px-1">
                                <span class="font-bold text-slate-700" x-text="g.name"></span>
                                <span class="text-xs font-semibold"
                                      x-bind:class="groupTotal(g) > g.max_score ? 'text-rose-500' : 'text-slate-400'"
                                      x-text="groupTotal(g) + ' / ' + g.max_score + '점'"></span>
                            </div>
                        </template>

                        <div class="space-y-3" x-bind:class="g.has_children ? 'pl-3 border-l-2 border-indigo-100' : ''">
                            <template x-for="cr in g.items" :key="cr.id">
                                <div class="rounded-xl border border-slate-200 p-4 sm:grid sm:grid-cols-[1fr_230px] sm:items-center sm:gap-3">
                                    <div>
                                        <div class="font-semibold text-slate-800">
                                            <span x-text="cr.name"></span>
                                            <span class="ml-1 text-xs font-normal text-slate-400" x-text="'(배점 ' + cr.max_score + '점)'"></span>
                                        </div>
                                        <p class="mt-0.5 text-xs text-slate-400" x-text="cr.description || ''"></p>
                                    </div>

                                    {{-- 점수 스테퍼 — 한 손으로 심사할 수 있게 큰 버튼, 정밀 입력은 가운데 숫자를 탭 --}}
                                    <div class="mt-3 flex items-center justify-between gap-2 sm:mt-0">
                                        <button type="button" aria-label="1점 내리기" x-bind:disabled="!isOpen"
                                                x-on:pointerdown="holdStart(cr, -1, $event)" x-on:pointerup="holdStop()"
                                                x-on:pointerleave="holdStop()" x-on:pointercancel="holdStop()"
                                                x-on:contextmenu.prevent
                                                class="h-12 w-12 shrink-0 select-none rounded-xl border-2 border-slate-200 text-2xl font-bold text-slate-500 transition active:bg-slate-100 disabled:opacity-40">&minus;</button>

                                        <div class="min-w-0 flex-1 text-center">
                                            <input type="number" inputmode="decimal" min="0" step="0.5" x-bind:max="cr.max_score"
                                                   x-model.number="draft[cr.id]" x-bind:disabled="!isOpen"
                                                   x-on:focus="$event.target.select()"
                                                   placeholder="–"
                                                   class="score-input w-full bg-transparent text-center text-3xl font-extrabold tabular-nums outline-none disabled:text-slate-400"
                                                   x-bind:class="draft[cr.id] > cr.max_score ? 'text-rose-600' : 'text-slate-900'">
                                            <div class="text-[11px] text-slate-400" x-text="'/ ' + cr.max_score + '점'"></div>
                                        </div>

                                        <button type="button" aria-label="1점 올리기" x-bind:disabled="!isOpen"
                                                x-on:pointerdown="holdStart(cr, 1, $event)" x-on:pointerup="holdStop()"
                                                x-on:pointerleave="holdStop()" x-on:pointercancel="holdStop()"
                                                x-on:contextmenu.prevent
                                                class="h-12 w-12 shrink-0 select-none rounded-xl border-2 border-slate-200 text-2xl font-bold text-slate-500 transition active:bg-slate-100 disabled:opacity-40">+</button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>

            <p class="mt-4 min-h-5 text-sm"
               x-bind:class="{
                   'text-rose-600': message.type === 'error',
                   'text-amber-600': message.type === 'pending',
                   'text-emerald-600': message.type === 'ok',
               }"
               x-text="message.text"></p>

            {{-- 데스크톱 저장 영역 — 모바일은 아래 고정 바 --}}
            <div class="mt-2 hidden items-center justify-end gap-2 lg:flex">
                <button type="button" x-on:click="goPrev()" x-bind:disabled="! hasPrev()"
                        class="rounded-lg border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 disabled:opacity-40">← 이전</button>
                <button type="button" x-on:click="saveAndNext()" x-bind:disabled="saving || !isOpen"
                        class="rounded-lg bg-indigo-600 px-8 py-3 font-semibold text-white transition hover:bg-indigo-700 disabled:bg-slate-300">
                    <span x-show="!saving" x-text="hasNext() ? '저장하고 다음 →' : '저장하고 마치기'"></span>
                    <span x-show="saving">저장 중…</span>
                </button>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-10 text-center text-slate-400 shadow-sm" x-show="!current()">
            등록된 평가 대상이 없습니다. 관리자에게 문의하세요.
        </section>
    </div>

    {{-- ================= 모바일 하단 고정 바 (lg 미만) ================= --}}
    <div class="fixed inset-x-0 bottom-0 z-30 flex gap-2 border-t border-slate-200 bg-white/95 px-4 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] backdrop-blur lg:hidden"
         x-show="current()" x-cloak>
        <button type="button" x-on:click="goPrev()" x-bind:disabled="! hasPrev()"
                class="shrink-0 rounded-xl border border-slate-300 px-4 py-3.5 text-sm font-semibold text-slate-600 disabled:opacity-40">← 이전</button>
        <button type="button" x-on:click="saveAndNext()" x-bind:disabled="saving || !isOpen"
                class="flex-1 rounded-xl bg-indigo-600 py-3.5 text-base font-bold text-white transition active:bg-indigo-700 disabled:bg-slate-300">
            <span x-show="!saving" x-text="hasNext() ? '저장하고 다음 →' : '저장하고 마치기'"></span>
            <span x-show="saving">저장 중…</span>
        </button>
    </div>

    {{-- 하단 고정 바에 마지막 항목이 가리지 않도록 --}}
    <div class="h-24 lg:hidden"></div>

    {{-- 서명 모달 --}}
    <div x-show="signatureOpen" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4"
         x-on:keydown.escape.window="signatureOpen = false">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6" x-on:click.outside="signatureOpen = false">
            <h3 class="font-bold text-lg mb-1">전자 서명</h3>
            <p class="text-sm text-slate-500 mb-4">아래 영역에 마우스나 손가락으로 서명하세요. 인쇄용 심사표에 삽입됩니다.</p>
            <canvas x-ref="sigpad" width="400" height="180"
                    class="w-full border-2 border-dashed border-slate-300 rounded-xl bg-slate-50 touch-none cursor-crosshair"
                    x-init="initPad($el)"
                    x-on:pointerdown="padStart($event)" x-on:pointermove="padMove($event)"
                    x-on:pointerup="padEnd()" x-on:pointerleave="padEnd()"></canvas>
            <div class="mt-4 flex justify-between">
                <button type="button" x-on:click="padClear()"
                        class="rounded-lg border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">지우기</button>
                <div class="flex gap-2">
                    <button type="button" x-on:click="signatureOpen = false"
                            class="rounded-lg px-4 py-2 text-sm text-slate-500 hover:bg-slate-50">취소</button>
                    <button type="button" x-on:click="saveSignature()"
                            class="rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 text-sm font-semibold">서명 저장</button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('head')
<style>
    /* 점수 칸은 스테퍼 버튼과 짝을 이루므로 브라우저 기본 증감 화살표를 숨긴다 */
    .score-input::-webkit-outer-spin-button,
    .score-input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
    .score-input { -moz-appearance: textfield; appearance: textfield; }
</style>
@endpush

@push('scripts')
<script>
    const PAYLOAD = @json($payload);
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;

    /* ------------------------------------------------------------------
     * 오프라인 보관함 (localStorage)
     *
     * 행사장 와이파이가 끊겨도 입력이 사라지지 않도록 두 가지를 기기에 남긴다.
     *   drafts — 아직 제출하지 않은 입력값 (대상별)
     *   queue  — 제출은 눌렀지만 전송이 안 된 것 (연결되면 자동 재전송)
     *
     * 심사위원 코드로 키를 나눠 한 기기에서 여러 코드를 써도 섞이지 않는다.
     * 사파리 프라이빗 모드 등 localStorage 를 못 쓰는 환경에서도 화면은 그대로 동작해야
     * 하므로 읽기·쓰기 실패는 조용히 무시한다.
     * ------------------------------------------------------------------ */
    const STORE_PREFIX = 'judge:' + PAYLOAD.judge.code + ':';

    function loadStore(name, fallback) {
        try {
            const raw = localStorage.getItem(STORE_PREFIX + name);
            return raw === null ? fallback : JSON.parse(raw);
        } catch (e) {
            return fallback;
        }
    }

    function saveStore(name, value) {
        try {
            localStorage.setItem(STORE_PREFIX + name, JSON.stringify(value));
        } catch (e) { /* 저장 실패는 무시 — 화면 동작이 우선 */ }
    }

    function newId() {
        return Date.now().toString(36) + Math.random().toString(36).slice(2, 8);
    }

    function judgeApp() {
        return {
            groups:     PAYLOAD.groups,
            criteria:   PAYLOAD.groups.flatMap(g => g.items), // 채점 대상 말단 항목들
            candidates: PAYLOAD.candidates,
            // saved[candidate_id][criterion_id] = score (서버에 저장된 값 + 전송 대기 중인 값)
            saved:      Object.fromEntries(Object.entries(PAYLOAD.scores).map(([k, v]) => [k, { ...v }])),
            draft:      {},          // 현재 선택된 대상의 입력값 (criterion_id → score)
            drafts:     {},          // 제출 전 입력값 보관 (candidate_id → draft)
            queue:      [],          // 전송 대기열
            pending:    {},          // candidate_id → 전송 대기 여부 (목록의 '대기' 배지)
            pendingSignature: false,
            online:     navigator.onLine,
            flushing:   false,
            selectedId: PAYLOAD.candidates.length ? PAYLOAD.candidates[0].id : null,
            isOpen:     PAYLOAD.event.is_open,
            isBlind:    PAYLOAD.event.is_blind,
            hasSignature: PAYLOAD.hasSignature,
            saving:     false,
            message:    { type: '', text: '' },
            signatureOpen: false,

            // 대상 목록 드로어 — 대상이 많은 행사에서 목록을 접어두고 검색·필터로 찾는다
            drawerOpen: false,
            search:     '',
            filter:     'all',
            filters:    [{ id: 'all', label: '전체' }, { id: 'todo', label: '미완료' }, { id: 'done', label: '완료' }],
            hold:       { timer: null, interval: null },

            init() {
                this.drafts = loadStore('drafts', {});
                this.queue  = loadStore('queue', []);
                this.restoreQueued();
                this.loadDraft();

                // 입력하는 즉시 기기에 남긴다 — 대상을 바꾸거나 앱을 닫아도 유실되지 않는다.
                this.$watch('draft', () => this.persistDraft());

                window.addEventListener('online',  () => { this.online = true; this.flush(); });
                window.addEventListener('offline', () => { this.online = false; });

                this.flush();
            },

            current()  { return this.candidates.find(c => c.id === this.selectedId) ?? null; },
            // 블라인드: '심사번호 01' / 이름 공개: '01. 대상명'
            label(c)   { return this.isBlind ? '심사번호 ' + c.number : c.number + '. ' + c.name; },
            select(id) {
                this.selectedId = id;
                this.message = { type: '', text: '' };
                this.loadDraft();
                this.drawerOpen = false;
            },

            loadDraft() {
                const saved = this.saved[this.selectedId] ?? {};
                const local = this.drafts[this.selectedId] ?? null;
                this.draft = {};
                this.criteria.forEach(cr => {
                    // 제출 전 입력값이 남아 있으면 그쪽이 최신이다
                    const kept = local ? local[cr.id] : undefined;
                    this.draft[cr.id] = kept !== undefined ? kept : (saved[cr.id] ?? null);
                });
            },

            persistDraft() {
                if (this.selectedId === null) return;
                this.drafts[this.selectedId] = { ...this.draft };
                saveStore('drafts', this.drafts);
            },

            clearDraft(candidateId) {
                delete this.drafts[candidateId];
                saveStore('drafts', this.drafts);
            },

            /** 전송 대기 중인 점수·서명을 화면에 미리 반영한다 (새로고침해도 그대로 보이도록) */
            restoreQueued() {
                this.queue.forEach(item => {
                    if (item.type === 'signature') {
                        this.hasSignature = true;
                        this.pendingSignature = true;
                        return;
                    }
                    this.saved[item.candidate_id] = { ...item.scores };
                    this.pending[item.candidate_id] = true;
                });
            },

            isComplete(candidateId) {
                const s = this.saved[candidateId] ?? {};
                return this.criteria.length > 0 && this.criteria.every(cr => s[cr.id] !== undefined && s[cr.id] !== null);
            },
            savedCount(candidateId) {
                const s = this.saved[candidateId] ?? {};
                return this.criteria.filter(cr => s[cr.id] !== undefined && s[cr.id] !== null).length;
            },
            statusOf(candidateId) {
                const n = this.savedCount(candidateId);
                if (n === 0) return '미완료';
                if (n === this.criteria.length) return '✓ ' + this.totalOf(candidateId) + '점';
                return this.totalOf(candidateId) + '점 · ' + n + '/' + this.criteria.length;
            },
            completedCount() { return this.candidates.filter(c => this.isComplete(c.id)).length; },
            totalOf(candidateId) {
                const s = this.saved[candidateId] ?? {};
                return Math.round(Object.values(s).reduce((a, b) => a + Number(b || 0), 0) * 10) / 10;
            },
            draftTotal() {
                return Math.round(Object.values(this.draft).reduce((a, b) => a + Number(b || 0), 0) * 10) / 10;
            },
            groupTotal(g) {
                return Math.round(g.items.reduce((a, cr) => a + Number(this.draft[cr.id] || 0), 0) * 10) / 10;
            },
            progress() {
                return this.candidates.length === 0 ? 0 : Math.round(this.completedCount() / this.candidates.length * 100);
            },
            positionLabel() {
                const i = this.candidates.findIndex(c => c.id === this.selectedId);
                return (i + 1) + ' / ' + this.candidates.length;
            },

            /* ---------- 목록 검색·필터 ---------- */

            matches(c) {
                const q = this.search.trim().toLowerCase();
                if (q === '') return true;
                // 블라인드 행사에서는 이름이 payload 에 없으므로 번호만 대상이 된다
                const hay = this.isBlind ? c.number : [c.number, c.name, c.affiliation].filter(Boolean).join(' ');
                return String(hay).toLowerCase().includes(q);
            },
            inFilter(c) {
                if (this.filter === 'todo') return ! this.isComplete(c.id);
                if (this.filter === 'done') return this.isComplete(c.id);
                return true;
            },
            visible() { return this.candidates.filter(c => this.matches(c) && this.inFilter(c)); },
            countOf(id) {
                if (id === 'todo') return this.candidates.length - this.completedCount();
                if (id === 'done') return this.completedCount();
                return this.candidates.length;
            },

            /* ---------- 대상 이동 ----------
             * 이동은 '지금 보이는 목록' 기준이다. 미완료 필터를 켜면 [저장하고 다음]이
             * 자동으로 미완료만 훑게 되어 대상이 많은 행사에서 효과가 크다. */

            navList() {
                const list = this.visible();
                return list.length > 0 ? list : this.candidates;
            },
            navIndex() { return this.navList().findIndex(c => c.id === this.selectedId); },
            hasPrev()  { return this.navIndex() > 0; },
            hasNext()  { const i = this.navIndex(); return i >= 0 && i < this.navList().length - 1; },
            goPrev()   { const i = this.navIndex(); if (i > 0) { this.select(this.navList()[i - 1].id); this.toTop(); } },
            goNext()   { const i = this.navIndex(); const l = this.navList(); if (i >= 0 && i + 1 < l.length) { this.select(l[i + 1].id); this.toTop(); } },
            toTop()    { window.scrollTo({ top: 0, behavior: 'smooth' }); },

            /** 저장하고 다음 대상으로. 저장이 거절되면(마감·검증 실패) 이동하지 않는다. */
            async saveAndNext() {
                // 저장하면 이 대상이 '완료'가 되어 미완료 필터에서 빠지므로, 다음 대상을 미리 정해 둔다
                const list   = this.navList();
                const i      = list.findIndex(c => c.id === this.selectedId);
                const nextId = (i >= 0 && i + 1 < list.length) ? list[i + 1].id : null;

                await this.submit();

                if (this.message.type === 'error') return;

                if (nextId === null) {
                    this.finish();
                    return;
                }

                this.select(nextId);
                this.toTop();
            },

            finish() {
                this.drawerOpen = false;

                if (! this.hasSignature) {
                    this.message = { type: 'ok', text: '모든 대상 심사를 마쳤습니다. 마지막으로 서명해 주세요.' };
                    this.signatureOpen = true;
                    return;
                }

                this.message = { type: 'ok', text: '모든 대상 심사를 마쳤습니다. 수고하셨습니다.' };
            },

            /* ---------- 점수 스테퍼 ---------- */

            bump(cr, delta) {
                if (! this.isOpen) return;
                const current = Number(this.draft[cr.id] ?? 0) || 0;
                // 0.5점 단위로 맞추고 0 ~ 배점 범위로 자른다
                this.draft[cr.id] = Math.min(cr.max_score, Math.max(0, Math.round((current + delta) * 2) / 2));
            },
            holdStart(cr, delta, event) {
                if (! this.isOpen) return;
                event.preventDefault();   // 길게 눌러도 텍스트 선택·스크롤이 끼어들지 않게
                this.bump(cr, delta);
                this.holdStop();
                this.hold.timer = setTimeout(() => {
                    this.hold.interval = setInterval(() => this.bump(cr, delta), 100);
                }, 400);
            },
            holdStop() {
                clearTimeout(this.hold.timer);
                clearInterval(this.hold.interval);
                this.hold.timer = null;
                this.hold.interval = null;
            },

            /* ---------- 전송 ---------- */

            /**
             * 한 건을 서버로 보낸다.
             * 네트워크가 끊겨 있으면 fetch 가 throw 한다 — 호출부에서 '오프라인'으로 해석한다.
             * 서버가 응답했다면 상태코드를 그대로 돌려준다(422 검증실패 · 423 심사마감 · 419 토큰만료).
             */
            async send(item, token) {
                const url  = item.type === 'signature' ? PAYLOAD.urls.signature : PAYLOAD.urls.scores;
                const body = item.type === 'signature'
                    ? { signature: item.signature }
                    : { candidate_id: item.candidate_id, scores: item.scores };

                const res = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                    body: JSON.stringify(body),
                });

                let json = {};
                try { json = await res.json(); } catch (e) { /* 419 등은 본문이 JSON이 아닐 수 있다 */ }

                return { ok: res.ok, status: res.status, message: json.message };
            },

            /** 캐시된 화면의 CSRF 토큰은 만료됐을 수 있다 — 현재 세션 토큰을 다시 받아온다. */
            async freshToken() {
                try {
                    const res = await fetch('{{ route('csrf.token') }}', { headers: { 'Accept': 'application/json' }, cache: 'no-store' });
                    if (res.ok) {
                        const json = await res.json();
                        if (json.token) return json.token;
                    }
                } catch (e) { /* 아직 오프라인 */ }

                return CSRF;
            },

            enqueue(item) {
                // 같은 대상의 점수는 마지막 제출만 남긴다 — 서버가 대상 단위 upsert 라 최신 1건이면 충분하다.
                this.queue = item.type === 'signature'
                    ? this.queue.filter(q => q.type !== 'signature')
                    : this.queue.filter(q => !(q.type === 'scores' && q.candidate_id === item.candidate_id));

                this.queue.push(item);
                saveStore('queue', this.queue);
            },

            dequeue(id) {
                this.queue = this.queue.filter(q => q.id !== id);
                saveStore('queue', this.queue);
            },

            /** 대기열을 순서대로 전송한다. 연결이 돌아왔을 때와 화면 진입 시 호출된다. */
            async flush() {
                if (this.flushing || ! navigator.onLine || this.queue.length === 0) return;

                this.flushing = true;

                try {
                    let token = await this.freshToken();

                    for (const item of [...this.queue]) {
                        let result;

                        try {
                            result = await this.send(item, token);
                        } catch (e) {
                            break;      // 아직 연결이 불안정하다 — 대기열을 그대로 두고 멈춘다
                        }

                        if (result.status === 419) {
                            token = await this.freshToken();
                            try {
                                result = await this.send(item, token);
                            } catch (e) {
                                break;
                            }
                            if (result.status === 419) break;   // 토큰을 못 받는 상태 — 다음 기회에
                        }

                        // 여기까지 왔으면 서버가 판단을 내린 것이다. 성공이든 거절이든 대기열에서 뺀다
                        // (422 검증실패·423 심사마감은 재시도해도 결과가 같아 무한 재시도가 된다).
                        this.dequeue(item.id);
                        this.settle(item, result);
                    }
                } finally {
                    this.flushing = false;
                }
            },

            settle(item, result) {
                if (item.type === 'signature') {
                    this.pendingSignature = false;
                } else {
                    delete this.pending[item.candidate_id];
                    this.clearDraft(item.candidate_id);
                }

                this.message = result.ok
                    ? { type: 'ok', text: result.message ?? '전송이 완료되었습니다.' }
                    : { type: 'error', text: result.message ?? '전송하지 못한 항목이 있습니다.' };
            },

            async submit() {
                // 클라이언트 측 검증 — 부분 제출 허용: 입력한 항목만 범위 검사, 빈 항목은 null로 전송
                const scores = {};
                let filled = 0;
                for (const cr of this.criteria) {
                    const v = this.draft[cr.id];
                    if (v === null || v === '' || v === undefined) {
                        scores[cr.id] = null;
                        continue;
                    }
                    if (Number(v) < 0 || Number(v) > cr.max_score) {
                        this.message = { type: 'error', text: `'${cr.name}' 점수는 0~${cr.max_score}점 사이여야 합니다.` };
                        return;
                    }
                    scores[cr.id] = Number(v);
                    filled++;
                }
                if (filled === 0) {
                    this.message = { type: 'error', text: '최소 한 개 항목 이상 점수를 입력해 주세요.' };
                    return;
                }

                const candidateId = this.selectedId;
                const item = { id: newId(), type: 'scores', candidate_id: candidateId, scores };

                this.saving = true;
                this.message = { type: '', text: '' };

                try {
                    let result = await this.send(item, CSRF);

                    if (result.status === 419) {
                        result = await this.send(item, await this.freshToken());
                    }

                    if (! result.ok) {
                        this.message = { type: 'error', text: result.message ?? '저장에 실패했습니다.' };
                        return;
                    }

                    this.saved[candidateId] = { ...scores };
                    delete this.pending[candidateId];
                    this.clearDraft(candidateId);
                    this.message = { type: 'ok', text: result.message };
                } catch (e) {
                    // 네트워크가 끊긴 경우 — 다시 입력하게 하지 않는다. 기기에 담아 두고 연결되면 보낸다.
                    this.enqueue(item);
                    this.pending[candidateId] = true;
                    this.saved[candidateId] = { ...scores };
                    this.message = { type: 'pending', text: '오프라인 — 기기에 저장했습니다. 연결되면 자동으로 전송됩니다.' };
                } finally {
                    this.saving = false;
                }
            },

            /* ---------- 서명 패드 ---------- */
            pad: { ctx: null, drawing: false, dirty: false },

            initPad(canvas) {
                const ctx = canvas.getContext('2d');
                ctx.lineWidth = 2.5;
                ctx.lineCap = 'round';
                ctx.strokeStyle = '#1e293b';
                this.pad.ctx = ctx;
            },
            padPos(e) {
                const r = e.target.getBoundingClientRect();
                return {
                    x: (e.clientX - r.left) * (e.target.width / r.width),
                    y: (e.clientY - r.top) * (e.target.height / r.height),
                };
            },
            padStart(e) {
                e.target.setPointerCapture(e.pointerId);
                this.pad.drawing = true;
                const p = this.padPos(e);
                this.pad.ctx.beginPath();
                this.pad.ctx.moveTo(p.x, p.y);
            },
            padMove(e) {
                if (!this.pad.drawing) return;
                const p = this.padPos(e);
                this.pad.ctx.lineTo(p.x, p.y);
                this.pad.ctx.stroke();
                this.pad.dirty = true;
            },
            padEnd() { this.pad.drawing = false; },
            padClear() {
                const c = this.$refs.sigpad;
                this.pad.ctx.clearRect(0, 0, c.width, c.height);
                this.pad.dirty = false;
            },

            async saveSignature() {
                if (!this.pad.dirty) {
                    alert('서명을 먼저 입력해 주세요.');
                    return;
                }

                const item = { id: newId(), type: 'signature', signature: this.$refs.sigpad.toDataURL('image/png') };

                try {
                    let result = await this.send(item, CSRF);

                    if (result.status === 419) {
                        result = await this.send(item, await this.freshToken());
                    }

                    if (! result.ok) {
                        alert(result.message ?? '서명 저장에 실패했습니다.');
                        return;
                    }

                    this.hasSignature = true;
                    this.pendingSignature = false;
                    this.signatureOpen = false;
                    this.message = { type: 'ok', text: result.message };
                } catch (e) {
                    this.enqueue(item);
                    this.hasSignature = true;
                    this.pendingSignature = true;
                    this.signatureOpen = false;
                    this.message = { type: 'pending', text: '오프라인 — 서명을 기기에 저장했습니다. 연결되면 자동으로 전송됩니다.' };
                }
            },
        };
    }
</script>
@endpush
