<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Profile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'gender',
        'email',
        'city',
        'neighborhood',
        'address_details',
        // Selfies
        'selfie_front',
        'selfie_left',
        'selfie_right',
        // Pièces d'identité
        'id_card_front',
        'id_card_back',
        // KYC
        'kyc_status',
        'kyc_matching_score',
        'approved_at',
        // Conducteur
        'driving_license_number',
        'driving_license_photo',
    ];

    protected $casts = [
        'approved_at'        => 'datetime',
        'kyc_matching_score' => 'float',
    ];

    // -----------------------------------------------------------------------
    // RELATIONS
    // -----------------------------------------------------------------------

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // -----------------------------------------------------------------------
    // HELPERS
    // -----------------------------------------------------------------------

    public function isApproved(): bool
    {
        return $this->kyc_status === 'approved';
    }

    public function isPending(): bool
    {
        return $this->kyc_status === 'pending';
    }

    public function fullName(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }
}