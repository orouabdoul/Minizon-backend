<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class SupportTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'user_id',
        'subject',
        'description',
        'priority',
        'channel',
        'status',
        'agent_id',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $ticket) {
            $ticket->uuid ??= (string) Str::uuid();
        });
    }

    // -----------------------------------------------------------------------
    // RELATIONS
    // -----------------------------------------------------------------------

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    // -----------------------------------------------------------------------
    // HELPERS
    // -----------------------------------------------------------------------

    public function isOpen(): bool     { return in_array($this->status, ['new', 'in_progress']); }
    public function isResolved(): bool { return in_array($this->status, ['resolved', 'closed']); }
}
