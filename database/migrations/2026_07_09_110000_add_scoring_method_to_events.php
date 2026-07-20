<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // 집계 방식: all = 전체 합계·평균 (기본), trimmed = 항목별 최고점·최저점 제외
            $table->string('scoring_method', 10)->default('all')->after('is_open');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('scoring_method');
        });
    }
};
