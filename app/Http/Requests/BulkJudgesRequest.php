<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** 심사위원 일괄 등록 — 웹·앱 공통. 한 줄에 한 명, 코드는 자동 발급된다. */
class BulkJudgesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['bulk' => ['required', 'string', 'max:5000']];
    }

    public function attributes(): array
    {
        return ['bulk' => '심사위원'];
    }
}
