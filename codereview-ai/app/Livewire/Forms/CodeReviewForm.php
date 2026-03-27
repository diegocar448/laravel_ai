<?php

namespace App\Livewire\Forms;

use Livewire\Form;
use App\Models\Project;
use App\Models\CodeReview;
use App\Enums\ReviewStatusEnum;

class CodeReviewForm extends Form
{
    public function store(Project $project): CodeReview
    {
        return $project->codeReview()->create([
            'review_status_id' => ReviewStatusEnum::Pending->value,
            'summary' => null,
        ]);
    }
}
