@extends('layouts.app')

@section('title', '안드로이드 앱 받기')

@section('header-right')
    <a href="{{ route('home') }}" class="text-indigo-600 hover:underline font-medium">← 홈으로</a>
@endsection

@section('content')

<div class="mx-auto max-w-2xl">

    <div class="text-center">
        <img src="/icons/icon-192.png" alt="" width="72" height="72" class="mx-auto rounded-2xl shadow-sm">
        <h1 class="mt-4 text-3xl font-bold text-slate-900">안드로이드 앱</h1>
        <p class="mt-2 text-slate-500">
            심사위원 채점과 주최자 관리를 한 앱에서 합니다.<br class="hidden sm:inline">
            주소창 없이 전체화면으로 열리고, 인터넷이 끊겨도 점수를 이어서 넣을 수 있습니다.
        </p>

        {{-- 앱이 심사위원 전용이라는 오해가 있었다. 무엇을 할 수 있는지 먼저 밝힌다. --}}
        <div class="mt-6 grid gap-3 text-left sm:grid-cols-2">
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <p class="text-sm font-bold text-slate-800">심사위원</p>
                <p class="mt-1 text-sm leading-relaxed text-slate-500">
                    6자리 코드나 QR 로 들어와 채점하고 서명합니다. 오프라인에서도 멈추지 않습니다.
                </p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <p class="text-sm font-bold text-slate-800">주최자</p>
                <p class="mt-1 text-sm leading-relaxed text-slate-500">
                    행사 만들기, 평가 항목·대상·심사위원 등록, 집계 확인, 기본설정까지 앱에서 합니다.
                    인쇄물은 앱에서 눌러 브라우저로 넘어갑니다.
                </p>
            </div>
        </div>
    </div>

    {{-- 받는 길이 둘이다. 어느 쪽이든 같은 앱이므로, 고르기 전에 그 사실을 먼저 말한다. --}}
    @if ($play)
        <p class="mt-8 text-center text-sm text-slate-500">
            받는 방법은 두 가지이고 <strong class="text-slate-700">설치되는 앱은 같습니다.</strong>
            편한 쪽을 고르세요.
        </p>

        {{--
            방법 1 — 플레이스토어 비공개 테스트.
            아직 공개 출시 전이라 누구나 받을 수는 없고, 구글 그룹스에 가입한 계정만
            스토어에서 앱이 보인다. 순서를 지키지 않으면 "찾을 수 없는 페이지" 가 떠서
            대부분 여기서 막힌다 — 그래서 번호를 붙여 차례를 못 박는다.
        --}}
        <section class="mt-4 rounded-2xl border-2 border-indigo-200 bg-white p-6 sm:p-8">
            <div class="flex flex-wrap items-center gap-2">
                <span class="rounded-full bg-indigo-600 px-2.5 py-1 text-xs font-bold text-white">방법 1</span>
                <h2 class="text-lg font-bold text-slate-900">플레이스토어에서 받기</h2>
                <span class="rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-bold text-indigo-700">권장</span>
            </div>
            <p class="mt-2 text-sm leading-relaxed text-slate-600">
                스토어를 거치므로 설치할 때 보안 경고가 뜨지 않고, 새 버전이 나오면 자동으로 갱신됩니다.
                아직 공개 출시 전이라 <strong>아래 세 단계를 순서대로</strong> 밟아야 합니다.
            </p>

            <ol class="mt-6 space-y-5">
                <li class="flex gap-4">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-sm font-bold text-indigo-700">1</span>
                    <div class="min-w-0 flex-1">
                        <p class="font-bold text-slate-800">구글 그룹스에 가입합니다</p>
                        <p class="mt-1 text-sm leading-relaxed text-slate-500">
                            테스터 명단 역할을 합니다. <strong>가입한 구글 계정</strong>으로만 앱이 보이므로,
                            폰에서 쓰는 계정으로 가입하세요.
                        </p>
                        <a href="{{ $play['group'] }}" target="_blank" rel="noopener"
                           class="mt-2.5 inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                            그룹 가입하기
                            <span aria-hidden="true" class="text-slate-400">↗</span>
                        </a>
                    </div>
                </li>

                <li class="flex gap-4">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-sm font-bold text-indigo-700">2</span>
                    <div class="min-w-0 flex-1">
                        <p class="font-bold text-slate-800">테스터로 등록합니다</p>
                        <p class="mt-1 text-sm leading-relaxed text-slate-500">
                            열린 페이지에서 <strong>테스터 되기</strong>를 누릅니다. PC·폰 어느 브라우저에서나 됩니다.
                        </p>
                        <a href="{{ $play['optIn'] }}" target="_blank" rel="noopener"
                           class="mt-2.5 inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                            테스터 등록하기
                            <span aria-hidden="true" class="text-slate-400">↗</span>
                        </a>
                    </div>
                </li>

                <li class="flex gap-4">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-sm font-bold text-indigo-700">3</span>
                    <div class="min-w-0 flex-1">
                        <p class="font-bold text-slate-800">안드로이드 기기에서 설치합니다</p>
                        <p class="mt-1 text-sm leading-relaxed text-slate-500">
                            1·2 단계를 마친 계정이 로그인된 <strong>안드로이드 기기</strong>에서 열어야 합니다.
                        </p>
                        <a href="{{ $play['store'] }}" target="_blank" rel="noopener"
                           class="mt-2.5 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-indigo-600 px-6 py-3.5 text-base font-bold text-white transition hover:bg-indigo-700 sm:w-auto">
                            플레이스토어에서 설치
                            <span aria-hidden="true">↗</span>
                        </a>
                    </div>
                </li>
            </ol>

            <div class="mt-6 rounded-xl bg-slate-50 p-4 text-sm leading-relaxed text-slate-600">
                <p class="font-bold text-slate-700">앱을 찾을 수 없다고 나온다면</p>
                <ul class="mt-1.5 list-disc space-y-1 pl-5">
                    <li>1·2 단계를 마친 계정으로 로그인되어 있는지 확인하세요. 폰에 계정이 여러 개면 자주 생기는 일입니다.</li>
                    <li>등록 직후에는 몇 분에서 길게는 몇 시간이 걸릴 수 있습니다. 시간을 두고 다시 열어 보세요.</li>
                    <li>그래도 안 되면 아래 <strong>방법 2</strong> 로 바로 받으실 수 있습니다.</li>
                </ul>
            </div>
        </section>
    @endif

    {{-- 방법 2 — APK 직접 배포. 플레이 등록을 기다릴 수 없는 현장을 위한 길이다. --}}
    <section class="mt-4 rounded-2xl border border-slate-200 bg-white p-6 sm:p-8">
        @if ($play)
            <div class="flex flex-wrap items-center gap-2">
                <span class="rounded-full bg-slate-200 px-2.5 py-1 text-xs font-bold text-slate-700">방법 2</span>
                <h2 class="text-lg font-bold text-slate-900">파일로 바로 받기</h2>
            </div>
            <p class="mt-2 text-sm leading-relaxed text-slate-600">
                그룹 가입 없이 지금 바로 설치합니다. 대신 새 버전은 이 페이지에서 직접 받아야 합니다.
            </p>
        @endif

        @if (! $release)
            <p class="{{ $play ? 'mt-6 ' : '' }}text-center text-slate-600">아직 게시된 앱 파일이 없습니다.</p>
            <p class="mt-2 text-center text-sm text-slate-400">
                앱 없이도 브라우저에서 바로 쓰실 수 있습니다 — 크롬 메뉴의 <strong>앱 설치</strong>를 눌러 주세요.
            </p>
        @else
            <div class="{{ $play ? 'mt-6 ' : '' }}flex flex-col items-center gap-6 sm:flex-row sm:items-center">
                <div class="min-w-0 flex-1 text-center sm:text-left">
                    <a href="/{{ $release['apk'] }}"
                       class="inline-flex w-full items-center justify-center rounded-xl bg-slate-800 px-6 py-3.5 text-base font-bold text-white transition hover:bg-slate-900 sm:w-auto">
                        ⤓ 앱 내려받기
                    </a>
                    <p class="mt-3 text-sm text-slate-500">
                        v{{ $release['version'] }} · 빌드 {{ $release['build'] }} · {{ $release['sizeText'] }}
                        @if ($release['publishedAt'])
                            · {{ $release['publishedAt']->format('Y-m-d') }}
                        @endif
                    </p>
                </div>

                {{-- 폰으로 바로 받도록 QR — 심사위원 접속안내 출력물과 같은 라이브러리를 쓴다 --}}
                <div class="shrink-0 text-center">
                    <div id="qr" class="inline-block rounded-lg border border-slate-200 p-2"></div>
                    <p class="mt-1.5 text-xs text-slate-400">폰으로 스캔</p>
                </div>
            </div>

            @if ($release['sha256'])
                <div class="mt-6 border-t border-slate-100 pt-4" x-data="{ copied: false }">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-xs font-semibold text-slate-500">SHA-256</span>
                        <button type="button"
                                x-on:click="navigator.clipboard.writeText('{{ $release['sha256'] }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                class="text-xs font-semibold text-indigo-600 hover:underline"
                                x-text="copied ? '복사됨' : '복사'"></button>
                    </div>
                    <p class="mt-1 break-all font-mono text-[11px] leading-relaxed text-slate-400">{{ $release['sha256'] }}</p>
                </div>
            @endif

            <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
                <p class="font-bold">설치할 때 경고가 뜨는 것은 정상입니다</p>
                <p class="mt-1.5 leading-relaxed">
                    구글 플레이를 거치지 않고 저희가 직접 배포하는 파일이라, 안드로이드가 보안 확인을 표시합니다.
                </p>
                <ol class="mt-3 list-decimal space-y-1 pl-5">
                    <li>위 버튼으로 파일을 받습니다.</li>
                    <li>브라우저가 물어보면 <strong>이 출처의 앱 설치를 허용</strong>합니다.</li>
                    <li>받은 파일을 눌러 <strong>설치</strong>합니다.</li>
                </ol>
            </div>
        @endif
    </section>

    <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-6 text-sm text-slate-600">
        <p class="font-bold text-slate-800">아이폰·아이패드를 쓰신다면</p>
        <p class="mt-1.5 leading-relaxed">
            iOS용 앱 파일은 없습니다. 대신 사파리로 이 사이트를 연 뒤
            아래쪽 <strong>공유</strong> → <strong>홈 화면에 추가</strong>를 누르면
            앱과 똑같이 전체화면으로 쓸 수 있습니다. 오프라인 기능도 그대로 동작합니다.
        </p>
    </div>
</div>

@if ($release)
    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
    <script>
        (function () {
            const target = document.getElementById('qr');
            if (! target || typeof qrcode !== 'function') return;

            const qr = qrcode(0, 'M');
            qr.addData(@json(url('/app')));
            qr.make();
            target.innerHTML = qr.createImgTag(4, 0);
        })();
    </script>
    @endpush
@endif

@endsection
