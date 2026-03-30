<?php

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
    <x-card class="max-w-md mx-auto mt-20">
        <x-card.header>
            <h1 class="text-2xl font-bold">Entrar</h1>
        </x-card.header>
        <x-card.body>
            <form wire:submit="login" class="space-y-4">
                <x-form.input wire:model="form.email" type="email" label="E-mail" />
                <x-form.input wire:model="form.password" type="password" label="Senha" />
                <x-button type="submit" class="w-full">Entrar</x-button>
            </form>

            <p class="mt-4 text-center text-sm text-gray-500">
                Nao tem conta? <a href="{{ route('register') }}" class="text-indigo-600">Cadastre-se</a>
            </p>
        </x-card.body>
    </x-card>
</x-layouts.guest>
