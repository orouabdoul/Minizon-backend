<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Message extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'conversation_id',
        'sender_id',
        'body',
        'reply_to_id',
        'attachment_path',
        'attachment_type',
        'delivered_at',
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
        'delivered_at' => 'datetime',
        'read_at'      => 'datetime',
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

    public function replyTo()
    {
        return $this->belongsTo(Message::class, 'reply_to_id')->withTrashed();
    }

    // -----------------------------------------------------------------------
    // HELPERS
    // -----------------------------------------------------------------------

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function isDelivered(): bool
    {
        return $this->delivered_at !== null;
    }

    public function resolveType(): string
    {
        if ($this->attachment_path) {
            return match ($this->attachment_type) {
                'audio'    => 'audio',
                'image'    => 'image',
                'document' => 'document',
                default    => 'document',
            };
        }
        return 'text';
    }
}
