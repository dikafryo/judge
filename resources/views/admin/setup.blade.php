@extends('layouts.app')

@section('title', $event->name . ' 기본설정')

@section('header-right')
    <x-admin.logout-form :event="$event" />
@endsection

@section('content')
@include('admin.partials.nav')

{{-- 집계 설정 — 저장 버튼 없이 고르는 즉시 저장된다 (사용자 결정 2026-09-12).
     피드백은 조작한 자리 옆에 둔다. 세 값은 성격이 달라서 한 곳에 몰면 무엇이 저장됐는지 알 수 없다. --}}
<section class="mb-6 bg-white rounded-2xl shadow-sm border border-slate-200 px-6 py-4"
         x-data="scoringSetup({
             method: @js($event->scoring_method),
             blind: @js($event->is_blind ? '1' : '0'),
             pass: @js((string) $event->pass_count),
             isOpen: @js($event->is_open),
             hasScores: @js($hasScores),
         })">
    <form method="POST" action="{{ route('admin.scoring-method', $event) }}"
          x-on:submit.prevent class="flex flex-col gap-y-2">
        @csrf

        {{-- 집계 방식 --}}
        <div class="w-full flex flex-wrap items-center gap-x-6 gap-y-2">
            <span class="font-bold text-sm">집계 방식</span>
            <label class="flex items-center gap-2 text-sm cursor-pointer">
                <input type="radio" name="scoring_method" value="all" x-model="method" x-on:change="save('method')" @disabled(! $event->is_open)
                       class="text-indigo-600 focus:ring-indigo-500">
                전체 합계·평균 <span class="text-xs text-slate-400">(기본)</span>
            </label>
            <label class="flex items-center gap-2 text-sm cursor-pointer">
                <input type="radio" name="scoring_method" value="trimmed" x-model="method" x-on:change="save('method')" @disabled(! $event->is_open)
                       class="text-indigo-600 focus:ring-indigo-500">
                최고·최저 점수 제외 <span class="text-xs text-slate-400">(채점 {{ $trimmedMinJudges }}인 이상일 때 적용)</span>
            </label>
            <x-admin.save-state group="method" :undo="true" />
        </div>

        {{-- 심사위원 화면 --}}
        <div class="w-full flex flex-wrap items-center gap-x-6 gap-y-2 pt-2 border-t border-slate-100">
            <span class="font-bold text-sm">심사위원 화면</span>
            <label class="flex items-center gap-2 text-sm cursor-pointer">
                <input type="radio" name="is_blind" value="1" x-model="blind" x-on:change="save('blind')" @disabled(! $event->is_open)
                       class="text-indigo-600 focus:ring-indigo-500">
                심사번호만 표시 <span class="text-xs text-slate-400">(블라인드 · 기본)</span>
            </label>
            <label class="flex items-center gap-2 text-sm cursor-pointer">
                <input type="radio" name="is_blind" value="0" x-model="blind" x-on:change="save('blind')" @disabled(! $event->is_open)
                       class="text-indigo-600 focus:ring-indigo-500">
                평가 대상 이름 공개
            </label>
            <x-admin.save-state group="blind" :undo="true" />
        </div>

        {{-- 선정자 수 --}}
        <div class="w-full flex flex-wrap items-center gap-x-4 gap-y-2 pt-2 border-t border-slate-100">
            <label class="flex items-center gap-2 text-sm">
                <span class="font-bold">선정자(선정기관) 수</span>
                {{-- 숫자는 다 입력한 뒤에 저장한다 — 칸을 벗어나거나 Enter 를 칠 때 --}}
                <input type="number" name="pass_count" min="1" max="1000" @disabled(! $event->is_open)
                       x-model="pass" x-on:blur="save('pass')" x-on:keydown.enter.prevent="save('pass')"
                       placeholder="미지정"
                       class="w-24 rounded-lg border-slate-300 border px-3 py-1.5 text-sm text-right outline-none focus:ring-2 focus:ring-indigo-500 disabled:bg-slate-50 disabled:text-slate-400">
                <span class="text-xs text-slate-400">곳 (비우면 선정 표시 안 함)</span>
            </label>
            <x-admin.save-state group="pass" />
        </div>

        {{-- 자바스크립트가 죽은 태블릿에서도 저장할 수단은 남겨 둔다 --}}
        <noscript>
            <div class="pt-2">
                <button class="rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-4 py-1.5 transition">저장</button>
            </div>
        </noscript>
    </form>

    {{-- 절사 경고는 고른 값에 따라 즉시 바뀌어야 한다 — 리로드가 없으므로 서버 @if 로는 거짓말을 하게 된다 --}}
    <template x-if="method === 'trimmed' && {{ $event->judges->count() }} < {{ $trimmedMinJudges }}">
        <x-alert class="mt-3">
            ⚠️ 현재 심사위원이 <strong>{{ $event->judges->count() }}명</strong>입니다.
            최고·최저 제외는 <strong>대상별 채점 심사위원이 {{ $trimmedMinJudges }}명 이상</strong>일 때만 적용되며, 미만이면 제외 없이 전체 점수로 집계됩니다.
        </x-alert>
    </template>
