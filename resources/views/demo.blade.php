@extends('layouts.app')

@section('title', '체험해 보기')

@section('header-right')
    <a href="{{ route('home') }}" class="text-indigo-600 hover:underline font-medium">← 홈으로</a>
@endsection

@section('content')

<div class="text-center mb-10">
    <span class="inline-flex items-center rounded-full bg-amber-100 text-amber-700 px-3 py-1 text-xs font-bold">체험용 샘플 행사</span>
    <h1 class="mt-3 text-3xl font-bold text-slate-900">온라인 심사 시스템 둘러보기</h1>
    <p class="mt-2 text-slate-500 leading-relaxed">
        실제 화면 그대로 미리 채워둔 샘플 행사입니다. 로그인 없이 심사위원 화면과 관리자 화면을 모두 볼 수 있고,<br class="hidden sm:inline">
        <strong class="text-slate-600">저장·수정은 되지 않으니</strong> 마음껏 눌러 보셔도 됩니다.
    </p>
</div>

@if (! $event)
    <div class="max-w-xl mx-auto rounded-2xl bg-white border border-slate-200 p-8 text-center">
        <p class="text-slate-600">샘플 데이터가 아직 준비되지 않았습니다.</p>
        <p class="mt-2 text-sm text-slate-400">서버에서 <code class="rounded bg-slate-100 px-1.5 py-0.5">php artisan judge:demo</code> 를 실행하면 생성됩니다.</p>
    </div>
@else

{{-- 이 시스템이 하는 일 --}}
<section class="grid sm:grid-cols-3 gap-4 mb-10">
    @foreach ([
        ['📋', '행사 준비', '평가 항목(100점 배점)·평가 대상·심사위원을 등록하면 심사위원별 접속 코드와 QR 안내문이 자동으로 만들어집니다.'],
        ['✍️', '현장 심사', '심사위원은 코드만 입력하면 바로 채점. 태블릿·휴대폰에서 항목별 점수를 넣고 전자서명까지 마칩니다.'],
        ['📊', '즉시 집계', '점수는 실시간으로 집계되어 순위·선정자·동점 여부가 바로 보이고, 결재란이 있는 최종집계표를 출력합니다.'],
    ] as [$icon, $title, $body])
        <div class="bg-white rounded-2xl border border-slate-200 p-6">
            <div class="text-2xl">{{ $icon }}</div>
            <h2 class="mt-3 font-bold text-slate-900">{{ $title }}</h2>
            <p class="mt-1.5 text-sm text-slate-500 leading-relaxed">{{ $body }}</p>
        </div>
    @endforeach
</section>

{{-- 샘플 행사 개요 --}}
<section class="bg-white rounded-2xl border border-slate-200 p-6 mb-10">
    <h2 class="text-lg font-bold text-slate-900">{{ $event->name }}</h2>
    <p class="mt-1 text-sm text-slate-500">{{ $event->description }}</p>

    <dl class="mt-5 grid grid-cols-2 sm:grid-cols-4 gap-3 text-center">
        @foreach ([
            ['평가 대상', $candidates->count() . '건'],
            ['심사위원', $judges->count() . '명'],
            ['평가 항목', $event->topCriteria()->count() . '개 대분류 / ' . $event->totalMaxScore() . '점 만점'],
            ['선정 인원', ($event->pass_count ?? 0) . '건'],
        ] as [$label, $value])
            <div class="rounded-xl bg-slate-50 border border-slate-200 px-3 py-4">
                <dt class="text-xs text-slate-400 font-semibold">{{ $label }}</dt>
                <dd class="mt-1 text-sm font-bold text-slate-800">{{ $value }}</dd>
            </div>
        @endforeach
    </dl>

    <p class="mt-4 text-xs text-slate-400">※ {{ $event->scoringMethodNote() }}</p>
</section>

