<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'conversation_id',
        'sender_id',
        'body',
        'attachment_path',
        'read_at',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $msg) {
            if (empty($msg->uuid)) {
                $msg->uuid = (string) Str::uuid();
            }
        });
    }

    protected $casts = [
        'read_at' => 'datetime',
    ];

    // -----------------------------------------------------------------------
    // RELATIONS
    // -----------------------------------------------------------------------

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    // -----------------------------------------------------------------------
    // HELPERS
    // -----------------------------------------------------------------------

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }
}
