<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'booking_id',
        'user_id',
        'gross_amount',
        'commission_amount',
        'net_amount',
        'provider',
        'phone_number',
        'idempotency_key',
        'transaction_reference',
        'provider_reference',
        'status',
    ];

    protected $casts = [
        'gross_amount'      => 'integer',
        'commission_amount' => 'integer',
        'net_amount'        => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $payment) {
            if (empty($payment->uuid)) {
                $payment->uuid = (string) Str::uuid();
            }
        });
    }

    // -----------------------------------------------------------------------
    // RELATIONS
    // -----------------------------------------------------------------------

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // -----------------------------------------------------------------------
    // HELPERS
    // -----------------------------------------------------------------------

    public function isPending(): bool  { return $this->status === 'pending'; }
    public function isLocked(): bool   { return $this->status === 'locked'; }
    public function isSuccess(): bool  { return $this->status === 'success'; }
    public function isFailed(): bool   { return $this->status === 'failed'; }
    public function isRefunded(): bool { return $this->status === 'refunded'; }
}
