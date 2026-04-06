<?php

use Illuminate\Support\Facades\DB;

test('health endpoint returns ok', function () {
    $this->getJson('/health')
        ->assertOk()
        ->assertJsonPath('status', 'ok');
});

test('login page loads', function () {
    $this->get('/login')->assertOk();
});

test('register page loads', function () {
    $this->get('/register')->assertOk();
});

test('database connection works', function () {
    expect(DB::connection()->getPdo())->not->toBeNull();
});

test('pgvector extension is available', function () {
    $result = DB::select("SELECT extname FROM pg_extension WHERE extname = 'vector'");
    expect($result)->not->toBeEmpty();
});
