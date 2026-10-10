<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', '온라인 심사 시스템') — Judge</title>

    {{-- Tailwind CSS + Alpine.js — Vite 빌드(public/build). 예전엔 Play CDN 이 매 접속마다
         400KB 스크립트를 받아 브라우저 안에서 CSS 를 만들었다. 빌드: npm run build (README §2) --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@700&display=swap">
    {{-- Material Symbols — 이모지 대신 쓰는 아이콘 폰트 (디자인 리뷰 2026-09 반영) --}}
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,300..600,0..1,0&display=block">

    {{-- PWA — 홈 화면 설치 / 앱 아이콘 / 오프라인 (public/manifest.json · public/sw.js) --}}
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#1F2933">
    <link rel="icon" type="image/png" sizes="32x32" href="/icons/favicon-32.png">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="심사">
    <meta name="mobile-web-app-capable" content="yes">

    @stack('head')
</head>
<body class="min-h-screen bg-slate-100 text-slate-800 antialiased">

    <header class="bg-white border-b border-slate-200">
        <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between">
            <div class="flex items-center gap-2 font-bold text-lg text-slate-900">
                {{-- 2026-09-30 공모전 기간: 개인을 특정할 수 있는 neis.me 로고·링크를 뺐다. 원본은 같은 폴더 index2.php --}}
                <a href="{{ route('home') }}" class="whitespace-nowrap hover:text-indigo-600">온라인 심사 시스템</a>
            </div>
            <div class="flex items-center text-sm text-slate-500">
                @yield('header-right')
                @include('partials.pwa-install')
                @include('partials.manual')
            </div>
        </div>
    </header>

    <main class="max-w-6xl mx-auto px-4 py-8">
        {{-- 플래시 메시지 --}}
        @if (session('status'))
            <x-alert tone="success" class="mb-6"
                     x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)">
                {{ session('status') }}
            </x-alert>
        @endif

        @if ($errors->any())
            <x-alert tone="danger" class="mb-6">
                <ul class="list-disc list-inside space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-alert>
        @endif

        @yield('content')
    </main>

    {{-- 개인정보처리방침은 플레이스토어 심사에서 확인하는 공개 문서다 — 모든 화면에서 닿아야 한다 --}}
    <footer class="max-w-6xl mx-auto px-4 py-8 text-center text-xs text-slate-500">
        <span class="inline-flex flex-wrap items-center justify-center gap-x-2 gap-y-1">
            <span>온라인 심사 시스템</span>
            <span aria-hidden="true">·</span>
            <a href="{{ route('privacy') }}" class="hover:text-indigo-600 underline underline-offset-2">개인정보처리방침</a>
        </span>
    </footer>

    @stack('scripts')

    {{-- 서비스워커 등록 — 실패해도 앱은 지금까지처럼 온라인으로 동작해야 하므로 조용히 무시한다 --}}
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('/sw.js').catch(function () {});
            });
        }
    </script>
</body>
</html>
