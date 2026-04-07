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

<div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold">{{ $project->name }}</h1>
                <div class="flex items-center gap-2 mt-1">
                    <span class="text-xs px-2 py-0.5 rounded bg-indigo-600/30 text-indigo-300">{{ strtoupper($project->language) }}</span>
                    <span class="text-xs text-gray-400">{{ $project->status->name }}</span>
                </div>
            </div>
        </div>

        <x-card>
            <x-card.header>
                <span class="text-sm font-medium">Codigo</span>
            </x-card.header>
            <x-card.body class="p-0">
                <pre class="rounded-b-lg overflow-x-auto text-sm m-0"><code class="{{ $project->language }}">{{ $project->code_snippet }}</code></pre>
            </x-card.body>
        </x-card>

        @if($project->codeReview && $project->codeReview->review_status_id === 1)
            <div wire:poll.3s class="mt-6 rounded-xl border border-indigo-500/30 bg-indigo-950/40 overflow-hidden">
                {{-- Barra de progresso animada no topo --}}
                <div class="h-1 w-full bg-indigo-900/50 overflow-hidden">
                    <div class="h-1 bg-indigo-500 animate-[progress_2s_ease-in-out_infinite]"
                         style="animation: progress 2s ease-in-out infinite; width: 40%"></div>
                </div>

                <div class="p-6">
                    <div class="flex items-start gap-4">
                        {{-- Icone animado --}}
                        <div class="flex-shrink-0 w-10 h-10 rounded-full bg-indigo-600/20 flex items-center justify-center">
                            <svg class="animate-spin h-5 w-5 text-indigo-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                            </svg>
                        </div>

                        <div class="flex-1">
                            <p class="text-sm font-semibold text-indigo-300">Analisando com IA...</p>
                            <p class="text-xs text-gray-400 mt-1">Os agentes estao revisando o codigo. Isso leva entre 10 e 30 segundos.</p>

                            {{-- Etapas --}}
                            <div class="mt-4 space-y-2">
                                @foreach([
                                    'CodeAnalyst — Analisando estrutura geral',
                                    'SecurityAnalyst — Verificando vulnerabilidades OWASP',
                                    'ArchitectureAnalyst — Avaliando padroes de design',
                                    'PerformanceAnalyst — Identificando gargalos',
                                ] as $i => $etapa)
                                    <div class="flex items-center gap-2 text-xs text-gray-400">
                                        <div class="w-1.5 h-1.5 rounded-full bg-indigo-500 animate-pulse"
                                             style="animation-delay: {{ $i * 0.3 }}s"></div>
                                        {{ $etapa }}
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <style>
                @keyframes progress {
                    0%   { width: 5%;  margin-left: 0; }
                    50%  { width: 40%; margin-left: 30%; }
                    100% { width: 5%;  margin-left: 100%; }
                }
            </style>
        @endif

        @if($project->codeReview && $project->codeReview->review_status_id === 3)
            <div class="mt-6 p-4 rounded-lg bg-red-900/30 border border-red-500/30 text-red-300 text-sm">
                Falha na analise. Tente novamente mais tarde.
            </div>
        @endif

        @if($project->codeReview && $project->codeReview->review_status_id === 2)
            <div class="mt-6 space-y-6">
                <h2 class="text-lg font-semibold">Resultado da Analise</h2>

                @if($project->codeReview->summary)
                    <x-card>
                        <x-card.header>
                            <span class="text-sm font-medium">Analise Completa</span>
                        </x-card.header>
                        <x-card.body>
                            <div class="text-sm leading-7 text-gray-300 space-y-3
                                [&_h1]:text-lg [&_h1]:font-bold [&_h1]:text-white [&_h1]:mt-4
                                [&_h2]:text-base [&_h2]:font-semibold [&_h2]:text-indigo-300 [&_h2]:mt-4 [&_h2]:border-b [&_h2]:border-gray-700 [&_h2]:pb-1
                                [&_h3]:text-sm [&_h3]:font-semibold [&_h3]:text-gray-200 [&_h3]:mt-3
                                [&_strong]:text-white [&_strong]:font-semibold
                                [&_code]:bg-gray-800 [&_code]:px-1.5 [&_code]:py-0.5 [&_code]:rounded [&_code]:text-indigo-300 [&_code]:text-xs
                                [&_pre]:bg-gray-800 [&_pre]:rounded-lg [&_pre]:p-4 [&_pre]:overflow-x-auto [&_pre]:my-3
                                [&_ul]:list-disc [&_ul]:pl-5 [&_ul]:space-y-1
                                [&_ol]:list-decimal [&_ol]:pl-5 [&_ol]:space-y-1
                                [&_li]:text-gray-300
                            ">{!! \Illuminate\Support\Str::markdown($project->codeReview->summary) !!}</div>
                        </x-card.body>
                    </x-card>
                @endif

                @if($project->codeReview->findings->count())
                    <h3 class="text-base font-semibold mt-4">Findings</h3>
                    @foreach($project->codeReview->findings as $finding)
                        <x-card>
                            <x-card.header>
                                <div class="flex items-center justify-between">
                                    <span>{{ $finding->pillar->name }} — {{ $finding->type->name }}</span>
                                    <span class="text-xs px-2 py-1 rounded
                                        {{ $finding->severity === 'critical' ? 'bg-red-600' : '' }}
                                        {{ $finding->severity === 'high' ? 'bg-orange-500' : '' }}
                                        {{ $finding->severity === 'medium' ? 'bg-yellow-500 text-black' : '' }}
                                        {{ $finding->severity === 'low' ? 'bg-gray-500' : '' }}
                                    ">{{ $finding->severity }}</span>
                                </div>
                            </x-card.header>
                            <x-card.body>{{ $finding->description }}</x-card.body>
                        </x-card>
                    @endforeach
                @endif
            </div>
        @else
            <form wire:submit="requestReview" class="space-y-6 mt-6">
                <h2 class="text-lg font-semibold">Solicitar Analise de IA</h2>
                <p class="text-sm text-gray-500">Preencha suas observacoes sobre o codigo antes de enviar para analise.</p>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <x-form.input
                        wire:model="form.architecture_strength"
                        label="Arquitetura — Ponto forte"
                        placeholder="Ex: Bom uso de interfaces, separacao de responsabilidades"
                    />
                    <x-form.input
                        wire:model="form.architecture_improvement"
                        label="Arquitetura — Ponto de melhoria"
                        placeholder="Ex: Alta coesao entre classes, falta de injecao de dependencia"
                    />
                    <x-form.input
                        wire:model="form.performance_strength"
                        label="Performance — Ponto forte"
                        placeholder="Ex: Queries otimizadas, uso de cache"
                    />
                    <x-form.input
                        wire:model="form.performance_improvement"
                        label="Performance — Ponto de melhoria"
                        placeholder="Ex: N+1 detectado, falta de paginacao"
                    />
                    <x-form.input
                        wire:model="form.security_strength"
                        label="Seguranca — Ponto forte"
                        placeholder="Ex: CSRF protegido, validacao de inputs"
                    />
                    <x-form.input
                        wire:model="form.security_improvement"
                        label="Seguranca — Ponto de melhoria"
                        placeholder="Ex: SQL injection risk, dados sensiveis expostos"
                    />
                </div>

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
