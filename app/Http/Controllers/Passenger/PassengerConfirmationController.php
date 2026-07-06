<?php

namespace App\Http\Controllers\Passenger;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Page "Confirmation de réservation" (ConfirmationReservationView).
 *
 * Fournit le contexte nécessaire avant que le passager confirme :
 *   – Méthodes de paiement Mobile Money disponibles (FedaPay : mtn/moov/celtiis)
 *   – Taux de commission (pour recalculer le total côté Flutter)
 *   – Infos trajet fraîches (places disponibles, prix)
 *   – Numéro de téléphone pré-rempli pour la saisie de paiement
 *
 * Flow côté Flutter après appel de cet endpoint :
 *   1. POST /api/trips/{uuid}/bookings          → crée la réservation (seats_booked)
 *   2. POST /api/bookings/{uuid}/pay            → escrow FedaPay (provider + phone_number)
 *   3. Navigue vers WaitingApprovalView         → polling du statut conducteur
 *
 * Nota : les champs pickup_note / dropoff_note saisis par l'utilisateur
 * dans _StopsCard ne sont pas encore persistés (migration Booking requise).
 * Le Flutter peut les transmettre dans l'étape 1 — le BookingController les
 * ignorera silencieusement jusqu'à l'ajout de la migration.
 */
class PassengerConfirmationController extends Controller
{
    private const COMMISSION_RATE = 10;

    private const PAYMENT_METHODS = [
        [
            'provider'    => 'mtn',
            'title'       => 'MTN Mobile Money',
            'description' => 'Disponible 24h/24 — réseau MTN Bénin',
            'icon'        => 'phone_android',
            'color'       => 0xFFFFCC00,
        ],
        [
            'provider'    => 'moov',
            'title'       => 'Moov Money',
            'description' => 'Paiement rapide — réseau Moov Africa',
            'icon'        => 'phone_android',
            'color'       => 0xFF0066CC,
        ],
        [
            'provider'    => 'celtiis',
            'title'       => 'Celtiis Money',
            'description' => 'Mobile Money — réseau Celtiis Bénin',
            'icon'        => 'phone_android',
            'color'       => 0xFFCC0000,
        ],
    ];

    // =========================================================================
    //  GET /api/passenger/trips/{uuid}/confirmation-context
    //  Contexte de la page de confirmation
    // =========================================================================

    #[OA\Get(
        path: '/api/passenger/trips/{uuid}/confirmation-context',
        operationId: 'passengerConfirmationContext',
        summary: 'Contexte de confirmation de réservation',
        description: "Données initiales de `ConfirmationReservationView` : méthodes de paiement Mobile Money disponibles, taux de commission, infos fraîches du trajet et numéro de téléphone pré-rempli du passager.\n\n**Flow de confirmation :**\n1. `POST /api/trips/{uuid}/bookings` — crée la réservation\n2. `POST /api/bookings/{uuid}/pay` — initie l'escrow FedaPay\n3. Navigation vers `WaitingApprovalView`",
        tags: ['👤 Passenger — Réservations'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Contexte de confirmation',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Contexte de confirmation.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'trip',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'uuid',            type: 'string', format: 'uuid'),
                                        new OA\Property(property: 'available_seats', type: 'integer', example: 3),
                                        new OA\Property(property: 'max_per_booking', type: 'integer', example: 2),
                                        new OA\Property(property: 'price_per_seat',  type: 'integer', example: 1500),
                                        new OA\Property(property: 'booking_mode',    type: 'string',  enum: ['instant', 'approval'], example: 'approval'),
                                        new OA\Property(property: 'distance_km',     type: 'string',  nullable: true, example: '42 km'),
                                    ]
                                ),
                                new OA\Property(property: 'commission_rate',  type: 'integer', example: 10, description: 'Pourcentage de frais de service (ex: 10 = 10%).'),
                                new OA\Property(property: 'user_phone',       type: 'string',  nullable: true, example: '0159000892', description: 'Numéro local pré-rempli pour le champ de paiement.'),
                                new OA\Property(
                                    property: 'payment_methods',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'provider',    type: 'string', enum: ['mtn', 'moov', 'celtiis'], example: 'mtn'),
                                            new OA\Property(property: 'title',       type: 'string', example: 'MTN Mobile Money'),
                                            new OA\Property(property: 'description', type: 'string', example: 'Disponible 24h/24 — réseau MTN Bénin'),
                                            new OA\Property(property: 'icon',        type: 'string', example: 'phone_android'),
                                            new OA\Property(property: 'color',       type: 'integer', format: 'int64', example: 0xFFFFCC00, description: 'ARGB pour Flutter Color(int).'),
                                        ]
                                    )
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Trajet introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function context(Request $request, string $uuid): JsonResponse
    {
        $trip = Trip::where('uuid', $uuid)->first();

        if (! $trip) {
            return $this->apiResponse(false, 'Trajet introuvable.', [], 404);
        }

        $user = $request->user();

        // Numéro de téléphone local (suppression indicatif pays béninois +229)
        $rawPhone   = $user->phone ?? '';
        $localPhone = preg_replace('/^\+?229/', '', $rawPhone);

        // Distance estimée depuis la durée (approximation si pas de champ dédié)
        $distanceKm = null;
        if ($trip->estimated_duration_minutes) {
            $estimatedKm = round($trip->estimated_duration_minutes / 60 * 50);
            $distanceKm  = $estimatedKm . ' km';
        }

        return $this->apiResponse(true, 'Contexte de confirmation.', [
            'trip' => [
                'uuid'            => $trip->uuid,
                'available_seats' => (int) $trip->available_seats,
                'max_per_booking' => (int) ($trip->max_per_booking ?? $trip->available_seats),
                'price_per_seat'  => (int) $trip->price_per_seat,
                'booking_mode'    => $trip->booking_mode ?? 'approval',
                'distance_km'     => $distanceKm,
            ],
            'commission_rate' => self::COMMISSION_RATE,
            'user_phone'      => $localPhone ?: null,
            'payment_methods' => self::PAYMENT_METHODS,
        ]);
    }
}
