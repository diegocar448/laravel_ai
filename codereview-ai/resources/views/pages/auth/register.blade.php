<?php
// resources/views/pages/auth/register.blade.php

use Livewire\Volt\Component;
use App\Livewire\Forms\RegisterForm;

new class extends Component
{
    public RegisterForm $form;

    public function register(): void
    {
        $this->form->store();
        $this->redirect(route('home'));
    }
}
?>

<x-layouts.guest>
    <div>
        <h1>Criar Conta</h1>

        <form wire:submit="register">
            <x-form.input wire:model="form.name" label="Nome" />
            <x-form.input wire:model="form.email" label="Email" type="email" />
            <x-form.input wire:model="form.password" label="Senha" type="password" />
            <x-form.input wire:model="form.password_confirmation" label="Confirmar Senha" type="password" />

            <x-button type="submit">Registrar</x-button>
        </form>

        <p>Ja tem conta? <a href="{{ route('login') }}">Faca login</a></p>
    </div>
</x-layouts.guest>
