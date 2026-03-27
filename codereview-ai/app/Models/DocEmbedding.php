<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Pgvector\Laravel\HasNeighbors;
use Pgvector\Laravel\Vector;

class DocEmbedding extends Model
{
    use HasFactory, HasNeighbors;

    protected $fillable = [
        'source',
        'title',
        'content',
        'embedding',
        'category',
    ];

    protected $casts = [
        'embedding' => Vector::class,
    ];
}
