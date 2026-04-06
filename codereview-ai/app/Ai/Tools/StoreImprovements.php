<?php

namespace App\Ai\Tools;

use App\Models\Improvement;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class StoreImprovements implements Tool
{
    public function __construct(
        private Project $project,
    ) {}

    public function description(): string
    {
        return 'Persist the generated improvements to the database as a Kanban board.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'improvements' => $schema->array()
                ->description('List of improvements to save')
                ->required(),
        ];
    }

    public function handle(Request $request): string
    {
        $improvements = $request->input('improvements', []);

        foreach ($improvements as $index => $data) {
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

        return 'Salvas ' . count($improvements) . ' melhorias com sucesso.';
    }
}
