<?php

use App\Models\User;
use Livewire\Volt\Volt;

test('user can login', function () {
    $user = User::factory()->create();

    Volt::test('pages/auth/login')
        ->set('form.email', $user->email)
        ->set('form.password', 'password')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect('/');
});

test('login fails with wrong password', function () {
    $user = User::factory()->create();

    Volt::test('pages/auth/login')
        ->set('form.email', $user->email)
        ->set('form.password', 'wrong-password')
        ->call('login')
        ->assertHasErrors('form.email');
});

test('guest cannot access home page', function () {
    $this->get('/')->assertRedirect('/login');
});

test('authenticated user can access home', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/')->assertOk();
});
