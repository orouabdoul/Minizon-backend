<?php

namespace App\Http\Controllers\Sandbox;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\TripValidation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Simule les callbacks FedaPay en local (APP_ENV=local).
 * Permet de tester le flux complet sans webhook entrant.
 *
 * POST /api/sandbox/payments/{uuid}/approve  → simule transaction.approved
 * POST /api/sandbox/payments/{uuid}/decline  → simule transaction.declined
 */
class SandboxPaymentController extends Controller
{
    public function approve(Request $request, string $uuid): JsonResponse
    {
        $this->guardProduction();

        $payment = Payment::with('booking')->where('uuid', $uuid)->first();

        if (! $payment) {
            return $this->apiResponse(false, 'Paiement introuvable.', [], 404);
        }

        if (! $payment->isPending()) {
            return $this->apiResponse(false, "Ce paiement est déjà en statut '{$payment->status}'.", [], 422);
        }

        DB::transaction(function () use ($payment) {
            $payment->update(['status' => 'locked']);

            $booking = $payment->booking;
            if ($booking) {
                $booking->update(['payment_status' => 'escrow_locked']);

                TripValidation::firstOrCreate(
                    ['booking_id' => $booking->id],
                    [
                        'trip_id'             => $booking->trip_id,
                        'passenger_confirmed' => false,
                        'auto_release_at'     => now()->addHours(24),
                        'status'              => 'waiting',
                    ]
                );
            }
        });

        return $this->apiResponse(true, '[SANDBOX] Paiement approuvé. Escrow verrouillé.', [
            'payment_uuid'   => $payment->uuid,
            'payment_status' => 'locked',
            'booking_uuid'   => $payment->booking?->uuid,
            'booking_payment_status' => 'escrow_locked',
        ]);
    }

    public function decline(Request $request, string $uuid): JsonResponse
    {
        $this->guardProduction();

        $payment = Payment::where('uuid', $uuid)->first();

        if (! $payment) {
            return $this->apiResponse(false, 'Paiement introuvable.', [], 404);
        }

        if (! $payment->isPending()) {
            return $this->apiResponse(false, "Ce paiement est déjà en statut '{$payment->status}'.", [], 422);
        }

        $payment->update(['status' => 'failed']);

        return $this->apiResponse(true, '[SANDBOX] Paiement refusé.', [
            'payment_uuid'   => $payment->uuid,
            'payment_status' => 'failed',
        ]);
    }

    private function guardProduction(): void
    {
        if (app()->isProduction()) {
            abort(404);
        }
    }
}
