@extends('layouts.app')

@section('title', '체험해 보기')

@section('header-right')
    <a href="{{ route('home') }}" class="text-indigo-600 hover:underline font-medium">← 홈으로</a>
@endsection

@section('content')

<div class="text-center mb-10">
    <span class="inline-flex items-center rounded-full bg-amber-100 text-amber-700 px-3 py-1 text-xs font-bold">체험용 샘플 행사</span>
    <h1 class="mt-3 text-3xl font-bold text-slate-900">온라인 심사 시스템 둘러보기</h1>
    <p class="mt-3 text-slate-500 leading-relaxed">
        이 시스템의 화면은 <strong class="text-slate-700">아래 3가지가 전부</strong>입니다. 더 배울 것은 없습니다.<br class="hidden sm:inline">
        미리 채워둔 샘플이라 <strong class="text-slate-600">저장·수정은 되지 않으니</strong> 마음껏 눌러 보세요.
    </p>
</div>

@if (! $event)
    <div class="max-w-xl mx-auto rounded-2xl bg-white border border-slate-200 p-8 text-center">
        <p class="text-slate-600">샘플 데이터가 아직 준비되지 않았습니다.</p>
        <p class="mt-2 text-sm text-slate-400">서버에서 <code class="rounded bg-slate-100 px-1.5 py-0.5">php artisan judge:demo</code> 를 실행하면 생성됩니다.</p>
    </div>
@else

<div class="max-w-2xl mx-auto space-y-4">

    {{-- 1. 심사위원 화면 --}}
    <a href="{{ $judges->first() ? route('judge.show', $judges->first()) : route('demo') }}"
       class="group flex items-center gap-5 rounded-2xl bg-white border border-slate-200 p-6 hover:border-indigo-400 hover:bg-indigo-50/40 transition">
        <span class="shrink-0 inline-flex items-center justify-center w-12 h-12 rounded-xl bg-indigo-100 text-indigo-600 text-xl font-bold">1</span>
        <span class="min-w-0 flex-1">
            <span class="block text-lg font-bold text-slate-900">심사위원 화면 보기</span>
            <span class="block mt-1 text-sm text-slate-500">심사위원이 점수를 넣고 서명하는 화면입니다.</span>
        </span>
        <span class="shrink-0 text-2xl text-slate-300 group-hover:text-indigo-500 transition">→</span>
    </a>

    {{-- 2. 관리자 화면 --}}
    <a href="{{ route('demo.admin') }}"
       class="group flex items-center gap-5 rounded-2xl bg-white border border-slate-200 p-6 hover:border-emerald-400 hover:bg-emerald-50/40 transition">
        <span class="shrink-0 inline-flex items-center justify-center w-12 h-12 rounded-xl bg-emerald-100 text-emerald-600 text-xl font-bold">2</span>
        <span class="min-w-0 flex-1">
            <span class="block text-lg font-bold text-slate-900">관리자 화면 보기</span>
            <span class="block mt-1 text-sm text-slate-500">행사 설정과 실시간 집계를 보는 화면입니다.</span>
        </span>
        <span class="shrink-0 text-2xl text-slate-300 group-hover:text-emerald-500 transition">→</span>
    </a>

    {{-- 3. 출력물 --}}
    <a href="{{ route('demo.print') }}" target="_blank"
       class="group flex items-center gap-5 rounded-2xl bg-white border border-slate-200 p-6 hover:border-slate-400 hover:bg-slate-50 transition">
        <span class="shrink-0 inline-flex items-center justify-center w-12 h-12 rounded-xl bg-slate-200 text-slate-600 text-xl font-bold">3</span>
        <span class="min-w-0 flex-1">
            <span class="block text-lg font-bold text-slate-900">출력물 보기</span>
            <span class="block mt-1 text-sm text-slate-500">결재란이 있는 A4 최종집계표입니다.</span>
        </span>
        <span class="shrink-0 text-2xl text-slate-300 group-hover:text-slate-500 transition">→</span>
    </a>

</div>

{{-- 마무리 CTA --}}
<section class="max-w-2xl mx-auto mt-10 rounded-2xl bg-slate-900 text-white p-8 text-center">
    <h2 class="text-xl font-bold">직접 행사를 만들어 보시겠어요?</h2>
    <p class="mt-2 text-sm text-slate-300">회원가입 없이, 행사명과 관리 비밀번호만 정하면 바로 시작합니다.</p>
    <a href="{{ route('events.index') }}"
       class="mt-5 inline-flex rounded-lg bg-white text-slate-900 font-bold px-6 py-3 hover:bg-slate-100 transition">
        새 행사 만들기
    </a>
</section>

@endif
@endsection
