<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PromoCode extends Model
{
    protected $table = 'promo_codes';

    protected $fillable = [
        'uuid', 'code', 'discount', 'description',
        'expires_at', 'usage_limit', 'usage_count', 'active',
    ];

    protected $casts = [
        'discount'    => 'integer',
        'usage_limit' => 'integer',
        'usage_count' => 'integer',
        'active'      => 'boolean',
        'expires_at'  => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $m) {
            $m->uuid ??= (string) Str::uuid();
        });
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isExhausted(): bool
    {
        return $this->usage_count >= $this->usage_limit;
    }

    // Valide si le code est utilisable par un passager
    public function isUsable(): bool
    {
        return $this->active && ! $this->isExpired() && ! $this->isExhausted();
    }

    // Incrémenter l'usage lors d'une réservation
    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }
}
