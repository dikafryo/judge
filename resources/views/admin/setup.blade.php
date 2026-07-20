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

{{-- 배점 합계 경고 --}}
@if ($totalMax !== 100)
    <div class="mb-6 rounded-lg bg-amber-50 border border-amber-300 text-amber-800 px-4 py-3 text-sm">
        ⚠️ 현재 평가 항목(1레벨) 배점 합계가 <strong>{{ $totalMax }}점</strong>입니다. 심사를 시작하려면 반드시 <strong>100점</strong>이 되도록 항목을 구성하세요.
    </div>
@endif

{{-- 2레벨 배점 불일치 경고 --}}
@php
    $mismatchGroups = $topCriteria->filter(function ($top) use ($byParent) {
        $children = $byParent->get($top->id, collect());
        return $children->isNotEmpty() && (int) $children->sum('max_score') !== (int) $top->max_score;
    });
@endphp
@if ($mismatchGroups->isNotEmpty())
    <div class="mb-6 rounded-lg bg-amber-50 border border-amber-300 text-amber-800 px-4 py-3 text-sm">
        ⚠️ 2레벨 배점 합계가 1레벨 배점과 다릅니다:
        @foreach ($mismatchGroups as $g)
            <strong>{{ $g->name }}</strong> (2레벨 합계 {{ (int) $byParent->get($g->id, collect())->sum('max_score') }}점 / 1레벨 {{ $g->max_score }}점){{ $loop->last ? '' : ', ' }}
        @endforeach
        — 심사 시작 전에 맞춰 주세요.
    </div>
@endif

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

{{-- 평가 항목 --}}
<section class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
    <div class="flex items-center justify-between mb-4">
        <h2 class="font-bold text-lg">평가 항목</h2>
        <span class="text-sm font-semibold {{ $totalMax === 100 ? 'text-emerald-600' : 'text-amber-600' }}">
            배점 합계 {{ $totalMax }} / 100
        </span>
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
        {{-- 1레벨(왼쪽) + 2레벨(오른쪽) 나란히 — 2/3 폭 --}}
        <ul class="lg:col-span-2 space-y-3">
            @forelse ($topCriteria as $criterion)
                @php
                    $children = $byParent->get($criterion->id, collect());
                    $childSum = (int) $children->sum('max_score');
                @endphp
                <li class="grid sm:grid-cols-2 gap-2 rounded-xl bg-slate-50 p-2">
                    {{-- 1레벨 카드 --}}
                    <div class="rounded-lg bg-white border border-slate-200 px-3 py-2 h-fit">
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="text-sm font-bold">{{ $criterion->name }}</span>
                                @if ($children->isNotEmpty())
                                    <span class="block text-xs {{ $childSum === (int) $criterion->max_score ? 'text-slate-400' : 'text-amber-600 font-semibold' }}">
                                        2레벨 {{ $childSum }}/{{ $criterion->max_score }}점
                                    </span>
                                @endif
                                @if ($criterion->description)
                                    <div class="text-xs text-slate-400">{{ $criterion->description }}</div>
                                @endif
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <span class="text-sm font-bold text-indigo-600">{{ $criterion->max_score }}점</span>
                                @if ($event->is_open)
                                    <form method="POST" action="{{ route('admin.criteria.destroy', [$event, $criterion]) }}"
                                          onsubmit="return confirm('이 항목{{ $children->isNotEmpty() ? '과 2레벨 항목' : '' }}, 관련 점수가 모두 삭제됩니다. 계속할까요?')">
                                        @csrf @method('DELETE')
                                        <button class="text-slate-300 hover:text-rose-500 text-sm">✕</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- 2레벨 목록 (1레벨 우측, 세로 배치) --}}
                    <ul class="space-y-1.5">
                        @forelse ($children as $child)
                            <li class="flex items-center justify-between rounded-lg bg-white border border-indigo-100 px-3 py-1.5">
                                <div>
                                    <span class="text-sm text-slate-600">{{ $child->name }}</span>
                                    @if ($child->description)
                                        <span class="text-xs text-slate-400 ml-1">{{ $child->description }}</span>
                                    @endif
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <span class="text-xs font-bold text-indigo-500">{{ $child->max_score }}점</span>
                                    @if ($event->is_open)
                                        <form method="POST" action="{{ route('admin.criteria.destroy', [$event, $child]) }}"
                                              onsubmit="return confirm('이 2레벨 항목과 관련 점수가 모두 삭제됩니다. 계속할까요?')">
                                            @csrf @method('DELETE')
                                            <button class="text-slate-300 hover:text-rose-500 text-xs">✕</button>
                                        </form>
                                    @endif
                                </div>
                            </li>
                        @empty
                            <li class="text-xs text-slate-400 px-2 py-2">2레벨 항목 없음 — 이 1레벨 항목에서 직접 채점</li>
                        @endforelse
                    </ul>
                </li>
            @empty
                <li class="text-sm text-slate-400 text-center py-4">등록된 항목이 없습니다.</li>
            @endforelse
        </ul>

        @unless ($event->is_open)
            <div class="h-fit rounded-xl border border-slate-200 p-4 bg-slate-50/50 text-sm text-slate-400 text-center">
                🔒 심사 마감 — 항목을 추가·삭제할 수 없습니다.
            </div>
        @else
        <form method="POST" action="{{ route('admin.criteria.store', $event) }}"
              class="space-y-2 h-fit rounded-xl border border-slate-200 p-4 bg-slate-50/50">
            @csrf
            <h3 class="text-sm font-bold text-slate-600 mb-1">항목 추가</h3>
            <select name="parent_id"
                    class="w-full rounded-lg border-slate-300 border px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500 text-slate-600 bg-white">
                <option value="">1레벨 항목으로 추가</option>
                @foreach ($topCriteria as $top)
                    <option value="{{ $top->id }}" {{ old('parent_id') == $top->id ? 'selected' : '' }}>
                        ‘{{ $top->name }}’의 2레벨 항목으로 추가
                    </option>
                @endforeach
            </select>
            <input type="text" name="name" required maxlength="100" placeholder="항목명 (예: 창의성 또는 b1. 독창성)"
                   class="w-full rounded-lg border-slate-300 border px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
            <input type="text" name="description" maxlength="500" placeholder="평가 기준 설명 (선택)"
                   class="w-full rounded-lg border-slate-300 border px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
            <div class="flex gap-2">
                <input type="number" name="max_score" required min="1" max="100" placeholder="배점"
                       class="w-24 rounded-lg border-slate-300 border px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                <button class="flex-1 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold py-2 transition">항목 추가</button>
            </div>
            <p class="text-xs text-slate-400">2레벨 항목이 있는 1레벨 항목은 2레벨에서 채점하며, 2레벨 배점 합계 = 1레벨 배점이어야 합니다.</p>
        </form>
        @endunless
    </div>
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
