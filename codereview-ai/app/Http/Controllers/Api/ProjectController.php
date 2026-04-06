<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Enums\ProjectStatusEnum;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ProjectController extends Controller
{
    #[OA\Get(
        path: '/api/projects',
        summary: 'Listar projetos do usuario',
        tags: ['Projects'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lista de projetos',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/Project')
                )
            ),
        ]
    )]
    public function index(Request $request)
    {
        return $request->user()
            ->projects()
            ->with(['status', 'codeReview.status'])
            ->latest()
            ->paginate(20);
    }

    #[OA\Post(
        path: '/api/projects',
        summary: 'Criar projeto para review',
        tags: ['Projects'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'language', 'code_snippet'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'API de Pagamentos'),
                    new OA\Property(property: 'language', type: 'string', enum: ['php', 'javascript', 'python', 'go', 'rust', 'java', 'typescript']),
                    new OA\Property(property: 'code_snippet', type: 'string', example: 'class PaymentService { ... }'),
                    new OA\Property(property: 'repository_url', type: 'string', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Projeto criado'),
            new OA\Response(response: 422, description: 'Validacao falhou'),
        ]
    )]
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'language' => 'required|string|in:php,javascript,python,go,rust,java,typescript',
            'code_snippet' => 'required|string|min:50',
            'repository_url' => 'nullable|url',
        ]);

        $project = $request->user()->projects()->create([
            ...$validated,
            'project_status_id' => ProjectStatusEnum::Active->value,
        ]);

        return response()->json($project->load('status'), 201);
    }

    #[OA\Get(
        path: '/api/projects/{id}',
        summary: 'Detalhes de um projeto',
        tags: ['Projects'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Projeto com review e improvements'),
            new OA\Response(response: 404, description: 'Nao encontrado'),
        ]
    )]
    public function show(Project $project)
    {
        $this->authorize('view', $project);

        return $project->load([
            'status',
            'codeReview.status',
            'codeReview.findings.pillar',
            'codeReview.findings.type',
            'improvements.type',
            'improvements.step',
        ]);
    }

    #[OA\Delete(
        path: '/api/projects/{id}',
        summary: 'Deletar projeto',
        tags: ['Projects'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Deletado'),
        ]
    )]
    public function destroy(Project $project)
    {
        $this->authorize('delete', $project);
        $project->delete();

        return response()->noContent();
    }
}
