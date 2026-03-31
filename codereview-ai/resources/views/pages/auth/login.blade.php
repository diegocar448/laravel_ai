<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Livewire\Forms\LoginForm;

new #[Layout('layouts::guest')] class extends Component
{
    public LoginForm $form;

    public function login(): void
    {
        $this->form->authenticate();
        $this->redirect(route('home'));
    }
}
?>

<x-card class="max-w-md mx-auto mt-20">
        <x-card.header>
            <h1 class="text-2xl font-bold">Entrar</h1>
        </x-card.header>
        <x-card.body>
            @if ($errors->has('form.email'))
                <x-alert type="danger" dismissible class="mb-4">
                    {{ $errors->first('form.email') }}
                </x-alert>
            @endif

            <form wire:submit="login" class="space-y-4">
                <x-form.input wire:model="form.email" type="email" label="E-mail" />
                <x-form.input wire:model="form.password" type="password" label="Senha" />
                <x-button type="submit" class="w-full" wire:loading.attr="disabled">
                    <span wire:loading.remove>Entrar</span>
                    <span wire:loading>Entrando...</span>
                </x-button>
            </form>

            <p class="mt-4 text-center text-sm text-gray-500">
                Nao tem conta? <a href="{{ route('register') }}" class="text-indigo-600">Cadastre-se</a>
            </p>
        </x-card.body>
</x-card>
