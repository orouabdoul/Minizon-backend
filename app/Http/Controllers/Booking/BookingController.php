<?php

namespace App\Http\Controllers\Booking;

use App\Helpers\GeoHelper;
use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\Booking;
use App\Models\Trip;
use App\Notifications\BookingStatusChanged;
use App\Notifications\NewBookingRequest;
use App\Notifications\PassengerCancelledBooking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BookingController extends Controller
{
    private const SERVICE_FEE_RATE = 0.05;

    // =========================================================================
    //  POST /api/trips/{uuid}/bookings
    // =========================================================================

    public function store(Request $request, string $uuid): JsonResponse
    {
        $validated = $request->validate([
            'seats_booked'             => ['required', 'integer', 'min:1'],
            // ── Prise en charge ──
            'pickup_city'              => ['required', 'string', 'max:100'],
            'pickup_arrondissement'    => ['nullable', 'string', 'max:150'],
            'pickup_neighborhood'      => ['nullable', 'string', 'max:100'],
            'pickup_address'           => ['required', 'string', 'max:500'],
            'pickup_latitude'          => ['nullable', 'numeric', 'between:-90,90'],
            'pickup_longitude'         => ['nullable', 'numeric', 'between:-180,180'],
            // ── Dépôt ──
            'dropoff_city'             => ['required', 'string', 'max:100'],
            'dropoff_arrondissement'   => ['nullable', 'string', 'max:150'],
            'dropoff_neighborhood'     => ['nullable', 'string', 'max:100'],
            'dropoff_address'          => ['required', 'string', 'max:500'],
            'dropoff_latitude'         => ['nullable', 'numeric', 'between:-90,90'],
            'dropoff_longitude'        => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $trip = Trip::where('uuid', $uuid)->first();

        if (! $trip) {
            return $this->apiResponse(false, 'Trajet introuvable.', [], 404);
        }

        if (! $trip->is_published || $trip->status !== 'pending') {
            return $this->apiResponse(false, 'Ce trajet n\'est plus disponible à la réservation.', [], 422);
        }

        if ($trip->user_id === $request->user()->id) {
            return $this->apiResponse(false, 'Vous ne pouvez pas réserver votre propre trajet.', [], 422);
        }

        $seatsRequested = (int) $validated['seats_booked'];

        if ($trip->available_seats < $seatsRequested) {
            return $this->apiResponse(false, "Seulement {$trip->available_seats} place(s) disponible(s) sur ce trajet.", [], 422);
        }

        // ── Résolution GPS pickup ─────────────────────────────────────────────
        $pickupLat = isset($validated['pickup_latitude'])  ? (float) $validated['pickup_latitude']  : null;
        $pickupLng = isset($validated['pickup_longitude']) ? (float) $validated['pickup_longitude'] : null;
        if (! $pickupLat || ! $pickupLng) {
            $coords    = GeoHelper::resolveCoordinates(
                $validated['pickup_city'],
                $validated['pickup_arrondissement'] ?? null,
                $validated['pickup_neighborhood']   ?? null
            );
            [$pickupLat, $pickupLng] = $coords ?? [null, null];
        }

        // ── Résolution GPS dropoff ────────────────────────────────────────────
        $dropoffLat = isset($validated['dropoff_latitude'])  ? (float) $validated['dropoff_latitude']  : null;
        $dropoffLng = isset($validated['dropoff_longitude']) ? (float) $validated['dropoff_longitude'] : null;
        if (! $dropoffLat || ! $dropoffLng) {
            $coords     = GeoHelper::resolveCoordinates(
                $validated['dropoff_city'],
                $validated['dropoff_arrondissement'] ?? null,
                $validated['dropoff_neighborhood']   ?? null
            );
            [$dropoffLat, $dropoffLng] = $coords ?? [null, null];
        }

        // ── Calcul distance passager (ORS → haversine×1.3) ────────────────────
        $passengerDistanceKm = ($pickupLat && $pickupLng && $dropoffLat && $dropoffLng)
            ? GeoHelper::distanceKm($pickupLat, $pickupLng, $dropoffLat, $dropoffLng)
            : 0.0;

        // ── Distance trajet : utilise distance_km stockée ou recalcul ─────────
        $tripDistanceKm = (float) ($trip->distance_km ?? 0);
        if ($tripDistanceKm <= 0 && $trip->departure_latitude && $trip->arrival_latitude) {
            $tripDistanceKm = GeoHelper::distanceKm(
                (float) $trip->departure_latitude, (float) $trip->departure_longitude,
                (float) $trip->arrival_latitude,   (float) $trip->arrival_longitude
            );
        }

        $calculatedPrice = GeoHelper::calculatePassengerPrice(
            $passengerDistanceKm,
            $tripDistanceKm,
            (int) $trip->price_per_seat
        );

        $priceSubtotal = $calculatedPrice * $seatsRequested;
        $serviceFee    = (int) round($priceSubtotal * self::SERVICE_FEE_RATE);
        $priceTotal    = $priceSubtotal + $serviceFee;

        $booking = DB::transaction(function () use (
            $trip, $request, $validated, $seatsRequested,
            $passengerDistanceKm, $calculatedPrice, $serviceFee,
            $pickupLat, $pickupLng, $dropoffLat, $dropoffLng
        ) {
            $booking = Booking::create([
                'trip_id'                => $trip->id,
                'passenger_id'           => $request->user()->id,
                'seats_booked'           => $seatsRequested,
                // ── Prise en charge (GPS résolu automatiquement) ──
                'pickup_city'            => $validated['pickup_city'],
                'pickup_arrondissement'  => $validated['pickup_arrondissement'] ?? null,
                'pickup_neighborhood'    => $validated['pickup_neighborhood'] ?? null,
                'pickup_address'         => $validated['pickup_address'],
                'pickup_latitude'        => $pickupLat,
                'pickup_longitude'       => $pickupLng,
                // ── Dépôt (GPS résolu automatiquement) ──
                'dropoff_city'           => $validated['dropoff_city'],
                'dropoff_arrondissement' => $validated['dropoff_arrondissement'] ?? null,
                'dropoff_neighborhood'   => $validated['dropoff_neighborhood'] ?? null,
                'dropoff_address'        => $validated['dropoff_address'],
                'dropoff_latitude'       => $dropoffLat,
                'dropoff_longitude'      => $dropoffLng,
                // ── Prix ──
                'passenger_distance_km'  => round($passengerDistanceKm, 2),
                'calculated_price'       => $calculatedPrice,
                'service_fee'            => $serviceFee,
                'status'                 => 'pending',
                'payment_status'         => 'unpaid',
            ]);

            $trip->decrement('available_seats', $seatsRequested);

            return $booking;
        });

        $this->notifyDriver($trip, $booking);

        return $this->apiResponse(true, 'Réservation créée.', [
            'booking_uuid'          => $booking->uuid,
            'booking_mode'          => $trip->booking_mode ?? 'approval',
            'calculated_price'      => $calculatedPrice,
            'price_subtotal'        => $priceSubtotal,
            'service_fee'           => $serviceFee,
            'price_total'           => $priceTotal,
            'passenger_distance_km' => round($passengerDistanceKm, 2),
            'trip_distance_km'      => round($tripDistanceKm, 2),
        ], 201);
    }

    // =========================================================================
    //  GET /api/bookings
    // =========================================================================

    public function index(Request $request): JsonResponse
    {
        $status   = $request->query('status');
        $query    = Booking::with('trip')
            ->where('passenger_id', $request->user()->id)
            ->orderByDesc('created_at');

        if ($status) {
            $query->where('status', $status);
        }

        $bookings = $query->get()->map(fn (Booking $b) => [
            'uuid'                  => $b->uuid,
            'status'                => $b->status,
            'payment_status'        => $b->payment_status,
            'seats_booked'          => $b->seats_booked,
            'calculated_price'      => $b->calculated_price,
            'service_fee'           => $b->service_fee,
            'passenger_distance_km' => $b->passenger_distance_km,
            'trip_uuid'             => $b->trip?->uuid,
            'origin'                => $b->trip?->departure_city,
            'destination'           => $b->trip?->arrival_city,
            'departure_time'        => $b->trip?->departure_time,
        ]);

        return $this->apiResponse(true, 'Réservations.', ['bookings' => $bookings]);
    }

    // =========================================================================
    //  GET /api/bookings/{uuid}
    // =========================================================================

    public function show(Request $request, string $uuid): JsonResponse
    {
        $booking = Booking::with(['trip.user.profile', 'trip.vehicle', 'payment'])
            ->where('uuid', $uuid)
            ->where('passenger_id', $request->user()->id)
            ->first();

        if (! $booking) {
            return $this->apiResponse(false, 'Réservation introuvable.', [], 404);
        }

        $profile = $booking->trip?->user?->profile;

        return $this->apiResponse(true, 'Réservation.', [
            'booking' => [
                'uuid'                   => $booking->uuid,
                'status'                 => $booking->status,
                'payment_status'         => $booking->payment_status,
                'seats_booked'           => $booking->seats_booked,
                'calculated_price'       => $booking->calculated_price,
                'service_fee'            => $booking->service_fee,
                'passenger_distance_km'  => $booking->passenger_distance_km,
                // ── Prise en charge — commune → arrondissement → quartier → point précis ──
                'pickup_city'            => $booking->pickup_city,
                'pickup_arrondissement'  => $booking->pickup_arrondissement,
                'pickup_neighborhood'    => $booking->pickup_neighborhood,
                'pickup_address'         => $booking->pickup_address,
                'pickup_latitude'        => $booking->pickup_latitude,
                'pickup_longitude'       => $booking->pickup_longitude,
                // ── Dépôt — commune → arrondissement → quartier → point précis ──
                'dropoff_city'           => $booking->dropoff_city,
                'dropoff_arrondissement' => $booking->dropoff_arrondissement,
                'dropoff_neighborhood'   => $booking->dropoff_neighborhood,
                'dropoff_address'        => $booking->dropoff_address,
                'dropoff_latitude'       => $booking->dropoff_latitude,
                'dropoff_longitude'      => $booking->dropoff_longitude,
                'trip_uuid'              => $booking->trip?->uuid,
                'origin'                 => $booking->trip?->departure_city,
                'destination'            => $booking->trip?->arrival_city,
                'departure_time'         => $booking->trip?->departure_time,
                'driver_name'            => trim(($profile?->first_name ?? '') . ' ' . ($profile?->last_name ?? '')) ?: null,
            ],
        ]);
    }

    // =========================================================================
    //  POST /api/bookings/{uuid}/cancel
    // =========================================================================

    public function cancel(Request $request, string $uuid): JsonResponse
    {
        $booking = Booking::with('trip')
            ->where('uuid', $uuid)
            ->where('passenger_id', $request->user()->id)
            ->first();

        if (! $booking) {
            return $this->apiResponse(false, 'Réservation introuvable.', [], 404);
        }

        if (in_array($booking->status, ['cancelled', 'rejected'], true)) {
            return $this->apiResponse(false, 'Cette réservation est déjà annulée.', [], 422);
        }

        if ($booking->trip?->status === 'completed') {
            return $this->apiResponse(false, 'Impossible d\'annuler un trajet déjà terminé.', [], 422);
        }

        DB::transaction(function () use ($booking) {
            if ($booking->status === 'accepted' && $booking->trip) {
                $booking->trip->increment('available_seats', $booking->seats_booked);
            }
            $booking->update(['status' => 'cancelled']);
        });

        $this->notifyDriver($booking->trip, $booking, cancelled: true);

        return $this->apiResponse(true, 'Réservation annulée.');
    }

    // =========================================================================
    //  GET /api/bookings/{uuid}/contact
    // =========================================================================

    public function contact(Request $request, string $uuid): JsonResponse
    {
        $booking = Booking::with('trip.user.profile')
            ->where('uuid', $uuid)
            ->where('passenger_id', $request->user()->id)
            ->first();

        if (! $booking) {
            return $this->apiResponse(false, 'Réservation introuvable.', [], 404);
        }

        if ($booking->status !== 'accepted') {
            return $this->apiResponse(false, 'Les coordonnées du conducteur ne sont disponibles qu\'après acceptation.', [], 403);
        }

        $driver  = $booking->trip?->user;
        $profile = $driver?->profile;

        return $this->apiResponse(true, 'Contact conducteur.', [
            'driver_name'  => trim(($profile?->first_name ?? '') . ' ' . ($profile?->last_name ?? '')) ?: 'Conducteur',
            'driver_phone' => $driver?->phone,
        ]);
    }

    // =========================================================================
    //  GET /api/admin/bookings
    // =========================================================================

    public function adminIndex(Request $request): JsonResponse
    {
        $query = Booking::with(['trip', 'passenger.profile'])->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        $bookings = $query->paginate(20);

        return $this->apiResponse(true, 'Réservations (admin).', ['bookings' => $bookings]);
    }

    // -------------------------------------------------------------------------

    private function notifyDriver(?Trip $trip, Booking $booking, bool $cancelled = false): void
    {
        if (! $trip?->user) return;

        try {
            if ($cancelled) {
                $trip->user->notify(new PassengerCancelledBooking($booking));

                $passenger = $booking->passenger;
                $profile   = $passenger?->profile;
                $name      = $profile
                    ? trim("{$profile->first_name} {$profile->last_name}")
                    : ($passenger?->phone ?? 'Un passager');
                $route = $trip ? "{$trip->departure_city} → {$trip->arrival_city}" : 'un trajet';

                AdminNotification::notifyAdmins(
                    type:        'user',
                    priority:    'normal',
                    title:       'Réservation annulée par un passager',
                    description: "{$name} a annulé sa réservation ({$booking->seats_booked} place(s)) sur {$route}.",
                    refType:     'booking',
                    refId:       $booking->uuid,
                    userId:      $passenger?->id,
                );
            } else {
                $trip->user->notify(new NewBookingRequest($booking));
            }
        } catch (\Throwable) {}
    }
}
