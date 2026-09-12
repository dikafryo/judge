<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** 집계 방식·블라인드 여부·선정자 수 — 웹·앱 공통. */
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
        ];
    }

    public function attributes(): array
    {
        return [
            'scoring_method' => '집계 방식',
            'is_blind' => '심사위원 화면',
            'pass_count' => '선정자 수',
        ];
    }
}
