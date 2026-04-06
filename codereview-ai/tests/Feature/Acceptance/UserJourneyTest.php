<?php

use App\Models\User;
use Livewire\Volt\Volt;

test('new user can register and see dashboard', function () {
    Volt::test('pages/auth/register')
        ->set('form.name', 'New User')
        ->set('form.email', 'new@test.com')
        ->set('form.password', 'password123')
        ->set('form.password_confirmation', 'password123')
        ->call('register')
        ->assertHasNoErrors();

    $user = User::where('email', 'new@test.com')->first();
    $this->actingAs($user)->get('/')->assertOk();
});

test('admin can access admin panel', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/admin/users')
        ->assertOk();
});

test('regular user cannot access admin panel', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin/users')
        ->assertForbidden();
});
