<?php
// resources/views/pages/home.blade.php

use Livewire\Volt\Component;
use App\Models\Project;
use App\Livewire\Forms\ProjectForm;

new class extends Component
{
    public ProjectForm $form;

    public function save(): void
    {
        $project = $this->form->store();
        $this->redirect(route('project', $project));
    }

    public function with(): array
    {
        return [
            'projects' => auth()->user()->projects()->latest()->get(),
        ];
    }
}
?>

<x-layouts::app>
    <div>
        <h1>Meus Projetos</h1>

        @foreach($projects as $project)
            <x-card>
                <x-card.header>
                    {{ $project->name }}
                    <span class="text-sm text-gray-500">{{ $project->language }}</span>
                </x-card.header>
                <x-card.body>
                    <code>{{ Str::limit($project->code_snippet, 200) }}</code>
                </x-card.body>
            </x-card>
        @endforeach

        <form wire:submit="save">
            <x-form.input wire:model="form.name" label="Nome do projeto" />
            <x-form.select wire:model="form.language" label="Linguagem" :options="[
                'php' => 'PHP',
                'javascript' => 'JavaScript',
                'python' => 'Python',
                'typescript' => 'TypeScript',
                'go' => 'Go',
                'rust' => 'Rust',
                'java' => 'Java',
            ]" />
            <x-form.textarea wire:model="form.code_snippet" label="Cole seu codigo aqui" rows="15" />
            <x-form.input wire:model="form.repository_url" label="URL do repositorio (opcional)" />
            <x-button type="submit">Enviar para analise</x-button>
        </form>
    </div>
</x-layouts::app>
