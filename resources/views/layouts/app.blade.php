<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', '온라인 심사 시스템') — Judge</title>

    {{-- Tailwind CSS (Play CDN — 빌드 스텝 없이 사용) --}}
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Pretendard', 'Noto Sans KR', 'sans-serif'] },
                },
            },
        };
    </script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@700&display=swap">

    {{-- Alpine.js --}}
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js"></script>

    <style>
        [x-cloak] { display: none !important; }
        /* neis.me Toolgrid 로고 (2026-08-05 로고 시스템 반영) */
        .nm-logo{display:inline-flex;align-items:center;gap:.46em;font-size:19px;text-decoration:none;font-family:'JetBrains Mono',ui-monospace,monospace}
        .nm-grid{display:grid;grid-template:1fr 1fr/1fr 1fr;gap:.18em;width:1.72em;height:1.72em;padding:.36em;box-sizing:border-box;background:#1F2933;border-radius:.18em}
        .nm-grid i{background:#F5F3EF;border-radius:.04em}
        .nm-grid i:last-child{background:#F0A04B}
        .nm-word{font-weight:700;letter-spacing:-.05em;color:#1F2933}
        .nm-word b{font-weight:700;color:#D1802A}
    </style>
    @stack('head')
</head>
<body class="min-h-screen bg-slate-100 text-slate-800 antialiased">

    <header class="bg-white border-b border-slate-200">
        <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between">
            <div class="flex items-center gap-2 font-bold text-lg text-slate-900">
                {{-- neisme Toolgrid 로고 → neis.me 홈 --}}
                <a href="https://neis.me/" class="nm-logo" title="neis.me 홈으로 이동">
                    <span class="nm-grid" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
                    <span class="nm-word">neis<b>.</b>me</span>
                </a>
                <span class="text-slate-300 font-normal" aria-hidden="true">&rsaquo;</span>
                <a href="{{ route('home') }}" class="hover:text-indigo-600">온라인 심사 시스템</a>
            </div>
            <div class="flex items-center text-sm text-slate-500">
                @yield('header-right')
                @include('partials.manual')
            </div>
        </div>
    </header>

    <main class="max-w-6xl mx-auto px-4 py-8">
        {{-- 플래시 메시지 --}}
        @if (session('status'))
            <div class="mb-6 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm"
                 x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 rounded-lg bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">
                <ul class="list-disc list-inside space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="max-w-6xl mx-auto px-4 py-8 text-center text-xs text-slate-400">
        온라인 심사 시스템
    </footer>

    @stack('scripts')
</body>
</html>
