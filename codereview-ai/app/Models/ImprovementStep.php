<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImprovementStep extends Model
{
    protected $fillable = ['name'];

    public function improvements(): HasMany
    {
        return $this->hasMany(Improvement::class);
    }
}
