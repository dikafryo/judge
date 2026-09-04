{{-- 앱 설치 버튼 — 헤더 우측, 사용 설명서(?) 버튼 왼쪽.
     설치 가능할 때만(=beforeinstallprompt 수신, 또는 미설치 iOS 사파리) 나타난다. --}}
@unless ($isJudgeApp ?? false)
<div x-data="{
        deferred: null,
        installed: false,
        iosHelp: false,
        get standalone() {
            return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
        },
        get ios() {
            return /iphone|ipad|ipod/i.test(navigator.userAgent);
        },
        get visible() {
            return ! this.standalone && ! this.installed && (this.deferred !== null || this.ios);
        },
        async install() {
            {{-- iOS 사파리는 beforeinstallprompt 가 없어 직접 설치를 띄울 수 없다 → 방법만 안내 --}}
            if (! this.deferred) { this.iosHelp = ! this.iosHelp; return; }

            this.deferred.prompt();
            const choice = await this.deferred.userChoice;
            this.deferred = null;
            if (choice.outcome === 'accepted') { this.installed = true; }
        },
     }"
     x-on:beforeinstallprompt.window.prevent="deferred = $event"
     x-on:appinstalled.window="installed = true; deferred = null; iosHelp = false"
     class="relative flex items-center">

    <button type="button" x-show="visible" x-cloak
            x-on:click="install()" title="홈 화면에 앱으로 추가"
            class="ml-3 inline-flex items-center justify-center w-7 h-7 rounded-full border border-slate-300 text-slate-400 hover:text-indigo-600 hover:border-indigo-400 transition">
        <span class="sr-only">앱 설치</span>
        <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M10 3v9m0 0 3.5-3.5M10 12 6.5 8.5" />
            <path d="M4 14v2a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1v-2" />
        </svg>
    </button>

    {{-- iOS 안내 팝오버 --}}
    <div x-show="iosHelp" x-cloak x-on:click.outside="iosHelp = false"
         x-on:keydown.escape.window="iosHelp = false"
         class="absolute right-0 top-9 z-50 w-64 rounded-xl border border-slate-200 bg-white p-4 text-left shadow-lg">
        <p class="font-bold text-slate-800 text-sm">홈 화면에 추가하기</p>
        <ol class="mt-2 space-y-1 list-decimal list-inside text-xs text-slate-600">
            <li>사파리 아래쪽 <span class="font-semibold">공유</span> 버튼을 누릅니다</li>
            <li><span class="font-semibold">홈 화면에 추가</span>를 선택합니다</li>
            <li><span class="font-semibold">추가</span>를 누르면 끝입니다</li>
        </ol>
        <p class="mt-2 text-xs text-slate-400">홈 화면 아이콘으로 열면 주소창 없이 전체화면으로 실행됩니다.</p>
    </div>
</div>
@endunless
