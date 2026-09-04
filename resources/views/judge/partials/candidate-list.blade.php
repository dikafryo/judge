{{-- 평가 대상 목록 + 검색·필터.
     데스크톱 사이드바와 모바일 드로어 양쪽에서 같은 마크업을 쓴다.
     대상이 100명을 넘는 행사에서는 목록만으로 못 찾으므로 검색과 필터가 필수다. --}}
<div class="flex min-h-0 flex-1 flex-col">

    <div class="space-y-2 pb-3">
        <input type="search" x-model="search" enterkeyhint="search"
               placeholder="{{ $event->is_blind ? '심사번호로 찾기' : '번호 · 이름으로 찾기' }}"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500">

        <div class="flex gap-1">
            <template x-for="f in filters" :key="f.id">
                <button type="button" x-on:click="filter = f.id"
                        class="flex-1 rounded-lg px-2 py-1.5 text-xs font-semibold transition"
                        x-bind:class="filter === f.id ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-500 hover:bg-slate-200'"
                        x-text="f.label + ' ' + countOf(f.id)"></button>
            </template>
        </div>
    </div>

    <ul class="min-h-0 flex-1 space-y-1 overflow-y-auto">
        <template x-for="c in visible()" :key="c.id">
            <li>
                <button type="button" x-on:click="select(c.id)"
                        class="flex w-full items-center justify-between rounded-lg px-3 py-3 text-left text-sm transition"
                        x-bind:class="selectedId === c.id
                            ? 'bg-indigo-600 text-white font-semibold'
                            : 'hover:bg-slate-100 text-slate-700'">
                    <span class="flex min-w-0 items-center gap-1.5 font-semibold">
                        <span class="truncate" x-text="label(c)"></span>
                        <span x-show="pending[c.id]" x-cloak title="전송 대기 중"
                              class="shrink-0 rounded px-1.5 py-0.5 text-[10px] font-bold"
                              x-bind:class="selectedId === c.id ? 'bg-white/25 text-white' : 'bg-amber-100 text-amber-700'">대기</span>
                    </span>
                    <span class="ml-2 shrink-0 text-xs"
                          x-bind:class="isComplete(c.id)
                              ? (selectedId === c.id ? 'text-emerald-200' : 'text-emerald-600')
                              : (savedCount(c.id) > 0
                                  ? (selectedId === c.id ? 'text-amber-200' : 'text-amber-600')
                                  : (selectedId === c.id ? 'text-white/50' : 'text-slate-300'))"
                          x-text="statusOf(c.id)"></span>
                </button>
            </li>
        </template>

        <template x-if="visible().length === 0">
            <li class="px-3 py-8 text-center text-sm text-slate-400">찾는 대상이 없습니다.</li>
        </template>
    </ul>
</div>
