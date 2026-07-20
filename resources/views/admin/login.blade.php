@extends('layouts.app')

@section('title', $event->name . ' 관리자')

@section('content')
<div class="max-w-md mx-auto bg-white rounded-2xl shadow-sm border border-slate-200 p-8 mt-8">
    <h1 class="text-xl font-bold text-slate-900">{{ $event->name }}</h1>
    <p class="mt-1 text-sm text-slate-500 mb-6">관리자 비밀번호를 입력하세요.</p>

    <form method="POST" action="{{ route('admin.login.post', $event) }}" class="space-y-4">
        @csrf
        <input type="password" name="password" required autofocus
               placeholder="관리 비밀번호"
               class="w-full rounded-lg border-slate-300 border px-4 py-3 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
        <button type="submit"
                class="w-full rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 transition">
            관리자 로그인
        </button>
    </form>
</div>
@endsection
