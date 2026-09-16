@extends('layouts.app')

@section('title', '전체 관리자')

@section('header-right')
    <form method="POST" action="{{ route('root.logout') }}">
        @csrf
        <button class="text-sm text-slate-500 hover:text-indigo-600">나가기</button>
    </form>
@endsection

@section('content')

{{--
    목록 전체가 하나의 폼이다. 지울 것을 고르고 아래에서 한 번에 보낸다.
    행사마다 따로 지우게 하면 수십 개를 치우는 일이 불가능해져 아무도 치우지 않는다.
--}}
<form method="POST" action="{{ route('root.destroy') }}" x-data="rootPurge()">
    @csrf
    @method('DELETE')

    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">전체 관리자</h1>
            <p class="mt-1 text-sm text-slate-500">
                행사 {{ $events->count() }}개
                @if ($emptyCount > 0)
                    · <span class="font-semibold text-amber-700">비어 있음 {{ $emptyCount }}개</span>
                @endif
            </p>
        </div>
        @if ($emptyCount > 0)
            <button type="button" x-on:click="selectEmpty()"
                    class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                비어 있는 행사 모두 선택
            </button>
        @endif
    </div>

    @if ($events->isEmpty())
        <div class="rounded-2xl border border-slate-200 bg-white p-12 text-center text-slate-500">
            아직 만들어진 행사가 없습니다.
        </div>
    @else
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
            @foreach ($events as $event)
                @php
                    $isEmpty = $event->candidates_count === 0
                        && $event->criteria_count === 0
                        && $event->judges_count === 0;
                @endphp
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2 border-b border-slate-100 px-4 py-3 last:border-b-0 sm:px-5">
                    <label class="flex cursor-pointer items-center">
                        <input type="checkbox" name="ids[]" value="{{ $event->id }}"
                               data-empty="{{ $isEmpty ? '1' : '0' }}"
                               x-on:change="count()"
                               @disabled($event->is_demo)
                               class="h-4 w-4 rounded border-slate-300 text-rose-600 focus:ring-rose-500 disabled:opacity-30">
                    </label>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-semibold text-slate-900">{{ $event->name }}</span>
                            @if ($event->is_demo)
                                <span class="rounded-full bg-indigo-50 px-2 py-0.5 text-[11px] font-bold text-indigo-700">체험용</span>
                            @elseif (! $event->is_open)
                                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-bold text-amber-800">마감</span>
                            @endif
                            @if ($isEmpty && ! $event->is_demo)
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-bold text-slate-500">비어 있음</span>
                            @endif
                        </div>
                        <p class="mt-0.5 text-xs text-slate-500">
                            대상 {{ $event->candidates_count }} · 항목 {{ $event->criteria_count }} · 심사위원 {{ $event->judges_count }}
                            · 개설 {{ $event->created_at?->format('Y-m-d') ?? '—' }}
                        </p>
                    </div>

                    {{-- 들어가기는 삭제 폼 안에 둘 수 없다(중첩 form 금지). formaction 으로 목적지만 바꾼다. --}}
                    <button type="submit" formmethod="POST" formaction="{{ route('root.enter', $event) }}"
                            class="rounded-lg border border-slate-300 px-3.5 py-1.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                        들어가기
                    </button>
                </div>
            @endforeach
        </div>

        <div class="mt-4 rounded-2xl border border-rose-200 bg-rose-50/50 p-5">
            <p class="text-sm font-bold text-rose-700">선택한 행사 삭제</p>
            <p class="mt-1 text-sm text-rose-600/80">
                평가 대상·항목·심사위원·모든 점수가 함께 사라지며 되돌릴 수 없습니다.
                체험용 샘플은 선택할 수 없습니다.
            </p>
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <label class="text-sm text-rose-700">
                    확인하려면 <strong>삭제</strong> 를 입력하세요
                    <input type="text" name="confirm" x-model="confirm" placeholder="삭제"
                           class="ml-2 w-28 rounded-lg border border-rose-300 bg-white px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-rose-400">
                </label>
                <button type="submit"
                        x-bind:disabled="selected === 0 || confirm.trim() !== '삭제'"
                        class="rounded-lg bg-rose-600 px-5 py-2 text-sm font-semibold text-white transition hover:bg-rose-700 disabled:cursor-not-allowed disabled:bg-rose-300">
                    <span x-text="selected === 0 ? '선택한 행사 삭제' : selected + '개 행사 삭제'"></span>
                </button>
            </div>
        </div>
    @endif
</form>

@push('scripts')
<script>
    function rootPurge() {
        return {
            selected: 0,
            confirm: '',

            boxes() {
                return Array.from(this.$el.querySelectorAll('input[name="ids[]"]:not(:disabled)'));
            },

            count() {
                this.selected = this.boxes().filter(box => box.checked).length;
            },

            // 만들다 만 테스트 행사가 대부분 이 모습이라, 한 번에 고를 수 있게 한다
            selectEmpty() {
                this.boxes().forEach(box => { box.checked = box.dataset.empty === '1'; });
                this.count();
            },
        };
    }
</script>
@endpush

@endsection
