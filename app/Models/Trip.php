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

        // Géographie — texte
        'departure_city',
        'departure_neighborhood',
        'departure_point',
        'arrival_city',
        'arrival_neighborhood',
        'arrival_point',

        // Géographie — GPS précis
        'departure_latitude',
        'departure_longitude',
        'arrival_latitude',
        'arrival_longitude',

        // Conditions du trajet
        'price_per_seat',
        'departure_time',
        'estimated_duration_minutes',
        'estimated_arrival_time',
        'description',

        // Arrêts intermédiaires & préférences
        'waypoints',
        'preferences',

        // Capacité & réservation
        'total_seats',
        'available_seats',
        'booking_mode',
        'max_per_booking',

        // Politique d'annulation
        'cancellation_policy',

        // Récurrence
        'is_recurring',
        'recurring_days',
        'recurring_end_date',

        // Financier
        'commission_rate',

        // Statut & visibilité
        'status',
        'is_published',
        'published_at',

        // Cycle de vie
        'started_at',
        'completed_at',

        // GPS temps réel (télémétrie pendant le voyage)
        'current_latitude',
        'current_longitude',

        // Modération
        'is_flagged',
        'moderation_note',

        // Statistiques
        'view_count',
    ];

    protected $casts = [
        'departure_time'             => 'datetime',
        'estimated_arrival_time'     => 'datetime',
        'published_at'               => 'datetime',
        'started_at'                 => 'datetime',
        'completed_at'               => 'datetime',
        'recurring_end_date'         => 'date',
        'price_per_seat'             => 'integer',
        'total_seats'                => 'integer',
        'available_seats'            => 'integer',
        'max_per_booking'            => 'integer',
        'estimated_duration_minutes' => 'integer',
        'commission_rate'            => 'integer',
        'view_count'                 => 'integer',
        'waypoints'                  => 'array',
        'preferences'                => 'array',
        'recurring_days'             => 'array',
        'is_recurring'               => 'boolean',
        'is_published'               => 'boolean',
        'is_flagged'                 => 'boolean',
        'departure_latitude'         => 'float',
        'departure_longitude'        => 'float',
        'arrival_latitude'           => 'float',
        'arrival_longitude'          => 'float',
        'current_latitude'           => 'float',
        'current_longitude'          => 'float',
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
    // SCOPES
    // -----------------------------------------------------------------------

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeVisible($query)
    {
        return $query->where('is_published', true)->where('is_flagged', false);
    }

    // -----------------------------------------------------------------------
    // HELPERS
    // -----------------------------------------------------------------------

    public function isPending(): bool    { return $this->status === 'pending'; }
    public function isActive(): bool     { return $this->status === 'active'; }
    public function isCompleted(): bool  { return $this->status === 'completed'; }
    public function isCancelled(): bool  { return $this->status === 'cancelled'; }
    public function isDraft(): bool      { return ! $this->is_published; }
    public function isInstant(): bool    { return $this->booking_mode === 'instant'; }
    public function requiresApproval(): bool { return $this->booking_mode === 'approval'; }

    public function hasSeatsAvailable(int $seats = 1): bool
    {
        return $this->available_seats >= $seats;
    }

    public function route(): string
    {
        return "{$this->departure_city} → {$this->arrival_city}";
    }

    /** Calcule les gains nets conducteur pour un nombre de places donné. */
    public function driverEarnings(int $seats = 1): int
    {
        $gross = $this->price_per_seat * $seats;
        return (int) round($gross * (1 - ($this->commission_rate / 100)));
    }

    /** Calcule la commission plateforme pour un nombre de places donné. */
    public function platformCommission(int $seats = 1): int
    {
        return $this->price_per_seat * $seats - $this->driverEarnings($seats);
    }
}
