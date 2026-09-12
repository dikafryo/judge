<?php

declare(strict_types=1);

namespace App\Http\Requests;

/** 점수 저장 — 웹 심사 화면용. 평가 대상을 본문으로 함께 보낸다. */
class StoreJudgeScoresRequest extends StoreScoresRequest
{
    public function rules(): array
    {
        return [
            'candidate_id' => ['required', 'integer'],
            ...parent::rules(),
        ];
    }

    public function attributes(): array
    {
        return ['candidate_id' => '평가 대상', ...parent::attributes()];
    }
}
