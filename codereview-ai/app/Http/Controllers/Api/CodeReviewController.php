<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\AnalyzeCodeJob;
use App\Models\CodeReview;
use App\Models\Project;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class CodeReviewController extends Controller
{
    #[OA\Post(
        path: '/api/projects/{projectId}/reviews',
        summary: 'Iniciar code review com IA',
        description: 'Cria um CodeReview e dispara o Agent de analise em background. Use GET /reviews/{id} para acompanhar o status.',
        tags: ['Code Reviews'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'projectId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['architecture_strength', 'architecture_improvement', 'performance_strength', 'performance_improvement', 'security_strength', 'security_improvement'],
                properties: [
                    new OA\Property(property: 'architecture_strength', type: 'string'),
                    new OA\Property(property: 'architecture_improvement', type: 'string'),
                    new OA\Property(property: 'performance_strength', type: 'string'),
                    new OA\Property(property: 'performance_improvement', type: 'string'),
                    new OA\Property(property: 'security_strength', type: 'string'),
                    new OA\Property(property: 'security_improvement', type: 'string'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Review iniciado (status: Pending)'),
            new OA\Response(response: 409, description: 'Projeto ja possui review'),
        ]
    )]
    public function store(Request $request, Project $project)
    {
        $this->authorize('update', $project);

        if ($project->codeReview) {
            return response()->json(['message' => 'Projeto ja possui review.'], 409);
        }

        $validated = $request->validate([
            'architecture_strength' => 'required|string',
            'architecture_improvement' => 'required|string',
            'performance_strength' => 'required|string',
            'performance_improvement' => 'required|string',
            'security_strength' => 'required|string',
            'security_improvement' => 'required|string',
        ]);

        $codeReview = $project->codeReview()->create([
            'review_status_id' => 1, // Pending
        ]);

        // Criar 6 findings (3 pilares x 2 tipos)
        $findings = [
            ['pillar' => 1, 'type' => 1, 'desc' => $validated['architecture_strength']],
            ['pillar' => 1, 'type' => 2, 'desc' => $validated['architecture_improvement']],
            ['pillar' => 2, 'type' => 1, 'desc' => $validated['performance_strength']],
            ['pillar' => 2, 'type' => 2, 'desc' => $validated['performance_improvement']],
            ['pillar' => 3, 'type' => 1, 'desc' => $validated['security_strength']],
            ['pillar' => 3, 'type' => 2, 'desc' => $validated['security_improvement']],
        ];

        foreach ($findings as $finding) {
            $codeReview->findings()->create([
                'review_pillar_id' => $finding['pillar'],
                'finding_type_id' => $finding['type'],
                'description' => $finding['desc'],
            ]);
        }

        // Dispara Agent em background
        AnalyzeCodeJob::dispatch($codeReview);

        $request->user()->update(['first_review_at' => now()]);

        return response()->json(
            $codeReview->load(['status', 'findings.pillar', 'findings.type']),
            201
        );
    }

    #[OA\Get(
        path: '/api/reviews/{id}',
        summary: 'Status e resultado de um code review',
        description: 'Retorna o review com findings. Status: 1=Pending, 2=Completed, 3=Failed.',
        tags: ['Code Reviews'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Review com findings'),
        ]
    )]
    public function show(CodeReview $codeReview)
    {
        $this->authorize('view', $codeReview->project);

        return $codeReview->load([
            'status',
            'project',
            'findings.pillar',
            'findings.type',
        ]);
    }
}
