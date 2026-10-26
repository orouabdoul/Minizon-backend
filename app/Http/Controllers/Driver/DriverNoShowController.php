<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Trip;
use App\Notifications\PassengerNoShow;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class DriverNoShowController extends Controller
{
    /**
     * POST /driver/trips/{uuid}/stops/{bookingUuid}/no-show
     *
     * Le conducteur signale un passager absent.
     * Conditions : trajet actif, passager non encore embarqué, pas déjà signalé absent.
     * Conséquence : libération immédiate des fonds au conducteur (100% indemnisation).
     */
    public function reportNoShow(string $uuid, string $bookingUuid): JsonResponse
    {
        $trip = Trip::where('uuid', $uuid)->firstOrFail();

        if ($trip->user_id !== auth()->id()) {
            return $this->apiResponse(false, 'Non autorisé.', [], 403);
        }

        // Accepte pending (embarquement en cours) et active
        if (! in_array($trip->status, ['pending', 'active'])) {
            return $this->apiResponse(false, 'Le trajet n\'est pas en cours.', [], 422);
        }

        $booking = Booking::with(['passenger', 'tripValidation'])
            ->where('uuid', $bookingUuid)
            ->where('trip_id', $trip->id)
            ->firstOrFail();

        if (! in_array($booking->status, ['accepted', 'confirmed'])) {
            return $this->apiResponse(false, 'Réservation non valide.', [], 422);
        }

        if ($booking->picked_up_at !== null) {
            return $this->apiResponse(false, 'Ce passager est déjà embarqué.', [], 422);
        }

        if ($booking->no_show_at !== null) {
            return $this->apiResponse(false, 'Ce passager a déjà été signalé absent.', [], 422);
        }

        // Vérification délai : au moins 15 min après l'heure de départ prévue
        $departureTime = $trip->departure_time;
        $noShowAllowedAt = $departureTime->copy()->addMinutes(15);
        if (now()->lt($noShowAllowedAt)) {
            $minutesLeft = (int) ceil(now()->diffInMinutes($noShowAllowedAt, false));
            return $this->apiResponse(false, "Vous pouvez signaler l'absence dans {$minutesLeft} min (15 min après le départ prévu).", [], 422);
        }

        $booking->update(['no_show_at' => now()]);

        // Libération immédiate des fonds au conducteur (pas de délai pour absence avérée)
        if ($booking->tripValidation) {
            $booking->tripValidation->update([
                'auto_release_at' => now()->subMinute(), // passé → libéré au prochain cycle
            ]);
        }

        try {
            $booking->passenger?->notify(new PassengerNoShow($trip, $booking));
        } catch (\Throwable $e) {
            Log::warning('DriverNoShowController: notif FCM échouée', [
                'booking_uuid' => $bookingUuid,
                'error'        => $e->getMessage(),
            ]);
        }

        return $this->apiResponse(true, 'Passager marqué absent. Fonds libérés.', [
            'booking_uuid' => $booking->uuid,
            'no_show_at'   => $booking->no_show_at->toIso8601String(),
        ]);
    }
}
