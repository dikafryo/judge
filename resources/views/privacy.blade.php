@extends('layouts.app')

@section('title', '개인정보처리방침')

@section('content')
{{-- 플레이스토어 등록에 필수인 공개 URL. 앱과 웹이 같은 서버·같은 데이터를 쓰므로 문서도 하나다. --}}
<article class="max-w-3xl mx-auto bg-white rounded-2xl shadow-sm border border-slate-200 px-6 py-8 sm:px-10">
    <h1 class="text-2xl font-bold text-slate-900">개인정보처리방침</h1>
    <p class="mt-2 text-sm text-slate-500">
        온라인 심사 시스템 (judge.sw4u.kr · 안드로이드 앱 kr.sw4u.judge_app)<br>
        시행일: {{ $effectiveDate }}
    </p>

    <div class="mt-8 space-y-8 text-sm leading-relaxed text-slate-700">

        <section>
            <h2 class="font-bold text-base text-slate-900 mb-2">1. 회원가입이 없습니다</h2>
            <p>
                이 서비스는 계정을 만들지 않습니다. 이메일 주소, 전화번호, 생년월일, 주소를 묻지 않고 받지도 않습니다.
                심사위원은 주최자가 발급한 <strong>접속 코드</strong>로 들어오고, 관리자는 <strong>행사별 비밀번호</strong>로 들어옵니다.
            </p>
        </section>

        <section>
            <h2 class="font-bold text-base text-slate-900 mb-2">2. 처리하는 정보</h2>
            <p>행사를 진행하는 데 꼭 필요한 것만 처리합니다.</p>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-200 text-slate-500">
                            <th class="py-2 pr-4 font-semibold">항목</th>
                            <th class="py-2 pr-4 font-semibold">누가 입력하나</th>
                            <th class="py-2 font-semibold">쓰이는 곳</th>
                        </tr>
                    </thead>
                    <tbody class="align-top">
                        <tr class="border-b border-slate-100">
                            <td class="py-2 pr-4">심사위원 이름</td>
                            <td class="py-2 pr-4">행사 주최자</td>
                            <td class="py-2">심사표·최종집계표에 표기</td>
                        </tr>
                        <tr class="border-b border-slate-100">
                            <td class="py-2 pr-4">접속 코드</td>
                            <td class="py-2 pr-4">자동 발급</td>
                            <td class="py-2">본인 심사 화면 입장</td>
                        </tr>
                        <tr class="border-b border-slate-100">
                            <td class="py-2 pr-4">전자서명 이미지</td>
                            <td class="py-2 pr-4">심사위원 본인</td>
                            <td class="py-2">심사 확인 서명란</td>
                        </tr>
                        <tr class="border-b border-slate-100">
                            <td class="py-2 pr-4">평가 점수</td>
                            <td class="py-2 pr-4">심사위원 본인</td>
                            <td class="py-2">집계·순위 산출</td>
                        </tr>
                        <tr class="border-b border-slate-100">
                            <td class="py-2 pr-4">평가 대상 이름·소속</td>
                            <td class="py-2 pr-4">행사 주최자</td>
                            <td class="py-2">최종집계표에 표기</td>
                        </tr>
                        <tr>
                            <td class="py-2 pr-4">관리 비밀번호</td>
                            <td class="py-2 pr-4">행사 주최자</td>
                            <td class="py-2">관리 화면 접근 (복호화할 수 없는 형태로 저장)</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="mt-3">
                위치 정보, 연락처, 사진첩, 통화 기록, 기기 식별자, 광고 식별자는 수집하지 않습니다.
            </p>
        </section>

        <section>
            <h2 class="font-bold text-base text-slate-900 mb-2">3. 카메라 권한</h2>
            <p>
                앱은 <strong>QR 코드로 심사 화면에 입장할 때만</strong> 카메라를 씁니다.
                화면에 비친 영상은 코드를 읽는 즉시 버려지며, 사진이나 영상으로 <strong>저장하지도 전송하지도 않습니다.</strong>
                QR 대신 6자리 코드를 손으로 입력해도 되므로, 카메라 권한을 거부해도 앱을 그대로 쓸 수 있습니다.
            </p>
        </section>

        <section>
            <h2 class="font-bold text-base text-slate-900 mb-2">4. 기기에 저장되는 것</h2>
            <p>
                심사위원 화면은 <strong>네트워크가 끊겨도 채점이 계속되어야</strong> 하므로, 담당한 심사 정보와
                아직 보내지 못한 점수를 기기 안에 보관합니다. 연결이 돌아오면 서버로 보내고, 앱 데이터를 지우면
                함께 사라집니다.
            </p>
            <p class="mt-2">
                관리자 화면은 <strong>기기에 아무것도 저장하지 않습니다.</strong> 오래된 집계를 최신으로 오인해
                발표하는 사고를 막기 위한 것이며, 같은 이유로 관리자 로그인 상태도 보관하지 않습니다.
            </p>
        </section>

        <section>
            <h2 class="font-bold text-base text-slate-900 mb-2">5. 제3자 제공·광고·분석</h2>
            <p>
                수집한 정보를 외부에 <strong>제공하거나 판매하지 않습니다.</strong>
                광고를 넣지 않고, 이용 행태를 추적하는 분석 도구도 넣지 않았습니다.
                데이터는 운영자가 관리하는 서버에만 저장되며, 전송 구간은 HTTPS 로 암호화됩니다.
            </p>
        </section>

        <section>
            <h2 class="font-bold text-base text-slate-900 mb-2">6. 보관 기간</h2>
            <ul class="list-disc list-inside space-y-1">
                <li>행사 주최자가 행사를 삭제하면 관련 정보가 <strong>즉시 모두 삭제</strong>됩니다.</li>
                <li>심사가 마감된 행사는 <strong>2년</strong> 뒤 자동 삭제됩니다.</li>
                <li>진행 중인 채로 방치된 행사는 <strong>30일</strong> 뒤 자동 삭제됩니다.</li>
                <li>심사를 마감하면 심사위원 접속 코드와 앱 인증 토큰은 즉시 회수됩니다.</li>
            </ul>
        </section>

        <section>
            <h2 class="font-bold text-base text-slate-900 mb-2">7. 열람·삭제 요청</h2>
            <p>
                본인의 정보를 확인하거나 지우고 싶으면 <strong>해당 행사의 주최자</strong>에게 요청하세요.
                주최자가 관리 화면에서 직접 처리할 수 있습니다. 주최자를 통하기 어려운 경우 아래로 연락하시면
                확인 후 처리해 드립니다.
            </p>
        </section>

        <section>
            <h2 class="font-bold text-base text-slate-900 mb-2">8. 문의</h2>
            <p>
                이메일: <a href="mailto:{{ $contactEmail }}" class="text-indigo-600 underline underline-offset-2">{{ $contactEmail }}</a>
            </p>
        </section>

        <section class="pt-4 border-t border-slate-100">
            <p class="text-slate-500">이 방침이 바뀌면 이 페이지에 새 시행일과 함께 올립니다.</p>
        </section>
    </div>
</article>
@endsection
