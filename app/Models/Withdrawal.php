<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class Withdrawal extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'amount',
        'provider',
        'phone_number',
        'reference',
        'status',
        'failed_reason',
        'processed_at',
    ];

    protected $casts = [
        'amount'       => 'integer',
        'processed_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $withdrawal) {
            if (empty($withdrawal->reference)) {
                $withdrawal->reference = 'WD-' . strtoupper(Str::random(12));
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

    // -----------------------------------------------------------------------
    // HELPERS
    // -----------------------------------------------------------------------

    public function isPending(): bool   { return $this->status === 'pending'; }
    public function isApproved(): bool  { return $this->status === 'approved'; }
    public function isRejected(): bool  { return $this->status === 'rejected'; }
    public function isFailed(): bool    { return $this->status === 'failed'; }
}
