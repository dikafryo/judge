@extends('layouts.app')

@section('title', '홈')

@section('header-right')
    @unless ($isJudgeApp)
        <a href="{{ route('demo') }}"
           class="inline-flex items-center rounded-lg border border-amber-300 bg-amber-50 px-3 py-1.5 text-sm font-medium text-amber-700 hover:bg-amber-100 transition mr-2">
            체험해 보기
        </a>
    @endunless
    <a href="{{ route('events.index') }}"
       class="inline-flex items-center rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-600 hover:text-indigo-600 hover:border-indigo-400 transition">
        행사관리
    </a>
@endsection

@section('content')
<div class="text-center mb-10">
    <h1 class="text-3xl font-bold text-slate-900">온라인 심사 시스템</h1>
    <p class="mt-2 text-slate-500">부여받은 심사위원 코드를 입력하거나, 전달받은 링크로 바로 접속하세요.</p>
</div>

{{-- 심사위원 입장 --}}
<section class="max-w-md mx-auto bg-white rounded-2xl shadow-sm border border-slate-200 p-8">
    <div class="flex items-center gap-3 mb-4">
        <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-indigo-100 text-indigo-600 text-xl">✍️</span>
        <h2 class="text-xl font-bold">심사위원 입장</h2>
    </div>

    <form method="POST" action="{{ route('judge.enter') }}" class="space-y-4">
        @csrf
        <input type="text" name="code" value="{{ old('code') }}" required
               inputmode="numeric" autocomplete="one-time-code" maxlength="8"
               placeholder="예: 483920"
               class="w-full rounded-lg border-slate-300 border px-4 py-3 text-center text-lg tracking-widest focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
        <button type="submit"
                class="w-full rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 transition">
            심사 시작하기
        </button>
    </form>
</section>

{{-- 처음 오신 분 · 앱 안내 — 앱 안에서는 둘 다 의미가 없어 감춘다 --}}
@unless ($isJudgeApp)
    <p class="mt-6 text-center text-sm text-slate-500">
        처음이신가요?
        <a href="{{ route('demo') }}" class="font-semibold text-amber-700 underline underline-offset-4 hover:text-amber-800">샘플 행사로 시스템을 먼저 둘러보세요</a>
        — 로그인 없이 심사위원·관리자 화면을 모두 볼 수 있습니다.
    </p>

    <p class="mt-3 text-center text-sm text-slate-400">
        심사위원이시라면
        <a href="{{ route('app.download') }}" class="font-medium text-slate-500 underline underline-offset-4 hover:text-indigo-600">앱으로 설치</a>해
        쓰시면 더 편합니다 — 전체화면으로 열리고, 인터넷이 끊겨도 점수가 보관됩니다.
    </p>
@endunless

{{-- 행사 담당자용 진입 링크 --}}
<p class="mt-8 text-center text-sm text-slate-400">
    새로운 심사를 만들거나, 기존 행사 관리를 하시려면
    <a href="{{ route('events.index') }}" class="text-slate-500 hover:text-indigo-600 underline underline-offset-4 font-medium">여기를 눌러</a> 주세요.
</p>
@endsection
