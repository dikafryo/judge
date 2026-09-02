@extends('layouts.app')

@section('title', $event->name . ' 기본설정')

@section('header-right')
    <form method="POST" action="{{ route('admin.logout', $event) }}">
        @csrf
        <button class="text-slate-400 hover:text-slate-600">로그아웃</button>
    </form>
@endsection

@section('content')
@include('admin.partials.nav')

{{-- 집계 방식 --}}
<section class="mb-6 bg-white rounded-2xl shadow-sm border border-slate-200 px-6 py-4">
    <form method="POST" action="{{ route('admin.scoring-method', $event) }}"
          class="flex flex-wrap items-center gap-x-6 gap-y-2">
        @csrf
        <span class="font-bold text-sm">집계 방식</span>
        <label class="flex items-center gap-2 text-sm cursor-pointer">
            <input type="radio" name="scoring_method" value="all" @disabled(! $event->is_open)
                   {{ $event->scoring_method !== 'trimmed' ? 'checked' : '' }}
                   class="text-indigo-600 focus:ring-indigo-500">
            전체 합계·평균 <span class="text-xs text-slate-400">(기본)</span>
        </label>
        <label class="flex items-center gap-2 text-sm cursor-pointer">
            <input type="radio" name="scoring_method" value="trimmed" @disabled(! $event->is_open)
                   {{ $event->scoring_method === 'trimmed' ? 'checked' : '' }}
                   class="text-indigo-600 focus:ring-indigo-500">
            최고·최저 점수 제외 <span class="text-xs text-slate-400">(채점 3인 이상일 때 적용)</span>
        </label>

        <div class="w-full flex flex-wrap items-center gap-x-6 gap-y-2 pt-2 border-t border-slate-100">
            <span class="font-bold text-sm">심사위원 화면</span>
            <label class="flex items-center gap-2 text-sm cursor-pointer">
                <input type="radio" name="is_blind" value="1" @disabled(! $event->is_open)
                       {{ $event->is_blind ? 'checked' : '' }}
                       class="text-indigo-600 focus:ring-indigo-500">
                심사번호만 표시 <span class="text-xs text-slate-400">(블라인드 · 기본)</span>
            </label>
            <label class="flex items-center gap-2 text-sm cursor-pointer">
                <input type="radio" name="is_blind" value="0" @disabled(! $event->is_open)
                       {{ ! $event->is_blind ? 'checked' : '' }}
                       class="text-indigo-600 focus:ring-indigo-500">
                평가 대상 이름 공개
            </label>
        </div>

        <div class="w-full flex flex-wrap items-center gap-x-4 gap-y-2 pt-2 border-t border-slate-100">
            <label class="flex items-center gap-2 text-sm">
                <span class="font-bold">선정자(선정기관) 수</span>
                <input type="number" name="pass_count" min="1" max="1000" @disabled(! $event->is_open)
                       value="{{ old('pass_count', $event->pass_count) }}" placeholder="미지정"
                       class="w-24 rounded-lg border-slate-300 border px-3 py-1.5 text-sm text-right outline-none focus:ring-2 focus:ring-indigo-500 disabled:bg-slate-50 disabled:text-slate-400">
                <span class="text-xs text-slate-400">곳 (비우면 선정 표시 안 함)</span>
            </label>
            <button @disabled(! $event->is_open)
                    class="rounded-lg bg-indigo-600 hover:bg-indigo-700 disabled:bg-slate-300 text-white text-sm font-semibold px-4 py-1.5 transition">저장</button>
        </div>
    </form>
    @if ($event->scoring_method === 'trimmed' && $event->judges->count() < 3)
        <div class="mt-3 rounded-lg bg-amber-50 border border-amber-300 text-amber-800 px-4 py-2.5 text-sm">
            ⚠️ 현재 심사위원이 <strong>{{ $event->judges->count() }}명</strong>입니다.
            최고·최저 제외는 <strong>대상별 채점 심사위원이 3명 이상</strong>일 때만 적용되며, 미만이면 제외 없이 전체 점수로 집계됩니다.
        </div>
    @endif
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
