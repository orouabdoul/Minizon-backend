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
        'status',
        'current_latitude',
        'current_longitude',
    ];

    protected $casts = [
        'departure_time'    => 'datetime',
        'price_per_seat'    => 'integer',
        'current_latitude'  => 'float',
        'current_longitude' => 'float',
    ];

    // -----------------------------------------------------------------------
    // BOOT : génération automatique de l'UUID
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

    public function route(): string
    {
        return "{$this->departure_city} → {$this->arrival_city}";
    }
}