<?php

use Illuminate\Support\Facades\Route;

// Rotas autenticadas
Route::middleware('auth')->group(function () {
    Route::livewire('/', 'pages.home')->name('home');
    Route::livewire('/kanban', 'pages.kanban')->name('kanban');
    Route::livewire('/project/{project}', 'pages.projects.show')->name('project');
    Route::livewire('/review/{codeReview}', 'pages.reviews.show')->name('review');

    // Admin
    Route::livewire('/admin/users', 'pages.admin.users')
        ->middleware('admin')
        ->name('admin.users');

    // Logout
    Route::post('/logout', function () {
        auth()->logout();
        return redirect('/login');
    })->name('logout');
});

// Rotas guest (nao autenticado)
Route::middleware('guest')->group(function () {
    Route::livewire('/login', 'pages.auth.login')->name('login');
    Route::livewire('/register', 'pages.auth.register')->name('register');
});

// Design System (publico)
Route::livewire('/design-system', 'pages.design-system.index')->name('design-system');
