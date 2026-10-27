<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class Dispute extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'booking_id',
        'reporter_id',
        'reason_type',
        'description',
        'proof_path',
        'status',
        'assigned_admin_id',
        'admin_decision_notes',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
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

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function assignedAdmin()
    {
        return $this->belongsTo(User::class, 'assigned_admin_id');
    }

    // -----------------------------------------------------------------------
    // HELPERS
    // -----------------------------------------------------------------------

    public function isPending(): bool      { return $this->status === 'pending'; }
    public function isResolved(): bool     { return str_starts_with($this->status, 'resolved'); }
    public function isInvestigating(): bool { return $this->status === 'investigating'; }
}