</section>

{{-- 최종집계표 하단 결재란 --}}
<section class="mb-6 bg-white rounded-2xl shadow-sm border border-slate-200 px-6 py-4">
    <form method="POST" action="{{ route('admin.report-signers', $event) }}" class="space-y-2.5">
        @csrf
        <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
            <span class="font-bold text-sm">최종집계표 서명</span>
            <label class="flex items-center gap-2 text-sm cursor-pointer">
                <input type="radio" name="show_judge_signs" value="1"
                       {{ $event->show_judge_signs ? 'checked' : '' }}
                       class="text-indigo-600 focus:ring-indigo-500">
                심사위원 서명란 포함 <span class="text-xs text-slate-400">(기본 — 결재란은 선택)</span>
            </label>
            <label class="flex items-center gap-2 text-sm cursor-pointer">
                <input type="radio" name="show_judge_signs" value="0"
                       {{ ! $event->show_judge_signs ? 'checked' : '' }}
                       class="text-indigo-600 focus:ring-indigo-500">
                심사위원 서명란 생략 <span class="text-xs text-slate-400">(결재란만 — 기록자 필수)</span>
            </label>
        </div>
        <div class="flex items-baseline flex-wrap gap-x-3 gap-y-1 pt-2 border-t border-slate-100">
            <span class="font-bold text-sm">결재란</span>
            <span class="text-xs text-slate-400">이름을 입력한 사람만 출력물 맨 아래 우측에 표시됩니다. (예: 기록자·확인자만 쓰려면 검토자 이름을 비워두세요)</span>
        </div>
        @php $savedSigners = collect($event->report_signers ?? [])->keyBy('role'); @endphp
        @foreach (['기록자', '검토자', '확인자'] as $role)
            @php $row = $savedSigners->get($role, []); @endphp
            <div class="flex flex-wrap items-center gap-2">
                <span class="w-12 shrink-0 text-sm font-semibold text-slate-600">{{ $role }}</span>
                <input type="text" name="signers[{{ $role }}][dept]" maxlength="50"
                       value="{{ old("signers.$role.dept", $row['dept'] ?? '') }}" placeholder="부서 (예: 총무과)"
                       class="w-40 rounded-lg border-slate-300 border px-3 py-1.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                <input type="text" name="signers[{{ $role }}][position]" maxlength="50"
                       value="{{ old("signers.$role.position", $row['position'] ?? '') }}" placeholder="직급 (예: 주무관)"
                       class="w-36 rounded-lg border-slate-300 border px-3 py-1.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                <input type="text" name="signers[{{ $role }}][name]" maxlength="50"
                       value="{{ old("signers.$role.name", $row['name'] ?? '') }}" placeholder="이름"
                       class="w-32 rounded-lg border-slate-300 border px-3 py-1.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
        @endforeach
        <button class="rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-4 py-1.5 transition">저장</button>
    </form>
</section>

{{-- 위험 구역: 행사 삭제 --}}
<section class="mt-8 rounded-2xl border border-rose-200 bg-rose-50/50 p-6"
         x-data="{ open: false, name: '' }">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h2 class="font-bold text-rose-700">행사 삭제</h2>
            <p class="text-sm text-rose-600/70 mt-0.5">평가 대상·항목·심사위원·모든 점수가 함께 삭제되며 되돌릴 수 없습니다.</p>
        </div>
        <button type="button" x-on:click="open = !open" x-show="!open"
                class="rounded-lg border border-rose-300 text-rose-600 hover:bg-rose-100 px-4 py-2 text-sm font-semibold transition">
            행사 삭제…
        </button>
    </div>

    <form x-show="open" x-cloak method="POST" action="{{ route('admin.destroy', $event) }}"
          class="mt-4 flex flex-wrap items-center gap-2"
          onsubmit="return confirm('정말 삭제합니까? 이 작업은 되돌릴 수 없습니다.')">
        @csrf @method('DELETE')
        <label class="w-full text-sm text-rose-700">
            삭제를 확인하려면 행사명 <strong>“{{ $event->name }}”</strong> 을(를) 그대로 입력하세요.
        </label>
        <input type="text" name="confirm_name" x-model="name" required
               placeholder="{{ $event->name }}"
               class="flex-1 min-w-48 rounded-lg border border-rose-300 bg-white px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-rose-500">
        <button type="submit" x-bind:disabled="name.trim() !== @js($event->name)"
                class="rounded-lg bg-rose-600 hover:bg-rose-700 disabled:bg-rose-300 disabled:cursor-not-allowed text-white px-5 py-2 text-sm font-semibold transition">
            영구 삭제
        </button>
        <button type="button" x-on:click="open = false; name = ''"
                class="rounded-lg px-3 py-2 text-sm text-slate-500 hover:bg-white transition">취소</button>
    </form>
