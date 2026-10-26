<?php

namespace App\Http\Controllers\Passenger;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Trip;
use App\Notifications\DriverEnRoute;
use App\Notifications\TripCancelledDriverAbsent;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class PassengerDriverAbsentController extends Controller
{
    /**
     * POST /passenger/bookings/{uuid}/report-driver-absent
     *
     * Un passager signale que le conducteur ne s'est pas présenté.
     *
     * - Si le conducteur a envoyé une position GPS récente (< 5 min) :
     *   → Notifier le passager "conducteur en route, patientez"
     * - Si TOUS les passagers non-embarqués ont signalé l'absence :
     *   → Annuler le trajet, rembourser tous les passagers
     * - Sinon : enregistrer le signalement et attendre
     */
    public function reportDriverAbsent(string $uuid): JsonResponse
    {
        $booking = Booking::with(['trip.bookings.passenger', 'passenger'])
            ->where('uuid', $uuid)
            ->where('passenger_id', auth()->id())
            ->firstOrFail();

        $trip = $booking->trip;

        if (! in_array($trip->status, ['pending', 'active'])) {
            return $this->apiResponse(false, 'Le trajet n\'est pas en attente de départ.', [], 422);
        }

        if ($booking->picked_up_at !== null) {
            return $this->apiResponse(false, 'Vous êtes déjà embarqué.', [], 422);
        }

        // Vérification délai : au moins 15 min après l'heure de départ prévue
        $departureTime   = $trip->departure_time;
        $absentAllowedAt = $departureTime->copy()->addMinutes(15);
        if (now()->lt($absentAllowedAt)) {
            $minutesLeft = (int) ceil(now()->diffInMinutes($absentAllowedAt, false));
            return $this->apiResponse(false, "Vous pourrez signaler l'absence dans {$minutesLeft} min.", [], 422);
        }

        if ($booking->driver_absent_reported_at !== null) {
            return $this->apiResponse(false, 'Vous avez déjà signalé l\'absence du conducteur.', [], 422);
        }

        $booking->update(['driver_absent_reported_at' => now()]);

        // ── Vérifier si le conducteur est en route (GPS récent < 5 min) ──────
        $gpsUpdatedAt = $trip->location_updated_at;
        $driverActive = $gpsUpdatedAt !== null && $gpsUpdatedAt->gte(now()->subMinutes(5));

        if ($driverActive) {
            // Conducteur en route → notifier CE passager uniquement
            try {
                $booking->passenger?->notify(new DriverEnRoute($trip));
            } catch (\Throwable $e) {
                Log::warning('PassengerDriverAbsentController: DriverEnRoute notif échouée', [
                    'booking_uuid' => $uuid,
                    'error'        => $e->getMessage(),
                ]);
            }

            return $this->apiResponse(true, 'Votre conducteur est en route. Veuillez patienter.', [
                'driver_en_route' => true,
                'auto_cancel'     => false,
            ]);
        }

        // ── Vérifier si TOUS les passagers non-embarqués ont signalé l'absence ─
        $activeBookings = $trip->bookings->where('status', 'accepted');
        $nonBoardedBookings = $activeBookings->whereNull('picked_up_at')->whereNull('no_show_at');
        $allReportedAbsent  = $nonBoardedBookings->every(
            fn ($b) => $b->driver_absent_reported_at !== null
        );

        if (! $allReportedAbsent) {
            return $this->apiResponse(true, 'Signalement enregistré. En attente des autres passagers.', [
                'driver_en_route' => false,
                'auto_cancel'     => false,
                'reported_count'  => $nonBoardedBookings->filter(fn ($b) => $b->driver_absent_reported_at !== null)->count(),
                'total_count'     => $nonBoardedBookings->count(),
            ]);
        }

        // ── Tous les passagers ont signalé → annuler le trajet ─────────────
        $trip->update([
            'status'       => 'cancelled',
            'cancelled_at' => now(),
            'cancel_reason'=> 'driver_absent',
        ]);

        // Remboursement : libérer les fonds vers les passagers (pas vers le conducteur)
        // On marque les validations comme "disputed" pour traitement admin
        foreach ($activeBookings as $b) {
            if ($b->tripValidation) {
                $b->tripValidation->update(['status' => 'disputed']);
            }
            $b->update(['payment_status' => 'refund_pending']);
        }

        // Notifier tous les passagers
        foreach ($activeBookings as $b) {
            try {
                $b->passenger?->notify(new TripCancelledDriverAbsent($trip));
            } catch (\Throwable $e) {
                Log::warning('PassengerDriverAbsentController: TripCancelledDriverAbsent notif échouée', [
                    'booking_id' => $b->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        return $this->apiResponse(true, 'Trajet annulé — conducteur absent. Remboursement en cours.', [
            'driver_en_route' => false,
            'auto_cancel'     => true,
        ]);
    }
}
