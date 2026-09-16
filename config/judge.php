<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| 온라인 심사 시스템 자체 설정
|--------------------------------------------------------------------------
| 공개 문서(개인정보처리방침)와 앱 받기 안내에 쓰이는 값들이다. 스토어 심사에서
| 문의처로 실제 연락이 가므로 받을 수 있는 주소를 넣어야 한다.
|
| 플레이 링크는 비공개 테스트라 주소가 바뀔 여지가 있어 설정으로 빼 둔다.
| 셋 중 하나라도 비우면 /app 화면의 플레이스토어 안내가 통째로 빠지고
| APK 직접 내려받기만 남는다.
*/

return [
    // 개인정보 열람·삭제 문의를 받는 주소
    'contact_email' => env('JUDGE_CONTACT_EMAIL', 'soogi0611@gmail.com'),

    // 방침을 고칠 때마다 함께 올린다
    'privacy_effective_date' => env('JUDGE_PRIVACY_EFFECTIVE_DATE', '2026년 9월 15일'),

    // 비공개 테스트 참가자를 받는 구글 그룹스. 여기 가입해야 테스터가 될 수 있다.
    'play_tester_group' => env('JUDGE_PLAY_TESTER_GROUP', 'https://groups.google.com/g/judge-online'),

    // 테스터 등록(옵트인) 페이지 — PC·모바일 브라우저에서 연다
    'play_opt_in_url' => env('JUDGE_PLAY_OPT_IN_URL', 'https://play.google.com/apps/testing/kr.iteach4u.app'),

    // 등록을 마친 뒤 설치하는 스토어 페이지 — 안드로이드 기기에서 연다
    'play_store_url' => env('JUDGE_PLAY_STORE_URL', 'https://play.google.com/store/apps/details?id=kr.iteach4u.app'),
];
