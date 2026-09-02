{{-- 체험용 샘플 행사 안내 — 조회·출력만 가능하고 저장은 차단된다는 것을 항상 알린다 --}}
@if ($event->is_demo)
    <div class="mb-6 rounded-xl bg-amber-50 border border-amber-300 text-amber-900 px-4 py-3 text-sm flex items-start gap-3">
        <span class="text-lg leading-none">🧪</span>
        <div>
            <strong>체험용 샘플 행사입니다.</strong>
            화면과 기능은 실제와 동일하지만 <strong>저장·수정·삭제는 되지 않습니다.</strong>
            <a href="{{ route('demo') }}" class="underline underline-offset-4 font-semibold hover:text-amber-700">체험 안내로 돌아가기</a>
        </div>
    </div>
@endif
