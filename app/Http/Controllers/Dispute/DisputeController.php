<?php

namespace App\Http\Controllers\Dispute;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\Booking;
use App\Models\Dispute;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DisputeController extends Controller
{
    // =========================================================================
    //  POST /api/bookings/{uuid}/disputes  — ouvrir un litige
    // =========================================================================

    public function store(Request $request, string $uuid): JsonResponse
    {
        $user    = $request->user();
        $booking = Booking::with(['trip', 'passenger'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        // Vérifier que l'utilisateur est partie à cette réservation
        $isPassenger = $booking->passenger_id === $user->id;
        $isDriver    = $booking->trip?->user_id === $user->id;

        if (! $isPassenger && ! $isDriver) {
            return $this->apiResponse(false, 'Vous n\'êtes pas partie à cette réservation.', [], 403);
        }

        // Un seul litige ouvert par réservation
        $existing = Dispute::where('booking_id', $booking->id)
            ->whereNotIn('status', ['resolved_refunded', 'resolved_paid_to_driver', 'closed'])
            ->first();

        if ($existing) {
            return $this->apiResponse(false, 'Un litige est déjà en cours pour cette réservation.', [
                'dispute_uuid' => $existing->uuid,
            ], 422);
        }

        $validated = $request->validate([
            'reason_type' => ['required', 'string', 'in:scam,bad_behavior,driver_absent,passenger_absent,other'],
            'description' => ['required', 'string', 'min:20', 'max:2000'],
            'proof'       => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $proofPath = null;
        if ($request->hasFile('proof')) {
            try {
                $proofPath = $request->file('proof')->store('disputes', 'public');
            } catch (\Throwable $e) {
                Log::warning('DisputeController: upload preuve échoué', ['err' => $e->getMessage()]);
            }
        }

        $dispute = Dispute::create([
            'booking_id'  => $booking->id,
            'reporter_id' => $user->id,
            'reason_type' => $validated['reason_type'],
            'description' => $validated['description'],
            'proof_path'  => $proofPath,
            'status'      => 'pending',
        ]);

        // Notifier les admins
        try {
            $trip       = $booking->trip;
            $routeLabel = $trip
                ? ($trip->departure_city . ' → ' . $trip->arrival_city)
                : 'Trajet inconnu';

            AdminNotification::notifyAdmins(
                type:        'dispute',
                priority:    'high',
                title:       'Nouveau litige ouvert',
                description: "Litige «{$validated['reason_type']}» sur la réservation #{$booking->id} ($routeLabel).",
                refType:     'dispute',
                refId:       $dispute->uuid,
                userId:      $user->id,
            );
        } catch (\Throwable $e) {
            Log::warning('DisputeController: notif admin échouée', ['err' => $e->getMessage()]);
        }

        return $this->apiResponse(true, 'Litige ouvert. Notre équipe examine votre signalement.', [
            'dispute_uuid' => $dispute->uuid,
            'status'       => $dispute->status,
        ], 201);
    }

    // =========================================================================
    //  GET /api/disputes  — mes litiges
    // =========================================================================

    public function index(Request $request): JsonResponse
    {
        $disputes = Dispute::with(['booking.trip'])
            ->where('reporter_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Dispute $d) => $this->format($d));

        return $this->apiResponse(true, 'Vos litiges.', $disputes);
    }

    // =========================================================================
    //  GET /api/disputes/{id}  — détail
    // =========================================================================

    public function show(Request $request, int $id): JsonResponse
    {
        $dispute = Dispute::with(['booking.trip'])
            ->where('reporter_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();

        return $this->apiResponse(true, 'Détail du litige.', $this->format($dispute));
    }

    // =========================================================================
    //  HELPERS
    // =========================================================================

    private function format(Dispute $d): array
    {
        $trip = $d->booking?->trip;
        return [
            'uuid'         => $d->uuid,
            'id'           => $d->id,
            'reason_type'  => $d->reason_type,
            'description'  => $d->description,
            'status'       => $d->status,
            'proof_url'    => $d->proof_path
                ? Storage::disk('public')->url($d->proof_path)
                : null,
            'booking_uuid' => $d->booking?->uuid,
            'trip'         => $trip ? [
                'uuid'           => $trip->uuid,
                'departure_city' => $trip->departure_city,
                'arrival_city'   => $trip->arrival_city,
                'status'         => $trip->status,
            ] : null,
            'created_at'   => $d->created_at?->toIso8601String(),
            'resolved_at'  => $d->resolved_at?->toIso8601String(),
            'admin_notes'  => $d->admin_decision_notes,
        ];
    }
}
