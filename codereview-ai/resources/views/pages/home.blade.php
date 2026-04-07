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

<div>
        <h1>Meus Projetos</h1>

        @forelse($projects as $project)
            <a href="{{ route('project', $project) }}" class="block">
                <x-card class="hover:border-indigo-500 transition-colors cursor-pointer">
                    <x-card.header>
                        <div class="flex items-center justify-between">
                            <span>{{ $project->name }}</span>
                            <div class="flex gap-2 text-sm text-gray-500">
                                <span>{{ strtoupper($project->language) }}</span>
                                <span>·</span>
                                <span>{{ $project->status->name ?? 'Active' }}</span>
                            </div>
                        </div>
                    </x-card.header>
                    <x-card.body>
                        <code class="text-xs">{{ Str::limit($project->code_snippet, 120) }}</code>
                    </x-card.body>
                </x-card>
            </a>
        @empty
            <p class="text-gray-500 text-sm">Nenhum projeto ainda. Crie o primeiro abaixo.</p>
        @endforelse

        <hr class="my-8 border-gray-700">
        <h2 class="text-lg font-semibold mb-4">Novo Projeto</h2>

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
