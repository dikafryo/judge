<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 심사 기본점수 — 심사위원 화면을 열었을 때 미리 채워 둘 비율(%).
     *
     * null 은 "채우지 않음" 이다. 기존 행사의 동작을 그대로 두려고 기본값을 주지 않았다.
     * 0 은 "0점으로 채움" 이라 null 과 뜻이 다르다.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->unsignedTinyInteger('default_score_percent')->nullable()->after('is_blind');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('default_score_percent');
        });
    }
};
