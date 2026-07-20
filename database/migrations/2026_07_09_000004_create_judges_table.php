<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('judges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');                          // 심사위원 이름
            $table->string('code', 16)->unique();            // 고유 접속 코드 (링크용)
            $table->longText('signature')->nullable();       // 전자서명 (canvas PNG dataURL)
            $table->timestamp('signed_at')->nullable();      // 서명 시각
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('judges');
    }
};
