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

    {{-- Alpine.js --}}
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js"></script>

    <style>
        [x-cloak] { display: none !important; }
    </style>
    @stack('head')
</head>
<body class="min-h-screen bg-slate-100 text-slate-800 antialiased">

    <header class="bg-white border-b border-slate-200">
        <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between">
            <a href="{{ route('home') }}" class="flex items-center gap-2 font-bold text-lg text-slate-900">
                <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-indigo-600 text-white text-sm">審</span>
                온라인 심사 시스템
            </a>
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
