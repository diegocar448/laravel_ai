<?php

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => (new Database\Seeders\LookupSeeder)->run())
    ->in('Feature', 'E2E', 'Performance', 'Smoke');

pest()->extend(TestCase::class)->in('Unit');
