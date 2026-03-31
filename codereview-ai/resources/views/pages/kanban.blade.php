<?php
// resources/views/pages/kanban.blade.php

use Livewire\Volt\Component;
use App\Models\Improvement;
use App\Enums\ImprovementStepEnum;

new class extends Component
{
    public function updateStep(int $improvementId, int $stepId): void
    {
        $improvement = Improvement::findOrFail($improvementId);

        $this->authorize('update', $improvement);

        $improvement->update([
            'improvement_step_id' => $stepId,
            'completed_at' => $stepId === ImprovementStepEnum::Done->value ? now() : null,
        ]);
    }

    public function updateOrder(array $items): void
    {
        foreach ($items as $item) {
            Improvement::where('id', $item['id'])->update(['order' => $item['order']]);
        }
    }

    public function with(): array
    {
        $improvements = auth()->user()->projects()
            ->with('improvements.type', 'improvements.step')
            ->get()
            ->pluck('improvements')
            ->flatten();

        return [
            'todo' => $improvements->where('improvement_step_id', ImprovementStepEnum::ToDo->value)->sortBy('order'),
            'inProgress' => $improvements->where('improvement_step_id', ImprovementStepEnum::InProgress->value)->sortBy('order'),
            'done' => $improvements->where('improvement_step_id', ImprovementStepEnum::Done->value)->sortBy('order'),
        ];
    }
}
?>

<div>
        <h1>Kanban de Melhorias</h1>

        <div class="grid grid-cols-3 gap-4">
            {{-- Coluna ToDo --}}
            <div>
                <h2>ToDo</h2>
                @foreach($todo as $item)
                    <x-card>
                        <x-card.header>{{ $item->title }}</x-card.header>
                        <x-card.body>{{ $item->description }}</x-card.body>
                    </x-card>
                @endforeach
            </div>

            {{-- Coluna InProgress --}}
            <div>
                <h2>In Progress</h2>
                @foreach($inProgress as $item)
                    <x-card>
                        <x-card.header>{{ $item->title }}</x-card.header>
                        <x-card.body>{{ $item->description }}</x-card.body>
                    </x-card>
                @endforeach
            </div>

            {{-- Coluna Done --}}
            <div>
                <h2>Done</h2>
                @foreach($done as $item)
                    <x-card>
                        <x-card.header>{{ $item->title }}</x-card.header>
                        <x-card.body>{{ $item->description }}</x-card.body>
                    </x-card>
                @endforeach
            </div>
        </div>
</div>
