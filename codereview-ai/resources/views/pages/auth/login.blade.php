<?php
// resources/views/pages/auth/login.blade.php

use Livewire\Volt\Component;
use App\Livewire\Forms\LoginForm;

new class extends Component
{
    public LoginForm $form;

    public function login(): void
    {
        $this->form->authenticate();
        $this->redirect(route('home'));
    }
}
?>

<x-layouts.guest>
    <div>
        <h1>Login</h1>

        <form wire:submit="login">
            <x-form.input wire:model="form.email" label="Email" type="email" />
            <x-form.input wire:model="form.password" label="Senha" type="password" />

            <label>
                <input type="checkbox" wire:model="form.remember">
                Lembrar de mim
            </label>

            <x-button type="submit">Entrar</x-button>
        </form>

        <p>Nao tem conta? <a href="{{ route('register') }}">Registre-se</a></p>
    </div>
</x-layouts.guest>
