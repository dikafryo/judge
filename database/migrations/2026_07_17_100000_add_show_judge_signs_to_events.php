<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // 최종집계표에 심사위원 전원 서명란 포함 여부 — false면 결재란(기록자 필수)만 표시
            $table->boolean('show_judge_signs')->default(true)->after('report_signers');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('show_judge_signs');
        });
    }
};
