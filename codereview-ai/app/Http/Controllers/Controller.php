<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: 'CodeReview AI API',
    version: '1.0.0',
    description: 'API REST para code review com IA — Laravel AI SDK + Gemini',
    contact: new OA\Contact(name: 'CodeReview AI', email: 'api@codereview.ai'),
)]
#[OA\Server(url: 'http://localhost', description: 'Local')]
#[OA\SecurityScheme(
    securityScheme: 'sanctum',
    type: 'http',
    scheme: 'bearer',
    description: 'Bearer token via POST /api/auth/token'
)]
abstract class Controller {}
