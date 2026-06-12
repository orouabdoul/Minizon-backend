<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'uuid',
        'phone',
        'password',
        'role_id',
        'otp_code',
        'otp_expires_at',
        'phone_verified_at',
        'is_verified',
        'is_blocked',
        'blocked_until',
        'penalty_points',
        'fcm_token',
    ];

    protected $hidden = [
        'password',
        'otp_code',
        'remember_token',
        'fcm_token',
    ];

    protected $casts = [
        'is_verified'       => 'boolean',
        'is_blocked'        => 'boolean',
        'phone_verified_at' => 'datetime',
        'otp_expires_at'    => 'datetime',
        'blocked_until'     => 'datetime',
        'penalty_points'    => 'integer',
    ];

    // -----------------------------------------------------------------------
    // BOOT
    // -----------------------------------------------------------------------

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $user) {
            if (empty($user->uuid)) {
                $user->uuid = (string) Str::uuid();
            }
        });
    }

    // -----------------------------------------------------------------------
    // RELATIONS
    // -----------------------------------------------------------------------

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function profile()
    {
        return $this->hasOne(Profile::class);
    }

    public function vehicle()
    {
        return $this->hasOne(Vehicle::class);
    }

    public function trips()
    {
        return $this->hasMany(Trip::class, 'user_id');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class, 'passenger_id');
    }

    public function withdrawals()
    {
        return $this->hasMany(Withdrawal::class);
    }

    public function conversations()
    {
        return $this->belongsToMany(Conversation::class, 'conversation_user');
    }

    public function messages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function disputes()
    {
        return $this->hasMany(Dispute::class, 'reporter_id');
    }

    public function penalties()
    {
        return $this->hasMany(Penalty::class);
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }

    public function reviewsReceived()
    {
        return $this->hasMany(Review::class, 'reviewee_id');
    }

    public function reviewsGiven()
    {
        return $this->hasMany(Review::class, 'reviewer_id');
    }

    // -----------------------------------------------------------------------
    // HELPERS
    // -----------------------------------------------------------------------

    public function isAdmin(): bool
    {
        return $this->role?->name === 'admin';
    }

    public function isDriver(): bool
    {
        return $this->role?->name === 'driver';
    }

    public function isPassenger(): bool
    {
        return $this->role?->name === 'passenger';
    }

    public function isActive(): bool
    {
        return ! $this->is_blocked;
    }

    public function averageRating(): ?float
    {
        $avg = $this->reviewsReceived()->avg('rating');
        return $avg ? round($avg, 1) : null;
    }
}
