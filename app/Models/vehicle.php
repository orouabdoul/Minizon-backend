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
        'license_plate',
        'available_seats',
        // Fichiers
        'vehicle_photo',
        'registration_doc',
        'insurance_doc',
        'tvm_doc',
        'technical_control_doc',
        // Statut
        'is_approved',
    ];

    protected $casts = [
        'is_approved'     => 'boolean',
        'available_seats' => 'integer',
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

    // -----------------------------------------------------------------------
    // HELPERS
    // -----------------------------------------------------------------------

    public function fullName(): string
    {
        return "{$this->brand} {$this->model} — {$this->color}";
    }
}