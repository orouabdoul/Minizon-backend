<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'trip_id',
        'passenger_id',
        'seats_booked',

        // Point de montée — commune → arrondissement → quartier → point précis
        'pickup_city',
        'pickup_arrondissement',
        'pickup_neighborhood',
        'pickup_address',
        'pickup_latitude',
        'pickup_longitude',

        // Point de descente — commune → arrondissement → quartier → point précis
        'dropoff_city',
        'dropoff_arrondissement',
        'dropoff_neighborhood',
        'dropoff_address',
        'dropoff_latitude',
        'dropoff_longitude',

        // Prix calculé automatiquement (Haversine)
        'passenger_distance_km',
        'calculated_price',
        'service_fee',
        'total_price',
        'status',
        'payment_status',
        'picked_up_at',
        'passenger_confirmed_at',
        'boarding_code',
        'boarding_method',
        'driver_lat_at_boarding',
        'driver_lng_at_boarding',
        'passenger_lat_at_boarding',
        'passenger_lng_at_boarding',
        'boarding_refused_at',
        'dropped_off_at',
        'no_show_at',
        'driver_absent_reported_at',
    ];

    protected $casts = [
        'seats_booked'               => 'integer',
        'picked_up_at'               => 'datetime',
        'dropped_off_at'             => 'datetime',
        'no_show_at'                 => 'datetime',
        'driver_absent_reported_at'  => 'datetime',
        'passenger_confirmed_at'     => 'datetime',
        'boarding_refused_at'        => 'datetime',
        'driver_lat_at_boarding'     => 'float',
        'driver_lng_at_boarding'     => 'float',
        'passenger_lat_at_boarding'  => 'float',
        'passenger_lng_at_boarding'  => 'float',
        'pickup_latitude'            => 'float',
        'pickup_longitude'      => 'float',
        'dropoff_latitude'      => 'float',
        'dropoff_longitude'     => 'float',
        'passenger_distance_km' => 'float',
        'calculated_price'      => 'integer',
        'service_fee'           => 'integer',
        'total_price'           => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $booking) {
            if (empty($booking->uuid)) {
                $booking->uuid = (string) Str::uuid();
            }
        });
    }

    // -----------------------------------------------------------------------
    // RELATIONS
    // -----------------------------------------------------------------------

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function passenger()
    {
        return $this->belongsTo(User::class, 'passenger_id');
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function tripValidation()
    {
        return $this->hasOne(TripValidation::class);
    }

    public function dispute()
    {
        return $this->hasOne(Dispute::class);
    }

    public function conversation()
    {
        return $this->hasOne(Conversation::class);
    }

    // -----------------------------------------------------------------------
    // HELPERS
    // -----------------------------------------------------------------------

    public function isPending(): bool   { return $this->status === 'pending'; }
    public function isAccepted(): bool  { return $this->status === 'accepted'; }
    public function isRejected(): bool  { return $this->status === 'rejected'; }
    public function isCancelled(): bool { return $this->status === 'cancelled'; }
    public function isPaid(): bool      { return $this->payment_status === 'escrow_locked'; }

    // Prix total payé par le passager (calculé_price × places + frais service)
    public function totalAmount(): int
    {
        return ($this->calculated_price * $this->seats_booked) + ($this->service_fee ?? 0);
    }
}
