<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Trip;
use App\Models\Withdrawal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Tableau de bord financier et statistique du conducteur.
 */
class DriverStatsController extends Controller
{
    #[OA\Get(
        path: '/api/driver/stats',
        operationId: 'driverStats',
        summary: 'Tableau de bord conducteur',
        description: <<<'DESC'
Retourne les statistiques clés du conducteur connecté :
- Gains totaux (fonds libérés en sa faveur)
- Solde disponible pour retrait (non encore retiré)
- Nombre de trajets effectués
- Note moyenne
- Nombre de passagers transportés
DESC,
        tags: ['📊 Stats Conducteur'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Statistiques du conducteur',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'total_earnings',         type: 'integer', example: 125000, description: 'Gains totaux XOF'),
                                new OA\Property(property: 'available_balance',      type: 'integer', example: 45000,  description: 'Solde disponible pour retrait'),
                                new OA\Property(property: 'total_withdrawals',      type: 'integer', example: 80000,  description: 'Total retiré'),
                                new OA\Property(property: 'trips_completed',        type: 'integer', example: 18),
                                new OA\Property(property: 'passengers_transported', type: 'integer', example: 42),
                                new OA\Property(property: 'average_rating',         type: 'number',  format: 'float', example: 4.6, nullable: true),
                                new OA\Property(property: 'total_reviews',          type: 'integer', example: 14),
                            ]
                        ),
                    ]
                )
            ),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Trajets complétés par ce conducteur
        $completedTripIds = Trip::where('user_id', $user->id)
            ->where('status', 'completed')
            ->pluck('id');

        // Gains = sum(net_amount) des paiements libérés sur ces trajets
        $totalEarnings = Payment::whereHas('booking', fn ($q) =>
                $q->whereIn('trip_id', $completedTripIds)
            )
            ->where('status', 'success')
            ->sum('net_amount');

        // Total retiré (approuvé)
        $totalWithdrawn = Withdrawal::where('user_id', $user->id)
            ->where('status', 'approved')
            ->sum('amount');

        // Solde disponible = gains - retraits approuvés
        $availableBalance = max(0, $totalEarnings - $totalWithdrawn);

        // Passagers transportés
        $passengersCount = Booking::whereIn('trip_id', $completedTripIds)
            ->where('status', 'accepted')
            ->sum('seats_booked');

        return response()->json([
            'success' => true,
            'message' => 'Tableau de bord conducteur.',
            'body'    => [
                'total_earnings'         => (int) $totalEarnings,
                'available_balance'      => (int) $availableBalance,
                'total_withdrawals'      => (int) $totalWithdrawn,
                'trips_completed'        => $completedTripIds->count(),
                'passengers_transported' => (int) $passengersCount,
                'average_rating'         => $user->averageRating(),
                'total_reviews'          => $user->reviewsReceived()->count(),
            ],
        ]);
    }
}
