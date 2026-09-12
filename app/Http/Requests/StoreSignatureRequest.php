<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** 전자서명 저장 — 웹·앱 공통. canvas 가 만든 PNG dataURL 만 받는다. */
class StoreSignatureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'signature' => ['required', 'string', 'starts_with:data:image/png;base64,', 'max:200000'],
        ];
    }

    public function attributes(): array
    {
        return ['signature' => '서명'];
    }
}
