<?php

namespace App\Ai\Tools;

use App\Models\Improvement;
use App\Models\Project;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Contracts\ToolSchema;

class StoreImprovements implements Tool
{
    public function __construct(
        private Project $project,
    ) {}

    public function name(): string
    {
        return 'store_improvements';
    }

    public function description(): string
    {
        return 'Persist the generated improvements to the database as a Kanban board.';
    }

    public function schema(): ToolSchema
    {
        return ToolSchema::make()
            ->array('improvements', 'List of improvements to save');
    }

    public function execute(array $parameters): string
    {
        foreach ($parameters['improvements'] as $index => $data) {
            Improvement::create([
                'project_id' => $this->project->id,
                'improvement_type_id' => $data['improvement_type_id'] ?? 1,
                'improvement_step_id' => 1, // ToDo
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'file_path' => $data['file_path'] ?? null,
                'priority' => $data['priority'] ?? 0,
                'order' => $index,
            ]);
        }

        return "Salvas " . count($parameters['improvements']) . " melhorias com sucesso.";
    }
}
