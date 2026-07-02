<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Page "Sécurité" — SOS, contacts d'urgence, signalement d'incident.
 *
 * Les contacts d'urgence nécessitent une migration dédiée (JSON sur Profile
 * ou table emergency_contacts) — les endpoints retournent des stubs pour l'instant.
 */
class DriverSafetyController extends Controller
{
    // =========================================================================
    //  POST /api/driver/safety/sos
    // =========================================================================

    #[OA\Post(
        path: '/api/driver/safety/sos',
        operationId: 'driverSafetySOSOA',
        summary: 'Envoyer une alerte SOS',
        description: 'Crée un ticket de support prioritaire et notifie les équipes MINIZON.',
        tags: ['🆘 Driver — Sécurité'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Alerte envoyée'),
        ]
    )]
    public function sos(Request $request): JsonResponse
    {
        $user = $request->user();

        SupportTicket::create([
            'user_id'     => $user->id,
            'subject'     => 'SOS — Alerte urgence conducteur',
            'description' => 'Alerte SOS déclenchée depuis l\'application.',
            'priority'    => 'high',
            'channel'     => 'app',
            'status'      => 'new',
        ]);

        return $this->apiResponse(true, 'Alerte SOS envoyée. Une équipe est en route.');
    }

    // =========================================================================
    //  POST /api/driver/safety/incidents
    // =========================================================================

    #[OA\Post(
        path: '/api/driver/safety/incidents',
        operationId: 'driverSafetyIncident',
        summary: 'Signaler un incident',
        tags: ['🆘 Driver — Sécurité'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['category'],
                properties: [
                    new OA\Property(property: 'category',    type: 'string', example: 'Passager agressif'),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Incident signalé'),
            new OA\Response(response: 422, description: 'Validation'),
        ]
    )]
    public function reportIncident(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'category'    => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        SupportTicket::create([
            'user_id'     => $user->id,
            'subject'     => "Incident — {$validated['category']}",
            'description' => $validated['description'] ?? 'Aucune description fournie.',
            'priority'    => 'high',
            'channel'     => 'app',
            'status'      => 'new',
        ]);

        return $this->apiResponse(true, 'Incident signalé. Notre équipe vous contacte sous 30 min.');
    }

    // =========================================================================
    //  GET /api/driver/safety/emergency-contacts
    // =========================================================================

    #[OA\Get(
        path: '/api/driver/safety/emergency-contacts',
        operationId: 'driverEmergencyContacts',
        summary: 'Lister les contacts d\'urgence du conducteur',
        tags: ['🆘 Driver — Sécurité'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Contacts d\'urgence'),
        ]
    )]
    public function emergencyContacts(Request $request): JsonResponse
    {
        $user    = $request->user();
        $profile = $user->profile;

        // emergency_contacts est un champ JSON sur Profile (migration requise)
        $contacts = $profile?->emergency_contacts ?? [];

        return $this->apiResponse(true, 'Contacts d\'urgence.', [
            'contacts' => $contacts,
        ]);
    }

    // =========================================================================
    //  POST /api/driver/safety/emergency-contacts
    // =========================================================================

    #[OA\Post(
        path: '/api/driver/safety/emergency-contacts',
        operationId: 'driverAddEmergencyContact',
        summary: 'Ajouter un contact d\'urgence',
        tags: ['🆘 Driver — Sécurité'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'phone'],
                properties: [
                    new OA\Property(property: 'name',     type: 'string', example: 'Jean Dupont'),
                    new OA\Property(property: 'phone',    type: 'string', example: '+229 97 00 00 00'),
                    new OA\Property(property: 'relation', type: 'string', example: 'Frère'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Contact ajouté'),
            new OA\Response(response: 422, description: 'Validation'),
        ]
    )]
    public function addEmergencyContact(Request $request): JsonResponse
    {
        $user    = $request->user();
        $profile = $user->profile;

        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:100'],
            'phone'    => ['required', 'string', 'max:20'],
            'relation' => ['nullable', 'string', 'max:50'],
        ]);

        $contacts   = $profile?->emergency_contacts ?? [];
        $contacts[] = [
            'id'       => (string) \Illuminate\Support\Str::uuid(),
            'name'     => $validated['name'],
            'phone'    => $validated['phone'],
            'relation' => $validated['relation'] ?? 'Proche',
        ];

        if ($profile) {
            $profile->update(['emergency_contacts' => $contacts]);
        }

        return $this->apiResponse(true, 'Contact d\'urgence ajouté.', [
            'contacts' => $contacts,
        ]);
    }

    // =========================================================================
    //  DELETE /api/driver/safety/emergency-contacts/{id}
    // =========================================================================

    #[OA\Delete(
        path: '/api/driver/safety/emergency-contacts/{id}',
        operationId: 'driverRemoveEmergencyContact',
        summary: 'Supprimer un contact d\'urgence',
        tags: ['🆘 Driver — Sécurité'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true,
                schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Contact supprimé'),
        ]
    )]
    public function removeEmergencyContact(Request $request, string $id): JsonResponse
    {
        $user    = $request->user();
        $profile = $user->profile;

        $contacts = collect($profile?->emergency_contacts ?? [])
            ->filter(fn ($c) => ($c['id'] ?? '') !== $id)
            ->values()
            ->all();

        if ($profile) {
            $profile->update(['emergency_contacts' => $contacts]);
        }

        return $this->apiResponse(true, 'Contact supprimé.', [
            'contacts' => $contacts,
        ]);
    }
}
