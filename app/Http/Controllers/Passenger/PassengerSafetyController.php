<?php

namespace App\Http\Controllers\Passenger;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

/**
 * Page "Sécurité" (SafetyView) — passager.
 *
 * Données persistées en JSON sur Profile (mêmes colonnes que le driver) :
 *   – emergency_contacts  : déjà utilisé par DriverSafetyController
 *   – safety_meta         : { sos_active, sos_activated_at, trip_share_active, trip_share_code }
 *
 * Si les colonnes n'existent pas encore sur la table profiles, les endpoints
 * retournent des valeurs par défaut sans lever d'erreur (nullsafe + ?? []).
 *
 * SOS → crée un SupportTicket de priorité 'critical' et met sos_active=true.
 * Trip share → génère un code court 6 car. alphanumérique (pas de tracking
 * temps réel implémenté côté serveur — le code sert uniquement de lien public).
 */
class PassengerSafetyController extends Controller
{
    // =========================================================================
    //  GET /api/passenger/safety
    //  Données initiales de la page SafetyView
    // =========================================================================

    #[OA\Get(
        path: '/api/passenger/safety',
        operationId: 'passengerSafetyContext',
        summary: 'Contexte initial de la page Sécurité (passager)',
        description: "Retourne l'état SOS, l'état du partage de trajet (avec le code public) et la liste des contacts d'urgence du passager.",
        tags: ['🆘 Passenger — Sécurité'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Contexte sécurité',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Données de sécurité.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'sos_active', type: 'boolean', example: false),
                                new OA\Property(
                                    property: 'trip_share',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'active', type: 'boolean', example: false),
                                        new OA\Property(property: 'code',   type: 'string',  nullable: true, example: 'TMP4X2'),
                                    ]
                                ),
                                new OA\Property(
                                    property: 'emergency_contacts',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'id',       type: 'string', example: 'uuid-v4'),
                                            new OA\Property(property: 'name',     type: 'string', example: 'Jean Dupont'),
                                            new OA\Property(property: 'phone',    type: 'string', example: '+229 97 00 00 00'),
                                            new OA\Property(property: 'relation', type: 'string', example: 'Frère'),
                                            new OA\Property(property: 'initials', type: 'string', example: 'JD'),
                                        ]
                                    )
                                ),
                            ]
                        ),
                    ]
                )
            ),
        ]
    )]
    public function context(Request $request): JsonResponse
    {
        $profile = $request->user()->profile;
        $meta    = $this->getMeta($profile);
        $rawContacts = $profile?->emergency_contacts ?? [];

        return $this->apiResponse(true, 'Données de sécurité.', [
            'sos_active'         => (bool) ($meta['sos_active'] ?? false),
            'trip_share'         => [
                'active' => (bool) ($meta['trip_share_active'] ?? false),
                'code'   => $meta['trip_share_code'] ?? null,
            ],
            'emergency_contacts' => $this->formatContacts((array) $rawContacts),
        ]);
    }

    // =========================================================================
    //  POST /api/passenger/safety/sos
    //  Activer l'alerte SOS
    // =========================================================================

    #[OA\Post(
        path: '/api/passenger/safety/sos',
        operationId: 'passengerSafetySOSOA',
        summary: 'Activer l\'alerte SOS (passager)',
        description: "Crée un ticket de support de priorité critique et marque `sos_active=true` sur le profil du passager. Les contacts d'urgence enregistrés sont inclus dans la description du ticket pour que l'équipe puisse les alerter.",
        tags: ['🆘 Passenger — Sécurité'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Alerte SOS activée'),
        ]
    )]
    public function activateSOS(Request $request): JsonResponse
    {
        $user    = $request->user();
        $profile = $user->profile;

        // Contacts d'urgence à inclure dans le ticket
        $contacts = $profile?->emergency_contacts ?? [];
        $contactList = collect($contacts)
            ->map(fn ($c) => "  • {$c['name']} ({$c['phone']})")
            ->join("\n");

        SupportTicket::create([
            'user_id'     => $user->id,
            'subject'     => 'SOS — Alerte urgence passager',
            'description' => "Alerte SOS déclenchée depuis l'application.\n\nContacts d'urgence :\n" . ($contactList ?: '(aucun contact enregistré)'),
            'priority'    => 'critical',
            'channel'     => 'app',
            'status'      => 'new',
        ]);

        // Persister l'état SOS
        $this->saveMeta($profile, [
            'sos_active'       => true,
            'sos_activated_at' => now()->toIso8601String(),
        ]);

        return $this->apiResponse(true, 'Alerte SOS envoyée. Une équipe vous contacte sous 5 min.');
    }

    // =========================================================================
    //  DELETE /api/passenger/safety/sos
    //  Annuler l'alerte SOS
    // =========================================================================

    #[OA\Delete(
        path: '/api/passenger/safety/sos',
        operationId: 'passengerSafetyCancelSOS',
        summary: 'Annuler l\'alerte SOS',
        tags: ['🆘 Passenger — Sécurité'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Alerte SOS annulée'),
        ]
    )]
    public function cancelSOS(Request $request): JsonResponse
    {
        $profile = $request->user()->profile;

        $this->saveMeta($profile, [
            'sos_active'       => false,
            'sos_activated_at' => null,
        ]);

        return $this->apiResponse(true, 'Alerte SOS annulée.');
    }

    // =========================================================================
    //  POST /api/passenger/safety/trip-share
    //  Démarrer le partage de trajet
    // =========================================================================

    #[OA\Post(
        path: '/api/passenger/safety/trip-share',
        operationId: 'passengerStartTripShare',
        summary: 'Démarrer le partage de trajet',
        description: "Génère un code court alphanumérique (6 caractères) et active le partage. Le lien public est `minizon.bj/track/{code}`.",
        tags: ['🆘 Passenger — Sécurité'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Partage activé',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Partage de trajet activé.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'code', type: 'string', example: 'TMP4X2'),
                            ]
                        ),
                    ]
                )
            ),
        ]
    )]
    public function startShare(Request $request): JsonResponse
    {
        $profile = $request->user()->profile;

        // Réutiliser un code existant ou en générer un nouveau
        $meta    = $this->getMeta($profile);
        $code    = ($meta['trip_share_active'] ?? false) && ! empty($meta['trip_share_code'])
            ? $meta['trip_share_code']
            : strtoupper(Str::random(6));

        $this->saveMeta($profile, [
            'trip_share_active' => true,
            'trip_share_code'   => $code,
        ]);

        return $this->apiResponse(true, 'Partage de trajet activé.', ['code' => $code]);
    }

    // =========================================================================
    //  DELETE /api/passenger/safety/trip-share
    //  Arrêter le partage de trajet
    // =========================================================================

    #[OA\Delete(
        path: '/api/passenger/safety/trip-share',
        operationId: 'passengerStopTripShare',
        summary: 'Arrêter le partage de trajet',
        tags: ['🆘 Passenger — Sécurité'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Partage arrêté'),
        ]
    )]
    public function stopShare(Request $request): JsonResponse
    {
        $profile = $request->user()->profile;

        $this->saveMeta($profile, [
            'trip_share_active' => false,
            'trip_share_code'   => null,
        ]);

        return $this->apiResponse(true, 'Partage de trajet arrêté.');
    }

    // =========================================================================
    //  GET /api/passenger/safety/emergency-contacts
    // =========================================================================

    #[OA\Get(
        path: '/api/passenger/safety/emergency-contacts',
        operationId: 'passengerEmergencyContacts',
        summary: 'Lister les contacts d\'urgence du passager',
        tags: ['🆘 Passenger — Sécurité'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Contacts d\'urgence'),
        ]
    )]
    public function emergencyContacts(Request $request): JsonResponse
    {
        $profile  = $request->user()->profile;
        $contacts = $profile?->emergency_contacts ?? [];

        return $this->apiResponse(true, 'Contacts d\'urgence.', [
            'contacts' => $this->formatContacts((array) $contacts),
        ]);
    }

    // =========================================================================
    //  POST /api/passenger/safety/emergency-contacts
    // =========================================================================

    #[OA\Post(
        path: '/api/passenger/safety/emergency-contacts',
        operationId: 'passengerAddEmergencyContact',
        summary: 'Ajouter un contact d\'urgence',
        tags: ['🆘 Passenger — Sécurité'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'phone'],
                properties: [
                    new OA\Property(property: 'name',     type: 'string', example: 'Jean Dupont'),
                    new OA\Property(property: 'phone',    type: 'string', example: '+229 97 00 00 00'),
                    new OA\Property(property: 'relation', type: 'string', nullable: true, example: 'Frère'),
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
        $profile = $request->user()->profile;

        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:100'],
            'phone'    => ['required', 'string', 'max:20'],
            'relation' => ['nullable', 'string', 'max:50'],
        ]);

        $contacts   = (array) ($profile?->emergency_contacts ?? []);
        $contacts[] = [
            'id'       => (string) Str::uuid(),
            'name'     => $validated['name'],
            'phone'    => $validated['phone'],
            'relation' => $validated['relation'] ?? 'Proche',
        ];

        if ($profile) {
            $profile->update(['emergency_contacts' => $contacts]);
        }

        return $this->apiResponse(true, 'Contact d\'urgence ajouté.', [
            'contacts' => $this->formatContacts($contacts),
        ]);
    }

    // =========================================================================
    //  DELETE /api/passenger/safety/emergency-contacts/{id}
    // =========================================================================

    #[OA\Delete(
        path: '/api/passenger/safety/emergency-contacts/{id}',
        operationId: 'passengerRemoveEmergencyContact',
        summary: 'Supprimer un contact d\'urgence',
        tags: ['🆘 Passenger — Sécurité'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Contact supprimé'),
        ]
    )]
    public function removeEmergencyContact(Request $request, string $id): JsonResponse
    {
        $profile  = $request->user()->profile;
        $contacts = collect($profile?->emergency_contacts ?? [])
            ->filter(fn ($c) => ($c['id'] ?? '') !== $id)
            ->values()
            ->all();

        if ($profile) {
            $profile->update(['emergency_contacts' => $contacts]);
        }

        return $this->apiResponse(true, 'Contact supprimé.', [
            'contacts' => $this->formatContacts($contacts),
        ]);
    }

    // =========================================================================
    //  HELPERS PRIVÉS
    // =========================================================================

    private function getMeta(?\App\Models\Profile $profile): array
    {
        $raw = $profile?->safety_meta ?? [];
        if (is_string($raw)) {
            $raw = json_decode($raw, true) ?: [];
        }
        return (array) $raw;
    }

    private function saveMeta(?\App\Models\Profile $profile, array $patch): void
    {
        if (! $profile) return;

        $current = $this->getMeta($profile);
        $profile->update(['safety_meta' => array_merge($current, $patch)]);
    }

    private function formatContacts(array $contacts): array
    {
        return array_values(array_map(function (array $c) {
            $name     = $c['name'] ?? '';
            $parts    = array_filter(explode(' ', $name));
            $initials = implode('', array_map(
                fn ($w) => strtoupper($w[0]),
                array_slice($parts, 0, 2)
            ));

            return [
                'id'       => $c['id']       ?? '',
                'name'     => $name,
                'phone'    => $c['phone']    ?? '',
                'relation' => $c['relation'] ?? 'Proche',
                'initials' => $initials ?: 'C',
            ];
        }, $contacts));
    }
}
