<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class Trip extends Model
{
    use HasFactory;

    // -------------------------------------------------------------------------
    //  Configuration
    // -------------------------------------------------------------------------

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
        'status',               // pending | active | completed
        'description',
        'current_latitude',
        'current_longitude',
    ];

    protected $casts = [
        'departure_time'    => 'datetime',
        'price_per_seat'    => 'integer',
        'current_latitude'  => 'float',
        'current_longitude' => 'float',
    ];

    // -------------------------------------------------------------------------
    //  UUID auto-généré à la création
    // -------------------------------------------------------------------------

    protected static function booted(): void
    {
        static::creating(function (Trip $trip) {
            if (empty($trip->uuid)) {
                $trip->uuid = (string) Str::uuid();
            }
        });
    }

    // -------------------------------------------------------------------------
    //  Relations
    // -------------------------------------------------------------------------

    /** Conducteur propriétaire du trajet */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Véhicule utilisé pour ce trajet */
    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    /** Réservations liées à ce trajet */
    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    // -------------------------------------------------------------------------
    //  Scopes utilitaires
    // -------------------------------------------------------------------------

    /** Trajets ouverts à la réservation */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /** Trajets en cours */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /** Trajets terminés */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
}