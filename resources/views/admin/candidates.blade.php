@extends('layouts.app')

@section('title', $event->name . ' 평가대상')

@section('header-right')
    <form method="POST" action="{{ route('admin.logout', $event) }}">
        @csrf
        <button class="text-slate-400 hover:text-slate-600">로그아웃</button>
    </form>
@endsection

@section('content')
@include('admin.partials.nav')

<div class="grid lg:grid-cols-2 gap-6">

    {{-- 등록된 평가 대상 --}}
    <section class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
        <h2 class="font-bold text-lg mb-1">평가 대상 <span class="text-sm text-slate-400 font-normal">({{ $event->candidates->count() }})</span></h2>
        <p class="text-xs text-slate-400 mb-4">
            @if ($event->is_blind)
                심사위원에게는 이름 대신 <strong>심사번호</strong>(등록순 01, 02…)만 보입니다 (블라인드 — 기본설정에서 변경 가능).
            @else
                심사위원에게 심사번호와 함께 <strong>이름이 공개</strong>됩니다 (기본설정에서 블라인드로 변경 가능).
            @endif
            심사 시작 후 대상을 삭제하면 뒤 번호가 당겨지므로 주의하세요.</p>

        <ul class="space-y-2 max-h-[32rem] overflow-y-auto">
            @forelse ($event->candidates as $candidate)
                <li class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2">
                    <div>
                        <span class="inline-flex items-center rounded bg-indigo-50 text-indigo-600 px-1.5 py-0.5 text-xs font-bold mr-1.5"
                              title="심사번호{{ $event->is_blind ? ' — 심사위원에게는 이 번호만 보입니다' : '' }}">{{ sprintf('%02d', $loop->iteration) }}</span>
                        <span class="text-sm font-medium">{{ $candidate->name }}</span>
                        @if ($candidate->affiliation)
                            <span class="text-xs text-slate-400 ml-1">{{ $candidate->affiliation }}</span>
                        @endif
                    </div>
                    @if ($event->is_open)
                        <form method="POST" action="{{ route('admin.candidates.destroy', [$event, $candidate]) }}"
                              onsubmit="return confirm('이 대상과 관련 점수가 모두 삭제됩니다. 계속할까요?')">
                            @csrf @method('DELETE')
                            <button class="text-slate-300 hover:text-rose-500 text-sm">✕</button>
                        </form>
                    @endif
                </li>
            @empty
                <li class="text-sm text-slate-400 text-center py-4">등록된 대상이 없습니다.</li>
            @endforelse
        </ul>
    </section>

    {{-- 일괄 등록 --}}
    <section class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 h-fit">
        <h2 class="font-bold text-lg mb-4">대상 추가 / 일괄 등록</h2>

        @unless ($event->is_open)
            <p class="text-sm text-slate-400 text-center py-6">🔒 심사 마감 — 평가 대상을 추가·삭제할 수 없습니다.</p>
        @else
            <form method="POST" action="{{ route('admin.candidates.store', $event) }}" class="space-y-2">
                @csrf
                <textarea name="bulk" rows="8" required
                          placeholder="한 줄에 하나씩 입력&#10;이름, 소속(선택)&#10;예)&#10;주식회사 미래테크, 부산&#10;김철수, OO대학교"
                          class="w-full rounded-lg border-slate-300 border px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                <button class="w-full rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold py-2 transition">대상 일괄 등록</button>
            </form>
            <p class="mt-2 text-xs text-slate-400">한 건만 입력해 추가할 수도, 여러 줄을 붙여넣어 일괄 등록할 수도 있습니다. 소속은 쉼표(<code>,</code>) 뒤에 적으세요. 소속이 없으면 이름만 적으면 됩니다.</p>
        @endunless
    </section>
</div>
@endsection
