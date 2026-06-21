<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class AdminNotification extends Model
{
    use HasFactory;

    protected $table = 'admin_notifications';

    protected $fillable = [
        'uuid',
        'type',
        'priority',
        'status',
        'title',
        'description',
        'ref_type',
        'ref_id',
        'user_id',
        'read_at',
        'handled_at',
    ];

    protected $casts = [
        'read_at'    => 'datetime',
        'handled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $n) {
            $n->uuid ??= (string) Str::uuid();
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isUnread(): bool  { return $this->status === 'unread'; }
    public function isHandled(): bool { return $this->status === 'handled'; }
}
