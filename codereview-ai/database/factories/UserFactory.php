<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => 'password', // cast 'hashed' faz o bcrypt
            'is_admin' => false,
            'first_review_at' => null,
            'first_plan_at' => null,
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(['is_admin' => true]);
    }

    public function withReview(): static
    {
        return $this->state(['first_review_at' => now()]);
    }
}
