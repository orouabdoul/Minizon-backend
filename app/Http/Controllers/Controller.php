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
#[OA\Server(url: 'https://minizon-api.onrender.com', description: 'Production')]
#[OA\Server(url: 'http://localhost:8000', description: 'Local')]
abstract class Controller
{
    protected function apiResponse(bool $success, string $message, mixed $body = [], int $status = 200): \Illuminate\Http\JsonResponse
    {
        return response()->json(['success' => $success, 'message' => $message, 'body' => $body], $status);
    }
}
