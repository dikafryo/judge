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
            심사위원용 앱입니다. 설치하면 주소창 없이 전체화면으로 열리고,<br class="hidden sm:inline">
            인터넷이 끊겨도 점수를 이어서 넣을 수 있습니다.
        </p>
    </div>

    @if (! $release)
        <div class="mt-8 rounded-2xl border border-slate-200 bg-white p-8 text-center">
            <p class="text-slate-600">아직 게시된 앱이 없습니다.</p>
            <p class="mt-2 text-sm text-slate-400">
                앱 없이도 브라우저에서 바로 쓰실 수 있습니다 — 크롬 메뉴의 <strong>앱 설치</strong>를 눌러 주세요.
            </p>
        </div>
    @else
        <div class="mt-8 rounded-2xl border border-slate-200 bg-white p-6 sm:p-8">
            <div class="flex flex-col items-center gap-6 sm:flex-row sm:items-center">
                <div class="min-w-0 flex-1 text-center sm:text-left">
                    <a href="/{{ $release['apk'] }}"
                       class="inline-flex w-full items-center justify-center rounded-xl bg-indigo-600 px-6 py-4 text-base font-bold text-white transition hover:bg-indigo-700 sm:w-auto">
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
        </div>

        <div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 p-6 text-sm text-amber-900">
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
