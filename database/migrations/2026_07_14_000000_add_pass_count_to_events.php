<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // 선정자(선정기관) 수 — null이면 선정 표시/동점 경고 사용 안 함
            $table->unsignedSmallInteger('pass_count')->nullable()->after('scoring_method');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('pass_count');
        });
    }
};
