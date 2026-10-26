<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Trip;
use App\Notifications\PassengerDroppedOff;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class DriverDropoffController extends Controller
{
    /**
     * POST /driver/trips/{uuid}/stops/{bookingUuid}/dropoff
     *
     * Marque un passager comme déposé et déclenche le timer de libération des fonds (1h).
     */
    public function dropoff(string $uuid, string $bookingUuid): JsonResponse
    {
        $trip = Trip::where('uuid', $uuid)->firstOrFail();

        if ($trip->user_id !== auth()->id()) {
            return $this->apiResponse(false, 'Non autorisé.', [], 403);
        }

        if (! $trip->isActive()) {
            return $this->apiResponse(false, 'Le trajet n\'est pas en cours.', [], 422);
        }

        $booking = Booking::with(['passenger', 'tripValidation'])
            ->where('uuid', $bookingUuid)
            ->where('trip_id', $trip->id)
            ->firstOrFail();

        if (! in_array($booking->status, ['accepted', 'confirmed'])) {
            return $this->apiResponse(false, 'Réservation non valide pour cette action.', [], 422);
        }

        if ($booking->picked_up_at === null) {
            return $this->apiResponse(false, 'Le passager n\'est pas encore embarqué.', [], 422);
        }

        if ($booking->dropped_off_at !== null) {
            return $this->apiResponse(false, 'Ce passager a déjà été déposé.', [], 422);
        }

        $booking->update(['dropped_off_at' => now()]);

        // Timer de libération : 1h après dépôt effectif
        if ($booking->tripValidation) {
            $booking->tripValidation->update([
                'auto_release_at' => now()->addHour(),
            ]);
        }

        try {
            $booking->passenger?->notify(new PassengerDroppedOff($trip, $booking));
        } catch (\Throwable $e) {
            Log::warning('DriverDropoffController: notif FCM échouée', [
                'booking_uuid' => $bookingUuid,
                'error'        => $e->getMessage(),
            ]);
        }

        return $this->apiResponse(true, 'Passager déposé.', [
            'booking_uuid'    => $booking->uuid,
            'dropped_off_at'  => $booking->dropped_off_at->toIso8601String(),
            'auto_release_at' => $booking->tripValidation?->auto_release_at?->toIso8601String(),
        ]);
    }
}
