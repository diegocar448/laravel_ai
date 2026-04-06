<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Improvement;
use App\Models\Project;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ImprovementController extends Controller
{
    #[OA\Get(
        path: '/api/projects/{projectId}/improvements',
        summary: 'Listar melhorias (Kanban) de um projeto',
        tags: ['Improvements'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'projectId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'step', in: 'query', required: false, description: '1=ToDo, 2=InProgress, 3=Done', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Lista de improvements'),
        ]
    )]
    public function index(Request $request, Project $project)
    {
        $this->authorize('view', $project);

        $query = $project->improvements()->with(['type', 'step'])->orderBy('order');

        if ($request->has('step')) {
            $query->where('improvement_step_id', $request->step);
        }

        return $query->get();
    }

    #[OA\Patch(
        path: '/api/improvements/{id}',
        summary: 'Atualizar improvement (mover no Kanban)',
        tags: ['Improvements'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'improvement_step_id', type: 'integer', description: '1=ToDo, 2=InProgress, 3=Done'),
                    new OA\Property(property: 'order', type: 'integer'),
                    new OA\Property(property: 'completed_at', type: 'string', format: 'date-time', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Improvement atualizado'),
        ]
    )]
    public function update(Request $request, Improvement $improvement)
    {
        $this->authorize('update', $improvement->project);

        $validated = $request->validate([
            'improvement_step_id' => 'sometimes|integer|in:1,2,3',
            'order' => 'sometimes|integer',
            'completed_at' => 'sometimes|nullable|date',
        ]);

        $improvement->update($validated);

        return $improvement->load(['type', 'step']);
    }
}
