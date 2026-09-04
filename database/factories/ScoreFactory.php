<?php

namespace Database\Factories;

use App\Models\Candidate;
use App\Models\Criterion;
use App\Models\Judge;
use App\Models\Score;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Score> */
class ScoreFactory extends Factory
{
    protected $model = Score::class;

    public function definition(): array
    {
        return [
            'judge_id'     => Judge::factory(),
            'candidate_id' => Candidate::factory(),
            'criterion_id' => Criterion::factory(),
            'score'        => fake()->numberBetween(0, 50),
        ];
    }
}
