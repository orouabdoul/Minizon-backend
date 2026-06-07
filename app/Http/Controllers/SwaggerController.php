<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

/**
 * Fichier de configuration globale Swagger UI — Minizon API.
 *
 * Ce fichier ne contient aucune logique métier.
 * Il déclare uniquement les métadonnées OpenAPI 3.0 :
 *  - @OA\Info       : titre, version, description
 *  - @OA\Server     : URL de base
 *  - @OA\SecurityScheme : schéma Bearer (Sanctum)
 *  - @OA\Schema     : modèles réutilisables (ErrorResponse, etc.)
 */

#[OA\Info(
    title: 'Minizon API',
    version: '1.0.0',
    description: "API REST de la plateforme **Minizon** — Application de transport et livraison.\n\n
### Authentification
Toutes les routes protégées nécessitent un token **Bearer (Sanctum)**.
Obtenez-le via `POST /api/auth/verify-otp` ou `POST /api/auth/admin/login`.

### Codes de réponse standards
| Code | Signification |
|------|---------------|
| 200  | Succès |
| 201  | Ressource créée |
| 401  | Non authentifié |
| 403  | Accès interdit |
| 404  | Ressource introuvable |
| 422  | Erreur de validation |
| 500  | Erreur serveur |",
    contact: new OA\Contact(
        name: 'Équipe Minizon',
        email: 'dev@minizon.com'
    ),
    license: new OA\License(
        name: 'Propriétaire — Minizon SAS',
        url: 'https://minizon.com'
    )
)]
#[OA\Server(
    url: 'http://localhost:8000',
    description: '🖥️ Serveur de développement local'
)]
#[OA\Server(
    url: 'https://api.minizon.com',
    description: '🌐 Serveur de production'
)]

// — Schéma de sécurité Bearer Token (Laravel Sanctum)
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'Sanctum',
    description: 'Token Sanctum — format : `Bearer {token}`'
)]

// — Schéma réutilisable : réponse d'erreur standard
#[OA\Schema(
    schema: 'ErrorResponse',
    title: 'Réponse d\'erreur',
    description: 'Structure standard retournée pour toutes les erreurs API.',
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'message', type: 'string',  example: 'Message d\'erreur explicite.'),
        new OA\Property(
            property: 'body',
            type: 'object',
            description: 'Détails additionnels (erreurs de validation, etc.)',
            nullable: true
        ),
    ]
)]

// — Schéma réutilisable : profil utilisateur complet
#[OA\Schema(
    schema: 'UserResource',
    title: 'Utilisateur complet',
    description: 'Représentation complète d\'un utilisateur avec profil, rôle et véhicule.',
    properties: [
        new OA\Property(property: 'id',             type: 'integer', example: 1),
        new OA\Property(property: 'uuid',           type: 'string',  format: 'uuid', example: '8f3b6c7a-9c2d-4e5f-a1b2-c3d4e5f6a7b8'),
        new OA\Property(property: 'phone',          type: 'string',  example: '+2290161165619'),
        new OA\Property(property: 'is_verified',    type: 'boolean', example: false),
        new OA\Property(property: 'is_blocked',     type: 'boolean', example: false),
        new OA\Property(property: 'penalty_points', type: 'integer', example: 0),
        new OA\Property(property: 'role',           type: 'string',  example: 'passenger', enum: ['passenger', 'driver', 'admin']),
        new OA\Property(property: 'profile',        type: 'object',  nullable: true),
        new OA\Property(property: 'vehicle',        type: 'object',  nullable: true),
    ]
)]
class SwaggerController extends Controller
{
    // Ce contrôleur est intentionnellement vide.
    // Il sert uniquement de support aux annotations OpenAPI globales.
}