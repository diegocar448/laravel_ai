<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class ImprovementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'improvement_type_id' => fake()->randomElement([1, 2, 3]),
            'improvement_step_id' => 1,
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'file_path' => 'app/Services/' . fake()->word() . 'Service.php',
            'priority' => fake()->randomElement([0, 1, 2]),
            'order' => fake()->numberBetween(0, 20),
            'completed_at' => null,
        ];
    }

    public function done(): static
    {
        return $this->state([
            'improvement_step_id' => 3,
            'completed_at' => now(),
        ]);
    }
}
