<?php

namespace Database\Factories;

use App\Models\Candidate;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Candidate> */
class CandidateFactory extends Factory
{
    protected $model = Candidate::class;

    public function definition(): array
    {
        return [
            'event_id'    => Event::factory(),
            'name'        => fake()->name(),
            'affiliation' => fake()->company(),
            'description' => null,
            'sort_order'  => 0,
        ];
    }
}
