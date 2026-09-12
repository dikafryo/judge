<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 행사 삭제 확인 — 웹·앱 공통.
 *
 * 행사명이 실제로 일치하는지는 여기서 보지 않는다. 그건 도메인 규칙이라
 * EventSetup::deleteEvent() 가 판단하고 SetupRejected 를 던진다.
 */
class DestroyEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['confirm_name' => ['required', 'string']];
    }

    public function attributes(): array
    {
        return ['confirm_name' => '행사명'];
    }
}
