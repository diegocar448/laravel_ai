<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LookupSeeder extends Seeder
{
    public function run(): void
    {
        // Project Statuses
        DB::table('project_statuses')->insert([
            ['id' => 1, 'name' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Completed', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'Archived', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Review Statuses
        DB::table('review_statuses')->insert([
            ['id' => 1, 'name' => 'Pending', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Completed', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'Failed', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Finding Types
        DB::table('finding_types')->insert([
            ['id' => 1, 'name' => 'Strength', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Improvement', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Review Pillars
        DB::table('review_pillars')->insert([
            ['id' => 1, 'name' => 'Architecture', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Performance', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'Security', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Improvement Types
        DB::table('improvement_types')->insert([
            ['id' => 1, 'name' => 'Refactor', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Fix', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'Optimization', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Improvement Steps
        DB::table('improvement_steps')->insert([
            ['id' => 1, 'name' => 'ToDo', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'InProgress', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'Done', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
