<?php

namespace App\Http\Controllers\Api;

use App\Enums\ProjectStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        return $request->user()
            ->projects()
            ->with(['status', 'codeReview.status'])
            ->latest()
            ->paginate(20);
    }

    public function store(Request $request): JsonResponse
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

    public function show(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        return response()->json($project->load([
            'status',
            'codeReview.status',
            'codeReview.findings.pillar',
            'codeReview.findings.type',
            'improvements.type',
            'improvements.step',
        ]));
    }

    public function destroy(Project $project): JsonResponse
    {
        $this->authorize('delete', $project);
        $project->delete();

        return response()->json(null, 204);
    }
}
