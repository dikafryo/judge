{{-- 자동 저장 상태 표시 — 조작한 자리 바로 옆에 둔다.

     · 색만으로 알리지 않는다: 글리프(✓ ⚠)와 한국어 문구가 본체고 색은 보조다
     · 성공은 2.5초 뒤 사라지고 실패는 남는다 — 실패가 조용히 지워지면 안 된다
     · min-h 로 자리를 미리 비워 표시가 사라질 때 줄 높이가 튀지 않게 한다
     · '저장 중'은 aria-hidden — 한 번 조작에 두 번 낭독되는 것을 막는다 --}}
@props(['group', 'undo' => false])

<span role="status" aria-live="polite"
      class="w-full sm:w-auto sm:ml-auto text-xs font-semibold min-h-[1.25rem] flex items-center gap-2"
      x-cloak>
    <span x-show="state.{{ $group }} === 'saving'" aria-hidden="true" class="text-slate-500">저장 중…</span>

    <span x-show="state.{{ $group }} === 'saved'" x-transition.opacity.duration.150ms class="text-emerald-700">
        ✓ 저장되었습니다
        <span class="sr-only" x-text="detail"></span>
    </span>

    @if ($undo)
        <button type="button" x-show="state.{{ $group }} === 'saved' && undoable.{{ $group }} !== null"
                x-on:click="undo('{{ $group }}')"
                class="underline underline-offset-2 text-slate-500 hover:text-slate-700 rounded focus:outline-none focus:ring-2 focus:ring-indigo-500">
            되돌리기
        </button>
    @endif

    <span x-show="state.{{ $group }} === 'error'" class="text-rose-700 flex items-center gap-2">
        ⚠ 저장 실패 — 다시 시도해 주세요
        <button type="button" x-on:click="save('{{ $group }}', true)"
                class="underline underline-offset-2 rounded focus:outline-none focus:ring-2 focus:ring-indigo-500">다시 저장</button>
    </span>
</span>
