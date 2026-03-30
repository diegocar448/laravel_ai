<?php

use Livewire\Volt\Component;
use App\Livewire\Forms\RegisterForm;

new class extends Component
{
    public RegisterForm $form;

    public function register(): void
    {
        $this->form->register();
        $this->redirect(route('home'));
    }
}
?>

<x-layouts.guest>
    <x-card class="max-w-md mx-auto mt-20">
        <x-card.header>
            <h1 class="text-2xl font-bold">Criar conta</h1>
        </x-card.header>
        <x-card.body>
            <form wire:submit="register" class="space-y-4">
                <x-form.input wire:model="form.name" label="Nome" />
                <x-form.input wire:model="form.email" type="email" label="E-mail" />
                <x-form.input wire:model="form.password" type="password" label="Senha" />
                <x-form.input wire:model="form.password_confirmation" type="password" label="Confirmar senha" />
                <x-button type="submit" class="w-full">Cadastrar</x-button>
            </form>

            <p class="mt-4 text-center text-sm text-gray-500">
                Ja tem conta? <a href="{{ route('login') }}" class="text-indigo-600">Entrar</a>
            </p>
        </x-card.body>
    </x-card>
</x-layouts.guest>
