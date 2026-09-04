<?php

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/** @extends Factory<Event> */
class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        return [
            'name'             => '제1회 ' . fake()->word() . ' 경진대회',
            'description'      => null,
            'event_date'       => now()->toDateString(),
            'admin_password'   => Hash::make('secret-password'),
            'is_open'          => true,
            'is_demo'          => false,
            'scoring_method'   => 'all',
            'pass_count'       => null,
            'is_blind'         => true,
            'report_signers'   => null,
            'show_judge_signs' => true,
        ];
    }

    public function closed(): static
    {
        return $this->state(['is_open' => false]);
    }

    public function demo(): static
    {
        return $this->state(['is_demo' => true]);
    }

    /** 이름 공개 심사 — 심사위원 화면에 대상 이름·소속이 내려간다 */
    public function named(): static
    {
        return $this->state(['is_blind' => false]);
    }
}
