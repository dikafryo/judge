<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('name');                          // 행사(프로젝트)명
            $table->text('description')->nullable();         // 행사 설명
            $table->date('event_date')->nullable();          // 행사일
            $table->string('admin_password');                // 관리 비밀번호 (Hash::make 저장)
            $table->boolean('is_open')->default(true);       // 심사 진행중 여부 (마감 시 false)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
