<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'project_status_id' => 1,
            'name' => $this->faker->words(3, true),
            'language' => 'PHP',
            'code_snippet' => '<?php echo "hello";',
        ];
    }
}
