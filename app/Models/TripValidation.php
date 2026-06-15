<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TripValidation extends Model
{
    use HasFactory;

    protected $fillable = [
        'trip_id',
        'booking_id',
        'passenger_confirmed',
        'passenger_confirmed_at',
        'auto_release_at',
        'status',
    ];

    protected $casts = [
        'passenger_confirmed'    => 'boolean',
        'passenger_confirmed_at' => 'datetime',
        'auto_release_at'        => 'datetime',
    ];

    // -----------------------------------------------------------------------
    // RELATIONS
    // -----------------------------------------------------------------------

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    // -----------------------------------------------------------------------
    // HELPERS
    // -----------------------------------------------------------------------

    public function isWaiting(): bool    { return $this->status === 'waiting'; }
    public function isReleased(): bool   { return $this->status === 'released'; }
    public function isDisputed(): bool   { return $this->status === 'disputed'; }
    public function isCancelled(): bool  { return $this->status === 'cancelled'; }

    public function shouldAutoRelease(): bool
    {
        return $this->isWaiting()
            && $this->auto_release_at !== null
            && now()->gte($this->auto_release_at);
    }
}
