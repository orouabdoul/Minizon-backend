<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DriverPayout extends Model
{
    protected $table = 'driver_payouts';

    protected $fillable = [
        'uuid', 'driver_id', 'gross_amount', 'commission_amount', 'net_amount',
        'trips_count', 'method', 'phone_number', 'reference',
        'status', 'failed_reason', 'admin_id', 'processed_at', 'paid_at',
    ];

    protected $casts = [
        'gross_amount'      => 'integer',
        'commission_amount' => 'integer',
        'net_amount'        => 'integer',
        'trips_count'       => 'integer',
        'processed_at'      => 'datetime',
        'paid_at'           => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $p) {
            $p->uuid      ??= (string) Str::uuid();
            $p->reference ??= 'PAY-' . strtoupper(Str::random(10));
        });
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function isPending(): bool    { return $this->status === 'en_attente'; }
    public function isProcessing(): bool { return $this->status === 'en_traitement'; }
    public function isPaid(): bool       { return $this->status === 'payé'; }
    public function isFailed(): bool     { return $this->status === 'échoué'; }
}
