<?php

namespace App\Http\Controllers\Sandbox;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\TripValidation;
use App\Notifications\PaymentConfirmed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contrôleur de simulation uniquement disponible en environnement local / sandbox.
 *
 * Permet de tester le flux de paiement complet sans passer par le checkout FedaPay :
 *   POST /api/sandbox/payments/{uuid}/approve  → simule transaction.approved
 *   POST /api/sandbox/payments/{uuid}/decline  → simule transaction.declined
 *
 * ⚠️  JAMAIS exposé en production (guard dans le service provider + route).
 */
class SandboxPaymentController extends Controller
{
    public function approve(Request $request, string $uuid): JsonResponse
    {
        $payment = Payment::with(['booking.trip', 'user'])->where('uuid', $uuid)->first();

        if (! $payment) {
            return response()->json(['success' => false, 'message' => 'Paiement introuvable.'], 404);
        }

        if (! $payment->isPending()) {
            return response()->json([
                'success' => false,
                'message' => "Ce paiement ne peut pas être approuvé (statut actuel : « {$payment->status} »).",
            ], 422);
        }

        // — Reproduit exactement handleApproved() du PaymentController —
        $payment->update(['status' => 'locked']);
        $payment->booking->update(['payment_status' => 'escrow_locked']);

        TripValidation::updateOrCreate(
            ['booking_id' => $payment->booking_id],
            [
                'trip_id'         => $payment->booking->trip_id,
                'auto_release_at' => now()->addHours(24),
                'status'          => 'waiting',
            ]
        );

        $payment->user->notify(new PaymentConfirmed($payment->load('booking.trip')));

        return response()->json([
            'success' => true,
            'message' => '[SANDBOX] Paiement approuvé — fonds en escrow (24h).',
            'body'    => [
                'payment_uuid'   => $payment->uuid,
                'status'         => 'locked',
                'payment_status' => 'escrow_locked',
                'escrow_until'   => now()->addHours(24)->toIso8601String(),
                'amount'         => $payment->gross_amount,
                'net_to_driver'  => $payment->net_amount,
            ],
        ]);
    }

    public function decline(Request $request, string $uuid): JsonResponse
    {
        $payment = Payment::with(['booking'])->where('uuid', $uuid)->first();

        if (! $payment) {
            return response()->json(['success' => false, 'message' => 'Paiement introuvable.'], 404);
        }

        if (! $payment->isPending()) {
            return response()->json([
                'success' => false,
                'message' => "Ce paiement ne peut pas être refusé (statut actuel : « {$payment->status} »).",
            ], 422);
        }

        $payment->update(['status' => 'failed']);
        $payment->booking->update(['payment_status' => 'unpaid']);

        return response()->json([
            'success' => true,
            'message' => '[SANDBOX] Paiement refusé — réservation revenue à "unpaid".',
            'body'    => [
                'payment_uuid'   => $payment->uuid,
                'status'         => 'failed',
                'payment_status' => 'unpaid',
            ],
        ]);
    }
}
