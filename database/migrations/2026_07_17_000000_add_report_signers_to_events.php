<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // 최종집계표 하단 결재란 — [{role: 기록자|검토자|확인자, dept, position, name}, ...], null이면 표시 안 함
            $table->json('report_signers')->nullable()->after('is_blind');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('report_signers');
        });
    }
};
