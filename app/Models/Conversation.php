<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'trip_id',
        'booking_id',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $conv) {
            if (empty($conv->uuid)) {
                $conv->uuid = (string) Str::uuid();
            }
        });
    }

    // -----------------------------------------------------------------------
    // RELATIONS
    // -----------------------------------------------------------------------

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function participants()
    {
        return $this->belongsToMany(User::class, 'conversation_user');
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function lastMessage()
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }
}
