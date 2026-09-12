{{-- 화면 상단 경고/안내 띠. 같은 클래스 문자열이 관리 화면 네 곳에 복사돼 있던 것을 모은다. --}}
@props(['tone' => 'warning'])

@php
    // 빨강이 두 종류(rose-700/800)로 갈려 있던 것을 대비가 높은 rose-800 으로 모은다
    $tones = [
        'warning' => 'bg-amber-50 border-amber-300 text-amber-800',
        'danger'  => 'bg-rose-50 border-rose-200 text-rose-800',
        'info'    => 'bg-indigo-50 border-indigo-200 text-indigo-800',
        'success' => 'bg-emerald-50 border-emerald-200 text-emerald-800',
    ];
@endphp

<div {{ $attributes->class(['rounded-lg border px-4 py-3 text-sm', $tones[$tone] ?? $tones['warning']]) }}>
    {{ $slot }}
</div>
