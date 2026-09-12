<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 행사 생성 — 웹(EventController)과 앱(Api\EventApiController)이 같은 규칙을 쓴다.
 *
 * 규칙이 두 곳으로 갈라지면 앱으로 만든 행사와 웹으로 만든 행사의 제약이 달라진다.
 * 한국어 항목명은 웹 에러 문구에만 쓰이고, 앱은 422 JSON 으로 받는다.
 */
class StoreEventRequest extends FormRequest
{
    /** 접근 제어는 라우트 미들웨어가 한다 (행사 생성은 누구나 가능) */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'event_date' => ['nullable', 'date'],
            'admin_password' => ['required', 'string', 'min:4', 'max:50'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => '행사명',
            'admin_password' => '관리 비밀번호',
        ];
    }
}
