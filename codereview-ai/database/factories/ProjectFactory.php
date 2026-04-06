<?php

namespace Database\Factories;

use App\Enums\ProjectStatusEnum;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'project_status_id' => ProjectStatusEnum::Active->value,
            'name' => fake()->words(3, true),
            'language' => fake()->randomElement(['php', 'javascript', 'python', 'typescript']),
            'code_snippet' => fake()->text(500),
            'repository_url' => fake()->optional()->url(),
        ];
    }

    public function completed(): static
    {
        return $this->state(['project_status_id' => ProjectStatusEnum::Completed->value]);
    }
}