{{-- 1. 심사위원 화면 --}}
<section class="bg-white rounded-2xl border border-slate-200 p-6 mb-6">
    <div class="flex items-center gap-3">
        <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-indigo-100 text-indigo-600 text-xl">✍️</span>
        <div>
            <h2 class="text-xl font-bold text-slate-900">1. 심사위원 화면 체험</h2>
            <p class="text-sm text-slate-500">심사위원에게 전달되는 코드로 들어가는 화면입니다. 아무 이름이나 눌러 보세요.</p>
        </div>
    </div>

    <div class="mt-5 grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
        @foreach ($judges as $judge)
            <a href="{{ route('judge.show', $judge) }}"
               class="group flex items-center justify-between rounded-xl border border-slate-200 px-4 py-3 hover:border-indigo-400 hover:bg-indigo-50/50 transition">
                <span>
                    <span class="block font-bold text-slate-800">{{ $judge->name }} 심사위원</span>
                    <span class="block text-xs text-slate-400 mt-0.5">접속 코드 <span class="font-mono tracking-widest text-slate-500">{{ $judge->code }}</span></span>
                </span>
                <span class="text-slate-300 group-hover:text-indigo-500 transition">→</span>
            </a>
        @endforeach
    </div>

    <p class="mt-4 text-xs text-slate-400">
        홈 화면에서 위 코드를 직접 입력해도 같은 화면으로 들어갑니다. 점수 입력·서명은 화면에서 동작하지만 저장 단계에서 안내 문구와 함께 차단됩니다.
    </p>
</section>

{{-- 2. 관리자 화면 --}}
<section class="bg-white rounded-2xl border border-slate-200 p-6 mb-6">
    <div class="flex items-center gap-3">
        <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-emerald-100 text-emerald-600 text-xl">📊</span>
        <div>
            <h2 class="text-xl font-bold text-slate-900">2. 관리자 화면 체험</h2>
            <p class="text-sm text-slate-500">행사 담당자가 보는 화면입니다. 비밀번호 없이 바로 들어갑니다.</p>
        </div>
    </div>

    <a href="{{ route('demo.admin') }}"
       class="mt-5 inline-flex items-center gap-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-5 py-3 transition">
        실시간 집계 대시보드로 들어가기 →
    </a>

    <p class="mt-4 text-sm text-slate-500 leading-relaxed">
        들어간 뒤 상단 탭에서 <strong>기본설정 · 평가항목 · 평가대상 · 심사위원 · 집계</strong> 를 모두 둘러볼 수 있습니다.
        심사위원 진행률, 순위, 선정 인원 커트라인과 동점 경고가 실제 데이터로 표시됩니다.
    </p>
</section>

{{-- 3. 출력물 --}}
<section class="bg-white rounded-2xl border border-slate-200 p-6 mb-10">
    <div class="flex items-center gap-3">
        <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-slate-200 text-slate-600 text-xl">🖨️</span>
        <div>
            <h2 class="text-xl font-bold text-slate-900">3. 출력물 미리 보기</h2>
            <p class="text-sm text-slate-500">결재·보관용 서류가 A4로 바로 나옵니다.</p>
        </div>
    </div>

    <div class="mt-5 grid sm:grid-cols-3 gap-3">
        <a href="{{ route('judge.print', $judges->first()) }}" target="_blank"
           class="rounded-xl border border-slate-200 px-4 py-4 hover:border-slate-400 hover:bg-slate-50 transition">
            <span class="block font-bold text-slate-800">개인 심사표</span>
            <span class="block text-xs text-slate-400 mt-1">심사위원이 매긴 점수 + 전자서명</span>
        </a>
        <a href="{{ route('demo.admin') }}"
           class="rounded-xl border border-slate-200 px-4 py-4 hover:border-slate-400 hover:bg-slate-50 transition">
            <span class="block font-bold text-slate-800">최종집계표 · QR 안내문</span>
            <span class="block text-xs text-slate-400 mt-1">관리자 화면에서 출력 (결재란 포함)</span>
        </a>
        <div class="rounded-xl border border-dashed border-slate-200 px-4 py-4">
            <span class="block font-bold text-slate-500">CSV 내려받기</span>
            <span class="block text-xs text-slate-400 mt-1">관리자 화면 집계 탭에서 엑셀용 CSV 다운로드</span>
        </div>
    </div>
</section>

{{-- 마무리 CTA --}}
<section class="rounded-2xl bg-slate-900 text-white p-8 text-center">
    <h2 class="text-xl font-bold">직접 행사를 만들어 보시겠어요?</h2>
    <p class="mt-2 text-sm text-slate-300">회원가입 없이, 행사명과 관리 비밀번호만 정하면 바로 시작합니다.</p>
    <a href="{{ route('events.index') }}"
       class="mt-5 inline-flex rounded-lg bg-white text-slate-900 font-bold px-6 py-3 hover:bg-slate-100 transition">
        새 행사 만들기
    </a>
</section>

@endif
@endsection
