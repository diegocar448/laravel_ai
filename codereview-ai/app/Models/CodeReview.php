<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CodeReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'review_status_id',
        'summary',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(ReviewStatus::class, 'review_status_id');
    }

    public function findings(): HasMany
    {
        return $this->hasMany(ReviewFinding::class);
    }
}
