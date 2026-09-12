<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\EventSetup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * 최종집계표 서명 방식 — 웹·앱 공통.
 *
 * 두 경로의 입력 모양이 다르다. 웹 폼은 역할을 배열 키로 보내고
 * (`signers[기록자][name]`), 앱은 역할을 필드로 담은 목록을 보낸다
 * (`signers[0] = {role, name}`). 검증 규칙은 `signers.*` 라 양쪽에 그대로 맞고,
 * 서비스에 넘길 목록 모양으로의 변환은 signerRows() 가 맡는다.
 *
 * 입력 자체를 정규화하지는 않는다 — 웹 폼이 검증 실패 시
 * `old("signers.기록자.name")` 으로 값을 되살리기 때문이다.
 */
class UpdateReportSignersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'show_judge_signs' => ['required', 'boolean'],
            'signers' => ['nullable', 'array'],
            // 앱만 role 을 필드로 보낸다. 웹은 배열 키가 곧 역할이라 규칙으로 잡히지 않는다 —
            // 어느 쪽이든 실제 역할 검사는 after() 가 맡는다.
            'signers.*.role' => ['sometimes', 'string'],
            'signers.*.dept' => ['nullable', 'string', 'max:50'],
            'signers.*.position' => ['nullable', 'string', 'max:50'],
            'signers.*.name' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function attributes(): array
    {
        return [
            'show_judge_signs' => '심사위원 서명란',
            'signers.*.dept' => '부서',
            'signers.*.position' => '직급',
            'signers.*.name' => '이름',
        ];
    }

    /**
     * 역할이 정해진 셋 중 하나인지 본다.
     *
     * 웹은 배열 키, 앱은 role 필드라 입력 위치가 달라서 규칙 문법으로는 한 번에 못 잡는다.
     * 여기서 보면 두 경로가 같은 제약을 받는다.
     *
     * @return list<callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach ((array) $this->input('signers', []) as $key => $row) {
                $role = is_array($row) ? ($row['role'] ?? $key) : $key;

                if (! in_array($role, EventSetup::SIGNER_ROLES, true)) {
                    $validator->errors()->add(
                        "signers.{$key}.role",
                        '결재란 역할은 '.implode(', ', EventSetup::SIGNER_ROLES).' 중 하나여야 합니다.',
                    );
                }
            }
        }];
    }

    /** 심사위원 서명란 포함 여부. 웹 폼은 '0'/'1' 문자열로 보낸다. */
    public function showJudgeSigns(): bool
    {
        return $this->boolean('show_judge_signs');
    }

    /**
     * 결재란을 서비스가 받는 목록 모양으로 맞춘다.
     * 역할이 필드에 없으면(웹) 배열 키를 역할로 쓴다.
     *
     * @return list<array{role: string, dept?: string|null, position?: string|null, name?: string|null}>
     */
    public function signerRows(): array
    {
        return collect($this->validated('signers') ?? [])
            ->map(fn (array $row, string|int $key): array => ['role' => (string) ($row['role'] ?? $key), ...$row])
            ->values()
            ->all();
    }
}
