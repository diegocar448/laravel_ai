<?php

return [
    'defaults' => [
        'routes' => [
            'api' => 'api/documentation',
        ],
        'info' => [
            'title' => 'CodeReview AI API',
            'version' => '1.0.0',
            'description' => 'API REST para code review com IA usando Laravel AI SDK',
        ],
        'securityDefinitions' => [
            'securitySchemes' => [
                'sanctum' => [
                    'type' => 'http',
                    'scheme' => 'bearer',
                    'description' => 'Token gerado via POST /api/auth/token',
                ],
            ],
        ],
    ],
];
