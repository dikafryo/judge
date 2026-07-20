<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('criteria', function (Blueprint $table) {
            // 2단계 평가항목: null = 대분류(또는 단독 항목), 값 있음 = 해당 대분류의 서브항목
            $table->foreignId('parent_id')->nullable()->after('event_id')
                ->constrained('criteria')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('criteria', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
        });
    }
};
