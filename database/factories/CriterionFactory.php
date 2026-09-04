<?php

namespace Database\Factories;

use App\Models\Criterion;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Criterion> */
class CriterionFactory extends Factory
{
    protected $model = Criterion::class;

    public function definition(): array
    {
        return [
            'event_id'    => Event::factory(),
            'parent_id'   => null,
            'name'        => fake()->word(),
            'description' => null,
            'max_score'   => 50,
            'sort_order'  => 0,
        ];
    }
}
