@extends('layouts.app')

@section('title', $event->name . ' 심사')

@section('header-right')
    <span class="font-medium text-slate-700">{{ $judge->name }}</span> 심사위원님
@endsection

@section('content')
<div x-data="judgeApp()" x-cloak>

    @include('partials.demo-banner')

    {{-- 상단 바 --}}
    <div class="flex items-start justify-between flex-wrap gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 flex items-center gap-2">
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
                    x-text="hasSignature ? '✍️ 서명 다시하기' : '✍️ 서명하기'"></button>
            <a href="{{ route('judge.print', $judge) }}" target="_blank"
               class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold hover:bg-slate-50 transition">🖨️ 심사표 출력</a>
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

    <div class="grid lg:grid-cols-[280px_1fr] gap-6">

        {{-- 대상 목록 --}}
        <aside class="bg-white rounded-2xl shadow-sm border border-slate-200 p-4 h-fit lg:sticky lg:top-4">
            <h2 class="text-sm font-bold text-slate-500 px-2 mb-2">평가 대상</h2>
            <ul class="space-y-1">
                <template x-for="c in candidates" :key="c.id">
                    <li>
                        <button type="button" x-on:click="select(c.id)"
                                class="w-full flex items-center justify-between rounded-lg px-3 py-2.5 text-left text-sm transition"
                                x-bind:class="selectedId === c.id
                                    ? 'bg-indigo-600 text-white font-semibold'
                                    : 'hover:bg-slate-100 text-slate-700'">
                            <span class="truncate font-semibold" x-text="label(c)"></span>
                            <span class="shrink-0 ml-2 text-xs"
                                  x-bind:class="isComplete(c.id)
                                      ? (selectedId === c.id ? 'text-emerald-200' : 'text-emerald-600')
                                      : (savedCount(c.id) > 0
                                          ? (selectedId === c.id ? 'text-amber-200' : 'text-amber-600')
                                          : (selectedId === c.id ? 'text-white/50' : 'text-slate-300'))"
                                  x-text="statusOf(c.id)"></span>
                        </button>
                    </li>
                </template>
            </ul>
        </aside>

        {{-- 점수 입력 폼 --}}
        <section class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6" x-show="current()">
            <div class="flex items-start justify-between flex-wrap gap-3 mb-6 pb-4 border-b border-slate-100">
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
                            <div class="flex items-center justify-between mb-2 px-1">
                                <span class="font-bold text-slate-700" x-text="g.name"></span>
                                <span class="text-xs font-semibold"
                                      x-bind:class="groupTotal(g) > g.max_score ? 'text-rose-500' : 'text-slate-400'"
                                      x-text="groupTotal(g) + ' / ' + g.max_score + '점'"></span>
                            </div>
                        </template>

                        <div class="space-y-3" x-bind:class="g.has_children ? 'pl-3 border-l-2 border-indigo-100' : ''">
                            <template x-for="cr in g.items" :key="cr.id">
                                <div class="grid sm:grid-cols-[1fr_170px] gap-3 items-center rounded-xl border border-slate-200 p-4">
                                    <div>
                                        <div class="font-semibold text-slate-800">
                                            <span x-text="cr.name"></span>
                                            <span class="ml-1 text-xs font-normal text-slate-400" x-text="'(배점 ' + cr.max_score + '점)'"></span>
                                        </div>
                                        <p class="text-xs text-slate-400 mt-0.5" x-text="cr.description || ''"></p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <input type="number" min="0" step="0.5" x-bind:max="cr.max_score"
                                               x-model.number="draft[cr.id]"
                                               x-bind:disabled="!isOpen"
                                               class="w-full rounded-lg border px-3 py-2.5 text-right text-lg font-bold outline-none focus:ring-2 focus:ring-indigo-500 disabled:bg-slate-50 disabled:text-slate-400"
                                               x-bind:class="draft[cr.id] > cr.max_score ? 'border-rose-400 text-rose-600' : 'border-slate-300'">
                                        <span class="text-sm text-slate-400 shrink-0" x-text="'/ ' + cr.max_score"></span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>

            <div class="mt-6 flex items-center justify-between flex-wrap gap-3">
                <p class="text-sm h-5"
                   x-bind:class="message.type === 'error' ? 'text-rose-600' : 'text-emerald-600'"
                   x-text="message.text"></p>
                <button type="button" x-on:click="submit()" x-bind:disabled="saving || !isOpen"
                        class="rounded-lg bg-indigo-600 hover:bg-indigo-700 disabled:bg-slate-300 text-white font-semibold px-8 py-3 transition">
                    <span x-show="!saving" x-text="savedCount(selectedId) > 0 ? '점수 수정 저장' : '점수 제출'"></span>
                    <span x-show="saving">저장 중…</span>
                </button>
            </div>
        </section>

        <section class="bg-white rounded-2xl shadow-sm border border-slate-200 p-10 text-center text-slate-400" x-show="!current()">
            등록된 평가 대상이 없습니다. 관리자에게 문의하세요.
        </section>
    </div>

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

@push('scripts')
<script>
    const PAYLOAD = @json($payload);
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;

    function judgeApp() {
        return {
            groups:     PAYLOAD.groups,
            criteria:   PAYLOAD.groups.flatMap(g => g.items), // 채점 대상 말단 항목들
            candidates: PAYLOAD.candidates,
            // saved[candidate_id][criterion_id] = score (서버에 저장된 값)
            saved:      Object.fromEntries(Object.entries(PAYLOAD.scores).map(([k, v]) => [k, { ...v }])),
            draft:      {},          // 현재 선택된 대상의 입력값 (criterion_id → score)
            selectedId: PAYLOAD.candidates.length ? PAYLOAD.candidates[0].id : null,
            isOpen:     PAYLOAD.event.is_open,
            isBlind:    PAYLOAD.event.is_blind,
            hasSignature: PAYLOAD.hasSignature,
            saving:     false,
            message:    { type: '', text: '' },
            signatureOpen: false,

            init() { this.loadDraft(); },

            current()  { return this.candidates.find(c => c.id === this.selectedId) ?? null; },
            // 블라인드: '심사번호 01' / 이름 공개: '01. 대상명'
            label(c)   { return this.isBlind ? '심사번호 ' + c.number : c.number + '. ' + c.name; },
            select(id) { this.selectedId = id; this.message = { type: '', text: '' }; this.loadDraft(); },

            loadDraft() {
                const saved = this.saved[this.selectedId] ?? {};
                this.draft = {};
                this.criteria.forEach(cr => { this.draft[cr.id] = saved[cr.id] ?? null; });
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

                this.saving = true;
                this.message = { type: '', text: '' };
                try {
                    const res = await fetch(PAYLOAD.urls.scores, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                        body: JSON.stringify({ candidate_id: this.selectedId, scores }),
                    });
                    const json = await res.json();
                    if (!res.ok) throw new Error(json.message ?? '저장에 실패했습니다.');

                    this.saved[this.selectedId] = { ...scores };
                    this.message = { type: 'ok', text: json.message };
                } catch (e) {
                    this.message = { type: 'error', text: e.message };
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
                try {
                    const res = await fetch(PAYLOAD.urls.signature, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                        body: JSON.stringify({ signature: this.$refs.sigpad.toDataURL('image/png') }),
                    });
                    const json = await res.json();
                    if (!res.ok) throw new Error(json.message ?? '서명 저장에 실패했습니다.');
                    this.hasSignature = true;
                    this.signatureOpen = false;
                    this.message = { type: 'ok', text: json.message };
                } catch (e) {
                    alert(e.message);
                }
            },
        };
    }
</script>
@endpush
