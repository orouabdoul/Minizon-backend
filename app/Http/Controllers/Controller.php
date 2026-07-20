<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Minizon API',
    description: 'API backend pour l\'application de covoiturage Minizon — Bénin.',
    contact: new OA\Contact(name: 'Minizon', email: 'contact@minizon.com'),
)]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
)]
#[OA\Server(url: 'https://minizon-api-iubm.onrender.com', description: 'Production')]
#[OA\Server(url: 'http://localhost/project-minizon-backend/public', description: 'Local (XAMPP)')]
#[OA\Server(url: 'http://localhost:8000', description: 'Local (artisan serve)')]
#[OA\Schema(
    schema: 'ErrorResponse',
    description: 'Réponse d\'erreur standard',
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'message', type: 'string',  example: 'Une erreur est survenue.'),
        new OA\Property(property: 'body',    type: 'object'),
    ]
)]
abstract class Controller
{
    protected function apiResponse(bool $success, string $message, mixed $body = [], int $status = 200): \Illuminate\Http\JsonResponse
    {
        return response()->json(['success' => $success, 'message' => $message, 'body' => $body], $status);
    }
}
