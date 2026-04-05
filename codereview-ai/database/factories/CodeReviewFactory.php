<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class CodeReviewFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'review_status_id' => 1,
            'summary' => null,
        ];
    }
}
