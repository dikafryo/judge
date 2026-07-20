<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 심사 마감 시 접속 코드를 회수(null)해 코드 재사용 풀에서 해제한다.
        // unique 인덱스는 유지 — MySQL은 NULL 중복을 허용하므로 회수된 행이 여러 개여도 문제없다.
        Schema::table('judges', function (Blueprint $table) {
            $table->string('code', 16)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('judges', function (Blueprint $table) {
            $table->string('code', 16)->nullable(false)->change();
        });
    }
};
