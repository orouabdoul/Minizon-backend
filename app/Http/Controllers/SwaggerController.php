<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: 'Minizon API',
    version: '1.0.0',
    description: 'API REST de la plateforme Minizon — Transport et livraison.',
    contact: new OA\Contact(name: 'Équipe Minizon', email: 'dev@minizon.com')
)]
#[OA\Server(url: 'http://localhost:8000', description: 'Serveur local')]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'Sanctum'
)]
#[OA\Schema(
    schema: 'ErrorResponse',
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'message', type: 'string',  example: 'Message d\'erreur.'),
        new OA\Property(property: 'body',    type: 'object',  nullable: true),
    ]
)]
class SwaggerController extends Controller
{
}