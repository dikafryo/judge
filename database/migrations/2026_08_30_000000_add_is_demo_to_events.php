<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // 체험용 샘플 행사 표시 — 목록에서 숨기고, 자동 삭제 대상에서 제외하며,
            // 모든 데이터 변경 요청을 차단(읽기 전용)한다.
            $table->boolean('is_demo')->default(false)->after('is_open');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('is_demo');
        });
    }
};
