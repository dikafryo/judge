<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** 평가 항목 등록 — 웹·앱 공통. 배점 합계 같은 도메인 규칙은 EventSetup 이 맡는다. */
class StoreCriterionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'max_score' => ['required', 'integer', 'min:1', 'max:100'],
            'parent_id' => ['nullable', 'integer'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => '항목명',
            'max_score' => '배점',
            'parent_id' => '1레벨 항목',
        ];
    }
}
