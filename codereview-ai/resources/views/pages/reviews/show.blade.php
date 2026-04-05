<?php
// resources/views/pages/reviews/show.blade.php

use App\Models\CodeReview;
use Illuminate\Support\Str;
use Livewire\Volt\Component;

new class extends Component
{
    public CodeReview $codeReview;

    public function with(): array
    {
        return [
            'codeReview' => $this->codeReview->fresh(),
            'isPending' => $this->codeReview->review_status_id === 1,
        ];
    }
}
?>

{{-- Se pendente, faz polling a cada 5 segundos --}}
@if($isPending)
    <div wire:poll.5s>
        <x-card>
            <x-card.body class="text-center">
                <div class="animate-spin h-8 w-8 border-4 border-indigo-600
                            border-t-transparent rounded-full mx-auto"></div>
                <p class="mt-4 text-gray-600">Analisando seu codigo...</p>
                <p class="text-sm text-gray-400">
                    3 Agents de IA estao revisando arquitetura, performance e seguranca
                </p>
            </x-card.body>
        </x-card>
    </div>
@else
    {{-- Resultado do code review --}}
    <x-card>
        <x-card.body>
            {!! Str::markdown($codeReview->summary) !!}
        </x-card.body>
    </x-card>

    {{-- Findings por pilar --}}
    @foreach($codeReview->findings as $finding)
        <x-card class="mt-4">
            <x-card.header class="flex justify-between">
                <span>{{ $finding->pillar->name }}</span>
                <x-severity-badge :severity="$finding->severity" />
            </x-card.header>
            <x-card.body>{{ $finding->description }}</x-card.body>
        </x-card>
    @endforeach
@endif
