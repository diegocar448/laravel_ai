<?php
// resources/views/pages/projects/show.blade.php

use Livewire\Volt\Component;
use App\Models\Project;
use App\Livewire\Forms\CodeReviewForm;

new class extends Component
{
    public Project $project;
    public CodeReviewForm $form;

    public function mount(Project $project): void
    {
        $this->authorize('view', $project);
        $this->project = $project;
    }

    public function requestReview(): void
    {
        $this->form->store($this->project);
        $this->redirect(route('project', $this->project));
    }

    public function with(): array
    {
        return [
            'project' => $this->project->load('codeReview.findings', 'improvements', 'status'),
        ];
    }
}
?>

<x-layouts::app>
    <div>
        <h1>{{ $project->name }}</h1>
        <span class="text-sm text-gray-500">{{ $project->language }}</span>
        <span class="text-sm">{{ $project->status->name }}</span>

        <div>
            <h2>Codigo</h2>
            <pre><code>{{ $project->code_snippet }}</code></pre>
        </div>

        @if($project->codeReview)
            <div>
                <h2>Resultado da Analise</h2>
                <p>{{ $project->codeReview->summary }}</p>

                @foreach($project->codeReview->findings as $finding)
                    <x-card>
                        <x-card.header>
                            {{ $finding->pillar->name }} — {{ $finding->type->name }}
                            <span class="text-sm">{{ $finding->severity }}</span>
                        </x-card.header>
                        <x-card.body>{{ $finding->description }}</x-card.body>
                    </x-card>
                @endforeach
            </div>
        @else
            <form wire:submit="requestReview">
                <x-button type="submit">
                    <span wire:loading.remove>Solicitar Analise IA</span>
                    <span wire:loading>Analisando...</span>
                </x-button>
            </form>
        @endif

        @if($project->improvements->count())
            <div>
                <h2>Melhorias</h2>
                @foreach($project->improvements as $improvement)
                    <x-card>
                        <x-card.header>{{ $improvement->title }}</x-card.header>
                        <x-card.body>{{ $improvement->description }}</x-card.body>
                    </x-card>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts::app>
