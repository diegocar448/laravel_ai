<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Livewire\Forms\RegisterForm;

new #[Layout('layouts::guest')] class extends Component
{
    public RegisterForm $form;

    public function register(): void
    {
        $this->form->store();

        // Só redireciona se não houver erros
        if (!$this->form->getErrorBag()->isNotEmpty()) {
            $this->redirect(route('home'));
        }
    }
}
?>

<x-card class="max-w-md mx-auto mt-20">
        <x-card.header>
            <h1 class="text-2xl font-bold">Criar conta</h1>
        </x-card.header>
        <x-card.body>
            @if ($errors->has('form.email'))
                <x-alert type="danger" dismissible class="mb-4">
                    {{ $errors->first('form.email') }}
                </x-alert>
            @endif

            <form wire:submit="register" class="space-y-4">
                <x-form.input wire:model="form.name" label="Nome" />
                <x-form.input wire:model="form.email" type="email" label="E-mail" />
                <x-form.input wire:model="form.password" type="password" label="Senha" />
                <x-form.input wire:model="form.password_confirmation" type="password" label="Confirmar senha" />
                <x-button type="submit" class="w-full" wire:loading.attr="disabled">
                    <span wire:loading.remove>Cadastrar</span>
                    <span wire:loading>Cadastrando...</span>
                </x-button>
            </form>

            <p class="mt-4 text-center text-sm text-gray-500">
                Ja tem conta? <a href="{{ route('login') }}" class="text-indigo-600">Entrar</a>
            </p>
        </x-card.body>
</x-card>
