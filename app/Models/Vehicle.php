<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Vehicle extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'vehicle_type_id',
        'brand',
        'model',
        'color',
        'year',
        'license_plate',
        'available_seats',
        // Fichiers
        'vehicle_photo',
        'registration_doc',
        'insurance_doc',
        'tvm_doc',
        'technical_control_doc',
        // Statut & vérification
        'is_approved',
        'verification_status',
        'rejection_reason',
        'verified_at',
        'verified_by',
    ];

    protected $casts = [
        'is_approved'     => 'boolean',
        'available_seats' => 'integer',
        'year'            => 'integer',
        'verified_at'     => 'datetime',
    ];

    // -----------------------------------------------------------------------
    // RELATIONS
    // -----------------------------------------------------------------------

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function vehicleType()
    {
        return $this->belongsTo(VehicleType::class);
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function trips()
    {
        return $this->hasMany(Trip::class);
    }

    // -----------------------------------------------------------------------
    // HELPERS
    // -----------------------------------------------------------------------

    public function fullName(): string
    {
        return "{$this->brand} {$this->model} — {$this->color}";
    }

    public function isPending(): bool   { return ($this->verification_status ?? 'pending') === 'pending'; }
    public function isApproved(): bool  { return ($this->verification_status ?? 'pending') === 'approved'; }
    public function isRejected(): bool  { return ($this->verification_status ?? 'pending') === 'rejected'; }
    public function isSuspended(): bool { return ($this->verification_status ?? 'pending') === 'suspended'; }
}
