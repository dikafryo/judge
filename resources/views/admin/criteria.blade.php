@extends('layouts.app')

@section('title', $event->name . ' 평가항목')

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
@endsection
