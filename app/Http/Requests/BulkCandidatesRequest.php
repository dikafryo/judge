<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** 평가 대상 일괄 등록 — 웹·앱 공통. 한 줄에 하나, "이름, 소속" 형식. */
class BulkCandidatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['bulk' => ['required', 'string', 'max:10000']];
    }

    public function attributes(): array
    {
        return ['bulk' => '평가 대상'];
    }
}
