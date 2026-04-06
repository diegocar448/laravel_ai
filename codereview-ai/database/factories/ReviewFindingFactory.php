<?php

namespace Database\Factories;

use App\Models\CodeReview;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReviewFindingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code_review_id' => CodeReview::factory(),
            'finding_type_id' => fake()->randomElement([1, 2]),
            'review_pillar_id' => fake()->randomElement([1, 2, 3]),
            'description' => fake()->sentence(),
            'severity' => fake()->randomElement(['low', 'medium', 'high', 'critical']),
            'agent_flagged_at' => null,
            'user_flagged_at' => null,
        ];
    }

    public function flaggedByAgent(): static
    {
        return $this->state(['agent_flagged_at' => now()]);
    }

    public function critical(): static
    {
        return $this->state(['severity' => 'critical']);
    }
}
