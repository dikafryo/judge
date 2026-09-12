<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 점수 저장 — 앱용. 평가 대상은 URL 에서 온다.
 *
 * 배점 초과·전부 비어 있음 같은 규칙은 여기가 아니라 ScoreWriter 에 있다.
 * 말단 항목이 무엇인지 알아야 판단할 수 있어서 행사 데이터가 필요하기 때문이다.
 */
class StoreScoresRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'scores' => ['required', 'array', 'min:1'],
            'scores.*' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function attributes(): array
    {
        return ['scores' => '점수'];
    }
}
