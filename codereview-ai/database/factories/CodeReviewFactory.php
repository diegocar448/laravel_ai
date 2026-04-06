<?php

namespace Database\Factories;

use App\Enums\ReviewStatusEnum;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class CodeReviewFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'review_status_id' => ReviewStatusEnum::Pending->value,
            'summary' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state([
            'review_status_id' => ReviewStatusEnum::Completed->value,
            'summary' => fake()->paragraphs(3, true),
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'review_status_id' => ReviewStatusEnum::Failed->value,
            'summary' => 'Erro ao analisar o codigo.',
        ]);
    }
}
