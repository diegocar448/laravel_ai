<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FindingType extends Model
{
    protected $fillable = ['name'];

    public function findings(): HasMany
    {
        return $this->hasMany(ReviewFinding::class);
    }
}
