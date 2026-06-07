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
    ];

    protected $hidden = [
        'password',
        'otp_code',
        'remember_token',
    ];

    protected $casts = [
        'is_verified'        => 'boolean',
        'is_blocked'         => 'boolean',
        'phone_verified_at'  => 'datetime',
        'otp_expires_at'     => 'datetime',
        'blocked_until'      => 'datetime',
        'penalty_points'     => 'integer',
    ];

    // -----------------------------------------------------------------------
    // BOOT : génération automatique de l'UUID à la création
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
}