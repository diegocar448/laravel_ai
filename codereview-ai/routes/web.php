<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// Rotas autenticadas
Route::middleware('auth')->group(function () {
    Route::livewire('/', 'pages.home')->name('home');
    Route::livewire('/kanban', 'pages.kanban')->name('kanban');
    Route::livewire('/project/{project}', 'pages.projects.show')->name('project');
    Route::livewire('/review/{codeReview}', 'pages.reviews.show')->name('review');

    // Admin — so is_admin = true
    Route::livewire('/admin/users', 'pages.admin.users')
        ->middleware('admin')
        ->name('admin.users');

    // Logout (mais seguro: invalida sessao + regenera CSRF token)
    Route::post('/logout', function () {
        auth()->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

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

// Health check (Docker/load balancer)
Route::get('/health', function () {
    try {
        DB::connection()->getPdo();
        return response()->json(['status' => 'ok']);
    } catch (\Exception $e) {
        return response()->json(['status' => 'error'], 500);
    }
});
