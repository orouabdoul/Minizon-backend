<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class Trip extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'user_id',
        'vehicle_id',
        'departure_city',
        'departure_neighborhood',
        'arrival_city',
        'arrival_neighborhood',
        'price_per_seat',
        'departure_time',
        'description',
        'total_seats',
        'available_seats',
        'status',
        'current_latitude',
        'current_longitude',
    ];

    protected $casts = [
        'departure_time'    => 'datetime',
        'price_per_seat'    => 'integer',
        'total_seats'       => 'integer',
        'available_seats'   => 'integer',
        'current_latitude'  => 'float',
        'current_longitude' => 'float',
    ];

    // -----------------------------------------------------------------------
    // BOOT
    // -----------------------------------------------------------------------

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $trip) {
            if (empty($trip->uuid)) {
                $trip->uuid = (string) Str::uuid();
            }
        });
    }

    // -----------------------------------------------------------------------
    // RELATIONS
    // -----------------------------------------------------------------------

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function conversations()
    {
        return $this->hasMany(Conversation::class);
    }

    public function tripValidations()
    {
        return $this->hasMany(TripValidation::class);
    }

    // -----------------------------------------------------------------------
    // HELPERS
    // -----------------------------------------------------------------------

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function hasSeatsAvailable(int $seats = 1): bool
    {
        return $this->available_seats >= $seats;
    }

    public function route(): string
    {
        return "{$this->departure_city} → {$this->arrival_city}";
    }
}
