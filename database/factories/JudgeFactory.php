<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Judge;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Judge> */
class JudgeFactory extends Factory
{
    protected $model = Judge::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name'     => fake()->name(),
            // 운영 코드와 같은 형식(6자리 숫자). unique 로 충돌을 피한다
            'code'     => (string) fake()->unique()->numberBetween(100000, 999999),
        ];
    }

    /** 심사 마감 시 코드가 회수된 상태 — 이때 심사위원 라우트는 404 가 된다 */
    public function codeRevoked(): static
    {
        return $this->state(['code' => null]);
    }
}
