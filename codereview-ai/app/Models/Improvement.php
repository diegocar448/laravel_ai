<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Improvement extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'improvement_type_id',
        'improvement_step_id',
        'title',
        'description',
        'file_path',
        'priority',
        'order',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(ImprovementType::class, 'improvement_type_id');
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(ImprovementStep::class, 'improvement_step_id');
    }
}
