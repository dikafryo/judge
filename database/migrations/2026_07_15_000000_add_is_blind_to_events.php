<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // 심사위원 화면 노출 — true: 심사번호만(블라인드, 기본) / false: 평가 대상 이름 공개
            $table->boolean('is_blind')->default(true)->after('pass_count');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('is_blind');
        });
    }
};
