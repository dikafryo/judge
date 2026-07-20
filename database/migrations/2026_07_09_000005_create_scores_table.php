<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('judge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('criterion_id')->constrained('criteria')->cascadeOnDelete();
            $table->decimal('score', 5, 1);                  // 부여 점수 (0 ~ 항목 배점)
            $table->timestamps();

            // 한 심사위원이 한 대상의 한 항목에 점수는 1개 — 재제출 시 upsert
            $table->unique(['judge_id', 'candidate_id', 'criterion_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scores');
    }
};
