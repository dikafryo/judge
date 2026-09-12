{{-- 관리 화면 공통 로그아웃 버튼 (각 화면의 @section('header-right') 안에서 쓴다) --}}
@props(['event'])

<form method="POST" action="{{ route('admin.logout', $event) }}">
    @csrf
    <button class="text-slate-400 hover:text-slate-600">로그아웃</button>
</form>
