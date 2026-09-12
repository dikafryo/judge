<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * 이 앱에는 회원 개념이 없다 — 행사는 비밀번호로, 심사위원은 접속 코드로 들어온다.
 * 그래서 기본 시딩으로 만들 것이 없다.
 *
 * 체험용 샘플 행사가 필요하면 `php artisan judge:demo` 를 쓴다.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        //
    }
}