</section>
@endsection

@push('scripts')
<script>
    /**
     * 집계 설정 자동 저장.
     *
     * 저장 버튼이 없으므로 지켜야 할 두 가지가 있다.
     *  1) 실패를 조용히 넘기지 않는다 — 실패 표시는 사라지지 않고, 고른 값도 되돌리지 않는다.
     *     방금 고른 것이 화면에 남아 있어야 다시 시도할 수 있다.
     *  2) 저장 요청은 세 값을 통째로 보낸다 — 서버 검증이 집계 방식·블라인드를 모두 요구한다.
     */
    function scoringSetup(initial) {
        return {
            method: initial.method,
            blind: initial.blind,
            pass: initial.pass,
            isOpen: initial.isOpen,
            hasScores: initial.hasScores,

            state: { method: 'idle', blind: 'idle', pass: 'idle' },
            undoable: { method: null, blind: null },   // 직전 값 — 되돌리기용
            detail: '',                                 // 스크린리더에 읽어 줄 전체 문장
            saved: null,                                // 마지막으로 저장에 성공한 값
            timers: {},

            init() {
                this.saved = this.snapshot();
            },

            snapshot() {
                return { method: this.method, blind: this.blind, pass: this.pass };
            },

            /**
             * 이름 공개는 되돌려도 심사위원이 이미 본 것은 회복되지 않는다.
             * 심사 진행 중 + 제출된 점수가 있을 때만 확인을 받는다 — 조건 없이 걸면 아무도 안 읽는다.
             */
            confirmReveal(next) {
                if (next.blind !== '0' || this.saved.blind !== '1') return true;
                if (! this.isOpen || ! this.hasScores) return true;

                return window.confirm('심사가 진행 중입니다. 이름을 공개하면 심사위원 화면에 평가 대상 이름이 즉시 표시되며, 다시 감춰도 이미 본 것은 되돌릴 수 없습니다. 공개할까요?');
            },

            async save(group, retry = false) {
                const next = this.snapshot();

                if (! retry && JSON.stringify(next) === JSON.stringify(this.saved)) return; // 바뀐 게 없으면 조용히 넘어간다

                if (! this.confirmReveal(next)) {
                    this.blind = this.saved.blind;   // 취소하면 고르기 전으로
                    return;
                }

                const before = this.saved[group];

                clearTimeout(this.timers[group]);
                // 200ms 안에 끝나면 '저장 중'을 띄우지 않는다 — 깜빡임이 신호를 흐린다
                const spinner = setTimeout(() => { this.state[group] = 'saving'; }, 200);

                try {
                    const res = await fetch('{{ route('admin.scoring-method', $event) }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify({
                            scoring_method: next.method,
                            is_blind: next.blind === '1',
                            pass_count: next.pass === '' ? null : next.pass,
                        }),
                    });

                    if (res.status === 401 || res.status === 419) { location.href = '{{ route('admin.login', $event) }}'; return; }
                    if (! res.ok) throw new Error(res.status);

                    const data = await res.json();
                    clearTimeout(spinner);

                    this.method = data.scoring_method;
                    this.blind = data.is_blind ? '1' : '0';
                    this.pass = data.pass_count === null ? '' : String(data.pass_count);
                    this.detail = data.detail ?? '';
                    this.saved = this.snapshot();

                    if (group in this.undoable) this.undoable[group] = before;

                    this.state[group] = 'saved';
                    this.timers[group] = setTimeout(() => {
                        if (this.state[group] === 'saved') {
                            this.state[group] = 'idle';
                            if (group in this.undoable) this.undoable[group] = null;
                        }
                    }, 2500);
                } catch (e) {
                    clearTimeout(spinner);
                    // 고른 값을 되돌리지 않는다 — 되돌리면 무엇을 다시 시도해야 할지 알 수 없다
                    this.state[group] = 'error';
                }
            },

            undo(group) {
                const before = this.undoable[group];
                if (before === null) return;

                this[group === 'method' ? 'method' : 'blind'] = before;
                this.undoable[group] = null;
                this.save(group, true);
            },
        };
    }
</script>
@endpush
