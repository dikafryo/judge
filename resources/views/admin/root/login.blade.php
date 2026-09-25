@extends('layouts.app')

@section('title', '전체 관리자')

@section('content')

<div class="mx-auto max-w-md">
    <div class="rounded-2xl border border-slate-200 bg-white p-8">
        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-slate-900">
            <span class="material-symbols-rounded text-[20px] leading-none text-white" aria-hidden="true">key</span>
        </div>
        <h1 class="mt-4 text-xl font-bold text-slate-900">전체 관리자</h1>
        <p class="mt-1.5 text-sm leading-relaxed text-slate-500">
            모든 행사를 열어 보고 정리하는 화면입니다. 행사를 진행하시는 분은
            <a href="{{ route('events.index') }}" class="text-indigo-600 underline">행사 목록</a>에서
            행사별 비밀번호로 들어가세요.
        </p>

        <form method="POST" action="{{ route('root.login.post') }}" class="mt-6 space-y-4">
            @csrf
            <div>
                <label for="password" class="block text-xs font-bold text-slate-500">전체 관리자 비밀번호</label>
                <input id="password" name="password" type="password" autofocus required autocomplete="current-password"
                       class="mt-1.5 w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-3 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
            </div>
            <button type="submit"
                    class="w-full rounded-xl bg-slate-900 px-6 py-3 text-sm font-bold text-white transition hover:bg-slate-800">
                들어가기
            </button>
        </form>
    </div>
</div>

@endsection
