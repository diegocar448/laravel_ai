<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewFinding extends Model
{
    use HasFactory;

    protected $fillable = [
        'code_review_id',
        'finding_type_id',
        'review_pillar_id',
        'description',
        'severity',
        'agent_flagged_at',
        'user_flagged_at',
    ];

    protected function casts(): array
    {
        return [
            'agent_flagged_at' => 'datetime',
            'user_flagged_at' => 'datetime',
        ];
    }

    public function codeReview(): BelongsTo
    {
        return $this->belongsTo(CodeReview::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(FindingType::class, 'finding_type_id');
    }

    public function pillar(): BelongsTo
    {
        return $this->belongsTo(ReviewPillar::class, 'review_pillar_id');
    }
}
