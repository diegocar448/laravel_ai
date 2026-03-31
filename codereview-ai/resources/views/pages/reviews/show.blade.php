<?php
// resources/views/pages/reviews/show.blade.php

use Livewire\Volt\Component;
use App\Models\CodeReview;

new class extends Component
{
    public CodeReview $codeReview;

    public function mount(CodeReview $codeReview): void
    {
        $this->authorize('view', $codeReview);
        $this->codeReview = $codeReview;
    }

    public function with(): array
    {
        return [
            'review' => $this->codeReview->load('project', 'status', 'findings.type', 'findings.pillar'),
        ];
    }
}
?>

<div>
        <h1>Review: {{ $review->project->name }}</h1>
        <span class="text-sm">{{ $review->status->name }}</span>

        @if($review->summary)
            <div>
                <h2>Resumo</h2>
                <p>{{ $review->summary }}</p>
            </div>
        @endif

        <div>
            <h2>Findings</h2>
            @foreach($review->findings as $finding)
                <x-card>
                    <x-card.header>
                        {{ $finding->pillar->name }} — {{ $finding->type->name }}
                        <span class="text-sm">{{ $finding->severity }}</span>
                    </x-card.header>
                    <x-card.body>{{ $finding->description }}</x-card.body>
                </x-card>
            @endforeach
        </div>

        <div wire:poll.5s>
            @if($review->status->name === 'Pending')
                <p>Analise em andamento... atualizando automaticamente.</p>
            @endif
        </div>
</div>
