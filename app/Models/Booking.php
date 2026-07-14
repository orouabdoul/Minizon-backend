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

        // Point de montée du passager
        'pickup_address',
        'pickup_latitude',
        'pickup_longitude',

        // Point de descente du passager
        'dropoff_address',
        'dropoff_latitude',
        'dropoff_longitude',

        'status',
        'payment_status',
        'picked_up_at',
    ];

    protected $casts = [
        'seats_booked'     => 'integer',
        'picked_up_at'     => 'datetime',
        'pickup_latitude'  => 'float',
        'pickup_longitude' => 'float',
        'dropoff_latitude' => 'float',
        'dropoff_longitude'=> 'float',
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
}
