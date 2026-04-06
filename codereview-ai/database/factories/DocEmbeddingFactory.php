<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Pgvector\Laravel\Vector;

class DocEmbeddingFactory extends Factory
{
    public function definition(): array
    {
        $embedding = array_map(fn () => fake()->randomFloat(6, -1, 1), range(1, 768));

        return [
            'source' => fake()->randomElement(['PSR-12', 'OWASP', 'Laravel Docs']),
            'title' => fake()->sentence(4),
            'content' => fake()->paragraph(3),
            'embedding' => new Vector($embedding),
            'category' => fake()->randomElement(['architecture', 'performance', 'security']),
        ];
    }
}
