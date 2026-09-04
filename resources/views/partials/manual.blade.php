{{-- 사용 설명서 모달 — 헤더 우측 ? 버튼. 기능이 바뀌면 이 파일의 해당 섹션도 함께 갱신할 것 --}}
<div x-data="{ manualOpen: false, tab: 'intro' }" class="flex items-center">
    {{-- ? 버튼 --}}
    <button type="button" x-on:click="manualOpen = true" title="사용 설명서"
            class="ml-3 inline-flex items-center justify-center w-7 h-7 rounded-full border border-slate-300 text-slate-400 hover:text-indigo-600 hover:border-indigo-400 text-sm font-bold transition">
        ?
    </button>

    {{-- 모달 --}}
    <div x-show="manualOpen" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4"
         x-on:keydown.escape.window="manualOpen = false">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-3xl max-h-[85vh] flex flex-col"
             x-on:click.outside="manualOpen = false">

            {{-- 헤더 --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
                <h3 class="font-bold text-lg">📖 사용 설명서</h3>
                <button type="button" x-on:click="manualOpen = false"
                        class="text-slate-400 hover:text-slate-600 text-xl leading-none">&times;</button>
            </div>

            {{-- 탭 --}}
            <div class="px-6 pt-3 flex flex-wrap gap-1.5 border-b border-slate-100 pb-3">
                <template x-for="t in [
                    { id: 'intro',  label: '🏁 처음이신가요?' },
                    { id: 'admin',  label: '🗂️ 관리자 가이드' },
                    { id: 'judge',  label: '✍️ 심사위원 가이드' },
                    { id: 'faq',    label: '💬 자주 묻는 질문' },
                ]" :key="t.id">
                    <button type="button" x-on:click="tab = t.id"
                            class="rounded-lg px-3.5 py-1.5 text-sm font-semibold transition"
                            x-bind:class="tab === t.id ? 'bg-indigo-600 text-white shadow' : 'text-slate-500 hover:bg-slate-100'"
                            x-text="t.label"></button>
                </template>
            </div>

            {{-- 본문 --}}
            <div class="overflow-y-auto px-6 py-5 text-sm leading-relaxed text-slate-600">

                {{-- ================= 처음이신가요? ================= --}}
                <div x-show="tab === 'intro'" class="space-y-5">
                    <section>
                        <h4 class="font-bold text-slate-800 text-base mb-2">이 서비스는?</h4>
                        <p>심사위원이 점수를 입력하면 <strong>즉시 자동 집계</strong>되는 온라인 심사 시스템.
                        회원가입 없이 <strong>비밀번호 하나</strong>로 행사를 운영합니다.</p>
                    </section>

                    <section>
                        <h4 class="font-bold text-slate-800 text-base mb-2">역할은 둘</h4>
                        <div class="grid sm:grid-cols-2 gap-3">
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <div class="font-bold text-slate-700 mb-1">🗂️ 관리자</div>
                                <p class="text-xs">행사·항목·대상·심사위원 등록, 실시간 집계, 결과 출력.</p>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <div class="font-bold text-slate-700 mb-1">✍️ 심사위원</div>
                                <p class="text-xs">받은 <strong>6자리 코드</strong>(QR·링크)로 접속해 점수 입력 + 전자서명. 로그인 없음.</p>
                            </div>
                        </div>
                    </section>

                    <section>
                        <h4 class="font-bold text-slate-800 text-base mb-2">전체 흐름</h4>
                        <ol class="space-y-1.5">
                            <li class="flex gap-3"><span class="shrink-0 inline-flex items-center justify-center w-6 h-6 rounded-full bg-indigo-100 text-indigo-600 text-xs font-bold">1</span><span><strong>행사 만들기</strong> — 홈 아래 "여기를 눌러"</span></li>
                            <li class="flex gap-3"><span class="shrink-0 inline-flex items-center justify-center w-6 h-6 rounded-full bg-indigo-100 text-indigo-600 text-xs font-bold">2</span><span><strong>평가 항목</strong> — 배점 합계 100점</span></li>
                            <li class="flex gap-3"><span class="shrink-0 inline-flex items-center justify-center w-6 h-6 rounded-full bg-indigo-100 text-indigo-600 text-xs font-bold">3</span><span><strong>대상·심사위원 등록</strong> — 코드 자동 발급</span></li>
                            <li class="flex gap-3"><span class="shrink-0 inline-flex items-center justify-center w-6 h-6 rounded-full bg-indigo-100 text-indigo-600 text-xs font-bold">4</span><span><strong>코드 배부</strong> — QR 카드 인쇄</span></li>
                            <li class="flex gap-3"><span class="shrink-0 inline-flex items-center justify-center w-6 h-6 rounded-full bg-indigo-100 text-indigo-600 text-xs font-bold">5</span><span><strong>심사</strong> — 집계 화면에 실시간 반영</span></li>
                            <li class="flex gap-3"><span class="shrink-0 inline-flex items-center justify-center w-6 h-6 rounded-full bg-indigo-100 text-indigo-600 text-xs font-bold">6</span><span><strong>마감</strong> — CSV 저장, 최종집계표 출력</span></li>
                        </ol>
                    </section>

                    <div class="rounded-lg bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 text-xs">
                        ⚠️ <strong>관리 비밀번호는 복구할 수 없습니다.</strong> 꼭 따로 적어 두세요.
                    </div>
                </div>

                {{-- ================= 관리자 가이드 ================= --}}
                <div x-show="tab === 'admin'" x-cloak class="space-y-6">

                    <section>
                        <h4 class="font-bold text-slate-800 text-base mb-2">1. 행사 만들기</h4>
                        <ul class="list-disc list-inside space-y-1">
                            <li>홈 → "여기를 눌러" → <strong>[＋ 새 행사 만들기]</strong>. 행사명과 비밀번호(4자 이상)만 있으면 됩니다.</li>
                            <li class="text-amber-700"><strong>자동 삭제</strong>: 행사일 기준 30일 후 삭제. <strong>마감한 행사는 2년 보관</strong> — 보관하려면 꼭 마감하세요.</li>
                        </ul>
                    </section>

                    <section>
                        <h4 class="font-bold text-slate-800 text-base mb-2">2. 기본설정</h4>
                        <ul class="list-disc list-inside space-y-1">
                            <li><strong>집계 방식</strong>: 전체 평균(기본) 또는 최고·최저 제외(채점 3인 이상일 때 적용).</li>
                            <li><strong>심사위원 화면</strong>: 심사번호만 표시(블라인드, 기본) 또는 이름 공개.</li>
                            <li><strong>최종집계표 서명</strong>: 심사위원 서명란 포함(기본) 또는 생략하고 결재란만 — 생략 시 <strong>기록자 필수</strong>.</li>
                            <li><strong>결재란</strong>: 기록자·검토자·확인자의 부서·직급·이름 입력 → 출력물 하단에 표시. 이름 비운 역할은 빠집니다.</li>
                            <li><strong>선정자 수</strong>: 지정하면 상위 N곳에 <span class="text-emerald-600 font-semibold">선정</span> 표시.</li>
                        </ul>
                    </section>

                    <section>
                        <h4 class="font-bold text-slate-800 text-base mb-2">3. 평가항목</h4>
                        <ul class="list-disc list-inside space-y-1">
                            <li>배점 합계 <strong>100점 필수</strong> — 100점이 될 때까지 탭에 필수 표시가 남습니다.</li>
                            <li>세부(2레벨) 항목을 두면 2레벨에서 채점하며, 세부 합계 = 상위 배점이어야 합니다.</li>
                        </ul>
                    </section>

                    <section>
                        <h4 class="font-bold text-slate-800 text-base mb-2">4. 평가 대상</h4>
                        <ul class="list-disc list-inside space-y-1">
                            <li>한 줄에 하나씩 <strong>이름, 소속</strong> 형식으로 입력합니다. (예: <code>김철수, OO대학교</code>) 소속이 없으면 이름만 적으면 됩니다.</li>
                            <li>여러 줄을 한꺼번에 붙여넣어 일괄 등록. 등록순으로 <strong>심사번호(01, 02…)</strong> 자동 부여.</li>
                            <li>심사 시작 후 삭제하면 뒤 번호가 당겨지니 시작 전에 확정하세요.</li>
                        </ul>
                    </section>

                    <section>
                        <h4 class="font-bold text-slate-800 text-base mb-2">5. 심사위원</h4>
                        <ul class="list-disc list-inside space-y-1">
                            <li>이름만 등록하면 <strong>6자리 코드</strong>가 자동 발급됩니다.</li>
                            <li><strong>[접속안내 출력]</strong> → QR 카드 인쇄 배부. 링크 복사로 문자·메신저 전달도 가능.</li>
                        </ul>
                    </section>

                    <section>
                        <h4 class="font-bold text-slate-800 text-base mb-2">6. 심사 당일</h4>
                        <ul class="list-disc list-inside space-y-1">
                            <li>"집계" 탭이 <strong>5초마다 자동 갱신</strong> — 진행률·총점·평균·순위 실시간 확인.</li>
                            <li>동점 경고(⚠️)가 뜨면 점수를 조정해 동점을 해소하세요.</li>
                            <li>심사위원 카드의 🖨️로 개별심사표 출력.</li>
                        </ul>
                    </section>

                    <section>
                        <h4 class="font-bold text-slate-800 text-base mb-2">7. 마감과 결과</h4>
                        <ul class="list-disc list-inside space-y-1">
                            <li><strong>[심사 마감하기]</strong> → 접속 코드 회수, 수정 잠김(조회·출력은 가능).</li>
                            <li><strong>[⬇️ CSV]</strong> 엑셀에서 바로 열림 · <strong>[🖨️ 최종결과 출력]</strong> A4 최종집계표.</li>
                            <li>재개하면 코드가 <strong>새로 발급</strong>되니 다시 배부해야 합니다.</li>
                        </ul>
                    </section>

                    <section>
                        <h4 class="font-bold text-slate-800 text-base mb-2">8. 행사 삭제</h4>
                        <p>기본설정 맨 아래에서 행사명을 그대로 재입력하면 삭제.
                        <span class="text-rose-600 font-semibold">모든 데이터가 지워지고 되돌릴 수 없습니다</span> — 출력·CSV 저장을 먼저 하세요.</p>
                    </section>
                </div>

                {{-- ================= 심사위원 가이드 ================= --}}
                <div x-show="tab === 'judge'" x-cloak class="space-y-6">

                    <section>
                        <h4 class="font-bold text-slate-800 text-base mb-2">1. 접속</h4>
                        <p>로그인 없음. 셋 중 하나로 접속하세요:
                        <strong>QR 스캔</strong> · 홈에서 <strong>6자리 코드</strong> 입력 · 받은 <strong>링크</strong> 클릭.</p>
                    </section>

                    <section>
                        <h4 class="font-bold text-slate-800 text-base mb-2">2. 점수 입력</h4>
                        <ul class="list-disc list-inside space-y-1">
                            <li>왼쪽 목록에서 대상 선택 → 항목별 점수 입력 → <strong>[점수 제출]</strong>.</li>
                            <li>배점을 넘길 수 없고, 일부만 입력해 제출해도 됩니다.</li>
                            <li>수정은 대상을 다시 선택해 고치고 <strong>[점수 수정 저장]</strong>. 집계에 즉시 반영.</li>
                        </ul>
                    </section>

                    <section>
                        <h4 class="font-bold text-slate-800 text-base mb-2">3. 전자 서명</h4>
                        <p><strong>[✍️ 서명하기]</strong> → 손가락·마우스로 서명 → 저장. 심사표·최종집계표에 자동 삽입됩니다.</p>
                    </section>

                    <section>
                        <h4 class="font-bold text-slate-800 text-base mb-2">4. 내 심사표 인쇄</h4>
                        <p>상단 🖨️ 버튼으로 내 점수가 정리된 개별심사표를 인쇄할 수 있습니다.</p>
                    </section>

                    @unless ($isJudgeApp)
                    <section>
                        <h4 class="font-bold text-slate-800 text-base mb-2">5. 앱으로 설치하기 (권장)</h4>
                        <ul class="list-disc list-inside space-y-1">
                            <li><strong>안드로이드</strong>: <a href="{{ route('app.download') }}" class="text-indigo-600 underline">앱 내려받기</a> 페이지에서 설치 (권장) 또는 헤더 우측 <strong>⤓</strong> 버튼.</li>
                            <li><strong>아이폰</strong>: 사파리 아래 <strong>공유</strong> → <strong>홈 화면에 추가</strong>.</li>
                            <li>홈 화면 아이콘으로 열면 주소창 없이 전체화면으로 실행되고, 매번 코드를 다시 입력하지 않아도 됩니다.</li>
                        </ul>
                    </section>
                    @endunless

                    <section>
                        <h4 class="font-bold text-slate-800 text-base mb-2">6. 인터넷이 끊겨도 괜찮습니다</h4>
                        <ul class="list-disc list-inside space-y-1">
                            <li>입력한 점수는 <strong>기기에 먼저 저장</strong>되므로 화면을 닫거나 대상을 바꿔도 사라지지 않습니다.</li>
                            <li>연결이 끊긴 채 <strong>[점수 제출]</strong>을 눌러도 됩니다. 대상 목록에 <strong>대기</strong> 배지가 붙고,
                                연결이 돌아오면 자동으로 전송됩니다. 다시 입력하지 않으셔도 됩니다.</li>
                            <li>서명도 같은 방식으로 처리됩니다.</li>
                        </ul>
                    </section>

                    <div class="rounded-lg bg-slate-50 border border-slate-200 px-4 py-3 text-xs text-slate-500">
                        ℹ️ 심사가 마감되면 접속할 수 없습니다. 수정할 것이 있으면 마감 전에 관리자에게 알려 주세요.
                    </div>
                </div>

                {{-- ================= FAQ ================= --}}
                <div x-show="tab === 'faq'" x-cloak class="space-y-4">
                    <div class="rounded-xl border border-slate-200 p-4">
                        <div class="font-bold text-slate-700 mb-1">Q. 심사 도중 인터넷이 끊기면 점수가 날아가나요?</div>
                        <p>아닙니다. 입력한 점수는 기기에 먼저 저장되고, 끊긴 상태에서 제출하면 대기열에 담깁니다.
                           연결이 돌아오는 즉시 자동으로 전송되며 대상 목록의 <strong>대기</strong> 배지가 사라집니다.
                           다만 <strong>전송이 끝나기 전에 심사가 마감되면</strong> 그 점수는 반영되지 않으니,
                           마감 전에 배지가 모두 없어졌는지 확인해 주세요.</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 p-4" @if ($isJudgeApp) hidden @endif>
                        <div class="font-bold text-slate-700 mb-1">Q. 앱처럼 설치해서 쓸 수 있나요?</div>
                        <p>네. 안드로이드는 <a href="{{ route('app.download') }}" class="text-indigo-600 underline">앱 내려받기</a> 페이지에서
                           설치 파일을 받으실 수 있고, 헤더 우측 <strong>⤓</strong> 버튼으로 브라우저에서 바로 설치해도 됩니다.
                           아이폰은 사파리 <strong>공유 → 홈 화면에 추가</strong>입니다. 앱스토어 등록은 하지 않았습니다.</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 p-4">
                        <div class="font-bold text-slate-700 mb-1">Q. 관리 비밀번호를 잊어버렸어요.</div>
                        <p>복구되지 않습니다. 사이트 운영자에게 문의하거나 행사를 새로 만드세요.</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 p-4">
                        <div class="font-bold text-slate-700 mb-1">Q. 심사위원이 코드를 잃어버렸어요.</div>
                        <p>"심사위원" 탭에서 코드 확인 후 다시 알려 주거나 카드를 재인쇄하세요.</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 p-4">
                        <div class="font-bold text-slate-700 mb-1">Q. 심사위원 화면에 이름이 안 보여요.</div>
                        <p>기본이 블라인드(심사번호만)입니다. "기본설정 &gt; 심사위원 화면"에서 <strong>이름 공개</strong>로 바꿀 수 있습니다.</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 p-4">
                        <div class="font-bold text-slate-700 mb-1">Q. 제출한 점수를 고칠 수 있나요?</div>
                        <p>마감 전이면 심사위원이 직접 수정할 수 있고, 집계에 즉시 반영됩니다.</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 p-4">
                        <div class="font-bold text-slate-700 mb-1">Q. 실수로 마감했어요.</div>
                        <p>[심사 재개하기]로 되돌릴 수 있습니다. 단, 코드가 새로 발급되니 다시 배부하세요.</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 p-4">
                        <div class="font-bold text-slate-700 mb-1">Q. "최고·최저 제외"가 적용이 안 돼요.</div>
                        <p>대상을 채점한 심사위원이 <strong>3명 이상</strong>일 때만 적용됩니다. 미만이면 전체 집계.</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 p-4">
                        <div class="font-bold text-slate-700 mb-1">Q. 배점 합계 100점 경고가 계속 떠요.</div>
                        <p>1단계 배점 합계 = 100, 세부 항목 합계 = 상위 배점이어야 합니다.</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 p-4">
                        <div class="font-bold text-slate-700 mb-1">Q. 행사가 저절로 삭제되나요?</div>
                        <p>네. 행사일 기준 30일 후 자동 삭제됩니다. <strong>마감한 행사는 2년 보관</strong>되니 보관할 행사는 마감하세요.</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 p-4">
                        <div class="font-bold text-slate-700 mb-1">Q. CSV 한글이 깨지지 않나요? 휴대폰으로 심사되나요?</div>
                        <p>둘 다 문제없습니다. CSV는 엑셀에서 바로 열리고, 심사 화면은 휴대폰·태블릿·PC 모두 지원합니다.</p>
                    </div>
                </div>
            </div>

            {{-- 푸터 --}}
            <div class="px-6 py-3 border-t border-slate-100 text-xs text-slate-400 text-center">
                온라인 심사 시스템 — 궁금한 점은 행사 담당자 또는 사이트 운영자에게 문의하세요
            </div>
        </div>
    </div>
</div>
