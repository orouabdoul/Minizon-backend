<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Dispute extends Model
{
    use HasFactory;

    protected $fillable = [
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
