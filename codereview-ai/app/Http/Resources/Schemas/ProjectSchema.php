<?php

namespace App\Http\Resources\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Project',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'language', type: 'string'),
        new OA\Property(property: 'code_snippet', type: 'string'),
        new OA\Property(property: 'repository_url', type: 'string', nullable: true),
        new OA\Property(property: 'status', type: 'object', properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'name', type: 'string'),
        ]),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ]
)]
class ProjectSchema {}
