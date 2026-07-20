@extends('layouts.app')

@section('title', $event->name . ' 대시보드')

@section('header-right')
    <form method="POST" action="{{ route('admin.logout', $event) }}">
        @csrf
        <button class="text-slate-400 hover:text-slate-600">로그아웃</button>
    </form>
@endsection

@section('content')
@include('admin.partials.nav')

<div x-data="dashboard()" x-cloak>

    <div class="flex items-start justify-between flex-wrap gap-4 mb-6">
        <div>
            <p class="text-sm text-slate-500 flex items-center gap-2">
                <span class="relative flex h-2.5 w-2.5">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                </span>
                5초마다 자동 갱신 · 마지막 갱신 <span x-text="data?.generated_at ?? '—'"></span>
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold"
                      x-show="data"
                      x-bind:class="data?.event.scoring_method === 'trimmed' ? 'bg-violet-50 text-violet-700' : 'bg-slate-100 text-slate-600'"
                      x-text="data?.event.scoring_method === 'trimmed' ? '집계: 최고·최저 총점 제외' : '집계: 전체 합계·평균'"></span>
                <span class="inline-flex items-center rounded-full bg-amber-50 text-amber-700 px-2.5 py-0.5 text-xs font-semibold"
                      x-show="data && data.event.scoring_method === 'trimmed' && data.judges.length < 3" x-cloak>
                    ⚠️ 심사위원 3명 미만 — 제외 없이 전체 집계
                </span>
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.export', $event) }}"
               class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold hover:bg-slate-50 transition">⬇️ CSV 다운로드</a>
            <a href="{{ route('admin.print', $event) }}" target="_blank"
               class="rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 text-sm font-semibold transition">🖨️ 최종결과 출력</a>
        </div>
    </div>

    {{-- 마지막 선정 순위 동점 경고 --}}
    <div x-show="data?.pass_tie" x-cloak
         class="mb-6 rounded-lg bg-rose-50 border border-rose-300 text-rose-700 px-4 py-3 text-sm">
        <strong>⚠️ 동점자 발생!</strong>
        <span x-text="data?.pass_tie
            ? `${data.pass_tie.rank}위 동점 ${data.pass_tie.tied}곳, 남은 선정 자리 ${data.pass_tie.slots}곳 — 점수를 조정해 동점을 해소하세요.`
            : ''"></span>
    </div>

    {{-- 심사위원 진행 현황 --}}
    <section class="mb-6">
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
            <template x-for="j in (data?.judges ?? [])" :key="j.judge_id">
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
                    <div class="flex items-center justify-between gap-1">
                        <span class="font-semibold text-sm truncate" x-text="j.name"></span>
                        <span class="flex items-center gap-1 shrink-0">
                            <span class="text-xs" x-text="j.signed ? '✍️' : ''" title="서명 완료"></span>
                            <a x-bind:href="'{{ url('admin/' . $event->id . '/judges') }}/' + j.judge_id + '/sheet'" target="_blank"
                               class="text-xs rounded bg-indigo-50 text-indigo-600 px-1.5 py-0.5 hover:bg-indigo-100 transition"
                               title="개별심사표 출력">🖨️</a>
                        </span>
                    </div>
                    <div class="mt-2 flex items-end justify-between">
                        <span class="text-lg font-bold"
                              x-bind:class="j.done === j.total && j.total > 0 ? 'text-emerald-600' : 'text-slate-700'"
                              x-text="j.done + ' / ' + j.total"></span>
                        <span class="text-xs text-slate-400">완료</span>
                    </div>
                    <div class="mt-2 h-1.5 rounded-full bg-slate-100 overflow-hidden">
                        <div class="h-full rounded-full bg-indigo-500 transition-all duration-500"
                             x-bind:style="'width:' + (j.total ? Math.round(j.done / j.total * 100) : 0) + '%'"></div>
                    </div>
                </div>
            </template>
        </div>
    </section>

    {{-- 집계 표 --}}
    <section class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 text-xs uppercase">
                        <th class="px-4 py-3 text-center w-16">순위</th>
                        <th class="px-4 py-3 text-left">평가 대상</th>
                        <template x-for="j in (data?.judges ?? [])" :key="'h' + j.judge_id">
                            <th class="px-3 py-3 text-center whitespace-nowrap" x-text="j.name"></th>
                        </template>
                        <th class="px-4 py-3 text-center bg-slate-100">총점</th>
                        <th class="px-4 py-3 text-center bg-indigo-50 text-indigo-600">평균</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <template x-for="row in (data?.rows ?? [])" :key="row.candidate_id">
                        <tr class="transition"
                            x-bind:class="row.pass === 'pass' ? 'bg-emerald-50/70 hover:bg-emerald-50'
                                        : row.pass === 'tie' ? 'bg-amber-50/80 hover:bg-amber-50'
                                        : 'hover:bg-slate-50'">
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold"
                                      x-bind:class="row.rank === 1 ? 'bg-amber-100 text-amber-700'
                                                  : row.rank === 2 ? 'bg-slate-200 text-slate-600'
                                                  : row.rank === 3 ? 'bg-orange-100 text-orange-700'
                                                  : 'text-slate-400'"
                                      x-text="row.rank ?? '—'"></span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded bg-slate-100 text-slate-500 px-1.5 py-0.5 text-xs font-bold mr-1.5"
                                      x-text="row.number" title="심사번호 (심사위원에게 보이는 번호)"></span>
                                <span class="font-semibold text-slate-800" x-text="row.name"></span>
                                <span class="text-xs text-slate-400 ml-1" x-text="row.affiliation ?? ''"></span>
                                <span x-show="row.pass === 'pass'" x-cloak
                                      class="ml-1 inline-flex items-center rounded-full bg-emerald-100 text-emerald-700 px-2 py-0.5 text-xs font-bold align-middle">선정</span>
                                <span x-show="row.pass === 'tie'" x-cloak
                                      class="ml-1 inline-flex items-center rounded-full bg-amber-100 text-amber-700 px-2 py-0.5 text-xs font-bold align-middle">동점 — 확정 불가</span>
                                <span class="text-xs text-slate-400 block"
                                      x-text="'심사 ' + row.judged_count + '/' + (data?.judges.length ?? 0) + '명 완료'"></span>
                            </td>
                            <template x-for="j in (data?.judges ?? [])" :key="'c' + row.candidate_id + '-' + j.judge_id">
                                <td class="px-3 py-3 text-center tabular-nums"
                                    x-bind:class="row.by_judge[j.judge_id] === null || row.by_judge[j.judge_id] === undefined
                                        ? 'text-slate-300'
                                        : (row.by_judge_excluded && row.by_judge_excluded[j.judge_id] !== undefined)
                                            ? 'text-rose-500 line-through'
                                            : 'text-slate-700 font-medium'"
                                    x-text="row.by_judge[j.judge_id] ?? '—'"></td>
                            </template>
                            <td class="px-4 py-3 text-center font-bold tabular-nums bg-slate-50" x-text="row.sum"></td>
                            <td class="px-4 py-3 text-center font-extrabold text-indigo-600 tabular-nums bg-indigo-50/50"
                                x-text="row.avg ?? '—'"></td>
                        </tr>
                    </template>
                    <tr x-show="data && data.rows.length === 0">
                        <td colspan="99" class="px-4 py-10 text-center text-slate-400">등록된 평가 대상이 없습니다. 설정에서 대상을 먼저 등록하세요.</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p class="px-4 py-3 text-xs text-slate-400 border-t border-slate-100">
            ※ <span x-text="data?.event.scoring_note ?? ''"></span><br>
            <template x-if="data?.event.scoring_method === 'trimmed'">
                <span>※ <span class="text-rose-500 line-through">취소선</span> 점수는 최고·최저 총점으로 집계에서 제외된 점수입니다.<br></span>
            </template>
            ※ 모든 항목 채점을 완료한 심사위원의 점수만 반영하며, 순위는 평균 점수 기준입니다.<br>
            <template x-if="data?.event.pass_count">
                <span>※ 선정자(선정기관) 수 <strong x-text="data.event.pass_count"></strong>곳 기준 — 평균 상위 순으로 <span class="text-emerald-600 font-semibold">선정</span> 표시되며, 마지막 선정 순위에 동점이 생기면 <span class="text-amber-600 font-semibold">동점</span>으로 표시됩니다.</span>
            </template>
        </p>
    </section>
</div>
@endsection

@push('scripts')
<script>
    function dashboard() {
        return {
            data: null,
            timer: null,
            // setInterval에 메서드를 그대로 넘기면 this 바인딩이 풀려 화면이 갱신되지 않으므로
            // 반드시 화살표 함수로 감싸 컴포넌트 컨텍스트를 유지한다
            init() {
                this.load();
                this.timer = setInterval(() => this.load(), 5000);
            },
            async load() {
                try {
                    const res = await fetch('{{ route('admin.dashboard.data', $event) }}', {
                        headers: { 'Accept': 'application/json' },
                        cache: 'no-store',
                    });
                    if (res.status === 401) { location.href = '{{ route('admin.login', $event) }}'; return; }
                    if (!res.ok) return; // 일시 오류는 다음 폴링에서 재시도
                    this.data = await res.json();
                } catch (e) {
                    /* 네트워크 일시 오류 — 다음 폴링에서 재시도 */
                }
            },
        };
    }
</script>
@endpush
