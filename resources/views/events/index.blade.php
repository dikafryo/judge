@extends('layouts.app')

@section('title', '행사 관리')

@section('header-right')
    <a href="{{ route('demo') }}" class="mr-3 text-amber-700 hover:underline font-medium">체험해 보기</a>
    <a href="{{ route('home') }}" class="text-indigo-600 hover:underline font-medium">← 홈으로</a>
@endsection

@section('content')
<div x-data="{ createOpen: {{ $errors->any() || $events->isEmpty() ? 'true' : 'false' }} }">

<div class="flex items-start justify-between flex-wrap gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">행사 관리</h1>
        <p class="mt-1 text-sm text-slate-500">행사를 클릭하면 해당 행사 설정으로 이동합니다. (관리 비밀번호 필요)</p>
        <p class="mt-1 text-xs text-amber-600">⚠️ 행사일(미지정 시 등록일) 기준 30일이 지나면 자동 삭제됩니다. <strong>마감 처리한 행사는 2년간 보관</strong>되니, 보관할 행사는 꼭 마감하세요.</p>
    </div>
    <button type="button" x-on:click="createOpen = !createOpen"
            class="rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 text-sm font-semibold transition">＋ 새 행사 만들기</button>
</div>

{{-- 새 행사 생성 폼 --}}
<section x-show="createOpen" x-cloak class="mb-6 bg-white rounded-2xl shadow-sm border border-emerald-200 p-6">
    <div class="flex items-center gap-3 mb-4">
        <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-emerald-100 text-emerald-600 text-lg">📋</span>
        <h2 class="text-lg font-bold">새 행사 만들기</h2>
    </div>
    <p class="text-sm text-slate-500 mb-4">행사를 만들고 비밀번호로 관리하세요. 별도 회원가입이 없습니다.</p>

    <form method="POST" action="{{ route('events.store') }}" class="grid sm:grid-cols-3 gap-3">
        @csrf
        <input type="text" name="name" value="{{ old('name') }}" required maxlength="100"
               placeholder="행사명 (예: 2026 창업 경진대회)"
               class="rounded-lg border-slate-300 border px-4 py-2.5 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
        {{-- 날짜 입력 — 숫자 연속 입력(20260712)도 자동으로 2026-07-12로 채워지고, 📅 버튼으로 달력 피커도 사용 가능 --}}
        <div class="relative">
            <input type="text" name="event_date" value="{{ old('event_date') }}" x-ref="dateText"
                   inputmode="numeric" maxlength="10" pattern="\d{4}-\d{2}-\d{2}"
                   placeholder="행사일 (예: 20260712)" title="연월일 8자리 숫자 (예: 20260712)"
                   x-on:input="const v = $el.value.replace(/\D/g, '').slice(0, 8);
                               $el.value = v.length > 6 ? v.slice(0, 4) + '-' + v.slice(4, 6) + '-' + v.slice(6)
                                         : v.length > 4 ? v.slice(0, 4) + '-' + v.slice(4)
                                         : v"
                   class="w-full rounded-lg border-slate-300 border px-4 py-2.5 pr-11 text-slate-600 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
            <input type="date" x-ref="datePicker" tabindex="-1" aria-hidden="true"
                   class="absolute right-2 bottom-1 w-px h-px opacity-0 pointer-events-none"
                   x-on:change="$refs.dateText.value = $event.target.value">
            <button type="button" title="달력에서 날짜 선택" aria-label="달력에서 날짜 선택"
                    x-on:click="$refs.datePicker.value = /^\d{4}-\d{2}-\d{2}$/.test($refs.dateText.value) ? $refs.dateText.value : '';
                                try { $refs.datePicker.showPicker() } catch (e) { $refs.datePicker.focus() }"
                    class="absolute right-2 top-1/2 -translate-y-1/2 rounded-md px-1.5 py-1 text-lg leading-none hover:bg-slate-100 transition">📅</button>
        </div>
        <input type="password" name="admin_password" required minlength="4"
               placeholder="관리 비밀번호 (4자 이상)"
               class="rounded-lg border-slate-300 border px-4 py-2.5 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
        <button type="submit"
                class="sm:col-span-3 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-3 transition">
            행사 만들기
        </button>
    </form>
    <p class="mt-3 text-xs text-slate-400">※ 비밀번호는 행사 관리(대상·항목·심사위원 등록, 대시보드)에 사용됩니다. 분실 시 복구할 수 없으니 꼭 기억해 두세요.</p>
</section>

<section class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-slate-50 text-slate-500 text-xs uppercase">
                    <th class="px-4 py-3 text-center w-16">번호</th>
                    <th class="px-4 py-3 text-left">행사명</th>
                    <th class="px-4 py-3 text-center whitespace-nowrap">행사일</th>
                    <th class="px-4 py-3 text-center">평가 대상</th>
                    <th class="px-4 py-3 text-center">심사위원</th>
                    <th class="px-4 py-3 text-center">상태</th>
                    <th class="px-4 py-3 text-center whitespace-nowrap">등록일</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($events as $event)
                    <tr class="hover:bg-indigo-50/40 transition cursor-pointer"
                        onclick="location.href='{{ route('admin.setup', $event) }}'">
                        <td class="px-4 py-3 text-center text-slate-400">
                            {{ $events->total() - (($events->currentPage() - 1) * $events->perPage()) - $loop->index }}
                        </td>
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.setup', $event) }}"
                               class="font-semibold text-slate-800 hover:text-indigo-600">{{ $event->name }}</a>
                            @if ($event->description)
                                <span class="block text-xs text-slate-400 truncate max-w-md">{{ $event->description }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center text-slate-500 whitespace-nowrap">
                            {{ $event->event_date?->format('Y-m-d') ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-center text-slate-600">{{ $event->candidates_count }}</td>
                        <td class="px-4 py-3 text-center text-slate-600">{{ $event->judges_count }}</td>
                        <td class="px-4 py-3 text-center">
                            @if ($event->is_open)
                                <span class="inline-flex items-center rounded-full bg-emerald-50 text-emerald-700 px-2.5 py-0.5 text-xs font-semibold">진행중</span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-slate-100 text-slate-500 px-2.5 py-0.5 text-xs font-semibold">마감</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center text-slate-400 whitespace-nowrap">
                            {{ $event->created_at->format('Y-m-d') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center text-slate-400">
                            등록된 행사가 없습니다. 위의 "새 행사 만들기"로 첫 행사를 만들어 보세요.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

@if ($events->hasPages())
    <div class="mt-6">
        {{ $events->links() }}
    </div>
@endif

</div>
@endsection
