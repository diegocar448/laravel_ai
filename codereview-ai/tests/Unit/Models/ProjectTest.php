<?php

use App\Models\Project;

test('project has correct fillable attributes', function () {
    $project = new Project;

    expect($project->getFillable())->toContain(
        'user_id', 'project_status_id', 'name', 'language', 'code_snippet', 'repository_url'
    );
});
