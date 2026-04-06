<?php

use App\Models\User;
use Livewire\Volt\Volt;

test('user can register', function () {
    Volt::test('pages/auth/register')
        ->set('form.name', 'Diego')
        ->set('form.email', 'diego@test.com')
        ->set('form.password', 'password123')
        ->set('form.password_confirmation', 'password123')
        ->call('register')
        ->assertHasNoErrors()
        ->assertRedirect('/');

    $this->assertDatabaseHas('users', ['email' => 'diego@test.com']);
});

test('registration requires valid email', function () {
    Volt::test('pages/auth/register')
        ->set('form.name', 'Diego')
        ->set('form.email', 'not-an-email')
        ->set('form.password', 'password123')
        ->set('form.password_confirmation', 'password123')
        ->call('register')
        ->assertHasErrors('form.email');
});

test('registration requires unique email', function () {
    User::factory()->create(['email' => 'taken@test.com']);

    Volt::test('pages/auth/register')
        ->set('form.name', 'Diego')
        ->set('form.email', 'taken@test.com')
        ->set('form.password', 'password123')
        ->set('form.password_confirmation', 'password123')
        ->call('register')
        ->assertHasErrors('form.email');
});
