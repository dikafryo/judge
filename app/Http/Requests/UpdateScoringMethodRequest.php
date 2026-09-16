<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** 집계 방식·블라인드 여부·심사 기본점수·선정자 수 — 웹·앱 공통. */
class UpdateScoringMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'scoring_method' => ['required', Rule::in(Event::SCORING_METHODS)],
            'is_blind' => ['required', 'boolean'],
            'pass_count' => ['nullable', 'integer', 'min:1', 'max:1000'],
            // 0 은 "0점으로 채움", 빈 값은 "채우지 않음" — 뜻이 다르므로 nullable 을 둔다
            'default_score_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
        ];
    }

    public function attributes(): array
    {
        return [
            'scoring_method' => '집계 방식',
            'is_blind' => '심사위원 화면',
            'pass_count' => '선정자 수',
            'default_score_percent' => '심사 기본점수',
        ];
    }
}
