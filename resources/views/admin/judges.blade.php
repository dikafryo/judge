@extends('layouts.app')

@section('title', $event->name . ' 심사위원')

@section('header-right')
    <form method="POST" action="{{ route('admin.logout', $event) }}">
        @csrf
        <button class="text-slate-400 hover:text-slate-600">로그아웃</button>
    </form>
@endsection

@section('content')
@include('admin.partials.nav')

<div class="grid lg:grid-cols-2 gap-6">

    {{-- 등록된 심사위원 --}}
    <section class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-bold text-lg">심사위원 <span class="text-sm text-slate-400 font-normal">({{ $event->judges->count() }})</span></h2>
            @if ($event->judges->whereNotNull('code')->isNotEmpty())
                <a href="{{ route('admin.judges.print', $event) }}" target="_blank"
                   class="text-xs rounded-lg border border-indigo-200 bg-indigo-50 text-indigo-600 px-3 py-1.5 font-semibold hover:bg-indigo-100 transition">
                    📱 접속안내 출력 (QR)
                </a>
            @endif
        </div>

        <ul class="space-y-2 max-h-[32rem] overflow-y-auto">
            @forelse ($event->judges as $judge)
                <li class="rounded-lg bg-slate-50 px-3 py-2">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium">{{ $judge->name }}</span>
                        <div class="flex items-center gap-2">
                            @if ($judge->code)
                                <a href="{{ route('judge.show', $judge) }}" target="_blank"
                                   class="text-xs rounded bg-indigo-50 text-indigo-600 px-2 py-1 hover:bg-indigo-100 transition">심사 바로가기 ↗</a>
                            @endif
                            @if ($event->is_open)
                                <form method="POST" action="{{ route('admin.judges.destroy', [$event, $judge]) }}"
                                      onsubmit="return confirm('이 심사위원과 입력한 점수가 모두 삭제됩니다. 계속할까요?')">
                                    @csrf @method('DELETE')
                                    <button class="text-slate-300 hover:text-rose-500 text-sm">✕</button>
                                </form>
                            @endif
                        </div>
                    </div>
                    <div class="mt-1 text-xs font-mono tracking-wider {{ $judge->code ? 'text-slate-400' : 'text-rose-400' }}">
                        {{ $judge->code ? '코드: ' . $judge->code : '코드 회수됨 (마감) — 재개 시 새로 발급' }}
                    </div>
                </li>
            @empty
                <li class="text-sm text-slate-400 text-center py-4">등록된 심사위원이 없습니다.</li>
            @endforelse
        </ul>
    </section>

    {{-- 일괄 등록 --}}
    <section class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 h-fit">
        <h2 class="font-bold text-lg mb-4">심사위원 등록 / 일괄 등록</h2>

        @unless ($event->is_open)
            <p class="text-sm text-slate-400 text-center py-6">🔒 심사 마감 — 심사위원을 추가·삭제할 수 없습니다.</p>
        @else
            <form method="POST" action="{{ route('admin.judges.store', $event) }}" class="space-y-2">
                @csrf
                <textarea name="bulk" rows="6" required
                          placeholder="한 줄에 한 명씩 이름 입력&#10;예)&#10;홍길동&#10;이심사"
                          class="w-full rounded-lg border-slate-300 border px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                <button class="w-full rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold py-2 transition">심사위원 일괄 등록</button>
            </form>
            <p class="mt-2 text-xs text-slate-400">등록 시 고유 접속 코드가 자동 발급됩니다. '심사 바로가기'로 심사 페이지를 열거나, '접속안내 출력 (QR)'로 심사위원에게 전달하세요.</p>
        @endunless
    </section>
</div>
@endsection
