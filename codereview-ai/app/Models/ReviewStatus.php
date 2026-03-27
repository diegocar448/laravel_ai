<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReviewStatus extends Model
{
    protected $fillable = ['name'];

    public function codeReviews(): HasMany
    {
        return $this->hasMany(CodeReview::class);
    }
}
