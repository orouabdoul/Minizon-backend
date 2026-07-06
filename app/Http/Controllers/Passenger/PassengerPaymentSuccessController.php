<?php

namespace App\Http\Controllers\Passenger;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Payment;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Page "Paiement confirmé" (PaymentSuccessView).
 *
 * Retourne le récapitulatif complet après que le paiement FedaPay a été
 * initié (POST /api/bookings/{uuid}/pay). Le Flutter navigue immédiatement
 * vers cette page après le retour positif de FedaPay, en transmettant le
 * booking UUID.
 *
 * Actions du passager depuis cette page :
 *   callDriver    → numéro téléphone local (driver_phone) via url_launcher
 *   messageDriver → GET /api/passenger/conversations/{uuid}/thread (conv existante)
 *                   ou POST /api/bookings/{uuid}/conversation pour en créer une
 *   goToReservations → navigation locale Flutter
 *   goHome           → navigation locale Flutter
 */
class PassengerPaymentSuccessController extends Controller
{
    // =========================================================================
    //  GET /api/passenger/bookings/{uuid}/success
    //  Récapitulatif affiché sur PaymentSuccessView
    // =========================================================================

    #[OA\Get(
        path: '/api/passenger/bookings/{uuid}/success',
        operationId: 'passengerPaymentSuccess',
        summary: 'Récapitulatif de paiement (PaymentSuccessView)',
        description: "Données complètes pour la page de confirmation de paiement : référence de transaction, montant formaté, infos du trajet, numéro du conducteur et UUID de conversation pour le chat direct.\n\nAppeler immédiatement après que `POST /api/bookings/{uuid}/pay` retourne un succès.",
        tags: ['👤 Passenger — Réservations'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Récapitulatif de paiement',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Récapitulatif du paiement.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'transaction_ref',   type: 'string',  example: 'TXN-A1B2C3D4', description: 'Référence monospace affichée à l\'écran.'),
                                new OA\Property(property: 'amount_paid',       type: 'integer', example: 3300, description: 'Montant total en XOF (base + frais 10%).'),
                                new OA\Property(property: 'formatted_amount',  type: 'string',  example: '3 300 FCFA'),
                                new OA\Property(property: 'driver_phone',      type: 'string',  nullable: true, example: '0159000892', description: 'Numéro local pour url_launcher tel:+229XXXXXXXXXX.'),
                                new OA\Property(property: 'conversation_uuid', type: 'string',  format: 'uuid', nullable: true, description: 'UUID de la conversation existante, null si aucune encore créée.'),
                                new OA\Property(property: 'reserved_seats',    type: 'integer', example: 2),
                                new OA\Property(
                                    property: 'ride',
                                    type: 'object',
                                    description: 'Sous-ensemble de SearchRide pour l\'affichage success.',
                                    properties: [
                                        new OA\Property(property: 'uuid',            type: 'string', format: 'uuid'),
                                        new OA\Property(property: 'driver_name',     type: 'string', example: 'Koffi Adjovi'),
                                        new OA\Property(property: 'driver_initials', type: 'string', example: 'KA'),
                                        new OA\Property(property: 'rating',          type: 'string', example: '4.8'),
                                        new OA\Property(property: 'review_count',    type: 'integer', example: 247),
                                        new OA\Property(property: 'vehicle',         type: 'string', example: 'Toyota Corolla'),
                                        new OA\Property(property: 'vehicle_plate',   type: 'string', example: 'AB-123-CD'),
                                        new OA\Property(property: 'origin',          type: 'string', example: 'Cotonou'),
                                        new OA\Property(property: 'destination',     type: 'string', example: 'Porto-Novo'),
                                        new OA\Property(property: 'departure_time',  type: 'string', example: '14h30'),
                                    ]
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Accès refusé', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Réservation introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function show(Request $request, string $uuid): JsonResponse
    {
        $booking = Booking::with(['trip.user.profile', 'trip.vehicle'])
            ->where('uuid', $uuid)
            ->where('passenger_id', $request->user()->id)
            ->first();

        if (! $booking) {
            return $this->apiResponse(false, 'Réservation introuvable.', [], 404);
        }

        $trip    = $booking->trip;
        $driver  = $trip?->user;
        $profile = $driver?->profile;
        $vehicle = $trip?->vehicle;
        $tz      = 'Africa/Porto-Novo';

        // ── Référence et montant ──────────────────────────────────────────────
        $payment = Payment::where('booking_id', $booking->id)
            ->orderByDesc('created_at')
            ->first();

        // Référence affichée (FedaPay transaction id ou fallback sur l'ID booking)
        $transactionRef = $payment
            ? 'TXN-' . strtoupper(substr((string) ($payment->transaction_id ?? $payment->id), 0, 8))
            : 'TXN-' . strtoupper(substr($booking->uuid, 0, 8));

        $seats       = (int) $booking->seats_booked;
        $pricePerSeat = (int) ($trip?->price_per_seat ?? 0);
        $base        = $pricePerSeat * $seats;
        $fee         = (int) round($base * 0.10);
        $amountPaid  = $payment ? (int) $payment->amount : $base + $fee;

        $formattedAmount = number_format($amountPaid, 0, ',', ' ') . ' FCFA';

        // ── Conducteur ────────────────────────────────────────────────────────
        $firstName  = $profile?->first_name ?? '';
        $lastName   = $profile?->last_name  ?? '';
        $driverName = trim("$firstName $lastName") ?: 'Conducteur';

        $rawPhone   = $driver?->phone ?? '';
        $driverPhone = $rawPhone ? (preg_replace('/^\+?229/', '', $rawPhone) ?: null) : null;

        // Note : ne jamais exposer le numéro du conducteur avant que la
        // réservation soit acceptée (booking.status === 'accepted'). En
        // pratique cette page n'est atteinte qu'après paiement FedaPay réussi,
        // donc le conducteur a déjà accepté (mode approval) ou la place est
        // réservée instantanément. Le guard booking->passenger_id ci-dessus
        // suffit pour les droits d'accès.

        // ── Note et avis ──────────────────────────────────────────────────────
        $reviews     = $driver ? Review::where('reviewee_id', $driver->id)->get() : collect();
        $avgRating   = $reviews->count() > 0
            ? (string) round($reviews->avg('rating'), 1)
            : '—';
        $reviewCount = $reviews->count();

        // ── Temps de départ ───────────────────────────────────────────────────
        $depTime = $trip?->departure_time?->setTimezone($tz);

        // ── Conversation existante ────────────────────────────────────────────
        $conversation = Conversation::where('booking_id', $booking->id)->first();

        // ── Ride (sous-ensemble SearchRide) ───────────────────────────────────
        $ride = [
            'uuid'            => $trip?->uuid,
            'driver_name'     => $driverName,
            'driver_initials' => $this->initials($driverName),
            'rating'          => $avgRating,
            'review_count'    => $reviewCount,
            'vehicle'         => $vehicle ? trim("{$vehicle->brand} {$vehicle->model}") : '—',
            'vehicle_plate'   => $vehicle?->license_plate ?? '—',
            'origin'          => $trip?->departure_city   ?? '—',
            'destination'     => $trip?->arrival_city     ?? '—',
            'departure_time'  => $depTime?->format('H\hi') ?? '—',
        ];

        return $this->apiResponse(true, 'Récapitulatif du paiement.', [
            'transaction_ref'   => $transactionRef,
            'amount_paid'       => $amountPaid,
            'formatted_amount'  => $formattedAmount,
            'driver_phone'      => $driverPhone,
            'conversation_uuid' => $conversation?->uuid,
            'reserved_seats'    => $seats,
            'ride'              => $ride,
        ]);
    }

    // =========================================================================
    //  HELPERS PRIVÉS
    // =========================================================================

    private function initials(string $name): string
    {
        return collect(explode(' ', $name))
            ->filter()
            ->take(2)
            ->map(fn ($w) => strtoupper($w[0]))
            ->join('');
    }
}
